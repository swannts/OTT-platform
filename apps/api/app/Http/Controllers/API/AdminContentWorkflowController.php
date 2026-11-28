<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AdminContentReviewSubmission;
use App\Services\Content\AdminContentPublicationService;
use App\Support\Content\AdminContentSnapshot;
use App\Support\Rbac\ExplicitPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Entertainment\Models\Entertainment;
use Modules\Video\Models\Video;

class AdminContentWorkflowController extends Controller
{
    public function queue(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'in:in_review,approved'],
            'content_type' => ['sometimes', 'in:entertainment,video'],
        ]);
        $status = $filters['status'] ?? 'in_review';
        $contentType = $filters['content_type'] ?? 'entertainment';
        abort_unless(ExplicitPermission::allows($request->user(), $status === 'approved' ? 'content.publish' : 'content.review'), 403);

        $query = AdminContentReviewSubmission::query()
            ->where('content_type', $contentType)
            ->where('status', $filters['status'] ?? 'in_review')
            ->with(['submitter:id,first_name,last_name,email'])
            ->latest('submitted_at');

        $submissions = $query->paginate(min(max($request->integer('per_page', 25), 1), 100));
        $contentIds = collect($submissions->items())->pluck('content_id')->unique();
        $model = $this->contentModel($contentType);
        $content = $model::query()->whereIn('id', $contentIds)->get()->keyBy(fn ($item) => (string) $item->getKey());

        return response()->json([
            'data' => collect($submissions->items())->map(function (AdminContentReviewSubmission $submission) use ($content, $contentType): array {
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
                    'content' => $item ? $this->contentPayload($item, $contentType) : null,
                ];
            })->values(),
            'meta' => ['current_page' => $submissions->currentPage(), 'last_page' => $submissions->lastPage(), 'per_page' => $submissions->perPage(), 'total' => $submissions->total()],
        ]);
    }

    public function submit(Request $request, string $content): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'content.submit'), 403);
        $data = $request->validate(['content_type' => ['sometimes', 'in:entertainment,video']]);
        $contentType = $data['content_type'] ?? 'entertainment';
        $model = $this->contentModel($contentType);
        $item = $model::query()->findOrFail($content);
        abort_unless($contentType !== 'video' || $item->getAttribute('type') === 'video', 404);
        abort_if($contentType === 'video' && (bool) $item->getAttribute('status'), 409, 'Published standalone videos cannot be submitted through the draft workflow.');

        $submission = DB::transaction(function () use ($item, $request, $contentType): AdminContentReviewSubmission {
            $latest = AdminContentReviewSubmission::query()
                ->where('content_type', $contentType)->where('content_id', (string) $item->getKey())
                ->lockForUpdate()->latest('revision')->first();
            abort_if($latest?->status === 'in_review', 409, 'This content already has a pending review.');

            $snapshot = $this->snapshot($item, $contentType);
            $submission = AdminContentReviewSubmission::query()->create([
                'content_type' => $contentType,
                'content_id' => (string) $item->getKey(),
                'revision' => ($latest?->revision ?? 0) + 1,
                'status' => 'in_review',
                'submitted_by' => $request->user()->getKey(),
                'snapshot' => $snapshot,
                'snapshot_hash' => hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
                'submitted_at' => now('UTC'),
            ]);
            $this->audit($request, 'content.submitted', $submission, null, ['revision' => $submission->revision, 'status' => $submission->status]);
            return $submission;
        });

        return response()->json(['data' => $this->submissionPayload($submission, $item, $contentType)], 201);
    }

    public function approve(Request $request, int $submission): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'content.approve'), 403);
        $data = $request->validate(['note' => ['sometimes', 'nullable', 'string', 'max:2000']]);

        [$record, $item] = DB::transaction(function () use ($request, $submission, $data): array {
            $record = AdminContentReviewSubmission::query()->lockForUpdate()->findOrFail($submission);
            abort_if($record->status !== 'in_review', 409, 'Only pending submissions can be approved.');
            abort_if((string) $record->submitted_by === (string) $request->user()->getKey(), 403, 'Reviewers cannot approve their own submission.');
            $model = $this->contentModel($record->content_type);
            $item = $model::query()->lockForUpdate()->findOrFail($record->content_id);
            $snapshot = json_encode($this->snapshot($item, $record->content_type), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            abort_unless(hash_equals($record->snapshot_hash, hash('sha256', $snapshot)), 409, 'Content changed after submission; submit a new revision for review.');

            $record->forceFill([
                'status' => 'approved', 'reviewed_by' => $request->user()->getKey(),
                'review_note' => $data['note'] ?? null, 'reviewed_at' => now('UTC'),
            ])->save();
            $this->audit($request, 'content.approved', $record, ['status' => 'in_review'], ['status' => 'approved', 'revision' => $record->revision]);
            return [$record, $item];
        });

        return response()->json(['data' => $this->submissionPayload($record, $item, $record->content_type)]);
    }

    public function reject(Request $request, int $submission): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'content.reject'), 403);
        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:2000']]);

        [$record, $item] = DB::transaction(function () use ($request, $submission, $data): array {
            $record = AdminContentReviewSubmission::query()->lockForUpdate()->findOrFail($submission);
            abort_if($record->status !== 'in_review', 409, 'Only pending submissions can be rejected.');
            abort_if((string) $record->submitted_by === (string) $request->user()->getKey(), 403, 'Reviewers cannot reject their own submission.');
            $model = $this->contentModel($record->content_type);
            $item = $model::query()->findOrFail($record->content_id);
            $record->forceFill([
                'status' => 'rejected', 'reviewed_by' => $request->user()->getKey(),
                'review_note' => $data['note'], 'reviewed_at' => now('UTC'),
            ])->save();
            $this->audit($request, 'content.rejected', $record, ['status' => 'in_review'], ['status' => 'rejected', 'revision' => $record->revision]);
            return [$record, $item];
        });

        return response()->json(['data' => $this->submissionPayload($record, $item, $record->content_type)]);
    }

    public function publish(Request $request, int $submission, AdminContentPublicationService $publication): JsonResponse
    {
        [$record, $item] = $publication->publish(
            $request->user(),
            $submission,
            substr((string) $request->header('X-Request-ID', ''), 0, 64) ?: null,
        );

        return response()->json(['data' => $this->submissionPayload($record, $item, $record->content_type)]);
    }

    private function contentPayload(Model $item, string $contentType): array
    {
        $name = $item->getAttribute('name');
        $poster = $item->getAttribute('poster_url') ?: $item->getAttribute('thumbnail_url') ?: $item->getAttribute('poster_tv_url');

        return [
            'id' => (string) $item->getKey(), 'name' => $name, 'type' => $contentType === 'video' ? 'Standalone video' : $item->getAttribute('type'),
            'content_rating' => $item->getAttribute('content_rating'), 'poster_url' => $poster,
            'release_date' => $item->release_date?->toDateString(), 'is_published' => (bool) $item->status,
        ];
    }

    private function submissionPayload(AdminContentReviewSubmission $submission, Model $item, string $contentType): array
    {
        return [
            'id' => $submission->id, 'content_id' => $submission->content_id, 'revision' => $submission->revision,
            'status' => $submission->status, 'note' => $submission->review_note,
            'submitted_at' => $submission->submitted_at?->toIso8601String(),
            'reviewed_at' => $submission->reviewed_at?->toIso8601String(), 'content' => $this->contentPayload($item, $contentType),
        ];
    }

    /** @return class-string<Model> */
    private function contentModel(string $contentType): string
    {
        return match ($contentType) {
            'video' => Video::class,
            'entertainment' => Entertainment::class,
            default => abort(404),
        };
    }

    /** @return array<string,mixed> */
    private function snapshot(Model $item, string $contentType): array
    {
        return $contentType === 'video'
            ? AdminContentSnapshot::forVideo($item)
            : AdminContentSnapshot::forEntertainment($item);
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
