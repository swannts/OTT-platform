<?php

namespace App\Console\Commands;

use App\Models\StagingDatasetManifest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class StagingDataStatus extends Command
{
    protected $signature = 'ott:staging-data-status {--dataset= : show one dataset}';
    protected $description = 'Show staging dataset manifests and registered record counts';

    public function handle(): int
    {
        if (! Schema::hasTable('staging_dataset_manifests')) {
            $this->warn('No staging dataset manifest table exists. Apply migrations before generating staging data.');

            return self::SUCCESS;
        }

        $query = StagingDatasetManifest::query()->orderBy('dataset_id');
        if ($this->option('dataset')) {
            $query->where('dataset_id', $this->option('dataset'));
        }
        $manifests = $query->get();
        if ($manifests->isEmpty()) {
            $this->line('No staging datasets are registered.');

            return self::SUCCESS;
        }

        $this->table(
            ['Dataset', 'Profile', 'Status', 'Seed', 'Reference date', 'Owned records', 'Started'],
            $manifests->map(fn (StagingDatasetManifest $item): array => [
                $item->dataset_id,
                $item->profile,
                $item->status,
                (string) $item->seed,
                $item->reference_date?->toDateString(),
                (string) (array_sum(array_map('intval', (array) $item->record_counts)) ?: 0),
                $item->started_at?->toIso8601String(),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
