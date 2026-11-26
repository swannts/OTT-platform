<?php

namespace App\Support\Staging;

use App\Services\AdminAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class StagingAnalyticsReportGenerator
{
    private const GROUPS = ['audience', 'subscriptions', 'content', 'revenue', 'playback'];

    public function generate(StagingGenerationContext $context, AdminAnalyticsService $analytics): int
    {
        $target = $context->profile->counts['analytics_reports'];
        if ($target === 0) return 0;
        $analysts = DB::table('staging_dataset_records as owners')->join('users', 'users.email', '=', 'owners.entity_key')
            ->join('model_has_roles as user_roles', function ($join): void {
                $join->on('user_roles.model_id', '=', 'users.id')->where('user_roles.model_type', '=', 'App\\Models\\User');
            })->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('owners.dataset_id', $context->datasetId)->where('owners.entity_type', 'staff_user')
            ->where('roles.name', 'analytics_manager')->where('users.status', 1)->whereNull('users.deleted_at')
            ->orderBy('users.id')->pluck('users.id')->map(static fn ($id): int => (int) $id)->all();
        if ($analysts === []) throw new RuntimeException('Staging analytics reports require an owned analytics-manager account.');

        $datasetPart = StagingIdentifiers::datasetPart($context->datasetId);
        for ($offset = 0; $offset < $target; $offset += $context->profile->batchSize) {
            $end = min($target, $offset + $context->profile->batchSize);
            $keys = [];
            for ($ordinal = $offset + 1; $ordinal <= $end; $ordinal++) $keys[] = sprintf('stg-%s-analytics-report-%06d', $datasetPart, $ordinal);
            $existingOwners = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                ->where('entity_type', 'analytics_report_run')->whereIn('entity_key', $keys)->get(['entity_key', 'record_id'])->keyBy('entity_key');
            $rows = [];
            $owners = [];
            for ($ordinal = $offset + 1; $ordinal <= $end; $ordinal++) {
                $key = sprintf('stg-%s-analytics-report-%06d', $datasetPart, $ordinal);
                $group = self::GROUPS[($ordinal - 1) % count(self::GROUPS)];
                $owner = $existingOwners->get($key);
                if ($owner !== null) {
                    if (! DB::table('analytics_report_runs')->where('id', $owner->record_id)->where('requested_by', $analysts[($ordinal - 1) % count($analysts)])->where('metric_group', $group)->exists()) {
                        throw new RuntimeException("Owned analytics report {$key} is missing or inconsistent.");
                    }
                    continue;
                }
                $from = $context->historyStart();
                $to = $context->referenceDate;
                try {
                    $result = $analytics->group($group, $from, $to);
                } catch (\Throwable $exception) {
                    throw new RuntimeException("Could not derive the {$group} staging analytics report from generated source data.", previous: $exception);
                }
                $at = $context->referenceDate->endOfDay();
                $rows[] = [
                    'requested_by' => $analysts[($ordinal - 1) % count($analysts)],
                    'metric_group' => $group,
                    'date_from' => $from->toDateString(),
                    'date_to' => $to->toDateString(),
                    'scheduled_at' => $at,
                    'status' => 'ready',
                    'result' => json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'error_code' => null,
                    'generated_at' => $at,
                    'created_at' => $at,
                    'updated_at' => $at,
                    '_ownership_key' => $key,
                ];
            }
            if ($rows === []) continue;
            $ownershipKeys = array_column($rows, '_ownership_key');
            foreach ($rows as &$row) unset($row['_ownership_key']);
            unset($row);
            DB::transaction(function () use ($rows, $ownershipKeys, $context): void {
                DB::table('analytics_report_runs')->insert($rows);
                $firstId = (int) DB::scalar('SELECT LAST_INSERT_ID()');
                if ($firstId < 1) throw new RuntimeException('Could not resolve generated analytics report identifiers.');
                $owners = [];
                foreach ($ownershipKeys as $index => $key) $owners[] = ['dataset_id' => $context->datasetId, 'entity_type' => 'analytics_report_run',
                    'entity_key' => $key, 'record_id' => (string) ($firstId + $index), 'created_at' => now(), 'updated_at' => now()];
                DB::table('staging_dataset_records')->insert($owners);
            });
        }
        $actual = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)->where('entity_type', 'analytics_report_run')->count();
        if ($actual !== $target) throw new RuntimeException("Staging analytics generated {$actual} of {$target} configured report runs.");
        return $actual;
    }
}
