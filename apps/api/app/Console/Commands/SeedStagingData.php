<?php

namespace App\Console\Commands;

use App\Models\StagingDatasetManifest;
use App\Support\Staging\StagingDataSafety;
use App\Support\Staging\StagingCustomerGenerator;
use App\Support\Staging\StagingCatalogGenerator;
use App\Support\Staging\StagingGenerationContext;
use App\Support\Staging\StagingProfile;
use App\Support\Staging\StagingStaffGenerator;
use App\Support\Staging\StagingSubscriptionGenerator;
use Database\Seeders\RbacFoundationSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class SeedStagingData extends Command
{
    protected $signature = 'ott:seed-staging
        {--profile= : small, standard, or large}
        {--history-months= : simulated history period}
        {--seed= : deterministic random seed}
        {--reference-date= : fixed simulation date (YYYY-MM-DD)}
        {--staff= : override staff target}
        {--customers= : override customer target}
        {--profiles= : override household profile target}
        {--devices= : override device target}
        {--movies= : override movie target}
        {--series= : override series target}
        {--counts= : JSON object of any supported profile count overrides}
        {--batch-size= : override insert batch size}
        {--dry-run : print configuration and safety checks without writing}';

    protected $description = 'Generate a protected, deterministic staging dataset';

    public function handle(
        StagingDataSafety $safety,
        StagingStaffGenerator $staffGenerator,
        StagingCustomerGenerator $customerGenerator,
        StagingCatalogGenerator $catalogGenerator,
        StagingSubscriptionGenerator $subscriptionGenerator,
    ): int {
        try {
            $profileName = (string) ($this->option('profile') ?: config('staging-data.profile'));
            $historyMonths = (int) ($this->option('history-months') ?? config('staging-data.history_months'));
            $seed = (int) ($this->option('seed') ?? config('staging-data.seed'));
            $referenceDate = (string) ($this->option('reference-date') ?? config('staging-data.reference_date'));
            $overrides = [];
            if ($this->option('counts') !== null) {
                $parsedOverrides = json_decode((string) $this->option('counts'), true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($parsedOverrides) || array_is_list($parsedOverrides)) {
                    throw new \InvalidArgumentException('--counts must be a JSON object of entity names to non-negative integer targets.');
                }
                foreach ($parsedOverrides as $entity => $count) {
                    if (! is_string($entity) || ! is_int($count) || $count < 0) {
                        throw new \InvalidArgumentException('--counts values must be non-negative JSON integers.');
                    }
                }
                $overrides = $parsedOverrides;
            }
            $overrides = array_replace($overrides, array_filter([
                'staff' => $this->option('staff'),
                'customers' => $this->option('customers'),
                'profiles' => $this->option('profiles'),
                'devices' => $this->option('devices'),
                'movies' => $this->option('movies'),
                'series' => $this->option('series'),
            ], static fn ($value): bool => $value !== null));
            $profile = StagingProfile::fromConfig(
                $profileName,
                $historyMonths,
                (int) ($this->option('batch-size') ?? config('staging-data.batch_size')),
                array_map('intval', $overrides),
            );
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $referenceDate)) {
                throw new \InvalidArgumentException('Reference date must use YYYY-MM-DD format.');
            }
            $date = $referenceDate;
            $datasetId = (string) (config('staging-data.dataset_id') ?: 'staging-'.$profileName);
            $context = new StagingGenerationContext($datasetId, $seed, $profile, $date);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $violations = $safety->violations($profileName);
        $this->table(['Setting', 'Value'], [
            ['Dataset', $datasetId],
            ['Profile', $profileName],
            ['Seed', (string) $seed],
            ['Reference date', $date],
            ['History months', (string) $historyMonths],
            ['Configured target customers', (string) $profile->counts['customers']],
            ['Configured target movies', (string) $profile->counts['movies']],
            ['Configured target series', (string) $profile->counts['series']],
            ['Configured target episodes', (string) $profile->counts['episodes']],
            ['Currently implemented generation', 'RBAC, staff, customers, profiles, devices, bilingual catalog, series hierarchy, plans, subscription histories, and synthetic payment attempts'],
        ]);

        if ($violations !== []) {
            $this->warn('Safety gate is closed:');
            foreach ($violations as $violation) {
                $this->line(" - {$violation}");
            }
        } else {
            $this->info('All configured staging safety checks passed.');
        }

        if ($this->option('dry-run')) {
            $this->comment('Dry run: no database writes were performed.');

            return $violations === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($violations !== []) {
            return self::FAILURE;
        }

        $safety->assertSafe($profileName);
        $lock = Cache::lock('ott:staging-seed:'.$datasetId, 86400);
        if (! $lock->get()) {
            $this->error("Dataset {$datasetId} is already being generated.");

            return self::FAILURE;
        }

        try {
            $configuration = [
                'profile' => $profileName,
                'seed' => $seed,
                'reference_date' => $date,
                'history_months' => $historyMonths,
                'batch_size' => $profile->batchSize,
                'counts' => $profile->counts,
                'implemented_domains' => ['rbac', 'staff', 'customers', 'profiles', 'devices', 'genres', 'movies', 'series', 'seasons', 'episodes', 'plans', 'subscriptions', 'payments'],
            ];
            $manifest = StagingDatasetManifest::query()->where('dataset_id', $datasetId)->first();
            $savedInputs = (array) ($manifest?->configuration ?? []);
            $requestedInputs = $configuration;
            unset($savedInputs['implemented_domains'], $requestedInputs['implemented_domains']);
            if ($manifest && ($this->canonicalizeConfiguration($savedInputs) !== $this->canonicalizeConfiguration($requestedInputs) || $manifest->status === 'complete')) {
                $this->error('This dataset already has a different or completed manifest. Use a new dataset identifier; reset is not available until all generated domains have safe ownership tracking.');

                return self::FAILURE;
            }

            $manifest ??= StagingDatasetManifest::query()->create([
                'dataset_id' => $datasetId,
                'profile' => $profileName,
                'seed' => $seed,
                'reference_date' => $date,
                'status' => 'running',
                'configuration' => $configuration,
                'started_at' => now(),
            ]);
            $manifest->forceFill(['status' => 'running', 'configuration' => $configuration, 'failure_summary' => null])->save();

            try {
                $this->call(RbacFoundationSeeder::class);
                $created = $staffGenerator->generate($context, (string) config('staging-data.staff_password'));
                $customerResults = $customerGenerator->generate($context, (string) config('staging-data.customer_password'));
                $catalogResults = $catalogGenerator->generate($context);
                $subscriptionResults = $subscriptionGenerator->generate($context);
                $recordCounts = DB::table('staging_dataset_records')
                    ->where('dataset_id', $datasetId)
                    ->select('entity_type', DB::raw('count(*) as records'))
                    ->groupBy('entity_type')
                    ->pluck('records', 'entity_type')
                    ->map(static fn ($count): int => (int) $count)
                    ->all();
                $recordCounts = [
                    'staff' => $recordCounts['staff_user'] ?? 0,
                    'customers' => $recordCounts['customer'] ?? 0,
                    'profiles' => $recordCounts['user_multi_profile'] ?? 0,
                    'devices' => $recordCounts['device'] ?? 0,
                    'genres' => $recordCounts['genre'] ?? 0,
                    'movies' => $recordCounts['entertainment'] ?? 0,
                    'series' => 0,
                    'seasons' => $recordCounts['season'] ?? 0,
                    'episodes' => $recordCounts['episode'] ?? 0,
                    'genre_mappings' => $recordCounts['entertainment_genre_mapping'] ?? 0,
                    'plans' => $recordCounts['plan'] ?? 0,
                    'subscriptions' => $recordCounts['subscription'] ?? 0,
                    'payments' => $recordCounts['subscription_transaction'] ?? 0,
                ];
                $recordCounts['movies'] = $catalogResults['movies'];
                $recordCounts['series'] = $catalogResults['series'];
                $manifest->forceFill([
                    'status' => 'partial',
                    'record_counts' => $recordCounts,
                    'failure_summary' => null,
                ])->save();
                $this->info(sprintf(
                    'Created %d new staff, %d new customers, %d new profiles, and %d new devices in this run.',
                    $created,
                    $customerResults['customers'],
                    $customerResults['profiles'],
                    $customerResults['devices'],
                ));
                $this->info(sprintf(
                    'Dataset catalog totals: %d genres, %d movies, %d series, %d seasons, and %d episodes.',
                    $catalogResults['genres'],
                    $catalogResults['movies'],
                    $catalogResults['series'],
                    $catalogResults['seasons'],
                    $catalogResults['episodes'],
                ));
                $this->info(sprintf(
                    'Dataset subscription totals: %d plans, %d subscriptions (%d current active, %d trials), and %d synthetic payment attempts.',
                    $subscriptionResults['plans'],
                    $subscriptionResults['subscriptions'],
                    $subscriptionResults['active'],
                    $subscriptionResults['trials'],
                    $subscriptionResults['payments'],
                ));
                $this->warn('This milestone remains partial: media assets, refunds/reconciliation, playback, engagement, marketing, support, notifications, and analytics are not yet generated. Catalog entries are unpublished and have no media attached; synthetic transactions never contact payment providers.');

                return self::SUCCESS;
            } catch (Throwable $exception) {
                $manifest->forceFill(['status' => 'failed', 'failure_summary' => mb_substr($exception->getMessage(), 0, 4000)])->save();
                throw $exception;
            }
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    /** @param array<string, mixed> $configuration
     *  @return array<string, mixed>
     */
    private function canonicalizeConfiguration(array $configuration): array
    {
        foreach ($configuration as $key => $value) {
            if (is_array($value)) {
                $configuration[$key] = $this->canonicalizeConfiguration($value);
            }
        }

        if (! array_is_list($configuration)) {
            ksort($configuration);
        }

        return $configuration;
    }
}
