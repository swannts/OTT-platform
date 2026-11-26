<?php

namespace App\Support\Staging;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Creates customer engagement against dataset-owned profiles and catalog titles. */
final class StagingEngagementGenerator
{
    /** @return array{watchlists:int,reviews:int,watch_history:int} */
    public function generate(StagingGenerationContext $context): array
    {
        $customers = DB::table('staging_dataset_records as owners')
            ->join('users', 'users.email', '=', 'owners.entity_key')
            ->where('owners.dataset_id', $context->datasetId)->where('owners.entity_type', 'customer')
            ->whereNull('users.deleted_at')->orderBy('users.id')
            ->get(['users.id', 'users.created_at']);
        $profiles = DB::table('staging_dataset_records as owners')
            ->join('user_multi_profiles as profiles', 'profiles.id', '=', 'owners.entity_key')
            ->where('owners.dataset_id', $context->datasetId)->where('owners.entity_type', 'user_multi_profile')
            ->where('profiles.is_child_profile', 0)->whereNull('profiles.deleted_at')->orderBy('profiles.id')
            ->get(['profiles.id', 'profiles.user_id']);
        $titles = DB::table('staging_dataset_records as owners')
            ->join('entertainments as content', 'content.id', '=', 'owners.record_id')
            ->where('owners.dataset_id', $context->datasetId)->where('owners.entity_type', 'entertainment')
            ->where('content.status', 1)->whereNull('content.deleted_at')->orderBy('content.id')
            ->get(['content.id', 'content.type', 'content.created_at']);

        if (($context->profile->counts['watchlists'] > 0 || $context->profile->counts['reviews'] > 0 || $context->profile->counts['watch_history'] > 0)
            && ($customers->isEmpty() || $profiles->isEmpty() || $titles->isEmpty())) {
            throw new RuntimeException('Staging customer engagement requires owned customers, adult profiles, and published titles.');
        }

        $results = [
            'watchlists' => $this->generateRows($context, 'watchlists', 'watchlist', 'watchlists', $customers, $profiles, $titles),
            'reviews' => $this->generateRows($context, 'reviews', 'review', 'reviews', $customers, $profiles, $titles),
            'watch_history' => $this->generateRows($context, 'watch_history', 'user_watch_history', 'user_watch_histories', $customers, $profiles, $titles),
        ];

        return $results;
    }

