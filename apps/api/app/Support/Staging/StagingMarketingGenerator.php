<?php

namespace App\Support\Staging;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class StagingMarketingGenerator
{
    /** @return array{consents: int, campaigns: int, draft: int, pending_approval: int, approved: int, rejected: int} */
    public function generate(StagingGenerationContext $context): array
    {
        $datasetPart = StagingIdentifiers::datasetPart($context->datasetId);
        $campaignTarget = $context->profile->counts['campaigns'];
        if ($campaignTarget === 0) return ['consents' => 0, 'campaigns' => 0, 'draft' => 0, 'pending_approval' => 0, 'approved' => 0, 'rejected' => 0];
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
                $rows = [];
                $owners = [];
                for ($ordinal = $offset + 1; $ordinal <= $end; $ordinal++) {
                    $key = sprintf('stg-%s-campaign-%06d', $datasetPart, $ordinal);
                    $owner = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                        ->where('entity_type', 'marketing_campaign')->where('entity_key', $key)->first(['record_id']);
                    if ($owner !== null) {
                        $existing = DB::table('marketing_campaigns')->where('id', $owner->record_id)->where('slug', $key)->first(['status']);
                        if ($existing === null) throw new RuntimeException("Owned staging campaign {$key} is missing or inconsistent.");
                        if (array_key_exists($existing->status, $states)) $states[$existing->status]++;
                        continue;
                    }
                    if (DB::table('marketing_campaigns')->where('slug', $key)->exists()) throw new RuntimeException("Campaign {$key} exists without staging ownership.");

                    $kind = match ($ordinal % 4) { 1 => 'promotion', 2 => 'notification', 3 => 'landing_page', default => 'campaign' };
                    $channel = $kind === 'notification' ? (($ordinal % 2) ? 'email' : 'push') : ($kind === 'campaign' ? 'in_app' : null);
                    $status = match ($ordinal % 6) { 1 => 'pending_approval', 2 => 'approved', 3 => 'rejected', 5 => 'pending_approval', default => 'draft' };
                    $createdAt = $this->createdAt($context, $ordinal);
                    $approvedBy = $status === 'approved' ? $approvers[($ordinal - 1) % count($approvers)] : null;
                    $author = $authors[($ordinal - 1) % count($authors)];
                    if ($approvedBy === $author) $approvedBy = $approvers[($ordinal) % count($approvers)];
                    $names = ['A season of reflection', 'Stories for family learning', 'Discover historical documentaries', 'New educational collection', 'Member learning offer'];
                    $slug = $key;
                    $rows[] = [
                        'name' => $names[($ordinal - 1) % count($names)], 'slug' => $slug, 'kind' => $kind, 'channel' => $channel,
                        'status' => $status, 'audience_segment' => ['all_consented', 'email_consented', 'push_consented', 'subscribers', 'registered'][($ordinal - 1) % 5],
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
        return ['consents' => DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)->where('entity_type', 'marketing_consent')->count(), 'campaigns' => $actual, ...$states];
    }

    private function generateConsents(StagingGenerationContext $context, string $datasetPart): void
    {
        $customers = DB::table('staging_dataset_records as owners')->join('users', 'users.email', '=', 'owners.entity_key')
            ->where('owners.dataset_id', $context->datasetId)->where('owners.entity_type', 'customer')->whereNull('users.deleted_at')
            ->orderBy('users.id')->get(['users.id', 'users.created_at']);
        if ($context->profile->counts['campaigns'] > 0 && $customers->isEmpty()) throw new RuntimeException('Staging marketing requires owned customer accounts.');
        foreach ($customers->chunk($context->profile->batchSize) as $batch) {
            DB::transaction(function () use ($batch, $context, $datasetPart): void {
                $rows = [];
                foreach ($batch as $customer) {
                    foreach (['email', 'push'] as $channel) {
                        $key = $customer->id.':'.$channel;
                        $owner = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                            ->where('entity_type', 'marketing_consent')->where('entity_key', $key)->first(['record_id']);
                        if ($owner !== null) {
                            $existing = DB::table('marketing_consents')->where('id', $owner->record_id)->where('user_id', $customer->id)->where('channel', $channel)->first();
                            if ($existing === null) throw new RuntimeException("Owned marketing consent {$key} is missing or inconsistent.");
                            continue;
                        }
                        if (DB::table('marketing_consents')->where('user_id', $customer->id)->where('channel', $channel)->exists()) {
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
