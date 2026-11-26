<?php

namespace App\Console\Commands;

use App\Models\StagingDatasetManifest;
use App\Support\Staging\StagingDataSafety;
use App\Support\Staging\StagingCustomerGenerator;
use App\Support\Staging\StagingGenerationContext;
use App\Support\Staging\StagingProfile;
use App\Support\Staging\StagingStaffGenerator;
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
        {--batch-size= : override insert batch size}
        {--dry-run : print configuration and safety checks without writing}';

    protected $description = 'Generate a protected, deterministic staging dataset';

    public function handle(
        StagingDataSafety $safety,
        StagingStaffGenerator $staffGenerator,
        StagingCustomerGenerator $customerGenerator,
    ): int {
        try {
            $profileName = (string) ($this->option('profile') ?: config('staging-data.profile'));
            $historyMonths = (int) ($this->option('history-months') ?? config('staging-data.history_months'));
            $seed = (int) ($this->option('seed') ?? config('staging-data.seed'));
            $referenceDate = (string) ($this->option('reference-date') ?? config('staging-data.reference_date'));
            $overrides = array_filter([
                'staff' => $this->option('staff'),
                'customers' => $this->option('customers'),
                'profiles' => $this->option('profiles'),
                'devices' => $this->option('devices'),
                'movies' => $this->option('movies'),
                'series' => $this->option('series'),
            ], static fn ($value): bool => $value !== null);
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
            ['Currently implemented generation', 'RBAC, staff, customers, profiles, and devices'],
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
                'implemented_domains' => ['rbac', 'staff', 'customers', 'profiles', 'devices'],
            ];
            $manifest = StagingDatasetManifest::query()->where('dataset_id', $datasetId)->first();
            if ($manifest && ($manifest->configuration !== $configuration || $manifest->status === 'complete')) {
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
            $manifest->forceFill(['status' => 'running', 'failure_summary' => null])->save();

            try {
                $this->call(RbacFoundationSeeder::class);
                $created = $staffGenerator->generate($context, (string) config('staging-data.staff_password'));
                $customerResults = $customerGenerator->generate($context, (string) config('staging-data.customer_password'));
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
                ];
                $manifest->forceFill([
                    'status' => 'partial',
                    'record_counts' => $recordCounts,
                    'failure_summary' => null,
                ])->save();
                $this->info(sprintf(
                    'Created %d staff, %d customers, %d profiles, and %d devices in this run.',
                    $created,
                    $customerResults['customers'],
                    $customerResults['profiles'],
                    $customerResults['devices'],
                ));
                $this->warn('This milestone is still in progress: catalog, finance, playback, engagement, marketing, support, notifications and analytics are not generated yet.');

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
}
