<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AdminContentReviewSubmission;
use App\Models\MediaAsset;
use App\Services\Media\MediaReadinessService;
use App\Support\Rbac\ExplicitPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Entertainment\Models\Entertainment;

class AdminContentWorkflowController extends Controller
{
    private const SNAPSHOT_FIELDS = [
        'name', 'name_bn', 'description', 'description_bn', 'type', 'content_rating',
        'release_date', 'plan_id', 'movie_access', 'video_upload_type', 'status', 'media_asset_id',
    ];

    public function queue(Request $request): JsonResponse
    {
        $filters = $request->validate(['status' => ['sometimes', 'in:in_review,approved']]);
        $status = $filters['status'] ?? 'in_review';
        abort_unless(ExplicitPermission::allows($request->user(), $status === 'approved' ? 'content.publish' : 'content.review'), 403);

        $query = AdminContentReviewSubmission::query()
            ->where('content_type', 'entertainment')
            ->where('status', $filters['status'] ?? 'in_review')
            ->with(['submitter:id,first_name,last_name,email'])
            ->latest('submitted_at');

        $submissions = $query->paginate(min(max($request->integer('per_page', 25), 1), 100));
        $contentIds = collect($submissions->items())->pluck('content_id')->unique();
        $content = Entertainment::query()->whereIn('id', $contentIds)->get()->keyBy(fn ($item) => (string) $item->getKey());

        return response()->json([
            'data' => collect($submissions->items())->map(function (AdminContentReviewSubmission $submission) use ($content): array {
                $item = $content->get((string) $submission->content_id);
                return [
                    'id' => $submission->id,
                    'content_id' => $submission->content_id,
                    'revision' => $submission->revision,
                    'status' => $submission->status,
                    'submitted_by' => $submission->submitter ? [
                        'id' => $submission->submitter->id,
                        'name' => trim(($submission->submitter->first_name ?? '').' '.($submission->submitter->last_name ?? '')) ?: $submission->submitter->email,
                    ] : null,
                    'submitted_at' => $submission->submitted_at?->toIso8601String(),
                    'content' => $item ? $this->contentPayload($item) : null,
                ];
            })->values(),
            'meta' => ['current_page' => $submissions->currentPage(), 'last_page' => $submissions->lastPage(), 'per_page' => $submissions->perPage(), 'total' => $submissions->total()],
        ]);
    }

