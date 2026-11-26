<?php

namespace App\Support\Staging;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class StagingContinueWatchGenerator
{
    public function generate(StagingGenerationContext $context): int
    {
        $target = $context->profile->counts['continue_watch'];
        if ($target === 0) return 0;

        $datasetPart = StagingIdentifiers::datasetPart($context->datasetId);
        $sessionCandidates = DB::table('playback_sessions as sessions')
            ->join('staging_dataset_records as session_owners', function ($join) use ($context): void {
                $join->on('session_owners.entity_key', '=', 'sessions.session_id')
                    ->where('session_owners.dataset_id', '=', $context->datasetId)
                    ->where('session_owners.entity_type', '=', 'playback_session');
            })
            ->join('entertainments as content', 'content.id', '=', 'sessions.content_id')
            ->join('media_assets as assets', 'assets.id', '=', 'content.media_asset_id')
            ->whereNotNull('sessions.ended_at')->whereNull('content.deleted_at')
            ->selectRaw("sessions.user_id, sessions.profile_id, sessions.content_id, sessions.ended_at as progress_at, TIMESTAMPDIFF(SECOND, sessions.created_at, sessions.ended_at) as watched_seconds, FLOOR(CAST(JSON_UNQUOTE(JSON_EXTRACT(assets.metadata, '$.Format.duration')) AS DECIMAL(12, 6))) as total_seconds, ROW_NUMBER() OVER (PARTITION BY sessions.user_id, sessions.profile_id, sessions.content_id ORDER BY sessions.ended_at DESC, sessions.session_id DESC) as progress_rank");
        $candidateQuery = DB::query()->fromSub($sessionCandidates, 'eligible_sessions')
            ->where('progress_rank', 1)->where('watched_seconds', '>', 0)->whereColumn('watched_seconds', '<', 'total_seconds')
            ->orderByRaw('CRC32(CONCAT(profile_id, ":", content_id, ":", ?))', [$context->datasetId]);

        $rows = [];
        $owners = [];
        $ordinal = 0;
        $candidateQuery->orderBy('profile_id')->orderBy('content_id')->chunk($context->profile->batchSize, function ($candidates) use ($context, $target, $datasetPart, &$rows, &$owners, &$ordinal): bool {
            $firstOrdinal = $ordinal + 1;
            $lastOrdinal = min($target, $ordinal + $candidates->count());
            if ($firstOrdinal > $lastOrdinal) return false;
            $keys = [];
            for ($keyOrdinal = $firstOrdinal; $keyOrdinal <= $lastOrdinal; $keyOrdinal++) {
                $keys[] = sprintf('stg-%s-continue-%08d', $datasetPart, $keyOrdinal);
            }
            $existingOwners = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                ->where('entity_type', 'continue_watch')->whereIn('entity_key', $keys)->get(['entity_key', 'record_id'])->keyBy('entity_key');
            if ($existingOwners->isNotEmpty()) {
                $present = DB::table('continue_watch')->whereIn('id', $existingOwners->pluck('record_id')->all())->count();
                if ($present !== $existingOwners->count()) throw new RuntimeException('Owned continue-watching rows are missing from their source table.');
            }
            foreach ($candidates as $candidate) {
                if ($ordinal >= $target) return false;
                $watched = min((int) $candidate->watched_seconds, (int) $candidate->total_seconds - 1);
                if ($watched < 1 || (int) $candidate->total_seconds < 2) continue;
                $ordinal++;
                $key = sprintf('stg-%s-continue-%08d', $datasetPart, $ordinal);
                if ($existingOwners->has($key)) continue;
                $at = (string) $candidate->progress_at;
                $rows[] = [
                    'user_id' => (int) $candidate->user_id,
                    'profile_id' => (int) $candidate->profile_id,
                    'entertainment_id' => (string) $candidate->content_id,
                    'episode_id' => null,
                    'entertainment_type' => 'movie',
                    'watched_time' => $this->formatDuration($watched),
                    'total_watched_time' => $this->formatDuration((int) $candidate->total_seconds),
                    'created_at' => $at,
                    'updated_at' => $at,
                ];
                $owners[] = ['dataset_id' => $context->datasetId, 'entity_type' => 'continue_watch', 'entity_key' => $key,
                    'record_id' => null, 'created_at' => now(), 'updated_at' => now()];
                if (count($rows) >= $context->profile->batchSize) {
                    $this->persist($rows, $owners);
                    $rows = [];
                    $owners = [];
                }
            }
            return true;
        });
        if ($rows !== []) $this->persist($rows, $owners);

        $actual = (int) DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)->where('entity_type', 'continue_watch')->count();
        if ($actual !== $target) throw new RuntimeException("Staging continue-watching generated {$actual} of {$target} configured records.");
        return $actual;
    }

    /** @param list<array<string,mixed>> $rows
     *  @param list<array<string,mixed>> $owners
     */
    private function persist(array $rows, array $owners): int
    {
        return DB::transaction(function () use ($rows, $owners): int {
            DB::table('continue_watch')->insert($rows);
            $firstId = (int) DB::scalar('SELECT LAST_INSERT_ID()');
            if ($firstId < 1) throw new RuntimeException('Unable to resolve generated continue-watching row identifiers.');
            foreach ($owners as $index => &$owner) $owner['record_id'] = (string) ($firstId + $index);
            unset($owner);
            DB::table('staging_dataset_records')->insert($owners);
            return count($rows);
        });
    }

    private function formatDuration(int $seconds): string
    {
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
}
