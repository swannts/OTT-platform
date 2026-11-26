<?php

namespace App\Support\Staging;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class StagingOperationsHistoryGenerator
{
    /** @return array{incidents: int, open: int, acknowledged: int, resolved: int, audit_logs: int} */
    public function generate(StagingGenerationContext $context): array
    {
        $staff = DB::table('staging_dataset_records as owners')->join('users', 'users.email', '=', 'owners.entity_key')
            ->join('model_has_roles as assigned_roles', function ($join): void {
                $join->on('assigned_roles.model_id', '=', 'users.id')->where('assigned_roles.model_type', '=', 'App\\Models\\User');
            })->join('roles', 'roles.id', '=', 'assigned_roles.role_id')
            ->where('owners.dataset_id', $context->datasetId)->where('owners.entity_type', 'staff_user')
            ->where('roles.name', 'technical_operations_admin')->whereNull('users.deleted_at')
            ->orderBy('users.id')->get(['users.id', 'users.created_at']);
        if (($context->profile->counts['incidents'] > 0 || $context->profile->counts['audit_logs'] > 0) && $staff->isEmpty()) {
            throw new RuntimeException('Staging operations history requires owned technical-operations staff accounts.');
        }

        $incidentStates = ['open' => 0, 'acknowledged' => 0, 'resolved' => 0];
        $incidentTarget = $context->profile->counts['incidents'];
        $datasetPart = StagingIdentifiers::datasetPart($context->datasetId);
        for ($offset = 0; $offset < $incidentTarget; $offset += $context->profile->batchSize) {
            $end = min($incidentTarget, $offset + $context->profile->batchSize);
            DB::transaction(function () use ($offset, $end, $context, $datasetPart, $staff, &$incidentStates): void {
                $keys = [];
                for ($ordinal = $offset + 1; $ordinal <= $end; $ordinal++) $keys[] = sprintf('stg-%s-incident-%06d', $datasetPart, $ordinal);
                $existingOwners = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                    ->where('entity_type', 'operations_incident')->whereIn('entity_key', $keys)->get(['entity_key', 'record_id'])->keyBy('entity_key');
                $titles = [];
                for ($ordinal = $offset + 1; $ordinal <= $end; $ordinal++) $titles[] = sprintf('[STAGING SIMULATION] Playback delivery incident %03d', $ordinal);
                $unownedTitles = DB::table('operations_incidents')->whereIn('title', $titles)->pluck('title')->flip();
                $rows = [];
                $owners = [];
                for ($ordinal = $offset + 1; $ordinal <= $end; $ordinal++) {
                    $key = sprintf('stg-%s-incident-%06d', $datasetPart, $ordinal);
                    $owner = $existingOwners->get($key);
                    $title = sprintf('[STAGING SIMULATION] Playback delivery incident %03d', $ordinal);
                    if ($owner !== null) {
                        $existing = DB::table('operations_incidents')->where('id', $owner->record_id)->where('title', $title)->first(['status']);
                        if ($existing === null) throw new RuntimeException("Owned staging incident {$key} is missing or inconsistent.");
                        if (array_key_exists($existing->status, $incidentStates)) $incidentStates[$existing->status]++;
                        continue;
                    }
                    if ($unownedTitles->has($title)) throw new RuntimeException("Incident {$key} exists without staging ownership.");
                    $status = match ($ordinal % 3) { 0 => 'open', 1 => 'acknowledged', default => 'resolved' };
                    $createdAt = $this->historicalTime($context, $ordinal, CarbonImmutable::parse($staff[($ordinal - 1) % $staff->count()]->created_at), 5);
                    $cutoff = $context->referenceDate->endOfDay();
                    $acknowledgedAt = $status === 'open' ? null : $createdAt->addMinutes(15 + ($ordinal % 200))->min($cutoff);
                    $resolvedAt = $status === 'resolved' ? $acknowledgedAt->addHours(1 + ($ordinal % 96))->min($cutoff) : null;
                    $severity = ['low', 'medium', 'high', 'critical'][($ordinal - 1) % 4];
                    $assignee = (int) $staff[($ordinal - 1) % $staff->count()]->id;
                    $rows[] = [
                        'title' => $title,
                        'summary' => 'Synthetic historical operations scenario for staging workflow tests. This record does not describe live infrastructure.',
                        'severity' => $severity,
                        'status' => $status,
                        'affected_services' => json_encode(['playback', 'media-delivery'], JSON_THROW_ON_ERROR),
                        'opened_by' => $assignee,
                        'assigned_to' => $assignee,
                        'resolved_by' => $status === 'resolved' ? $assignee : null,
                        'acknowledged_at' => $acknowledgedAt,
                        'resolved_at' => $resolvedAt,
                        'created_at' => $createdAt,
                        'updated_at' => $resolvedAt ?? $acknowledgedAt ?? $createdAt,
                    ];
                    $owners[] = ['dataset_id' => $context->datasetId, 'entity_type' => 'operations_incident', 'entity_key' => $key,
                        'record_id' => null, 'created_at' => now(), 'updated_at' => now()];
                    $incidentStates[$status]++;
                }
                if ($rows === []) return;
                DB::table('operations_incidents')->insert($rows);
                $incidentIds = DB::table('operations_incidents')->whereIn('title', array_column($rows, 'title'))->get(['id', 'title'])->keyBy('title');
                foreach ($owners as &$owner) {
                    $ordinal = (int) substr($owner['entity_key'], -6);
                    $owner['record_id'] = (string) $incidentIds[sprintf('[STAGING SIMULATION] Playback delivery incident %03d', $ordinal)]->id;
                }
                unset($owner);
                DB::table('staging_dataset_records')->insert($owners);
            });
        }
        $actualIncidents = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)->where('entity_type', 'operations_incident')->count();
        if ($actualIncidents !== $incidentTarget) throw new RuntimeException("Staging operations generated {$actualIncidents} of {$incidentTarget} configured incidents.");

