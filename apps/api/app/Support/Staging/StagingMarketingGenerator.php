<?php

namespace App\Support\Staging;

use App\Support\Marketing\CampaignAudience;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class StagingMarketingGenerator
{
    /** @return array{consents:int,campaigns:int,draft:int,pending_approval:int,approved:int,rejected:int,deliveries:int} */
    public function generate(StagingGenerationContext $context): array
    {
        $datasetPart = StagingIdentifiers::datasetPart($context->datasetId);
        $campaignTarget = $context->profile->counts['campaigns'];
        if ($campaignTarget === 0) return ['consents' => 0, 'campaigns' => 0, 'draft' => 0, 'pending_approval' => 0, 'approved' => 0, 'rejected' => 0, 'deliveries' => 0];
        $this->generateConsents($context, $datasetPart);

        $authors = $this->staffForRole($context, 'marketing_manager');
        $approvers = $this->staffForRole($context, 'super-admin');
        if ($authors === [] || $approvers === []) throw new RuntimeException('Staging marketing campaigns require owned marketing managers and independent super-admin approvers.');
        $coupons = DB::table('staging_dataset_records as owners')->join('coupons', 'coupons.id', '=', 'owners.record_id')
            ->where('owners.dataset_id', $context->datasetId)->where('owners.entity_type', 'coupon')->where('coupons.status', true)
            ->where(fn ($query) => $query->whereNull('coupons.start_date')->orWhereDate('coupons.start_date', '<=', $context->referenceDate))
            ->where(fn ($query) => $query->whereNull('coupons.expire_date')->orWhereDate('coupons.expire_date', '>=', $context->referenceDate))
            ->orderBy('coupons.id')->pluck('coupons.id')->map(static fn ($id): int => (int) $id)->all();
        if ($coupons === []) throw new RuntimeException('Staging marketing campaigns require at least one currently eligible owned coupon.');

        $states = ['draft' => 0, 'pending_approval' => 0, 'approved' => 0, 'rejected' => 0];
        for ($offset = 0; $offset < $campaignTarget; $offset += $context->profile->batchSize) {
            $end = min($campaignTarget, $offset + $context->profile->batchSize);
            DB::transaction(function () use ($offset, $end, $context, $datasetPart, $authors, $approvers, $coupons, &$states): void {
                $keys = [];
                for ($ordinal = $offset + 1; $ordinal <= $end; $ordinal++) $keys[] = sprintf('stg-%s-campaign-%06d', $datasetPart, $ordinal);
                $existingOwners = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                    ->where('entity_type', 'marketing_campaign')->whereIn('entity_key', $keys)->get(['entity_key', 'record_id'])->keyBy('entity_key');
                $unownedSlugs = DB::table('marketing_campaigns')->whereIn('slug', $keys)->pluck('slug')->flip();
                $rows = [];
                $owners = [];
                for ($ordinal = $offset + 1; $ordinal <= $end; $ordinal++) {
                    $key = sprintf('stg-%s-campaign-%06d', $datasetPart, $ordinal);
                    $owner = $existingOwners->get($key);
                    if ($owner !== null) {
                        $existing = DB::table('marketing_campaigns')->where('id', $owner->record_id)->where('slug', $key)->first(['status']);
                        if ($existing === null) throw new RuntimeException("Owned staging campaign {$key} is missing or inconsistent.");
                        if (array_key_exists($existing->status, $states)) $states[$existing->status]++;
                        continue;
                    }
                    if ($unownedSlugs->has($key)) throw new RuntimeException("Campaign {$key} exists without staging ownership.");

                    $kind = match ($ordinal % 4) { 1 => 'promotion', 2 => 'notification', 3 => 'landing_page', default => 'campaign' };
                    $audienceSegment = ['all_consented', 'email_consented', 'push_consented', 'subscribers', 'registered'][($ordinal - 1) % 5];
                    $channel = $kind === 'notification'
                        ? match ($audienceSegment) {
                            'email_consented' => 'email',
                            'push_consented' => 'push',
                            default => ($ordinal % 2) ? 'email' : 'push',
                        }
                        : ($kind === 'campaign' ? 'in_app' : null);
                    $status = match ($ordinal % 6) { 1 => 'pending_approval', 2 => 'approved', 3 => 'rejected', 5 => 'pending_approval', default => 'draft' };
                    $createdAt = $this->createdAt($context, $ordinal);
                    $approvedBy = $status === 'approved' ? $approvers[($ordinal - 1) % count($approvers)] : null;
                    $author = $authors[($ordinal - 1) % count($authors)];
                    if ($approvedBy === $author) $approvedBy = $approvers[($ordinal) % count($approvers)];
                    $names = ['A season of reflection', 'Stories for family learning', 'Discover historical documentaries', 'New educational collection', 'Member learning offer'];
                    $slug = $key;
                    $rows[] = [
                        'name' => $names[($ordinal - 1) % count($names)], 'slug' => $slug, 'kind' => $kind, 'channel' => $channel,
                        'status' => $status, 'audience_segment' => $audienceSegment,
                        'subject' => $channel === 'email' ? 'A new learning collection from NOOR' : null,
                        'body' => 'Synthetic staging campaign for testing editorial discovery and subscription communication workflows. No message is delivered.',
                        'call_to_action' => 'Explore the collection', 'destination_url' => 'https://example.test/staging',
                        'coupon_id' => $kind === 'promotion' ? $coupons[($ordinal - 1) % count($coupons)] : null,
                        'featured_content' => null, 'landing_page' => $kind === 'landing_page' ? json_encode(['headline' => 'Explore thoughtful learning', 'description' => 'Synthetic staging landing page content.'], JSON_THROW_ON_ERROR) : null,
                        'variant_b' => null, 'variant_b_percent' => 0,
                        'created_by' => $author, 'approved_by' => $approvedBy,
                        'approved_at' => $status === 'approved' ? $createdAt->addHours(2) : null,
                        'published_at' => null, 'scheduled_at' => null, 'expires_at' => null, 'deactivated_at' => null,
                        'created_at' => $createdAt, 'updated_at' => $status === 'approved' ? $createdAt->addHours(2) : $createdAt,
                    ];
                    $owners[] = ['dataset_id' => $context->datasetId, 'entity_type' => 'marketing_campaign', 'entity_key' => $key, 'record_id' => null, 'created_at' => now(), 'updated_at' => now()];
                    $states[$status]++;
                }
                if ($rows === []) return;
                DB::table('marketing_campaigns')->insert($rows);
                $campaigns = DB::table('marketing_campaigns')->whereIn('slug', array_column($rows, 'slug'))->get(['id', 'slug'])->keyBy('slug');
                foreach ($owners as &$owner) $owner['record_id'] = (string) $campaigns[$owner['entity_key']]->id;
                unset($owner);
                DB::table('staging_dataset_records')->insert($owners);
            });
        }
        $actual = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)->where('entity_type', 'marketing_campaign')->count();
        if ($actual !== $campaignTarget) throw new RuntimeException("Staging marketing generated {$actual} of {$campaignTarget} configured campaigns.");
        $deliveries = $this->generateSuppressedDeliveries($context);
        return ['consents' => DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)->where('entity_type', 'marketing_consent')->count(), 'campaigns' => $actual, ...$states, 'deliveries' => $deliveries];
    }

    private function generateSuppressedDeliveries(StagingGenerationContext $context): int
    {
        $campaigns = DB::table('staging_dataset_records as owners')->join('marketing_campaigns as campaigns', 'campaigns.id', '=', 'owners.record_id')
            ->where('owners.dataset_id', $context->datasetId)->where('owners.entity_type', 'marketing_campaign')
            ->where('campaigns.status', 'approved')->where('campaigns.kind', 'notification')->whereIn('campaigns.channel', ['email', 'push'])
            ->orderBy('campaigns.id')->get(['campaigns.id', 'campaigns.channel', 'campaigns.audience_segment', 'campaigns.variant_b_percent', 'campaigns.created_at', 'campaigns.approved_at']);
        foreach ($campaigns as $campaign) {
            CampaignAudience::query((string) $campaign->audience_segment, (string) $campaign->channel)
                ->join('staging_dataset_records as customer_owners', function ($join) use ($context): void {
                    $join->on('customer_owners.entity_key', '=', 'users.email')->where('customer_owners.dataset_id', '=', $context->datasetId)->where('customer_owners.entity_type', '=', 'customer');
                })
                ->select('users.id as id')->orderBy('users.id')->chunkById($context->profile->batchSize, function ($users) use ($context, $campaign): void {
                    $userIds = $users->pluck('id')->all();
                    $existing = DB::table('marketing_campaign_deliveries')->where('campaign_id', $campaign->id)->where('channel', $campaign->channel)
                        ->whereIn('user_id', $userIds)->get(['id', 'user_id'])->keyBy('user_id');
                    $keys = array_map(fn ($userId): string => $campaign->id.':'.$userId.':'.$campaign->channel, $userIds);
                    $owned = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                        ->where('entity_type', 'marketing_campaign_delivery')->whereIn('entity_key', $keys)->get(['entity_key', 'record_id'])->keyBy('entity_key');
                    $rows = [];
                    $newKeys = [];
                    foreach ($userIds as $userId) {
                        $key = $campaign->id.':'.$userId.':'.$campaign->channel;
                        $delivery = $existing->get($userId);
                        if ($delivery) {
                            $owner = $owned->get($key);
                            if (! $owner || (string) $owner->record_id !== (string) $delivery->id) {
                                throw new RuntimeException("Marketing delivery {$key} exists without matching staging ownership.");
                            }
                            continue;
                        }
                        if ($owned->has($key)) throw new RuntimeException("Owned marketing delivery {$key} is missing from its source table.");
                        $variant = (int) $campaign->variant_b_percent > 0
                            && (hexdec(substr(hash('sha256', $campaign->id.':'.$userId), 0, 4)) % 100) < (int) $campaign->variant_b_percent ? 'B' : 'A';
                        $rows[] = ['campaign_id' => $campaign->id, 'user_id' => $userId, 'channel' => $campaign->channel,
                            'variant' => $variant, 'status' => 'cancelled', 'failure_code' => 'staging_external_delivery_disabled',
                            'created_at' => $campaign->approved_at ?? $campaign->created_at,
                            'updated_at' => $campaign->approved_at ?? $campaign->created_at];
                        $newKeys[] = $key;
                    }
                    if ($rows === []) return;
                    DB::transaction(function () use ($rows, $newKeys, $context, $campaign): void {
                        DB::table('marketing_campaign_deliveries')->insert($rows);
                        $userIds = array_column($rows, 'user_id');
                        $inserted = DB::table('marketing_campaign_deliveries')->where('campaign_id', $campaign->id)
                            ->where('channel', $campaign->channel)->whereIn('user_id', $userIds)->get(['id', 'user_id'])->keyBy('user_id');
                        $owners = [];
                        foreach ($rows as $index => $row) {
                            $key = $newKeys[$index];
                            $delivery = $inserted->get($row['user_id']);
                            if (! $delivery) throw new RuntimeException("Unable to resolve generated suppressed marketing delivery {$key}.");
                            $owners[] = ['dataset_id' => $context->datasetId, 'entity_type' => 'marketing_campaign_delivery', 'entity_key' => $key,
                                'record_id' => (string) $delivery->id, 'created_at' => now(), 'updated_at' => now()];
                        }
                        DB::table('staging_dataset_records')->insert($owners);
                    });
                }, 'users.id', 'id');
        }
        return (int) DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)->where('entity_type', 'marketing_campaign_delivery')->count();
    }

    private function generateConsents(StagingGenerationContext $context, string $datasetPart): void
    {
        $customers = DB::table('staging_dataset_records as owners')->join('users', 'users.email', '=', 'owners.entity_key')
            ->where('owners.dataset_id', $context->datasetId)->where('owners.entity_type', 'customer')->whereNull('users.deleted_at')
            ->orderBy('users.id')->get(['users.id', 'users.created_at']);
        if ($context->profile->counts['campaigns'] > 0 && $customers->isEmpty()) throw new RuntimeException('Staging marketing requires owned customer accounts.');
        foreach ($customers->chunk($context->profile->batchSize) as $batch) {
            DB::transaction(function () use ($batch, $context, $datasetPart): void {
                $consentKeys = [];
                foreach ($batch as $customer) foreach (['email', 'push'] as $channel) $consentKeys[] = $customer->id.':'.$channel;
                $existingOwners = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                    ->where('entity_type', 'marketing_consent')->whereIn('entity_key', $consentKeys)->get(['entity_key', 'record_id'])->keyBy('entity_key');
                $existingConsents = DB::table('marketing_consents')->whereIn('user_id', $batch->pluck('id')->all())
                    ->whereIn('channel', ['email', 'push'])->get(['user_id', 'channel'])->keyBy(fn ($row) => $row->user_id.':'.$row->channel);
                $rows = [];
                foreach ($batch as $customer) {
                    foreach (['email', 'push'] as $channel) {
                        $key = $customer->id.':'.$channel;
                        $owner = $existingOwners->get($key);
                        if ($owner !== null) {
                            $existing = DB::table('marketing_consents')->where('id', $owner->record_id)->where('user_id', $customer->id)->where('channel', $channel)->first();
                            if ($existing === null) throw new RuntimeException("Owned marketing consent {$key} is missing or inconsistent.");
                            continue;
                        }
                        if ($existingConsents->has($key)) {
                            throw new RuntimeException("Customer marketing consent {$key} exists without staging ownership.");
                        }
                        $consented = $channel === 'email' ? ((int) $customer->id % 4 !== 0) : ((int) $customer->id % 5 !== 0);
                        $createdAt = CarbonImmutable::parse($customer->created_at);
                        $rows[] = ['user_id' => $customer->id, 'channel' => $channel, 'consented' => $consented,
                            'consented_at' => $consented ? $createdAt : null, 'source' => 'account', 'created_at' => $createdAt, 'updated_at' => $createdAt,
                            '_ownership_key' => $key];
                    }
                }
                if ($rows === []) return;
                $keys = array_column($rows, '_ownership_key');
                foreach ($rows as &$row) unset($row['_ownership_key']);
                unset($row);
                DB::table('marketing_consents')->insert($rows);
                $users = DB::table('marketing_consents')->whereIn('user_id', array_column($rows, 'user_id'))
                    ->whereIn('channel', ['email', 'push'])->get(['id', 'user_id', 'channel'])->keyBy(fn ($row) => $row->user_id.':'.$row->channel);
                $owners = [];
                foreach ($keys as $key) $owners[] = ['dataset_id' => $context->datasetId, 'entity_type' => 'marketing_consent', 'entity_key' => $key, 'record_id' => (string) $users[$key]->id, 'created_at' => now(), 'updated_at' => now()];
                DB::table('staging_dataset_records')->insert($owners);
            });
        }
    }

    /** @return list<int> */
    private function staffForRole(StagingGenerationContext $context, string $role): array
    {
        return DB::table('staging_dataset_records as owners')->join('users', 'users.email', '=', 'owners.entity_key')
            ->join('model_has_roles as staff_roles', function ($join): void {
                $join->on('staff_roles.model_id', '=', 'users.id')->where('staff_roles.model_type', '=', 'App\\Models\\User');
            })->join('roles', 'roles.id', '=', 'staff_roles.role_id')
            ->where('owners.dataset_id', $context->datasetId)->where('owners.entity_type', 'staff_user')->where('roles.name', $role)
            ->where('users.status', 1)->whereNull('users.deleted_at')->orderBy('users.id')->pluck('users.id')->map(static fn ($id): int => (int) $id)->all();
    }

    private function createdAt(StagingGenerationContext $context, int $ordinal): CarbonImmutable
    {
        $latest = $context->referenceDate->endOfDay()->subHours(3);
        $seconds = max(1, $context->historyStart()->diffInSeconds($latest));
        $hash = hash('sha256', $context->seed.':campaign:'.$ordinal);
        return $context->historyStart()->addSeconds(hexdec(substr($hash, 0, 8)) % $seconds);
    }
}
