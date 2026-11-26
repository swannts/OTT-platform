<?php

namespace App\Console\Commands;

use App\Support\Staging\StagingDataIntegrityValidator;
use Illuminate\Console\Command;

class VerifyStagingData extends Command
{
    protected $signature = 'ott:verify-staging-data {--dataset= : validate one registered dataset} {--format=table : table or json}';
    protected $description = 'Validate staging dataset manifests and currently supported ownership relationships';

    public function handle(StagingDataIntegrityValidator $validator): int
    {
        $report = $validator->report($this->option('dataset'));
        if ($this->option('format') === 'json') {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->info($report['valid'] ? 'Currently supported staging integrity checks passed.' : 'Staging integrity checks failed.');
            if (! $report['complete']) {
                $this->warn('The dataset is partial or incomplete; this validator does not cover all business domains yet.');
            }
            $this->comment('Coverage: '.$report['coverage']);
            if ($report['datasets'] !== []) {
                $this->table(
                    ['Dataset', 'Profile', 'Status', 'Registered records'],
                    array_map(static fn (array $dataset): array => [
                        $dataset['dataset_id'], $dataset['profile'], $dataset['status'], $dataset['registered_records'],
                    ], $report['datasets']),
                );
            }
            foreach ($report['violations'] as $violation) {
                $this->error($violation);
            }
        }

        return $report['valid'] && $report['complete'] ? self::SUCCESS : self::FAILURE;
    }
}
