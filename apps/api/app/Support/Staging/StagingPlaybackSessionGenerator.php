<?php

namespace App\Support\Staging;

use App\Models\MediaAsset;
use App\Models\User;
use App\Services\Media\MediaReadinessService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class StagingPlaybackSessionGenerator
{
    /** @return array{playback_sessions:int,device_tokens:int,expired_sessions:int,usable_tokens:int} */
    public function generate(StagingGenerationContext $context, MediaReadinessService $readiness): array
    {
        $target = $context->profile->counts['playback_sessions'];
        if ($target === 0) return ['playback_sessions' => 0, 'device_tokens' => 0, 'expired_sessions' => 0, 'usable_tokens' => 0];

        $devices = $this->eligibleDevices($context);
        if ($devices === []) throw new RuntimeException('Playback history requires owned active customer devices and adult profiles.');
        $devices = array_slice($devices, 0, min($target, count($devices)));
        $tokens = $this->ensureUnusableDeviceTokens($context, $devices);
        $titles = $this->eligibleTitles($context, $readiness);
        if ($titles === []) throw new RuntimeException('Playback history requires published titles with ready local media and a valid Bangladesh license window.');

        $start = $context->historyStart();
        $end = $context->referenceDate->endOfDay();
        $batchSize = $context->profile->batchSize;
        $sessionRows = [];
        $ownershipRows = [];
        $now = now('UTC')->toDateTimeString();
        for ($ordinal = 1; $ordinal <= $target; $ordinal++) {
            $deviceIndex = ($ordinal - 1) % count($devices);
            $device = $devices[$deviceIndex];
            $tokenId = $tokens[$device['device_id']] ?? null;
            if ($tokenId === null) throw new RuntimeException("A non-authenticating staging token was not generated for device {$device['device_id']}.");

            $entropy = (int) hexdec(substr(hash('sha256', $context->seed.':playback:'.$ordinal), 0, 8));
            $title = $this->selectAvailableTitle($titles, $device, $start, $end, $entropy);
            $availableSeconds = max(1, $title['available_start']->diffInSeconds($title['available_end']));
            $maxPlaybackSeconds = min(1800, $title['media_duration_seconds'], $availableSeconds);
            $duration = 1 + ($entropy % max(1, $maxPlaybackSeconds));
            $latestStart = max(0, $availableSeconds - $duration);
            $playedAt = $title['available_start']->addSeconds($latestStart > 0 ? $entropy % ($latestStart + 1) : 0);
            $endedAt = $playedAt->addSeconds($duration);
            $heartbeatAt = $endedAt->subSeconds(30);
            $sessionId = StagingIdentifiers::stableUuid($context->datasetId, 'playback-session:'.str_pad((string) $ordinal, 10, '0', STR_PAD_LEFT));

            $sessionRows[] = [
                'session_id' => $sessionId, 'user_id' => $device['user_id'], 'device_token_id' => $tokenId,
                'profile_id' => $device['profile_id'], 'content_id' => $title['content_id'], 'content_type' => 'movie',
                'last_heartbeat_at' => $heartbeatAt->toDateTimeString(), 'expires_at' => $heartbeatAt->addSeconds(120)->toDateTimeString(),
                'ended_at' => $endedAt->toDateTimeString(), 'created_at' => $playedAt->toDateTimeString(),
                'updated_at' => $endedAt->toDateTimeString(),
            ];
            $ownershipRows[] = [
                'dataset_id' => $context->datasetId, 'entity_type' => 'playback_session',
                'entity_key' => $sessionId, 'record_id' => $sessionId, 'created_at' => $now, 'updated_at' => $now,
            ];

            if (count($sessionRows) >= $batchSize) {
                $this->persistSessionBatch($sessionRows, $ownershipRows, $context);
                $sessionRows = [];
                $ownershipRows = [];
            }
        }
        if ($sessionRows !== []) $this->persistSessionBatch($sessionRows, $ownershipRows, $context);

        $sessionCount = (int) DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)->where('entity_type', 'playback_session')->count();
        if ($sessionCount !== $target) throw new RuntimeException("Playback generation produced {$sessionCount} of {$target} configured sessions.");
        return ['playback_sessions' => $sessionCount, 'device_tokens' => count($tokens), 'expired_sessions' => $sessionCount, 'usable_tokens' => 0];
    }

    /** @return list<array{device_id:string,device_pk:int,user_id:int,email:string,profile_id:int,device_created_at:string,token_id:?int}> */
    private function eligibleDevices(StagingGenerationContext $context): array
    {
        $profiles = DB::table('user_multi_profiles')->selectRaw('user_id, MIN(id) as profile_id')
            ->where('is_child_profile', 0)->whereNull('deleted_at')->groupBy('user_id');
        return DB::table('staging_dataset_records as device_owners')
            ->join('devices', 'devices.device_id', '=', 'device_owners.entity_key')
            ->join('users', 'users.id', '=', 'devices.user_id')
            ->join('staging_dataset_records as customer_owners', function ($join) use ($context): void {
                $join->on('customer_owners.entity_key', '=', 'users.email')->where('customer_owners.dataset_id', '=', $context->datasetId)->where('customer_owners.entity_type', '=', 'customer');
            })
            ->joinSub($profiles, 'adult_profiles', 'adult_profiles.user_id', '=', 'users.id')
            ->where('device_owners.dataset_id', $context->datasetId)->where('device_owners.entity_type', 'device')
            ->where('users.user_type', 'user')->where('users.status', 1)->where('users.is_banned', 0)
            ->whereNull('users.deleted_at')->whereNull('devices.deleted_at')
            ->orderBy('devices.id')
            ->get([
                'devices.id as device_pk', 'devices.device_id', 'devices.user_id', 'users.email',
                'adult_profiles.profile_id', 'devices.created_at as device_created_at', 'devices.sanctum_token_id as token_id',
                'devices.active_profile as active_profile',
            ])
            ->map(static fn ($row): array => [
                'device_id' => (string) $row->device_id, 'device_pk' => (int) $row->device_pk,
                'user_id' => (int) $row->user_id, 'email' => (string) $row->email,
                'profile_id' => (int) $row->profile_id,
                'device_created_at' => (string) $row->device_created_at,
                'token_id' => $row->token_id === null ? null : (int) $row->token_id,
            ])->all();
    }

    /** @param list<array<string,mixed>> $devices
     *  @return array<string,int> device key => Sanctum token row id
     */
    private function ensureUnusableDeviceTokens(StagingGenerationContext $context, array $devices): array
    {
        $ids = [];
        $expiry = $context->referenceDate->endOfDay()->toDateTimeString();
        $created = $context->historyStart()->toDateTimeString();
        foreach (array_chunk($devices, $context->profile->batchSize) as $chunk) {
            $deviceKeys = array_column($chunk, 'device_id');
            $owned = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                ->where('entity_type', 'playback_token')->whereIn('entity_key', $deviceKeys)
                ->get(['entity_key', 'record_id'])->keyBy('entity_key');
            $newTokens = [];
            $newDeviceMap = [];
            foreach ($chunk as $device) {
                $owner = $owned->get($device['device_id']);
                if ($owner) {
                    $token = DB::table('personal_access_tokens')->where('id', $owner->record_id)->first();
                    if (! $token || (int) $token->id !== (int) $device['token_id'] || (int) $token->tokenable_id !== $device['user_id']
                        || $token->tokenable_type !== User::class || json_decode((string) $token->abilities, true) !== []
                        || (string) $token->expires_at < $expiry) {
                        throw new RuntimeException("Staging device token ownership is missing, mismatched, or usable for {$device['device_id']}.");
                    }
                    $ids[$device['device_id']] = (int) $token->id;
                    continue;
                }
                if ($device['token_id'] !== null) throw new RuntimeException("Refusing to replace an unowned Sanctum token on staging device {$device['device_id']}.");
                $newTokens[] = [
                    'tokenable_type' => User::class, 'tokenable_id' => $device['user_id'],
                    'name' => 'staging-playback-fixture', 'token' => hash('sha256', random_bytes(32)),
                    'abilities' => '[]', 'last_used_at' => null, 'expires_at' => $expiry,
                    'created_at' => $created, 'updated_at' => $created,
                ];
                $newDeviceMap[] = $device;
            }
            if ($newTokens === []) continue;
            DB::transaction(function () use ($newTokens, $newDeviceMap, $context, $expiry, $ids): void {
                DB::table('personal_access_tokens')->insert($newTokens);
                $hashes = array_column($newTokens, 'token');
                $inserted = DB::table('personal_access_tokens')->whereIn('token', $hashes)->get(['id', 'token']);
                $tokensByHash = $inserted->keyBy('token');
                $owners = [];
                $tokenIdsByDevice = [];
                foreach ($newTokens as $index => $tokenRow) {
                    $device = $newDeviceMap[$index];
                    $token = $tokensByHash->get($tokenRow['token']);
                    if (! $token) throw new RuntimeException('Could not resolve a generated unusable staging token.');
                    $tokenIdsByDevice[$device['device_id']] = (int) $token->id;
                    $ids[$device['device_id']] = (int) $token->id;
                    $owners[] = [
                        'dataset_id' => $context->datasetId, 'entity_type' => 'playback_token',
                        'entity_key' => $device['device_id'], 'record_id' => (string) $token->id,
                        'created_at' => now(), 'updated_at' => now(),
                    ];
                }
                DB::table('staging_dataset_records')->insert($owners);
                $this->attachDeviceTokens($newDeviceMap, $tokenIdsByDevice);
            });
        }

        // Refresh IDs from the ownership registry after each transactional chunk.
        return DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
            ->where('entity_type', 'playback_token')->whereIn('entity_key', array_column($devices, 'device_id'))
            ->pluck('record_id', 'entity_key')->map(static fn ($id): int => (int) $id)->all();
    }

    /** @param list<array<string,mixed>> $devices
     *  @param array<string,int> $tokenIds
     */
    private function attachDeviceTokens(array $devices, array $tokenIds): void
    {
        $tokenCases = [];
        $profileCases = [];
        $tokenBindings = [];
        $profileBindings = [];
        $bindings = [];
        $where = [];
        foreach ($devices as $device) {
            $key = $device['device_id'];
            $tokenCases[] = 'WHEN ? THEN ?';
            $tokenBindings[] = $key;
            $tokenBindings[] = $tokenIds[$key];
            $profileCases[] = 'WHEN ? THEN ?';
            $profileBindings[] = $key;
            $profileBindings[] = $device['profile_id'];
            $where[] = '?';
        }
        $bindings = [...$tokenBindings, ...$profileBindings, now('UTC')->toDateTimeString()];
        array_push($bindings, ...array_column($devices, 'device_id'));
        DB::update(
            'UPDATE devices SET sanctum_token_id = CASE device_id '.implode(' ', $tokenCases).' END, active_profile = CASE device_id '.implode(' ', $profileCases).' END, updated_at = ? WHERE device_id IN ('.implode(',', $where).')',
            $bindings,
        );
    }

    /** @return list<array{content_id:string,asset_id:int,media_duration_seconds:int,window_start:?CarbonImmutable,window_end:?CarbonImmutable}> */
    private function eligibleTitles(StagingGenerationContext $context, MediaReadinessService $readiness): array
    {
        $start = $context->historyStart();
        $end = $context->referenceDate->endOfDay();
        $rows = DB::table('staging_dataset_records as owners')
            ->join('entertainments', 'entertainments.id', '=', 'owners.record_id')
            ->join('media_assets', 'media_assets.id', '=', 'entertainments.media_asset_id')
            ->join('content_license_windows as licenses', function ($join): void {
                $join->on('licenses.content_id', '=', 'entertainments.id')->where('licenses.content_type', '=', 'entertainment');
            })
            ->join('content_license_territories as territories', function ($join): void {
                $join->on('territories.content_id', '=', 'entertainments.id')
                    ->where('territories.content_type', '=', 'entertainment')->where('territories.country_code', '=', 'BD');
            })
            ->where('owners.dataset_id', $context->datasetId)->where('owners.entity_type', 'entertainment')
            ->where('entertainments.type', 'movie')->where('entertainments.status', 1)->whereNull('entertainments.deleted_at')
            ->where('entertainments.movie_access', 'free')->where('media_assets.status', 'ready')->where('media_assets.disk', 'private')
            ->where(function ($query) use ($end): void { $query->whereNull('licenses.starts_at')->orWhere('licenses.starts_at', '<=', $end); })
            ->where(function ($query) use ($start): void { $query->whereNull('licenses.ends_at')->orWhere('licenses.ends_at', '>=', $start); })
            ->orderBy('entertainments.id')->get([
                'entertainments.id as content_id', 'entertainments.media_asset_id as asset_id',
                'entertainments.release_date', 'licenses.starts_at', 'licenses.ends_at',
            ]);
        $eligible = [];
        $seen = [];
        foreach ($rows as $row) {
            if (isset($seen[$row->content_id])) continue;
            $seen[$row->content_id] = true;
            $asset = MediaAsset::query()->with('assetTracks')->find($row->asset_id);
            if (! $asset || ! $readiness->inspect($asset)['ready']) continue;
            $mediaDuration = $asset->metadata['Format']['duration'] ?? null;
            $mediaDurationSeconds = is_numeric($mediaDuration) ? (int) floor((float) $mediaDuration) : 0;
            if ($mediaDurationSeconds < 1) continue;
            $windowStart = $start;
            if ($row->starts_at !== null) {
                $candidate = CarbonImmutable::parse($row->starts_at);
                if ($candidate->greaterThan($windowStart)) $windowStart = $candidate;
            }
            if ($row->release_date !== null) {
                $release = CarbonImmutable::parse($row->release_date)->startOfDay();
                if ($release->greaterThan($windowStart)) $windowStart = $release;
            }
            $windowEnd = $end;
            if ($row->ends_at !== null) {
                $candidate = CarbonImmutable::parse($row->ends_at);
                if ($candidate->lessThan($windowEnd)) $windowEnd = $candidate;
            }
            if ($windowStart->greaterThanOrEqualTo($windowEnd) || $windowStart->diffInSeconds($windowEnd) < 120) continue;
            $eligible[] = [
                'content_id' => (string) $row->content_id, 'asset_id' => (int) $row->asset_id,
                'media_duration_seconds' => $mediaDurationSeconds,
                'available_start' => $windowStart, 'available_end' => $windowEnd,
            ];
        }
        return $eligible;
    }

    /** @param list<array<string,mixed>> $titles
     *  @param array<string,mixed> $device
     *  @return array<string,mixed>
     */
    private function selectAvailableTitle(array $titles, array $device, CarbonImmutable $historyStart, CarbonImmutable $referenceEnd, int $entropy): array
    {
        $deviceCreated = CarbonImmutable::parse($device['device_created_at']);
        $offset = $entropy % count($titles);
        for ($attempt = 0; $attempt < count($titles); $attempt++) {
            $title = $titles[($offset + $attempt) % count($titles)];
            $availableStart = $title['available_start']->greaterThan($deviceCreated) ? $title['available_start'] : $deviceCreated;
            $availableEnd = $title['available_end']->lessThan($referenceEnd) ? $title['available_end'] : $referenceEnd;
            if ($availableStart->lessThan($availableEnd) && $availableStart->diffInSeconds($availableEnd) >= 120) {
                $title['available_start'] = $availableStart;
                $title['available_end'] = $availableEnd;
                return $title;
            }
        }
        throw new RuntimeException("No valid playback-license period overlaps device {$device['device_id']} activity.");
    }

    /** @param list<array<string,mixed>> $sessions
     *  @param list<array<string,mixed>> $owners
     */
    private function persistSessionBatch(array $sessions, array $owners, StagingGenerationContext $context): void
    {
        DB::transaction(function () use ($sessions, $owners, $context): void {
            $keys = array_column($owners, 'entity_key');
            $existingOwners = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                ->where('entity_type', 'playback_session')->whereIn('entity_key', $keys)->pluck('entity_key')->all();
            if ($existingOwners !== []) {
                $present = array_fill_keys($existingOwners, true);
                foreach ($owners as $owner) {
                    if (! isset($present[$owner['entity_key']])) throw new RuntimeException('Staging playback session batch has an incomplete prior ownership range.');
                }
                $found = DB::table('playback_sessions')->whereIn('session_id', $keys)->count();
                if ($found !== count($keys)) throw new RuntimeException('An owned staging playback session is missing from the session table.');
                return;
            }
            if (DB::table('playback_sessions')->whereIn('session_id', $keys)->exists()) {
                throw new RuntimeException('Refusing to adopt an unowned playback session identifier.');
            }
            DB::table('playback_sessions')->insert($sessions);
            DB::table('staging_dataset_records')->insert($owners);
        });
    }
}
