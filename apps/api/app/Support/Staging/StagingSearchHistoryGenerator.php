<?php

namespace App\Support\Staging;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class StagingSearchHistoryGenerator
{
    /** @return array{search_history: int} */
    public function generate(StagingGenerationContext $context): array
    {
        $target = $context->profile->counts['search_history'];
        if ($target === 0) return ['search_history' => 0];

        $profiles = DB::table('staging_dataset_records as profile_owner')
            ->join('user_multi_profiles as profiles', 'profiles.id', '=', 'profile_owner.entity_key')
            ->join('users', 'users.id', '=', 'profiles.user_id')
            ->join('staging_dataset_records as customer_owner', function ($join) use ($context): void {
                $join->on('customer_owner.entity_key', '=', 'users.email')
                    ->where('customer_owner.dataset_id', '=', $context->datasetId)
                    ->where('customer_owner.entity_type', '=', 'customer');
            })
            ->where('profile_owner.dataset_id', $context->datasetId)
            ->where('profile_owner.entity_type', 'user_multi_profile')
            ->whereNull('users.deleted_at')
            ->orderBy('profiles.id')
            ->get(['profiles.id as profile_id', 'users.id as user_id', 'users.created_at as user_created_at']);
        $catalog = DB::table('staging_dataset_records as owners')
            ->join('entertainments', 'entertainments.id', '=', 'owners.record_id')
            ->where('owners.dataset_id', $context->datasetId)
            ->where('owners.entity_type', 'entertainment')
            ->whereNull('entertainments.deleted_at')
            ->orderBy('entertainments.id')
            ->get(['entertainments.name', 'entertainments.name_bn', 'entertainments.type']);
        if ($profiles->isEmpty() || $catalog->isEmpty()) {
            throw new RuntimeException('Staging search history requires owned customer profiles and catalog titles.');
        }

        $batchSize = $context->profile->batchSize;
        $datasetPart = StagingIdentifiers::datasetPart($context->datasetId);
        $created = 0;
        for ($offset = 0; $offset < $target; $offset += $batchSize) {
            $end = min($target, $offset + $batchSize);
            $keys = [];
            for ($index = $offset; $index < $end; $index++) {
                $keys[] = sprintf('stg-%s-search-%08d', $datasetPart, $index + 1);
            }
            $owned = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                ->where('entity_type', 'user_search_history')->whereIn('entity_key', $keys)
                ->get(['entity_key', 'record_id'])->keyBy('entity_key');
            if ($owned->isNotEmpty()) {
                $ownedIds = $owned->pluck('record_id')->filter()->all();
                $presentIds = DB::table('user_search_histories')->whereIn('id', $ownedIds)->pluck('id')->map(static fn ($id): string => (string) $id)->all();
                if (count($presentIds) !== $owned->count()) {
                    throw new RuntimeException('Owned staging search-history records are missing from their source table.');
                }
            }

            $newRows = [];
            $newOwners = [];
            for ($index = $offset; $index < $end; $index++) {
                $key = sprintf('stg-%s-search-%08d', $datasetPart, $index + 1);
                if ($owned->has($key)) continue;
                $profile = $profiles[($index * 131) % $profiles->count()];
                $title = $catalog[($index * 17 + intdiv($index, 7)) % $catalog->count()];
                $language = $index % 4 === 0 ? 'bn' : 'en';
                $query = trim((string) ($language === 'bn' ? $title->name_bn : $title->name));
                if ($query === '') $query = $language === 'bn' ? 'ইসলামি শিক্ষা' : 'Islamic learning';
                $contentType = $title->type === 'tvshow' ? 'tvshow' : 'movie';
                $userCreatedAt = CarbonImmutable::parse($profile->user_created_at);
                $availableSeconds = max(0, $userCreatedAt->diffInSeconds($context->referenceDate->endOfDay()));
                $entropy = hash('sha256', $context->seed.':search:'.$index);
                $secondsAfterRegistration = $availableSeconds === 0 ? 0 : hexdec(substr($entropy, 0, 8)) % ($availableSeconds + 1);
                $createdAt = $userCreatedAt->addSeconds($secondsAfterRegistration);
                $newRows[] = [
                    'user_id' => (int) $profile->user_id,
                    'profile_id' => (int) $profile->profile_id,
                    'search_query' => mb_substr($query, 0, 255),
                    'search_id' => null,
                    'type' => $contentType,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
                $newOwners[] = ['dataset_id' => $context->datasetId, 'entity_type' => 'user_search_history', 'entity_key' => $key, 'record_id' => null, 'created_at' => now(), 'updated_at' => now()];
            }
            if ($newRows === []) continue;

            DB::transaction(function () use ($newRows, $newOwners, $context, &$created): void {
                DB::table('user_search_histories')->insert($newRows);
                // MySQL allocates a contiguous auto-increment range for this single multi-row insert.
                $firstId = (int) DB::scalar('SELECT LAST_INSERT_ID()');
                if ($firstId < 1) throw new RuntimeException('Could not resolve generated search-history row identifiers.');
                foreach ($newOwners as $index => &$owner) {
                    $owner['record_id'] = (string) ($firstId + $index);
                }
                unset($owner);
                DB::table('staging_dataset_records')->insert($newOwners);
                $created += count($newRows);
            });
        }

        $actual = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)->where('entity_type', 'user_search_history')->count();
        if ($actual !== $target) {
            throw new RuntimeException("Staging search history generated {$actual} of {$target} configured records.");
        }
        return ['search_history' => $actual];
    }
}