    /** @param Collection<int,object> $customers
     *  @param Collection<int,object> $profiles
     *  @param Collection<int,object> $titles
     */
    private function generateRows(StagingGenerationContext $context, string $targetName, string $entityType, string $table, Collection $customers, Collection $profiles, Collection $titles): int
    {
        $target = $context->profile->counts[$targetName];
        if ($target === 0) return 0;

        $datasetPart = StagingIdentifiers::datasetPart($context->datasetId);
        $profileByUser = $profiles->groupBy('user_id');
        $count = 0;
        for ($offset = 0; $offset < $target; $offset += $context->profile->batchSize) {
            $end = min($target, $offset + $context->profile->batchSize);
            $keys = [];
            for ($ordinal = $offset + 1; $ordinal <= $end; $ordinal++) {
                $keys[] = sprintf('stg-%s-%s-%08d', $datasetPart, $entityType, $ordinal);
            }
            $existingOwners = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                ->where('entity_type', $entityType)->whereIn('entity_key', $keys)->get(['entity_key', 'record_id'])->keyBy('entity_key');
            if ($existingOwners->isNotEmpty()) {
                $ownedIds = $existingOwners->pluck('record_id')->filter()->all();
                $presentIds = DB::table($table)->whereIn('id', $ownedIds)->pluck('id')->map(static fn ($id): string => (string) $id)->all();
                if (count($presentIds) !== $existingOwners->count()) {
                    throw new RuntimeException("Owned staging {$entityType} records are missing from {$table}.");
                }
            }
            $rows = [];
            $owners = [];
            for ($ordinal = $offset + 1; $ordinal <= $end; $ordinal++) {
                $key = sprintf('stg-%s-%s-%08d', $datasetPart, $entityType, $ordinal);
                if ($existingOwners->has($key)) continue;

                // Sequential customer/title pairs avoid duplicate reviews and keep customer activity distributed.
                $customer = $customers[intdiv($ordinal - 1, $titles->count()) % $customers->count()];
                $title = $titles[($ordinal - 1) % $titles->count()];
                $customerProfiles = $profileByUser->get($customer->id);
                if (! $customerProfiles || $customerProfiles->isEmpty()) {
                    throw new RuntimeException("Staging customer {$customer->id} has no owned adult profile.");
                }
                $profile = $customerProfiles->values()[($ordinal - 1) % $customerProfiles->count()];
                $createdAt = $this->eventTime($context, $entityType, $ordinal, $customer->created_at, $title->created_at);
                $common = ['user_id' => (int) $customer->id, 'created_at' => $createdAt, 'updated_at' => $createdAt];
                if ($table === 'watchlists') {
                    $rows[] = $common + ['entertainment_id' => (string) $title->id, 'profile_id' => (int) $profile->id, 'type' => (string) $title->type];
                } elseif ($table === 'reviews') {
                    $reviews = [
                        ['rating' => 5, 'review' => 'A thoughtful, well-presented story with clear historical context.'],
                        ['rating' => 4, 'review' => 'Useful and engaging. I would recommend it to families.'],
                        ['rating' => 4, 'review' => 'বিষয়টি সুন্দর ও সম্মানজনকভাবে উপস্থাপন করা হয়েছে।'],
                        ['rating' => 3, 'review' => 'Interesting topic with room for more detail.'],
                    ];
                    $rows[] = $common + ['entertainment_id' => (string) $title->id] + $reviews[($ordinal - 1) % count($reviews)];
                } else {
                    $rows[] = $common + [
                        'entertainment_id' => (string) $title->id, 'profile_id' => (int) $profile->id,
                        'entertainment_type' => (string) $title->type,
                        'created_by' => (int) $customer->id, 'updated_by' => (int) $customer->id,
                    ];
                }
                $owners[] = ['dataset_id' => $context->datasetId, 'entity_type' => $entityType, 'entity_key' => $key,
                    'record_id' => null, 'created_at' => now(), 'updated_at' => now()];
            }
            if ($rows === []) continue;

            DB::transaction(function () use ($table, $rows, $owners, $context, &$count): void {
                DB::table($table)->insert($rows);
                $firstId = (int) DB::scalar('SELECT LAST_INSERT_ID()');
                if ($firstId < 1) throw new RuntimeException("Unable to resolve inserted staging records for {$table}.");
                foreach ($owners as $index => &$owner) $owner['record_id'] = (string) ($firstId + $index);
                unset($owner);
                DB::table('staging_dataset_records')->insert($owners);
                $count += count($rows);
            });
        }

        $actual = (int) DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)->where('entity_type', $entityType)->count();
        if ($actual !== $target) throw new RuntimeException("Staging {$entityType} generated {$actual} of {$target} configured records.");
        return $actual;
    }

    private function eventTime(StagingGenerationContext $context, string $domain, int $ordinal, string $customerCreatedAt, string $titleCreatedAt): string
    {
        $earliest = CarbonImmutable::parse($customerCreatedAt)->greaterThan(CarbonImmutable::parse($titleCreatedAt))
            ? CarbonImmutable::parse($customerCreatedAt) : CarbonImmutable::parse($titleCreatedAt);
        $latest = $context->referenceDate->endOfDay();
        if ($earliest->greaterThan($latest)) throw new RuntimeException('Staging engagement cannot precede customer registration or content creation.');
        $span = max(0, $earliest->diffInSeconds($latest));
        $entropy = hash('sha256', $context->seed.':'.$domain.':'.$ordinal);
        $offset = $span > 0 ? hexdec(substr($entropy, 0, 8)) % ($span + 1) : 0;
        return $earliest->addSeconds($offset)->toDateTimeString();
    }
}
