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
                'coverage' => 'manifest, staff, customers, profiles, devices, bilingual catalog, cast/crew credits, series hierarchy, plans, subscriptions, and synthetic transactions',
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
                'plans' => $groupedCounts['plan'] ?? 0,
                'subscriptions' => $groupedCounts['subscription'] ?? 0,
                'payments' => $groupedCounts['subscription_transaction'] ?? 0,
                'cast_crew' => $groupedCounts['cast_crew'] ?? 0,
            ];
            $supportedTypes = ['staff_user', 'customer', 'user_multi_profile', 'device', 'genre', 'entertainment', 'entertainment_genre_mapping', 'season', 'episode', 'cast_crew', 'entertainment_talent_mapping', 'plan', 'planlimitation_mapping', 'subscription', 'subscription_transaction'];
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
            $invalidPlans = DB::table('staging_dataset_records as records')
                ->leftJoin('plan', 'plan.id', '=', 'records.record_id')
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'plan')
                ->where(function ($query): void {
                    $query->whereNull('plan.id')->orWhereNotNull('plan.deleted_at')->orWhere('plan.status', '!=', 1);
                })->count();
            $invalidPlanMappings = DB::table('staging_dataset_records as records')
                ->leftJoin('planlimitation_mapping as mappings', 'mappings.id', '=', 'records.record_id')
                ->leftJoin('staging_dataset_records as plan_owner', function ($join) use ($manifest): void {
                    $join->where('plan_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('plan_owner.entity_type', '=', 'plan')
                        ->whereRaw('plan_owner.record_id = CAST(mappings.plan_id AS CHAR)');
                })
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'planlimitation_mapping')
                ->where(function ($query): void {
                    $query->whereNull('mappings.id')->orWhereNotNull('mappings.deleted_at')->orWhereNull('plan_owner.id');
                })->count();
            $invalidSubscriptions = DB::table('staging_dataset_records as records')
                ->leftJoin('subscriptions', 'subscriptions.id', '=', 'records.record_id')
                ->leftJoin('users', 'users.id', '=', 'subscriptions.user_id')
                ->leftJoin('staging_dataset_records as customer_owner', function ($join) use ($manifest): void {
                    $join->on('customer_owner.entity_key', '=', 'users.email')
                        ->where('customer_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('customer_owner.entity_type', '=', 'customer');
                })
                ->leftJoin('staging_dataset_records as plan_owner', function ($join) use ($manifest): void {
                    $join->where('plan_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('plan_owner.entity_type', '=', 'plan')
                        ->whereRaw('plan_owner.record_id = CAST(subscriptions.plan_id AS CHAR)');
                })
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'subscription')
                ->where(function ($query): void {
                    $query->whereNull('subscriptions.id')->orWhereNotNull('subscriptions.deleted_at')
                        ->orWhereNull('customer_owner.id')->orWhereNull('plan_owner.id')
                        ->orWhereNull('subscriptions.start_date')->orWhereNull('subscriptions.end_date')
                        ->orWhereColumn('subscriptions.end_date', '<=', 'subscriptions.start_date')
                        ->orWhere(function ($query): void {
                            $query->where('subscriptions.status', 'active')->where('subscriptions.is_trial', 0)
                                ->whereNull('subscriptions.payment_id');
                        })
                        ->orWhere(function ($query): void {
                            $query->whereNotNull('subscriptions.payment_id')->whereNotExists(function ($subquery): void {
                                $subquery->selectRaw('1')->from('subscriptions_transactions as paid_transaction')
                                    ->whereColumn('paid_transaction.id', 'subscriptions.payment_id')
                                    ->whereColumn('paid_transaction.subscriptions_id', 'subscriptions.id')
                                    ->whereColumn('paid_transaction.user_id', 'subscriptions.user_id')
                                    ->where('paid_transaction.payment_type', 'synthetic-staging')
                                    ->where('paid_transaction.payment_status', 'paid')
                                    ->whereNull('paid_transaction.deleted_at');
                            });
                        })
                        ->orWhere(function ($query): void {
                            $query->where('subscriptions.is_trial', 1)
                                ->where(function ($trial): void {
                                    $trial->where('subscriptions.status', '!=', 'active')->orWhere('subscriptions.total_amount', '!=', 0);
                                });
                        })
                        ->orWhereNotIn('subscriptions.status', ['active', 'inactive']);
                })->count();
            $invalidPayments = DB::table('staging_dataset_records as records')
                ->leftJoin('subscriptions_transactions as transactions', 'transactions.id', '=', 'records.record_id')
                ->leftJoin('staging_dataset_records as subscription_owner', function ($join) use ($manifest): void {
                    $join->where('subscription_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('subscription_owner.entity_type', '=', 'subscription')
                        ->whereRaw('subscription_owner.record_id = CAST(transactions.subscriptions_id AS CHAR)');
                })
                ->leftJoin('subscriptions', 'subscriptions.id', '=', 'transactions.subscriptions_id')
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'subscription_transaction')
                ->where(function ($query): void {
                    $query->whereNull('transactions.id')->orWhereNotNull('transactions.deleted_at')
                        ->orWhereNull('subscription_owner.id')->orWhereColumn('transactions.user_id', '!=', 'subscriptions.user_id')
                        ->orWhere('transactions.payment_type', '!=', 'synthetic-staging')
                        ->orWhereNotIn('transactions.payment_status', ['paid', 'failed']);
                })->count();
            if ($invalidPlans || $invalidPlanMappings || $invalidSubscriptions || $invalidPayments) {
                $violations[] = "Dataset {$manifest->dataset_id} has invalid subscription relationships: {$invalidPlans} plans, {$invalidPlanMappings} plan limitations, {$invalidSubscriptions} subscriptions, {$invalidPayments} synthetic transactions.";
            }
            $invalidTalent = DB::table('staging_dataset_records as records')
                ->leftJoin('cast_crew', 'cast_crew.id', '=', 'records.record_id')
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'cast_crew')
                ->where(function ($query): void {
                    $query->whereNull('cast_crew.id')->orWhereNotNull('cast_crew.deleted_at')
                        ->orWhere('cast_crew.status', '!=', 1)->orWhereNotIn('cast_crew.type', ['actor', 'director']);
                })->count();
            $invalidTalentMappings = DB::table('staging_dataset_records as records')
                ->leftJoin('entertainment_talent_mapping as mappings', 'mappings.id', '=', 'records.record_id')
                ->leftJoin('staging_dataset_records as content_owner', function ($join) use ($manifest): void {
                    $join->on('content_owner.record_id', '=', 'mappings.entertainment_id')
                        ->where('content_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('content_owner.entity_type', '=', 'entertainment');
                })
                ->leftJoin('staging_dataset_records as talent_owner', function ($join) use ($manifest): void {
                    $join->on('talent_owner.record_id', '=', 'mappings.talent_id')
                        ->where('talent_owner.dataset_id', '=', $manifest->dataset_id)
                        ->where('talent_owner.entity_type', '=', 'cast_crew');
                })
                ->where('records.dataset_id', $manifest->dataset_id)
                ->where('records.entity_type', 'entertainment_talent_mapping')
                ->where(function ($query): void {
                    $query->whereNull('mappings.id')->orWhereNotNull('mappings.deleted_at')
                        ->orWhereNull('content_owner.id')->orWhereNull('talent_owner.id');
                })->count();
            if ($invalidTalent || $invalidTalentMappings) {
                $violations[] = "Dataset {$manifest->dataset_id} has {$invalidTalent} invalid fictional cast/crew records and {$invalidTalentMappings} invalid title credits.";
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
            foreach (['staff' => 'staff', 'customers' => 'customers', 'devices' => 'devices', 'cast_crew' => 'cast_crew', 'plans' => 'plans', 'subscriptions' => 'subscriptions', 'payments' => 'payments'] as $entity => $targetKey) {
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
                'cast_crew_mappings' => $groupedCounts['entertainment_talent_mapping'] ?? 0,
            ];
            foreach ($catalogCounts as $entity => $actual) {
                if (($recordCounts[$entity] ?? 0) !== $actual) {
                    $violations[] = "Dataset {$manifest->dataset_id} manifest {$entity} count does not match its catalog ownership records.";
                }
                if (isset($configuredCounts[$entity]) && $actual !== (int) $configuredCounts[$entity]) {
                    $violations[] = "Dataset {$manifest->dataset_id} has {$actual} {$entity}; configured target is {$configuredCounts[$entity]}.";
                }
            }
            $registeredCount = array_sum($counts) + array_sum($catalogCounts) + ($groupedCounts['entertainment_genre_mapping'] ?? 0)
                + ($groupedCounts['planlimitation_mapping'] ?? 0);
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
            'coverage' => 'manifest, staff role, customer identity, profile and device ownership, unpublished bilingual catalog, genre mappings, fictional cast/crew credits, series hierarchy, plans, subscription lifecycle, and synthetic payment attempts; remaining business-domain checks are pending',
            'checked_at' => now()->toIso8601String(),
            'datasets' => $datasets,
            'violations' => array_values(array_unique($violations)),
        ];
    }
}
