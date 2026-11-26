<?php

namespace App\Support\Staging;

use App\Models\AdminContentReviewSubmission;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Entertainment\Models\Entertainment;
use RuntimeException;

final class StagingContentReviewGenerator
{
    private const SNAPSHOT_FIELDS = [
        'name', 'name_bn', 'description', 'description_bn', 'type', 'content_rating',
        'release_date', 'plan_id', 'movie_access', 'video_upload_type', 'status', 'media_asset_id',
    ];

    /** @return array{content_review_submissions:int,in_review:int,approved:int,rejected:int} */
    public function generate(StagingGenerationContext $context): array
    {
        $target = $context->profile->counts['content_review_submissions'];
        $empty = ['content_review_submissions' => 0, 'in_review' => 0, 'approved' => 0, 'rejected' => 0];
        if ($target === 0) return $empty;

        $contentIds = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
            ->where('entity_type', 'entertainment')->orderBy('entity_key')->pluck('record_id')->all();
        if ($contentIds === []) throw new RuntimeException('Content review history requires owned staging catalog content.');

        $staff = DB::table('staging_dataset_records as owners')
            ->join('users', 'users.email', '=', 'owners.entity_key')
            ->join('model_has_roles as assignments', function ($join): void {
                $join->on('assignments.model_id', '=', 'users.id')->where('assignments.model_type', '=', User::class);
            })
            ->join('roles', 'roles.id', '=', 'assignments.role_id')
            ->where('owners.dataset_id', $context->datasetId)->where('owners.entity_type', 'staff_user')
            ->whereIn('roles.name', ['content_editor', 'content_manager', 'content_reviewer'])
            ->where('users.status', 1)->orderBy('users.id')
            ->get(['users.id', 'roles.name as role'])->groupBy('role');
        $submitters = $staff->get('content_editor', collect())->pluck('id')->unique()->values()->all();
        $reviewers = $staff->get('content_reviewer', collect())->pluck('id')->unique()->values()->all();
        if ($submitters === [] || $reviewers === []) throw new RuntimeException('Content review history requires active, owned content editors and independent reviewers.');

        $perContent = intdiv($target, count($contentIds));
        $remainder = $target % count($contentIds);
        $batch = [];
        $totals = $empty;
        $now = now('UTC')->toDateTimeString();
        $start = $context->historyStart();
        $end = Carbon::parse($context->referenceDate, 'UTC');
        $span = max(1, $start->diffInSeconds($end));

        foreach ($contentIds as $index => $contentId) {
            $revisions = $perContent + ($index < $remainder ? 1 : 0);
            if ($revisions === 0) continue;
            $item = Entertainment::query()->find($contentId);
            if (! $item) throw new RuntimeException("Owned staging content {$contentId} is missing.");
            $snapshot = $this->snapshot($item);
            $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $hash = hash('sha256', $snapshotJson);
            $keys = [];
            for ($revision = 1; $revision <= $revisions; $revision++) $keys[] = (string) $contentId.':'.$revision;

            $owners = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                ->where('entity_type', 'content_review_submission')->whereIn('entity_key', $keys)
                ->get(['entity_key', 'record_id'])->keyBy('entity_key');
            $existing = AdminContentReviewSubmission::query()->where('content_type', 'entertainment')
                ->where('content_id', (string) $contentId)->whereBetween('revision', [1, $revisions])
                ->get()->keyBy('revision');

            foreach ($keys as $offset => $key) {
                $revision = $offset + 1;
                $owner = $owners->get($key);
                $record = $existing->get($revision);
                if ($owner) {
                    if (! $record || (string) $owner->record_id !== (string) $record->getKey()) {
                        throw new RuntimeException("Owned content review {$key} is missing or no longer matches its ownership record.");
                    }
                    if (! hash_equals($hash, $record->snapshot_hash)) throw new RuntimeException("Staging content {$contentId} changed after review generation.");
                    continue;
                }
                if ($record) throw new RuntimeException("Refusing to adopt unowned content review {$key}.");

                $isLatest = $revision === $revisions;
                $status = $isLatest
                    ? match ($index % 10) { 0 => 'in_review', 1 => 'rejected', default => 'approved' }
                    : ($revision % 2 === 0 ? 'approved' : 'rejected');
                $reviewerId = $status === 'in_review' ? null : $reviewers[($index + $revision) % count($reviewers)];
                $submittedAt = $start->copy()->addSeconds((int) floor($span * (($index * max(1, $revisions) + $revision) / max(1, count($contentIds) * max(1, $perContent + 1)))));
                if ($submittedAt->greaterThan($end)) $submittedAt = $end->copy();
                $reviewedAt = $reviewerId === null ? null : $submittedAt->copy()->addMinutes(20 + (($index + $revision) % 500));
                if ($reviewedAt?->greaterThan($end)) $reviewedAt = $end->copy();

                $batch[] = [
                    'content_type' => 'entertainment', 'content_id' => (string) $contentId,
                    'revision' => $revision, 'status' => $status,
                    'submitted_by' => $submitters[$index % count($submitters)], 'reviewed_by' => $reviewerId,
                    'snapshot' => $snapshotJson, 'snapshot_hash' => $hash,
                    'review_note' => $status === 'rejected' ? 'Please clarify the metadata and resubmit for review.' : null,
                    'submitted_at' => $submittedAt->toDateTimeString(), 'reviewed_at' => $reviewedAt?->toDateTimeString(),
                    'created_at' => $now, 'updated_at' => $now,
                ];
                $totals[$status]++;
                if (count($batch) >= $context->profile->batchSize) {
                    $this->persistBatch($batch, $context);
                    $batch = [];
                }
            }
        }
        if ($batch !== []) $this->persistBatch($batch, $context);

        $statusCounts = DB::table('staging_dataset_records as owners')
            ->join('admin_content_review_submissions as submissions', 'submissions.id', '=', 'owners.record_id')
            ->where('owners.dataset_id', $context->datasetId)->where('owners.entity_type', 'content_review_submission')
            ->groupBy('submissions.status')->select('submissions.status', DB::raw('count(*) as records'))
            ->pluck('records', 'status');
        foreach (['in_review', 'approved', 'rejected'] as $status) $totals[$status] = (int) ($statusCounts[$status] ?? 0);
        $totals['content_review_submissions'] = array_sum(array_intersect_key($totals, array_flip(['in_review', 'approved', 'rejected'])));
        if ($totals['content_review_submissions'] !== $target) {
            throw new RuntimeException("Staging content review generation produced {$totals['content_review_submissions']} of {$target} configured submissions.");
        }
        return $totals;
    }

