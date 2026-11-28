<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AdminContentReviewSubmission;
use App\Support\Rbac\AdminAuditLogger;
use App\Support\Rbac\ExplicitPermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Modules\Subscriptions\Models\Plan;
use Modules\Video\Models\Video;

class AdminVideoCatalogController extends Controller
{
    public function uploadArtwork(Request $request): JsonResponse
    {
        abort_unless(
            ExplicitPermission::allows($request->user(), 'content.create')
                || ExplicitPermission::allows($request->user(), 'content.update'),
            403,
        );
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=5000,max_height=5000'],
        ]);

        $path = $request->file('image')->storePublicly('video-artwork', 'public');
        abort_if(! $path, 500, 'The artwork image could not be stored.');

        return response()->json(['data' => ['url' => Storage::disk('public')->url($path)]], 201);
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'content.view'), 403);
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:120'],
            'status' => ['sometimes', 'string', Rule::in(['draft', 'published'])],
            'access' => ['sometimes', 'string', Rule::in(['free', 'paid', 'pay-per-view'])],
            'media_source' => ['sometimes', 'string', Rule::in(['linked', 'missing'])],
            'artwork' => ['sometimes', 'string', Rule::in(['complete', 'missing'])],
            'review_status' => ['sometimes', 'string', Rule::in(['not_submitted', 'in_review', 'approved', 'rejected'])],
            'sort_by' => ['sometimes', 'string', Rule::in(['recently_updated', 'oldest_updated', 'title_asc', 'title_desc', 'release_newest', 'release_oldest'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Video::query()->where('type', 'video')->with('plan:id,name,level');
        if (! empty($filters['search'])) {
            $term = '%'.addcslashes($filters['search'], '%_\\').'%';
            $query->where(fn (Builder $builder) => $builder
                ->where('name', 'like', $term)
                ->orWhere('name_bn', 'like', $term)
                ->orWhere('slug', 'like', $term));
        }
        if (isset($filters['status'])) $query->where('status', $filters['status'] === 'published');
        if (isset($filters['access'])) $query->where('access', $filters['access']);
        if (isset($filters['media_source'])) {
            if ($filters['media_source'] === 'linked') $query->whereNotNull('media_asset_id');
            else $query->whereNull('media_asset_id');
        }
        if (($filters['artwork'] ?? null) === 'missing') {
            $query->whereNull('poster_url')->whereNull('poster_tv_url')->whereNull('thumbnail_url');
        } elseif (($filters['artwork'] ?? null) === 'complete') {
            $query->where(fn (Builder $builder) => $builder->whereNotNull('poster_url')->orWhereNotNull('poster_tv_url')->orWhereNotNull('thumbnail_url'));
        }

        if (($filters['review_status'] ?? null) === 'not_submitted') {
            $query->whereNotExists(fn ($review) => $review->selectRaw('1')
                ->from('admin_content_review_submissions as latest_review')
                ->where('latest_review.content_type', 'video')
                ->whereColumn('latest_review.content_id', 'videos.id'));
        } elseif (isset($filters['review_status'])) {
            $reviewStatus = $filters['review_status'];
            $query->whereExists(fn ($review) => $review->selectRaw('1')
                ->from('admin_content_review_submissions as latest_review')
                ->where('latest_review.content_type', 'video')
                ->whereColumn('latest_review.content_id', 'videos.id')
                ->where('latest_review.status', $reviewStatus)
                ->whereRaw('latest_review.revision = (SELECT MAX(newest_review.revision) FROM admin_content_review_submissions AS newest_review WHERE newest_review.content_type = latest_review.content_type AND newest_review.content_id = latest_review.content_id)'));
        }

        match ($filters['sort_by'] ?? 'recently_updated') {
            'oldest_updated' => $query->orderBy('updated_at'),
            'title_asc' => $query->orderBy('name')->orderByDesc('updated_at'),
            'title_desc' => $query->orderByDesc('name')->orderByDesc('updated_at'),
            'release_newest' => $query->orderByDesc('release_date')->orderByDesc('updated_at'),
            'release_oldest' => $query->orderBy('release_date')->orderByDesc('updated_at'),
            default => $query->orderByDesc('updated_at'),
        };
        $query->orderBy('videos.id');

        $videos = $query->paginate($filters['per_page'] ?? 25)->withQueryString();
        $videoIds = $videos->getCollection()->map(fn (Video $video): string => (string) $video->getKey());
        $reviewStatuses = AdminContentReviewSubmission::query()
            ->where('content_type', 'video')->whereIn('content_id', $videoIds)
            ->orderByDesc('revision')->get(['content_id', 'status', 'revision'])
            ->unique('content_id')->keyBy('content_id');

        return response()->json([
            'data' => $videos->getCollection()->map(function (Video $video) use ($reviewStatuses): array {
                return $this->payload($video) + [
                    'review_status' => $reviewStatuses->get((string) $video->getKey())?->status,
                ];
            })->values(),
            'plans' => Plan::query()->where('status', 1)->orderBy('level')->get(['id', 'name', 'level']),
            'meta' => [
                'current_page' => $videos->currentPage(), 'last_page' => $videos->lastPage(),
                'per_page' => $videos->perPage(), 'total' => $videos->total(),
            ],
        ]);
    }

    public function store(Request $request, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'content.create'), 403);
        $data = $this->validatedData($request);
        $this->validateAccessRequirements($data);
        $video = DB::transaction(function () use ($request, $data, $audit): Video {
            $video = new Video();
            $video->forceFill($data + [
                'type' => 'video', 'status' => false,
                'created_by' => $request->user()->getKey(), 'updated_by' => $request->user()->getKey(),
            ]);
            $video->save();
            $audit->record($request->user(), 'video.draft.created', Video::class, $video->getKey(), null, $this->auditSummary($video));

            return $video;
        });

        return response()->json(['data' => $this->payload($video->load('plan:id,name,level'))], 201);
    }

    public function show(Request $request, Video $video): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'content.view'), 403);
        abort_unless($video->type === 'video', 404);

        return response()->json(['data' => $this->payload($video->load('plan:id,name,level'))]);
    }

    public function update(Request $request, Video $video, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'content.update'), 403);
        abort_unless($video->type === 'video', 404);
        abort_if((bool) $video->status, 409, 'Published videos must be changed through a reviewed content revision.');
        $data = $this->validatedData($request, true);
        $this->validateAccessRequirements($data, $video);

        DB::transaction(function () use ($request, $video, $data, $audit): void {
            $locked = Video::query()->whereKey($video->getKey())->lockForUpdate()->firstOrFail();
            abort_if((bool) $locked->status, 409, 'This video was published while you were editing it.');
            $reviewStatus = AdminContentReviewSubmission::query()
                ->where('content_type', 'video')->where('content_id', (string) $locked->getKey())
                ->latest('revision')->value('status');
            abort_if(in_array($reviewStatus, ['in_review', 'approved'], true), 409, 'This video is locked while it is awaiting review or publication.');
            $before = $this->auditSummary($locked);
            $locked->forceFill($data + ['updated_by' => $request->user()->getKey()])->save();
            $audit->record($request->user(), 'video.draft.updated', Video::class, $locked->getKey(), $before, $this->auditSummary($locked));
        });

        return response()->json(['data' => $this->payload($video->fresh()->load('plan:id,name,level'))]);
    }

    public function destroy(Request $request, Video $video, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'content.delete'), 403);
        abort_unless($video->type === 'video', 404);

        DB::transaction(function () use ($request, $video, $audit): void {
            $locked = Video::query()->whereKey($video->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($locked->type === 'video', 404);
            abort_if((bool) $locked->status, 409, 'Published videos cannot be deleted from the draft catalog.');

            $reviewStatus = AdminContentReviewSubmission::query()
                ->where('content_type', 'video')->where('content_id', (string) $locked->getKey())
                ->latest('revision')->value('status');
            abort_if(in_array($reviewStatus, ['in_review', 'approved'], true), 409, 'Videos awaiting review or publication cannot be deleted.');

            $before = $this->auditSummary($locked);
            $locked->delete();
            $audit->record($request->user(), 'video.draft.deleted', Video::class, $locked->getKey(), $before, ['deleted' => true]);
        });

        return response()->json(['data' => ['deleted' => true, 'retained_media_asset_id' => $video->media_asset_id]]);
    }

    /** @return array<string, mixed> */
    private function validatedData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'name_bn' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:30000'],
            'description_bn' => ['sometimes', 'nullable', 'string', 'max:30000'],
            'short_desc' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'poster_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'poster_tv_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'thumbnail_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'duration' => ['sometimes', 'nullable', 'string', 'max:32'],
            'release_date' => ['sometimes', 'nullable', 'date'],
            'access' => [$partial ? 'sometimes' : 'required', 'string', Rule::in(['free', 'paid', 'pay-per-view'])],
            'plan_id' => ['sometimes', 'nullable', 'integer', Rule::exists('plan', 'id')->where('status', 1)],
            'IMDb_rating' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:10'],
            'content_rating' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_restricted' => ['sometimes', 'boolean'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'purchase_type' => ['sometimes', 'nullable', 'string', Rule::in(['rental', 'onetime'])],
            'access_duration' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3650'],
            'discount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99'],
            'available_for' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3650'],
        ]);
    }

    /** @param array<string,mixed> $data */
    private function validateAccessRequirements(array $data, ?Video $video = null): void
    {
        $access = $data['access'] ?? $video?->access ?? 'free';
        if ($access === 'paid') {
            abort_unless(! empty($data['plan_id'] ?? $video?->plan_id), 422, 'Choose an active subscription plan for paid access.');
        }
        if ($access === 'pay-per-view') {
            $purchaseType = $data['purchase_type'] ?? $video?->purchase_type;
            abort_unless(is_numeric($data['price'] ?? $video?->price)
                && in_array($purchaseType, ['rental', 'onetime'], true)
                && (int) ($data['available_for'] ?? $video?->available_for) > 0
                && ($purchaseType !== 'rental' || (int) ($data['access_duration'] ?? $video?->access_duration) > 0),
                422,
                'Pay-per-view videos need a price, purchase type, availability window, and rental duration when applicable.');
        }
    }

    /** @return array<string, mixed> */
    private function payload(Video $video): array
    {
        return [
            'id' => (string) $video->getKey(), 'name' => $video->getRawOriginal('name'), 'name_bn' => $video->getRawOriginal('name_bn'),
            'slug' => $video->getAttribute('slug'),
            'description' => $video->getRawOriginal('description'), 'description_bn' => $video->getRawOriginal('description_bn'),
            'short_desc' => $video->short_desc, 'poster_url' => $video->poster_url, 'poster_tv_url' => $video->poster_tv_url,
            'thumbnail_url' => $video->thumbnail_url, 'duration' => $video->duration, 'release_date' => $video->release_date?->toDateString(),
            'access' => $video->access ?: 'free', 'plan_id' => $video->plan_id, 'plan' => $video->plan?->only(['id', 'name', 'level']),
            'IMDb_rating' => $video->IMDb_rating, 'content_rating' => $video->content_rating,
            'is_restricted' => (bool) $video->is_restricted, 'price' => $video->price, 'purchase_type' => $video->purchase_type,
            'access_duration' => $video->access_duration, 'discount' => $video->discount, 'available_for' => $video->available_for,
            'status' => (bool) $video->status ? 'published' : 'draft', 'media_asset_id' => $video->media_asset_id,
            'updated_at' => $video->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    private function auditSummary(Video $video): array
    {
        return $video->only(['name', 'access', 'plan_id', 'release_date', 'status', 'media_asset_id']);
    }
}
