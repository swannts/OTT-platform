<?php

namespace App\Support\Staging;

use App\Models\StagingDatasetManifest;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class StagingDataIntegrityValidator
{
    /** @return array{valid: bool, complete: bool, coverage: string, checked_at: string, datasets: array<int, array<string, mixed>>, violations: array<int, string>} */
    public function report(?string $datasetId = null): array
    {
        $violations = [];
        if (! Schema::hasTable('staging_dataset_manifests') || ! Schema::hasTable('staging_dataset_records')) {
            return [
                'valid' => false,
                'complete' => false,
                'coverage' => 'staging manifest, staff, customer, profile, device, bilingual catalog, season, and episode ownership only',
                'checked_at' => now()->toIso8601String(),
                'datasets' => [],
                'violations' => ['Staging manifest and ownership tables are missing; run database migrations first.'],
            ];
        }

        $query = StagingDatasetManifest::query()->orderBy('dataset_id');
        if ($datasetId !== null) {
            $query->where('dataset_id', $datasetId);
        }

        $datasets = $query->get()->map(function (StagingDatasetManifest $manifest) use (&$violations): array {
            $groupedCounts = DB::table('staging_dataset_records')
                ->where('dataset_id', $manifest->dataset_id)
                ->select('entity_type', DB::raw('count(*) as records'))
                ->groupBy('entity_type')
                ->pluck('records', 'entity_type')
                ->map(static fn ($count): int => (int) $count)
                ->all();
            $counts = [
                'staff' => $groupedCounts['staff_user'] ?? 0,
                'customers' => $groupedCounts['customer'] ?? 0,
                'profiles' => $groupedCounts['user_multi_profile'] ?? 0,
                'devices' => $groupedCounts['device'] ?? 0,
            ];
            $supportedTypes = ['staff_user', 'customer', 'user_multi_profile', 'device', 'genre', 'entertainment', 'entertainment_genre_mapping', 'season', 'episode'];
            $unknownTypes = array_diff(array_keys($groupedCounts), $supportedTypes);
            foreach ($unknownTypes as $type) {
                $violations[] = "Dataset {$manifest->dataset_id} has an unsupported ownership type {$type}.";
            }
            $registeredUserTypes = ['staff_user', 'customer'];
            $invalidUsers = DB::table('staging_dataset_records as records')
                ->leftJoin('users', 'users.email', '=', 'records.entity_key')
                ->where('records.dataset_id', $manifest->dataset_id)
                ->whereIn('records.entity_type', $registeredUserTypes)
                ->where(function ($query): void {
                    $query->whereNull('users.id')->orWhereNotNull('users.deleted_at');
                })
                ->count();
            if ($invalidUsers > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidUsers} missing or soft-deleted owned user accounts.";
            }
            $invalidStaff = DB::table('staging_dataset_records as records')
                ->join('users', 'users.email', '=', 'records.entity_key')
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'staff_user')
                ->where(function ($query): void {
                    $query->whereNull('users.user_type')->orWhere('users.user_type', '!=', 'admin');
                })
                ->count();
            $invalidCustomers = DB::table('staging_dataset_records as records')
                ->join('users', 'users.email', '=', 'records.entity_key')
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'customer')
                ->where(function ($query): void {
                    $query->whereNull('users.user_type')->orWhere('users.user_type', '!=', 'user');
                })
                ->count();
            if ($invalidStaff > 0 || $invalidCustomers > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidStaff} invalid staff and {$invalidCustomers} invalid customer account types.";
            }
            $invalidProfiles = DB::table('staging_dataset_records as records')
                ->leftJoin('user_multi_profiles as profiles', 'profiles.id', '=', 'records.entity_key')
                ->leftJoin('users', 'users.id', '=', 'profiles.user_id')
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'user_multi_profile')
                ->where(function ($query): void {
                    $query->whereNull('profiles.id')->orWhereNotNull('profiles.deleted_at')->orWhereNull('users.id')->orWhereNotNull('users.deleted_at');
                })
                ->count();
            if ($invalidProfiles > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidProfiles} missing or invalid customer profiles.";
            }
            $invalidDevices = DB::table('staging_dataset_records as records')
                ->leftJoin('devices', 'devices.device_id', '=', 'records.entity_key')
                ->leftJoin('users', 'users.id', '=', 'devices.user_id')
                ->leftJoin('staging_dataset_records as customer_owner', function ($join) use ($manifest): void {
                    $join->on('customer_owner.entity_key', '=', 'users.email')
                        ->where('customer_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('customer_owner.entity_type', '=', 'customer');
                })
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'device')
                ->where(function ($query): void {
                    $query->whereNull('devices.id')->orWhereNotNull('devices.deleted_at')->orWhereNull('users.id')->orWhereNull('customer_owner.id');
                })
                ->count();
            if ($invalidDevices > 0) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidDevices} missing or incorrectly owned devices.";
            }
            $invalidCatalogQuery = DB::table('staging_dataset_records as records')
                ->leftJoin('entertainments', 'entertainments.id', '=', 'records.record_id')
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'entertainment')
                ->where(function ($query): void {
                    $query->whereNull('entertainments.id')->orWhereNotNull('entertainments.deleted_at')
                        ->orWhere('entertainments.status', '!=', 0);
                });
            if (Schema::hasColumn('entertainments', 'media_asset_id')) {
                $invalidCatalogQuery->orWhere(function ($query) use ($manifest): void {
                    $query->where('records.dataset_id', $manifest->dataset_id)
                        ->where('records.entity_type', 'entertainment')
                        ->whereNotNull('entertainments.media_asset_id');
                });
            }
            $invalidCatalog = $invalidCatalogQuery->count();
            $invalidGenres = DB::table('staging_dataset_records as records')
                ->leftJoin('genres', 'genres.id', '=', 'records.record_id')
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'genre')
                ->where(function ($query): void {
                    $query->whereNull('genres.id')->orWhereNotNull('genres.deleted_at')->orWhere('genres.status', '!=', 1);
                })->count();
            $invalidMappings = DB::table('staging_dataset_records as records')
                ->leftJoin('entertainment_gener_mapping as mappings', 'mappings.id', '=', 'records.record_id')
                ->leftJoin('staging_dataset_records as entertainment_owner', function ($join) use ($manifest): void {
                    $join->on('entertainment_owner.record_id', '=', 'mappings.entertainment_id')
                        ->where('entertainment_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('entertainment_owner.entity_type', '=', 'entertainment');
                })
                ->leftJoin('staging_dataset_records as genre_owner', function ($join) use ($manifest): void {
                    $join->on('genre_owner.record_id', '=', 'mappings.genre_id')
                        ->where('genre_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('genre_owner.entity_type', '=', 'genre');
                })
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'entertainment_genre_mapping')
                ->where(function ($query): void {
                    $query->whereNull('mappings.id')->orWhereNotNull('mappings.deleted_at')
                        ->orWhereNull('entertainment_owner.id')->orWhereNull('genre_owner.id');
                })->count();
            $invalidSeasons = DB::table('staging_dataset_records as records')
                ->leftJoin('seasons', 'seasons.id', '=', 'records.record_id')
                ->leftJoin('entertainments as parent_series', 'parent_series.id', '=', 'seasons.entertainment_id')
                ->leftJoin('staging_dataset_records as series_owner', function ($join) use ($manifest): void {
                    $join->on('series_owner.record_id', '=', 'seasons.entertainment_id')
                        ->where('series_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('series_owner.entity_type', '=', 'entertainment');
                })
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'season')
                ->where(function ($query): void {
                    $query->whereNull('seasons.id')->orWhereNotNull('seasons.deleted_at')
                        ->orWhere('seasons.status', '!=', 0)->orWhereNull('series_owner.id')
                        ->orWhereNull('parent_series.id')->orWhere('parent_series.type', '!=', 'tvshow');
                })->count();
            $invalidEpisodes = DB::table('staging_dataset_records as records')
                ->leftJoin('episodes', 'episodes.id', '=', 'records.record_id')
                ->leftJoin('seasons', 'seasons.id', '=', 'episodes.season_id')
                ->leftJoin('staging_dataset_records as season_owner', function ($join) use ($manifest): void {
                    $join->on('season_owner.record_id', '=', 'episodes.season_id')
                        ->where('season_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('season_owner.entity_type', '=', 'season');
                })
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'episode')
                ->where(function ($query): void {
                    $query->whereNull('episodes.id')->orWhereNotNull('episodes.deleted_at')
                        ->orWhere('episodes.status', '!=', 0)->orWhereNull('seasons.id')
                        ->orWhereColumn('episodes.entertainment_id', '!=', 'seasons.entertainment_id')
                        ->orWhereNull('season_owner.id');
                })->count();
            if ($invalidCatalog || $invalidGenres || $invalidMappings || $invalidSeasons || $invalidEpisodes) {
                $violations[] = "Dataset {$manifest->dataset_id} has invalid catalog relationships: {$invalidGenres} genres, {$invalidCatalog} catalog records, {$invalidMappings} genre mappings, {$invalidSeasons} seasons, {$invalidEpisodes} episodes.";
            }
            $roleNames = array_keys(RbacRegistry::roles());
            DB::table('staging_dataset_records')
                ->where('dataset_id', $manifest->dataset_id)
                ->where('entity_type', 'staff_user')
                ->orderBy('id')
                ->chunk(200, function ($staffRecords) use ($roleNames, $manifest, &$violations): void {
                    foreach ($staffRecords as $record) {
                        if (! preg_match('/\.staff-(\d+)@example\.test$/', $record->entity_key, $matches)) {
                            $violations[] = "Dataset {$manifest->dataset_id} has an invalid synthetic staff email {$record->entity_key}.";
                            continue;
                        }
                        $user = User::query()->where('email', $record->entity_key)->first();
                        $roleName = $roleNames[((int) $matches[1] - 1) % count($roleNames)] ?? null;
                        if ($user === null || $roleName === null || $user->getRoleNames()->all() !== [$roleName]) {
                            $violations[] = "Dataset {$manifest->dataset_id} staff user {$record->entity_key} has an unexpected role assignment.";
                        }
                    }
                });
            $recordCounts = (array) $manifest->record_counts;
            foreach ($counts as $entity => $actual) {
                if (($recordCounts[$entity] ?? 0) !== $actual) {
                    $violations[] = "Dataset {$manifest->dataset_id} manifest {$entity} count does not match its ownership records.";
                }
            }
            $configuredCounts = (array) ($manifest->configuration['counts'] ?? []);
            foreach (['staff' => 'staff', 'customers' => 'customers', 'devices' => 'devices'] as $entity => $targetKey) {
                if (isset($configuredCounts[$targetKey]) && $counts[$entity] !== (int) $configuredCounts[$targetKey]) {
                    $violations[] = "Dataset {$manifest->dataset_id} has {$counts[$entity]} {$entity}; configured target is {$configuredCounts[$targetKey]}.";
                }
            }
            if (isset($configuredCounts['profiles'])) {
                $profileTarget = (int) $configuredCounts['profiles'];
                $tolerance = (int) ceil($profileTarget * 0.1);
                if (abs($counts['profiles'] - $profileTarget) > $tolerance) {
                    $violations[] = "Dataset {$manifest->dataset_id} profile count is outside the configured target's 10% tolerance.";
                }
            }
            $catalogCounts = [
                'genres' => $groupedCounts['genre'] ?? 0,
                'movies' => DB::table('staging_dataset_records as records')->join('entertainments', 'entertainments.id', '=', 'records.record_id')->where('records.dataset_id', $manifest->dataset_id)->where('records.entity_type', 'entertainment')->where('entertainments.type', 'movie')->count(),
                'series' => DB::table('staging_dataset_records as records')->join('entertainments', 'entertainments.id', '=', 'records.record_id')->where('records.dataset_id', $manifest->dataset_id)->where('records.entity_type', 'entertainment')->where('entertainments.type', 'tvshow')->count(),
                'seasons' => $groupedCounts['season'] ?? 0,
                'episodes' => $groupedCounts['episode'] ?? 0,
            ];
            foreach ($catalogCounts as $entity => $actual) {
                if (($recordCounts[$entity] ?? 0) !== $actual) {
                    $violations[] = "Dataset {$manifest->dataset_id} manifest {$entity} count does not match its catalog ownership records.";
                }
                if (isset($configuredCounts[$entity]) && $actual !== (int) $configuredCounts[$entity]) {
                    $violations[] = "Dataset {$manifest->dataset_id} has {$actual} {$entity}; configured target is {$configuredCounts[$entity]}.";
                }
            }
            $registeredCount = array_sum($counts) + array_sum($catalogCounts) + ($groupedCounts['entertainment_genre_mapping'] ?? 0);
            return [
                'dataset_id' => $manifest->dataset_id,
                'profile' => $manifest->profile,
                'status' => $manifest->status,
                'registered_records' => $registeredCount,
                'manifest_counts' => $manifest->record_counts,
                'started_at' => $manifest->started_at?->toIso8601String(),
                'completed_at' => $manifest->completed_at?->toIso8601String(),
            ];
        })->all();

        if ($datasets === []) {
            $violations[] = $datasetId === null
                ? 'No staging dataset manifests exist.'
                : "No staging dataset manifest exists for {$datasetId}.";
        }

        $complete = $datasets !== [];
        foreach ($datasets as $dataset) {
            $complete = $complete && $dataset['status'] === 'complete';
        }

        return [
            'valid' => $violations === [],
            'complete' => $complete,
            'coverage' => 'manifest, staff role, customer identity, profile and device ownership, unpublished bilingual catalog, genre mappings, and series hierarchy; remaining business-domain checks are pending',
            'checked_at' => now()->toIso8601String(),
            'datasets' => $datasets,
            'violations' => array_values(array_unique($violations)),
        ];
    }
}