    /** @param array<string,mixed> $row */
    private function persistBatch(array $rows, StagingGenerationContext $context): void
    {
        DB::transaction(function () use ($rows, $context): void {
            DB::table('admin_content_review_submissions')->insert($rows);
            $contentIds = array_values(array_unique(array_column($rows, 'content_id')));
            $firstRevision = min(array_column($rows, 'revision'));
            $lastRevision = max(array_column($rows, 'revision'));
            $inserted = DB::table('admin_content_review_submissions')->where('content_type', 'entertainment')
                ->whereIn('content_id', $contentIds)->whereBetween('revision', [$firstRevision, $lastRevision])
                ->get(['id', 'content_id', 'revision']);
            $rowKeys = [];
            foreach ($rows as $row) $rowKeys[$row['content_id'].':'.$row['revision']] = true;
            $ownershipRows = [];
            foreach ($inserted as $record) {
                $key = $record->content_id.':'.$record->revision;
                if (! isset($rowKeys[$key])) continue;
                $ownershipRows[] = [
                    'dataset_id' => $context->datasetId, 'entity_type' => 'content_review_submission',
                    'entity_key' => $key, 'record_id' => $record->id,
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
            if (count($ownershipRows) !== count($rows)) throw new RuntimeException('Could not map all generated content review records to their ownership keys.');
            DB::table('staging_dataset_records')->insert($ownershipRows);
        });
    }

    /** @return array<string,mixed> */
    private function snapshot(Entertainment $item): array
    {
        $attributes = $item->getAttributes();
        $snapshot = [];
        foreach (self::SNAPSHOT_FIELDS as $field) $snapshot[$field] = $attributes[$field] ?? null;
        foreach (['poster_url', 'thumbnail_url', 'video_url_input'] as $field) {
            $value = $attributes[$field] ?? null;
            $snapshot[$field.'_sha256'] = $value ? hash('sha256', (string) $value) : null;
        }
        ksort($snapshot);
        return $snapshot;
    }
}