        $auditTarget = $context->profile->counts['audit_logs'];
        $auditTotal = $this->generateAuditLogs($context, $staff, $datasetPart, $auditTarget);
        return ['incidents' => $actualIncidents, ...$incidentStates, 'audit_logs' => $auditTotal];
    }

    /** @param \Illuminate\Support\Collection<int, object> $staff */
    private function generateAuditLogs(StagingGenerationContext $context, $staff, string $datasetPart, int $target): int
    {
        if ($target === 0) return 0;
        $sourceCount = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)->whereNotIn('entity_type', ['admin_audit_log'])->count();
        if ($sourceCount === 0) throw new RuntimeException('Staging audit history requires owned source records.');
        $sourceCursor = 0;
        $sourceRead = 0;
        for ($offset = 0; $offset < $target;) {
            $limit = min($context->profile->batchSize, $target - $offset, $sourceCount - $sourceRead);
            $sources = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                ->whereNotIn('entity_type', ['admin_audit_log'])->where('id', '>', $sourceCursor)->orderBy('id')->limit($limit)
                ->get(['id', 'entity_type', 'entity_key', 'record_id']);
            if ($sources->isEmpty()) throw new RuntimeException('Could not resolve staging source rows for audit fixtures.');
            $sourceCursor = (int) $sources->last()->id;
            $sourceRead += $sources->count();
            $auditKeys = [];
            for ($ordinal = $offset + 1; $ordinal <= $offset + $limit; $ordinal++) $auditKeys[] = sprintf('stg-%s-audit-%08d', $datasetPart, $ordinal);
            $existingOwners = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                ->where('entity_type', 'admin_audit_log')->whereIn('entity_key', $auditKeys)->get(['entity_key', 'record_id'])->keyBy('entity_key');
            $rows = [];
            $owners = [];
            foreach ($sources as $index => $source) {
                $ordinal = $offset + $index + 1;
                $key = sprintf('stg-%s-audit-%08d', $datasetPart, $ordinal);
                $owned = $existingOwners->get($key);
                if ($owned !== null) {
                    if (! DB::table('admin_audit_logs')->where('id', $owned->record_id)->exists()) throw new RuntimeException("Owned audit record {$key} is missing.");
                    continue;
                }
                $actor = $staff[$ordinal % $staff->count()];
                $eventAt = $this->historicalTime($context, $ordinal, CarbonImmutable::parse($actor->created_at), 1);
                $rows[] = [
                    'actor_id' => (int) $actor->id,
                    'action' => 'staging.fixture.generated',
                    'resource_type' => 'staging_dataset_record',
                    'resource_id' => (string) $source->id,
                    'request_id' => substr(hash('sha256', $context->datasetId.':audit-request:'.$ordinal), 0, 64),
                    'before_values' => null,
                    'after_values' => json_encode(['dataset_id' => $context->datasetId, 'entity_type' => $source->entity_type,
                        'fixture_key' => $source->entity_key, 'synthetic' => true], JSON_THROW_ON_ERROR),
                    'outcome' => 'success',
                    'created_at' => $eventAt,
                ];
                $owners[] = ['dataset_id' => $context->datasetId, 'entity_type' => 'admin_audit_log', 'entity_key' => $key,
                    'record_id' => null, 'created_at' => now(), 'updated_at' => now()];
            }
            if ($rows !== []) DB::transaction(function () use ($rows, $owners): void {
                DB::table('admin_audit_logs')->insert($rows);
                $firstId = (int) DB::scalar('SELECT LAST_INSERT_ID()');
                if ($firstId < 1) throw new RuntimeException('Could not resolve generated audit-log identifiers.');
                foreach ($owners as $index => &$owner) $owner['record_id'] = (string) ($firstId + $index);
                unset($owner);
                DB::table('staging_dataset_records')->insert($owners);
            });
            $offset += $sources->count();
            if ($sourceRead >= $sourceCount) {
                $sourceCursor = 0;
                $sourceRead = 0;
            }
        }
        $actual = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)->where('entity_type', 'admin_audit_log')->count();
        if ($actual !== $target) throw new RuntimeException("Staging audit history generated {$actual} of {$target} configured records.");
        return $actual;
    }

    private function historicalTime(StagingGenerationContext $context, int $ordinal, CarbonImmutable $staffCreatedAt, int $daysBeforeReference): CarbonImmutable
    {
        $latest = $context->referenceDate->endOfDay()->subDays($daysBeforeReference);
        $earliest = $staffCreatedAt->greaterThan($context->historyStart()) ? $staffCreatedAt : $context->historyStart();
        if ($earliest->greaterThan($latest)) $latest = $context->referenceDate->endOfDay();
        $seconds = max(1, $earliest->diffInSeconds($latest));
        $hash = hash('sha256', $context->seed.':operations:'.$ordinal);
        return $earliest->addSeconds(hexdec(substr($hash, 0, 8)) % $seconds);
    }
}