    public function submit(Request $request, string $content): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'content.submit'), 403);
        $item = Entertainment::query()->findOrFail($content);

        $submission = DB::transaction(function () use ($item, $request): AdminContentReviewSubmission {
            $latest = AdminContentReviewSubmission::query()
                ->where('content_type', 'entertainment')->where('content_id', (string) $item->getKey())
                ->lockForUpdate()->latest('revision')->first();
            abort_if($latest?->status === 'in_review', 409, 'This content already has a pending review.');

            $snapshot = $this->snapshot($item);
            $submission = AdminContentReviewSubmission::query()->create([
                'content_type' => 'entertainment',
                'content_id' => (string) $item->getKey(),
                'revision' => ($latest?->revision ?? 0) + 1,
                'status' => 'in_review',
                'submitted_by' => $request->user()->getKey(),
                'snapshot' => $snapshot,
                'snapshot_hash' => hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                'submitted_at' => now('UTC'),
            ]);
            $this->audit($request, 'content.submitted', $submission, null, ['revision' => $submission->revision, 'status' => $submission->status]);
            return $submission;
        });

        return response()->json(['data' => $this->submissionPayload($submission, $item)], 201);
    }

    public function approve(Request $request, int $submission): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'content.approve'), 403);
        $data = $request->validate(['note' => ['sometimes', 'nullable', 'string', 'max:2000']]);

        [$record, $item] = DB::transaction(function () use ($request, $submission, $data): array {
            $record = AdminContentReviewSubmission::query()->lockForUpdate()->findOrFail($submission);
            abort_if($record->status !== 'in_review', 409, 'Only pending submissions can be approved.');
            abort_if((string) $record->submitted_by === (string) $request->user()->getKey(), 403, 'Reviewers cannot approve their own submission.');
            $item = Entertainment::query()->lockForUpdate()->findOrFail($record->content_id);
            abort_unless(hash_equals($record->snapshot_hash, hash('sha256', json_encode($this->snapshot($item), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))), 409, 'Content changed after submission; submit a new revision for review.');

            $record->forceFill([
                'status' => 'approved', 'reviewed_by' => $request->user()->getKey(),
                'review_note' => $data['note'] ?? null, 'reviewed_at' => now('UTC'),
            ])->save();
            $this->audit($request, 'content.approved', $record, ['status' => 'in_review'], ['status' => 'approved', 'revision' => $record->revision]);
            return [$record, $item];
        });

        return response()->json(['data' => $this->submissionPayload($record, $item)]);
    }

    public function reject(Request $request, int $submission): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'content.reject'), 403);
        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:2000']]);

        [$record, $item] = DB::transaction(function () use ($request, $submission, $data): array {
            $record = AdminContentReviewSubmission::query()->lockForUpdate()->findOrFail($submission);
            abort_if($record->status !== 'in_review', 409, 'Only pending submissions can be rejected.');
            abort_if((string) $record->submitted_by === (string) $request->user()->getKey(), 403, 'Reviewers cannot reject their own submission.');
            $item = Entertainment::query()->findOrFail($record->content_id);
            $record->forceFill([
                'status' => 'rejected', 'reviewed_by' => $request->user()->getKey(),
                'review_note' => $data['note'], 'reviewed_at' => now('UTC'),
            ])->save();
            $this->audit($request, 'content.rejected', $record, ['status' => 'in_review'], ['status' => 'rejected', 'revision' => $record->revision]);
            return [$record, $item];
        });

        return response()->json(['data' => $this->submissionPayload($record, $item)]);
    }

    public function publish(Request $request, int $submission, MediaReadinessService $mediaReadiness): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'content.publish'), 403);

        [$record, $item] = DB::transaction(function () use ($request, $submission, $mediaReadiness): array {
            $record = AdminContentReviewSubmission::query()->lockForUpdate()->findOrFail($submission);
            abort_if($record->status !== 'approved', 409, 'Only approved submissions can be published.');
            $item = Entertainment::query()->lockForUpdate()->findOrFail($record->content_id);
            abort_unless(hash_equals($record->snapshot_hash, hash('sha256', json_encode($this->snapshot($item), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))), 409, 'Content changed after approval; submit a new revision for review.');
            abort_unless($item->name && $item->type && ($item->poster_url || $item->thumbnail_url), 422, 'Content requires a title, type, and artwork before publication.');
            if ($item->media_asset_id) {
                $asset = MediaAsset::query()->find($item->media_asset_id);
                abort_unless($asset && $mediaReadiness->inspect($asset)['ready'], 409, 'The attached media asset is not fully processed and ready for publication.');
            }
            $item->forceFill(['status' => true])->save();
            $record->forceFill(['status' => 'published'])->save();
            $this->audit($request, 'content.published', $record, ['status' => 'approved'], ['status' => 'published', 'revision' => $record->revision]);
            return [$record, $item];
        });

        return response()->json(['data' => $this->submissionPayload($record, $item)]);
    }

    private function snapshot(Entertainment $item): array
    {
        $attributes = $item->getAttributes();
        $snapshot = [];
        foreach (self::SNAPSHOT_FIELDS as $field) {
            $snapshot[$field] = $attributes[$field] ?? null;
        }
        foreach (['poster_url', 'thumbnail_url', 'video_url_input'] as $field) {
            $value = $attributes[$field] ?? null;
            $snapshot[$field.'_sha256'] = $value ? hash('sha256', (string) $value) : null;
        }
        ksort($snapshot);
        return $snapshot;
    }

    private function contentPayload(Entertainment $item): array
    {
        return [
            'id' => (string) $item->getKey(), 'name' => $item->getAttribute('name'), 'type' => $item->type,
            'content_rating' => $item->content_rating, 'poster_url' => $item->poster_url ?: $item->thumbnail_url,
            'release_date' => $item->release_date?->toDateString(), 'is_published' => (bool) $item->status,
        ];
    }

    private function submissionPayload(AdminContentReviewSubmission $submission, Entertainment $item): array
    {
        return [
            'id' => $submission->id, 'content_id' => $submission->content_id, 'revision' => $submission->revision,
            'status' => $submission->status, 'note' => $submission->review_note,
            'submitted_at' => $submission->submitted_at?->toIso8601String(),
            'reviewed_at' => $submission->reviewed_at?->toIso8601String(), 'content' => $this->contentPayload($item),
        ];
    }

    private function audit(Request $request, string $action, AdminContentReviewSubmission $record, ?array $before, ?array $after): void
    {
        AdminAuditLog::query()->create([
            'actor_id' => $request->user()->getKey(), 'action' => $action,
            'resource_type' => 'content_review_submission', 'resource_id' => (string) $record->id,
            'request_id' => substr((string) $request->header('X-Request-ID', ''), 0, 64) ?: null, 'before_values' => $before,
            'after_values' => $after, 'outcome' => 'success', 'created_at' => now('UTC'),
        ]);
    }
}
