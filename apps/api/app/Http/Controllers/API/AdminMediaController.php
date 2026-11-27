<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\MediaAssetTrack;
use App\Models\MediaProcessingJob;
use App\Models\MediaUploadSession;
use App\Services\Upload\FileUploadValidationService;
use App\Services\Media\MediaReadinessService;
use App\Support\Rbac\AdminAuditLogger;
use App\Support\Rbac\ExplicitPermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Entertainment\Models\Entertainment;
use Modules\Episode\Models\Episode;
use Modules\Video\Models\Video;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminMediaController extends Controller
{
    private const MAX_SIZE = 10 * 1024 * 1024 * 1024;
    private const CHUNK_SIZE = 8 * 1024 * 1024;

    public function index(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.view'), 403);
        $filters = $request->validate([
            'status' => ['sometimes', 'string', 'in:uploaded,queued,processing,ready,failed'],
            'category' => ['sometimes', 'string', 'in:movie,episode,trailer,teaser,standalone_video,promotional_video,audio,subtitle,poster,backdrop,thumbnail,artwork,other,video'],
            'quality_status' => ['sometimes', 'string', 'in:pending_inspection,technical_failed,awaiting_manual_review,approved,rejected,reprocessing_required'],
            'search' => ['sometimes', 'string', 'max:120'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'sort' => ['sometimes', 'string', 'in:created_at,original_name,size_bytes,status,duration'],
            'sort_dir' => ['sometimes', 'string', 'in:asc,desc'],
            'language' => ['sometimes', 'string', 'max:12'],
            'content_type' => ['sometimes', 'string', 'in:entertainment,episode,video'],
            'content_id' => ['sometimes', 'string', 'max:64'],
            'uploaded_from' => ['sometimes', 'date'],
            'uploaded_to' => ['sometimes', 'date', 'after_or_equal:uploaded_from'],
            'attached' => ['sometimes', 'boolean'],
            'has_tracks' => ['sometimes', 'boolean'],
            'track_kind' => ['sometimes', 'string', 'in:audio,subtitle'],
        ]);

        $query = MediaAsset::query()->with([
            'assetTracks', 'latestProcessingJob', 'entertainments:id,name,name_bn,status,media_asset_id',
            'episodes:id,name,status,media_asset_id', 'videos:id,name,status,media_asset_id',
        ])
            ->withCount('processingJobs');
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['category'])) $query->where('category', $filters['category']);
        if (isset($filters['quality_status'])) $query->where('quality_status', $filters['quality_status']);
        if (!empty($filters['search'])) {
            $term = '%'.addcslashes($filters['search'], '%_\\').'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('original_name', 'like', $term)
                    ->orWhere('display_name', 'like', $term)
                    ->orWhereHas('entertainments', fn (Builder $content) => $content->where('name', 'like', $term)->orWhere('name_bn', 'like', $term))
                    ->orWhereHas('episodes', fn (Builder $content) => $content->where('name', 'like', $term))
                    ->orWhereHas('videos', fn (Builder $content) => $content->where('name', 'like', $term));
            });
        }
        if (isset($filters['language'])) $query->whereHas('assetTracks', fn (Builder $tracks) => $tracks->where('language', $filters['language']));
        if (array_key_exists('has_tracks', $filters)) {
            $filter_var = filter_var($filters['has_tracks'], FILTER_VALIDATE_BOOLEAN);
            if ($filter_var) $query->whereHas('assetTracks');
            else $query->whereDoesntHave('assetTracks');
        }
        if (isset($filters['track_kind'])) $query->whereHas('assetTracks', fn (Builder $tracks) => $tracks->where('kind', $filters['track_kind']));
        if (isset($filters['content_type'], $filters['content_id'])) {
            $relation = match ($filters['content_type']) {
                'episode' => 'episodes', 'video' => 'videos', default => 'entertainments',
            };
            $query->whereHas($relation, fn (Builder $content) => $content->whereKey($filters['content_id']));
        }
        if (isset($filters['attached'])) {
            if ($filters['attached']) {
                $query->where(fn (Builder $builder) => $builder->whereHas('entertainments')->orWhereHas('episodes')->orWhereHas('videos'));
            } else {
                $query->whereDoesntHave('entertainments')->whereDoesntHave('episodes')->whereDoesntHave('videos');
            }
        }
        if (isset($filters['uploaded_from'])) $query->whereDate('created_at', '>=', $filters['uploaded_from']);
        if (isset($filters['uploaded_to'])) $query->whereDate('created_at', '<=', $filters['uploaded_to']);

        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['sort_dir'] ?? 'desc';
        if ($sort === 'duration') {
            $query->orderByRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.Format.duration')) AS DECIMAL(14,3)) {$direction}");
        } else {
            $query->orderBy($sort, $direction);
        }
        $assets = $query->paginate($filters['per_page'] ?? 25)->withQueryString();
        $canPreview = ExplicitPermission::allows($request->user(), 'media.preview');
        return response()->json([
            'data' => $assets->getCollection()->map(fn (MediaAsset $asset): array => $this->assetPayload($asset, $canPreview)),
            'meta' => [
                'current_page' => $assets->currentPage(), 'last_page' => $assets->lastPage(),
                'per_page' => $assets->perPage(), 'total' => $assets->total(),
                'from' => $assets->firstItem(), 'to' => $assets->lastItem(),
                'links' => $assets->linkCollection()->values(),
            ],
        ]);
    }

    public function show(Request $request, MediaAsset $asset): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.view'), 403);
        return response()->json(['data' => $this->assetPayload($asset->load([
            'processingJobs', 'assetTracks', 'uploader:id,name,email', 'qualityReviewer:id,name,email',
            'entertainments:id,name,name_bn,type,status,media_asset_id', 'replacement:id,version,status,quality_status',
            'episodes:id,name,status,media_asset_id', 'videos:id,name,status,media_asset_id',
            'replacements:id,replaces_asset_id,version,status,quality_status,quality_notes,created_at',
        ]))]);
    }

    public function update(Request $request, MediaAsset $asset, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.update'), 403);
        $data = $request->validate([
            'display_name' => ['sometimes', 'string', 'min:1', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'category' => ['sometimes', 'string', 'in:movie,episode,trailer,teaser,standalone_video,promotional_video,video,audio,subtitle,poster,backdrop,thumbnail,artwork,other'],
        ]);
        abort_if($data === [], 422, 'Provide at least one asset field to update.');
        DB::transaction(function () use ($request, $asset, $data, $audit): void {
            $locked = MediaAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $before = $locked->only(array_keys($data));
            $locked->fill($data)->save();
            $audit->record($request->user(), 'media.asset.updated', MediaAsset::class, $locked->id, $before, $locked->only(array_keys($data)));
        });
        return response()->json(['data' => $this->assetPayload($asset->fresh())]);
    }

    public function quality(Request $request, MediaAsset $asset, MediaReadinessService $readiness): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.view'), 403);
        return response()->json(['data' => [
            'asset_id' => $asset->id, 'status' => $asset->status,
            ...$readiness->inspect($asset),
        ]]);
    }

    public function storage(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.storage.view'), 403);
        $assets = MediaAsset::query();
        $totals = (clone $assets)->selectRaw('category, disk, SUM(size_bytes) AS bytes, COUNT(*) AS asset_count')
            ->groupBy('category', 'disk')->get();
        return response()->json(['data' => [
            'source_bytes_estimated' => (int) (clone $assets)->sum('size_bytes'),
            'by_category' => $totals->groupBy('category')->map(fn ($rows) => [
                'bytes_estimated' => (int) $rows->sum('bytes'), 'assets' => (int) $rows->sum('asset_count'),
            ]),
            'by_provider' => $totals->groupBy('disk')->map(fn ($rows) => [
                'bytes_estimated' => (int) $rows->sum('bytes'), 'assets' => (int) $rows->sum('asset_count'),
            ]),
            'largest_assets' => (clone $assets)->orderByDesc('size_bytes')->limit(10)
                ->get(['id', 'display_name', 'original_name', 'category', 'disk', 'size_bytes', 'status']),
            'unattached_assets' => (clone $assets)->whereDoesntHave('entertainments')->whereDoesntHave('episodes')->whereDoesntHave('videos')->count(),
            'incomplete_uploads' => MediaUploadSession::query()->where('status', 'uploading')->where('expires_at', '>', now())->count(),
            'storage_source' => 'application metadata estimate; provider-reported usage is unavailable',
        ]]);
    }

    public function archived(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.view'), 403);
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:120'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $query = MediaAsset::onlyTrashed()->with(['uploader:id,name,email', 'archiver:id,name,email'])->orderByDesc('archived_at')->orderByDesc('deleted_at');
        if (isset($filters['search'])) {
            $term = '%'.addcslashes($filters['search'], '%_\\').'%';
            $query->where(fn (Builder $builder) => $builder->where('original_name', 'like', $term)->orWhere('display_name', 'like', $term));
        }
        $items = $query->paginate($filters['per_page'] ?? 25)->withQueryString();
        return response()->json([
            'data' => $items->getCollection()->map(fn (MediaAsset $asset) => $this->assetPayload($asset)),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'per_page' => $items->perPage(), 'total' => $items->total()],
        ]);
    }

    public function contentOptions(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'content.view'), 403);
        $data = $request->validate(['search' => ['required', 'string', 'min:2', 'max:120']]);
        $term = '%'.addcslashes($data['search'], '%_\\').'%';
        $results = collect();
        foreach ([
            ['entertainment', Entertainment::query()], ['episode', Episode::query()], ['video', Video::query()],
        ] as [$type, $query]) {
            $results = $results->concat($query->where(function (Builder $builder) use ($term): void {
                $builder->where('name', 'like', $term);
                if (\Illuminate\Support\Facades\Schema::hasColumn($builder->getModel()->getTable(), 'name_bn')) {
                    $builder->orWhere('name_bn', 'like', $term);
                }
            })->orderBy('name')->limit(20)->get(['id', 'name', 'status'])->map(fn ($content) => [
                'id' => (string) $content->id, 'title' => (string) $content->name,
                'type' => $type, 'status' => (bool) $content->status ? 'published' : 'draft',
            ]));
        }
        return response()->json(['data' => $results->values()]);
    }

    public function preview(Request $request, MediaAsset $asset): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.preview'), 403);
        abort_unless($asset->disk === 'private' && in_array($asset->status, ['ready', 'processing', 'failed'], true) && filled($asset->rendition_path), 409, 'No processed preview is available.');
        $disk = Storage::disk($asset->disk);
        $base = trim((string) $asset->rendition_path, '/');
        $files = ['master.m3u8', 'poster.jpg'];
        $urls = [];
        foreach ($files as $file) {
            if ($disk->exists($base.'/'.$file)) {
                $urls[$file === 'poster.jpg' ? 'poster_url' : 'manifest_url'] = $this->previewUrl($asset, $file);
            }
        }
        return response()->json(['data' => $urls, 'expires_in_seconds' => 120]);
    }

    public function previewFile(Request $request, MediaAsset $asset, string $file): BinaryFileResponse|\Illuminate\Http\Response
    {
        $relativeFile = $this->normalizePreviewPath($file);
        abort_unless($asset->disk === 'private' && in_array($asset->status, ['ready', 'processing', 'failed'], true) && filled($asset->rendition_path), 404);
        $disk = Storage::disk($asset->disk);
        $root = realpath($disk->path(trim((string) $asset->rendition_path, '/')));
        $fullPath = realpath($disk->path(trim((string) $asset->rendition_path, '/').'/'.$relativeFile));
        abort_unless($root && $fullPath && str_starts_with($fullPath, $root.DIRECTORY_SEPARATOR) && is_file($fullPath), 404);
        if (strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)) === 'm3u8') {
            $manifest = file_get_contents($fullPath);
            abort_unless(is_string($manifest), 404);
            $base = trim(dirname($relativeFile), '.');
            $manifest = preg_replace_callback('/URI="([^"]+)"/', function (array $match) use ($asset, $base): string {
                return 'URI="'.$this->previewUrl($asset, $this->normalizePreviewPath(($base ? $base.'/' : '').$match[1])).'"';
            }, $manifest);
            $lines = preg_split('/\r\n|\n|\r/', (string) $manifest);
            foreach ($lines as &$line) {
                $trimmed = trim($line);
                if ($trimmed !== '' && !str_starts_with($trimmed, '#')) {
                    $line = $this->previewUrl($asset, $this->normalizePreviewPath(($base ? $base.'/' : '').$trimmed));
                }
            }
            unset($line);
            return response(implode("\n", $lines), 200, ['Content-Type' => 'application/vnd.apple.mpegurl', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        }
        return response()->file($fullPath, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function processingJobs(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.view'), 403);
        $filters = $request->validate([
            'status' => ['sometimes', 'string', 'in:queued,processing,completed,failed,cancelled'],
            'type' => ['sometimes', 'string', 'max:32'], 'asset_id' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $query = MediaProcessingJob::query()->with('asset:id,display_name,original_name,status')->orderByDesc('created_at');
        foreach (['status' => 'status', 'type' => 'type', 'media_asset_id' => 'asset_id'] as $column => $filter) {
            if (isset($filters[$filter])) $query->where($column, $filters[$filter]);
        }
        $jobs = $query->paginate($filters['per_page'] ?? 25)->withQueryString();
        return response()->json([
            'data' => $jobs->getCollection()->map(fn (MediaProcessingJob $job) => [
                'id' => $job->id, 'asset_id' => $job->media_asset_id, 'asset_name' => $job->asset?->display_name ?: $job->asset?->original_name,
                'type' => $job->type, 'status' => $job->status, 'stage' => $job->stage, 'progress' => $job->progress,
                'priority' => $job->priority, 'worker_name' => $job->worker_name, 'attempts' => $job->attempts,
                'max_attempts' => $job->max_attempts, 'failure_code' => $job->failure_code,
                'failure_message' => $job->failure_message, 'created_at' => $job->created_at?->toIso8601String(),
                'started_at' => $job->started_at?->toIso8601String(), 'completed_at' => $job->completed_at?->toIso8601String(),
            ]),
            'meta' => ['current_page' => $jobs->currentPage(), 'last_page' => $jobs->lastPage(), 'per_page' => $jobs->perPage(), 'total' => $jobs->total()],
        ]);
    }

    public function cancelProcessingJob(Request $request, MediaProcessingJob $job, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.processing.manage'), 403);
        DB::transaction(function () use ($request, $job, $audit): void {
            $locked = MediaProcessingJob::query()->whereKey($job->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, ['queued', 'processing'], true), 409, 'Only active processing jobs can be cancelled.');
            $asset = MediaAsset::query()->whereKey($locked->media_asset_id)->lockForUpdate()->firstOrFail();
            $before = ['status' => $locked->status];
            $locked->update(['status' => 'cancelled', 'failure_code' => 'processing_cancelled', 'failure_message' => 'Cancelled by an authorized administrator.', 'completed_at' => now()]);
            if ($asset->status !== 'ready' && ! filled($asset->rendition_path)) {
                $asset->update(['status' => 'failed', 'failure_code' => 'processing_cancelled']);
            }
            $audit->record($request->user(), 'media.processing.cancelled', MediaProcessingJob::class, $locked->id, $before, ['status' => 'cancelled']);
        });
        return response()->json(['data' => ['id' => $job->id, 'status' => 'cancelled']]);
    }

    public function versions(Request $request, MediaAsset $asset): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.view'), 403);
        return response()->json(['data' => $this->versionChain($asset)->map(fn (MediaAsset $version) => [
            'id' => $version->id, 'version' => $version->version, 'status' => $version->status,
            'quality_status' => $version->quality_status, 'is_active' => (bool) $version->is_active_version,
            'archived' => $version->trashed(),
            'original_name' => $version->original_name, 'size_bytes' => $version->size_bytes,
            'created_at' => $version->created_at?->toIso8601String(),
        ])->values()]);
    }

    public function activateVersion(Request $request, MediaAsset $asset, MediaReadinessService $readiness, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.version.activate'), 403);
        return $this->setActiveVersion($request, $asset, $asset, $readiness, $audit, 'media.version.activated');
    }

    public function rollbackVersion(Request $request, MediaAsset $asset, MediaAsset $version, MediaReadinessService $readiness, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.version.rollback'), 403);
        abort_unless($this->versionChain($asset)->contains('id', $version->id), 404);
        return $this->setActiveVersion($request, $asset, $version, $readiness, $audit, 'media.version.rolled_back');
    }

    public function reviewQuality(Request $request, MediaAsset $asset, MediaReadinessService $readiness, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.quality.review'), 403);
        $data = $request->validate(['decision' => ['required', 'string', 'in:approve,reject,reprocess'], 'notes' => ['required', 'string', 'min:3', 'max:2000']]);
        $decision = match ($data['decision']) { 'approve' => 'approved', 'reject' => 'rejected', default => 'reprocessing_required' };
        DB::transaction(function () use ($request, $asset, $data, $decision, $readiness, $audit): void {
            $locked = MediaAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'ready', 409, 'Quality decisions require a ready asset.');
            abort_unless((int) $locked->uploaded_by !== (int) $request->user()->id, 409, 'A different administrator must perform the quality review.');
            if ($data['decision'] === 'approve') abort_unless($readiness->inspect($locked)['ready'], 409, 'Technical validation must pass before manual approval.');
            $before = ['quality_status' => $locked->quality_status];
            $locked->forceFill(['quality_status' => $decision, 'quality_notes' => $data['notes'], 'quality_reviewed_by' => $request->user()->id, 'quality_reviewed_at' => now()])->save();
            $audit->record($request->user(), 'media.quality.reviewed', MediaAsset::class, $locked->id, $before, ['quality_status' => $decision]);
        });
        return response()->json(['data' => ['asset_id' => $asset->id, 'quality_status' => $decision]]);
    }

    public function restore(Request $request, int $assetId, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.archive.restore'), 403);
        $asset = MediaAsset::withTrashed()->findOrFail($assetId);
        abort_unless($asset->trashed(), 409, 'This asset is not archived.');
        $disk = Storage::disk($asset->disk);
        abort_unless($disk->exists($asset->source_path), 409, 'The retained source is missing; this asset cannot be restored.');
        if ($asset->status === 'ready') abort_unless(filled($asset->rendition_path) && $disk->exists(trim($asset->rendition_path, '/').'/master.m3u8'), 409, 'The active rendition is missing; restore is unavailable.');
        DB::transaction(function () use ($request, $asset, $audit): void {
            $locked = MediaAsset::withTrashed()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->trashed(), 409, 'This asset is no longer archived.');
            $locked->forceFill(['archived_at' => null, 'archived_by' => null, 'archive_reason' => null, 'deleted_at' => null])->save();
            $audit->record($request->user(), 'media.asset.restored', MediaAsset::class, $locked->id, ['archived' => true], ['archived' => false]);
        });
        return response()->json(['data' => ['id' => $asset->id, 'restored' => true, 'published' => false]]);
    }

    public function beginUpload(Request $request, FileUploadValidationService $uploads): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.upload'), 403);
        $data = $request->validate([
            'file_name' => ['required', 'string', 'max:255'],
            'size_bytes' => ['required', 'integer', 'min:1', 'max:'.self::MAX_SIZE],
            'sha256' => ['sometimes', 'nullable', 'regex:/^[a-fA-F0-9]{64}$/'],
            'session_id' => ['sometimes', 'uuid'],
            'replaces_asset_id' => ['sometimes', 'integer', 'min:1'],
            'category' => ['sometimes', 'string', 'in:movie,episode,trailer,teaser,standalone_video,promotional_video,video'],
            'display_name' => ['sometimes', 'string', 'max:255'],
        ]);
        $replacesAssetId = isset($data['replaces_asset_id']) ? (int) $data['replaces_asset_id'] : null;
        $previous = null;
        if ($replacesAssetId !== null) {
            abort_unless(ExplicitPermission::allows($request->user(), 'media.update'), 403);
            $previous = MediaAsset::query()->findOrFail($replacesAssetId);
            abort_unless(in_array($previous->status, ['ready', 'failed'], true), 409, 'Only idle media assets can be replaced.');
        }
        $name = basename(str_replace('\\', '/', trim($data['file_name'])));
        abort_if($name !== $data['file_name'] || $uploads->hasDangerousExtension($name), 422, 'Invalid media filename.');
        $profile = config('upload_profiles.profiles.video');
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        abort_unless(in_array($extension, $profile['extensions'] ?? [], true), 422, 'Unsupported video extension.');
        abort_if((int) $data['size_bytes'] > ((int) $profile['max_kb'] * 1024), 422, 'Media file exceeds the configured upload limit.');
        $category = $data['category'] ?? $previous?->category ?? 'video';
        $displayName = trim($data['display_name'] ?? pathinfo($name, PATHINFO_FILENAME));
        abort_if($displayName === '', 422, 'Enter a display name for the media asset.');

        $chunkCount = (int) ceil($data['size_bytes'] / self::CHUNK_SIZE);
        abort_if($chunkCount > 2048, 422, 'Media file has too many upload parts.');
        if (!empty($data['session_id'])) {
            $session = MediaUploadSession::query()->whereKey($data['session_id'])
                ->where('user_id', $request->user()->id)->firstOrFail();
            abort_unless($session->status === 'uploading' && !$session->expires_at->isPast()
                && $session->original_name === $name && (int) $session->total_size === (int) $data['size_bytes']
                && $session->replaces_asset_id === $replacesAssetId && $session->category === $category
                && $session->display_name === $displayName, 409, 'Upload session cannot be resumed for this file.');
            return response()->json(['data' => $this->uploadPayload($session)], 200);
        }
        $session = DB::transaction(function () use ($request, $name, $data, $replacesAssetId, $chunkCount, $category, $displayName): MediaUploadSession {
            if ($replacesAssetId !== null) {
                $previous = MediaAsset::query()->whereKey($replacesAssetId)->lockForUpdate()->firstOrFail();
                abort_unless(in_array($previous->status, ['ready', 'failed'], true), 409, 'Only idle media assets can be replaced.');
                abort_if(MediaAsset::withTrashed()->where('replaces_asset_id', $replacesAssetId)->exists(), 409, 'Start a replacement from the latest source version to keep the version history linear.');
                $activeReplacement = MediaUploadSession::query()->where('replaces_asset_id', $replacesAssetId)
                    ->whereIn('status', ['uploading', 'assembling'])->where('expires_at', '>', now())->exists();
                abort_if($activeReplacement, 409, 'A replacement upload is already active for this asset.');
            }
            return MediaUploadSession::query()->create([
                'id' => (string) Str::uuid(), 'user_id' => $request->user()->id,
                'original_name' => $name, 'display_name' => $displayName, 'category' => $category,
                'total_size' => $data['size_bytes'], 'chunk_size' => self::CHUNK_SIZE, 'chunk_count' => $chunkCount,
                'expected_sha256' => isset($data['sha256']) ? strtolower($data['sha256']) : null,
                'replaces_asset_id' => $replacesAssetId, 'expires_at' => now()->addHours(24),
            ]);
        });
        return response()->json(['data' => $this->uploadPayload($session)], 201);
    }

    public function uploadChunk(Request $request, string $sessionId, int $index): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.upload'), 403);
        $session = $this->ownedActiveSession($request, $sessionId);
        abort_if($index < 0 || $index >= $session->chunk_count, 422, 'Chunk index is out of range.');
        $request->validate(['chunk' => ['required', 'file', 'max:8192'], 'chunk_sha256' => ['sometimes', 'regex:/^[a-fA-F0-9]{64}$/']]);
        /** @var UploadedFile $chunk */
        $chunk = $request->file('chunk');
        $expectedBytes = $index === $session->chunk_count - 1
            ? $session->total_size - ($index * $session->chunk_size)
            : $session->chunk_size;
        abort_unless($chunk->getSize() === $expectedBytes, 422, 'Chunk size does not match the upload session.');

        $chunkHash = hash_file('sha256', $chunk->getRealPath());
        abort_unless(is_string($chunkHash), 500, 'Unable to verify uploaded chunk.');
        if ($request->filled('chunk_sha256')) {
            abort_unless(hash_equals(strtolower($request->string('chunk_sha256')->toString()), $chunkHash), 422, 'Chunk checksum verification failed.');
        }
        $created = DB::transaction(function () use ($request, $sessionId, $index, $chunk, $chunkHash): bool {
            $locked = MediaUploadSession::query()->whereKey($sessionId)->where('user_id', $request->user()->id)
                ->where('status', 'uploading')->where('expires_at', '>', now())->lockForUpdate()->firstOrFail();
            abort_if($index >= $locked->chunk_count, 422, 'Chunk index is out of range.');
            $disk = Storage::disk('private');
            $path = "media/uploads/{$locked->id}/{$index}.part";
            if ($disk->exists($path)) {
                $existingHash = hash_file('sha256', $disk->path($path));
                abort_unless(is_string($existingHash) && hash_equals($existingHash, $chunkHash), 409, 'Chunk index already contains different data.');
                $created = false;
            } else {
                abort_unless($disk->putFileAs("media/uploads/{$locked->id}", $chunk, "{$index}.part"), 500, 'Unable to store uploaded chunk.');
                $created = true;
            }
            $checksums = $locked->chunk_checksums ?? [];
            $existingChecksum = $checksums[(string) $index] ?? null;
            abort_if($existingChecksum && ! hash_equals($existingChecksum, $chunkHash), 409, 'Upload session chunk identity changed.');
            $checksums[(string) $index] = $chunkHash;
            $locked->update(['chunk_checksums' => $checksums]);
            return $created;
        });
        return response()->json(['data' => ['index' => $index, 'sha256' => $chunkHash, 'received' => true]], $created ? 201 : 200);
    }

    public function cancelUpload(Request $request, string $session, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.upload'), 403);
        $cancelled = DB::transaction(function () use ($request, $session, $audit): bool {
            $locked = MediaUploadSession::query()->whereKey($session)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->user_id === (int) $request->user()->id, 404);
            if (in_array($locked->status, ['cancelled', 'expired'], true)) return false;
            abort_unless(in_array($locked->status, ['uploading', 'assembling'], true), 409, 'Completed uploads cannot be cancelled.');
            $before = ['status' => $locked->status];
            $locked->update(['status' => 'cancelled']);
            $audit->record($request->user(), 'media.upload.cancelled', MediaUploadSession::class, $locked->id, $before, ['status' => 'cancelled']);
            return true;
        });
        if ($cancelled) Storage::disk('private')->deleteDirectory("media/uploads/{$session}");
        return response()->json(['data' => ['id' => $session, 'cancelled' => true]]);
    }

    public function completeUpload(Request $request, string $sessionId, FileUploadValidationService $uploads, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.upload'), 403);
        $disk = Storage::disk('private');
        $session = DB::transaction(function () use ($request, $sessionId): MediaUploadSession {
            $session = MediaUploadSession::query()->whereKey($sessionId)->lockForUpdate()->firstOrFail();
            abort_unless((int) $session->user_id === (int) $request->user()->id, 404);
            abort_if($session->status !== 'uploading' || $session->expires_at->isPast(), 409, 'Upload session is no longer active.');
            $session->update(['status' => 'assembling']);
            return $session;
        });

        $assembled = tmpfile();
        abort_if($assembled === false, 500, 'Unable to assemble media upload.');
        $hash = hash_init('sha256');
        $total = 0;
        try {
            for ($index = 0; $index < $session->chunk_count; $index++) {
                $chunkPath = "media/uploads/{$session->id}/{$index}.part";
                abort_unless($disk->exists($chunkPath), 409, "Upload is missing chunk {$index}.");
                $stream = $disk->readStream($chunkPath);
                abort_if($stream === false, 500, 'Unable to read uploaded media chunk.');
                while (!feof($stream)) {
                    $buffer = fread($stream, 1024 * 1024);
                    if ($buffer === false) {
                        fclose($stream);
                        abort(500, 'Unable to assemble uploaded media.');
                    }
                    if ($buffer !== '') {
                        $total += strlen($buffer);
                        hash_update($hash, $buffer);
                        fwrite($assembled, $buffer);
                    }
                }
                fclose($stream);
            }
            $sha = hash_final($hash);
            abort_unless($total === (int) $session->total_size, 422, 'Assembled size does not match the upload declaration.');
            abort_if($session->expected_sha256 && !hash_equals($session->expected_sha256, $sha), 422, 'Media checksum verification failed.');
            $duplicate = MediaAsset::query()->where('sha256', $sha)->first();
            if ($duplicate) {
                MediaUploadSession::query()->whereKey($session->id)->where('status', 'assembling')->update(['status' => 'duplicate']);
                $disk->deleteDirectory("media/uploads/{$session->id}");
                return response()->json(['data' => [
                    'upload_complete' => false, 'duplicate' => true,
                    'existing_asset_id' => $duplicate->id, 'message' => 'This exact source file is already in the media library.',
                ]], 409);
            }
            $tempMeta = stream_get_meta_data($assembled);
            $path = $tempMeta['uri'];
            $uploaded = new UploadedFile($path, $session->original_name, mime_content_type($path) ?: null, null, true);
            $uploads->validate($uploaded, 'video');
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
            $assetDirectory = (string) Str::uuid();
            $sourcePath = "media/originals/{$assetDirectory}/source.".strtolower(pathinfo($session->original_name, PATHINFO_EXTENSION));
            $read = fopen($path, 'rb');
            $stored = $disk->put($sourcePath, $read);
            fclose($read);
            abort_unless($stored, 500, 'Unable to store uploaded media.');

            [$asset, $job] = DB::transaction(function () use ($request, $session, $sourcePath, $mime, $total, $sha, $audit): array {
                $locked = MediaUploadSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
                abort_unless($locked->status === 'assembling', 409, 'Upload is no longer available for completion.');
                $previous = $locked->replaces_asset_id
                    ? MediaAsset::query()->whereKey($locked->replaces_asset_id)->lockForUpdate()->firstOrFail()
                    : null;
                if ($previous) {
                    abort_unless(in_array($previous->status, ['ready', 'failed'], true), 409, 'Media asset became busy while replacement was uploading.');
                }
                $asset = MediaAsset::query()->create([
                    'replaces_asset_id' => $previous?->id, 'version' => $previous ? $previous->version + 1 : 1,
                    'is_active_version' => $previous === null,
                    'category' => $session->category, 'display_name' => $session->display_name,
                    'quality_status' => 'pending_inspection',
                    'uploaded_by' => $request->user()->id, 'original_name' => $session->original_name,
                    'disk' => 'private', 'source_path' => $sourcePath, 'mime_type' => $mime,
                    'extension' => strtolower(pathinfo($session->original_name, PATHINFO_EXTENSION)),
                    'size_bytes' => $total, 'sha256' => $sha, 'status' => 'queued',
                ]);
                $job = MediaProcessingJob::query()->create([
                    'media_asset_id' => $asset->id, 'type' => 'transcode', 'status' => 'queued',
                    'idempotency_key' => hash('sha256', 'transcode:'.$asset->id.':v'.$asset->version),
                ]);
                $locked->update(['status' => 'completed']);
                $audit->record($request->user(), 'media.upload.completed', MediaAsset::class, $asset->id, null, ['version' => 1, 'size_bytes' => $total]);
                return [$asset, $job];
            });
            $published = \App\Services\EventPublisher::publish('media.transcode.requested', ['media_asset_id' => $asset->id, 'job_id' => $job->id], 'media-job-'.$job->id);
            if (!$published) {
                $job->update(['status' => 'failed', 'failure_code' => 'queue_unavailable', 'failure_message' => 'The processing queue did not accept the job.', 'completed_at' => now()]);
                $asset->update(['status' => 'failed', 'failure_code' => 'queue_unavailable']);
                $audit->record($request->user(), 'media.transcode.enqueue_failed', MediaAsset::class, $asset->id, ['status' => 'queued'], ['status' => 'failed'], 'failure');
            }
            $disk->deleteDirectory("media/uploads/{$session->id}");
            return response()->json(['data' => [
                ...$this->assetPayload($asset->fresh()->load(['processingJobs', 'assetTracks'])),
                'upload_complete' => true, 'processing_queue' => $published ? 'queued' : 'unavailable',
            ]], $published ? 201 : 202);
        } catch (\Throwable $exception) {
            MediaUploadSession::query()->whereKey($session->id)->where('status', 'assembling')->update(['status' => 'uploading']);
            if (isset($sourcePath)) {
                $disk->delete($sourcePath);
            }
            throw $exception;
        } finally {
            fclose($assembled);
        }
    }

    public function retry(Request $request, MediaAsset $asset, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.transcode.retry'), 403);
        $job = DB::transaction(function () use ($request, $asset, $audit): MediaProcessingJob {
            $lockedAsset = MediaAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lockedAsset->status, ['failed', 'ready'], true), 409, 'Only failed processing on an idle asset can be retried.');
            $previous = $lockedAsset->processingJobs()->where('type', 'transcode')->latest()->lockForUpdate()->firstOrFail();
            abort_unless($previous->status === 'failed', 409, 'The latest processing attempt is not failed.');
            $previousAssetStatus = $lockedAsset->status;
            $attemptsUsed = (int) $lockedAsset->processingJobs()->where('type', 'transcode')->sum('attempts');
            abort_if($attemptsUsed >= $previous->max_attempts, 409, 'Media job retry limit has been reached.');
            $retryNumber = $lockedAsset->processingJobs()->where('type', 'transcode')->count();
            $job = MediaProcessingJob::query()->create([
                'media_asset_id' => $lockedAsset->id, 'type' => 'transcode', 'status' => 'queued',
                'attempts' => 0, 'max_attempts' => $previous->max_attempts,
                'idempotency_key' => hash('sha256', 'transcode:'.$lockedAsset->id.':retry:'.$retryNumber),
            ]);
            $hasActiveRendition = $lockedAsset->status === 'ready' && filled($lockedAsset->rendition_path);
            if (! $hasActiveRendition) $lockedAsset->update(['status' => 'queued', 'failure_code' => null]);
            $audit->record($request->user(), 'media.transcode.retry_requested', MediaAsset::class, $asset->id,
                ['status' => $previousAssetStatus], ['status' => $lockedAsset->fresh()->status, 'job_id' => $job->id, 'preserving_active_rendition' => $hasActiveRendition]);
            return $job;
        });
        $published = \App\Services\EventPublisher::publish('media.transcode.requested', ['media_asset_id' => $asset->id, 'job_id' => $job->id], 'media-job-'.$job->id);
        if (!$published) {
            $job->update(['status' => 'failed', 'failure_code' => 'queue_unavailable', 'failure_message' => 'The processing queue did not accept the retry.', 'completed_at' => now()]);
            if (! filled($asset->rendition_path)) $asset->update(['status' => 'failed', 'failure_code' => 'queue_unavailable']);
        }
        return response()->json(['data' => ['job_id' => $job->id, 'status' => $job->fresh()->status, 'queue_available' => (bool) $published]], 202);
    }

    public function addTrack(Request $request, MediaAsset $asset, FileUploadValidationService $uploads, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:subtitle,audio'],
            'language' => ['required', 'string', 'regex:/^[a-zA-Z]{2,3}([-_][a-zA-Z0-9]{2,8})?$/'],
            'display_name' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', 'string', 'in:main,dub,commentary,description,captions,forced,other'],
            'is_default' => ['sometimes', 'boolean'], 'is_forced' => ['sometimes', 'boolean'],
            'is_accessibility' => ['sometimes', 'boolean'], 'is_original' => ['sometimes', 'boolean'],
            'is_dubbed' => ['sometimes', 'boolean'], 'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'file' => ['required', 'file', 'max:51200'],
        ]);
        $permission = $data['kind'] === 'subtitle' ? 'media.subtitles.manage' : 'media.audio.manage';
        abort_unless(ExplicitPermission::allows($request->user(), $permission), 403);
        abort_unless(in_array($asset->status, ['uploaded', 'ready', 'failed'], true), 409, 'Wait for current media processing to finish before changing tracks.');
        $role = $data['role'] ?? 'main';
        abort_if($asset->assetTracks()->where('kind', $data['kind'])->where('language', strtolower($data['language']))->where('role', $role)->exists(), 409, 'This track role and language already exists.');
        abort_if($asset->assetTracks()->where('kind', $data['kind'])->count() >= 8, 422, 'The maximum number of tracks for this type has been reached.');
        $file = $request->file('file');
        $uploads->validate($file, $data['kind']);
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $path = "media/tracks/{$asset->id}/".(string) Str::uuid().".{$extension}";
        $storedPath = Storage::disk('private')->putFileAs(dirname($path), $file, basename($path));
        abort_unless(is_string($storedPath) && $storedPath === $path, 500, 'The uploaded track could not be stored.');

        try {
            [$track, $job, $wasReady] = DB::transaction(function () use ($request, $asset, $data, $file, $filename, $path, $audit, $role): array {
                $lockedAsset = MediaAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
                abort_unless(in_array($lockedAsset->status, ['uploaded', 'ready', 'failed'], true), 409, 'Wait for current media processing to finish before changing tracks.');
                abort_if($lockedAsset->assetTracks()->where('kind', $data['kind'])->where('language', strtolower($data['language']))->where('role', $role)->exists(), 409, 'This track role and language already exists.');
                abort_if($lockedAsset->assetTracks()->where('kind', $data['kind'])->count() >= 8, 422, 'The maximum number of tracks for this type has been reached.');
                $kind = $data['kind'];
                if ((bool) ($data['is_default'] ?? false)) $lockedAsset->assetTracks()->where('kind', $kind)->update(['is_default' => false]);
                $track = MediaAssetTrack::query()->create([
                    'media_asset_id' => $asset->id, 'uploaded_by' => $request->user()->id,
                    'kind' => $data['kind'], 'language' => strtolower($data['language']),
                    'display_name' => $data['display_name'] ?? pathinfo($filename, PATHINFO_FILENAME), 'role' => $role,
                    'is_default' => (bool) ($data['is_default'] ?? false), 'is_forced' => (bool) ($data['is_forced'] ?? false),
                    'is_accessibility' => (bool) ($data['is_accessibility'] ?? false), 'is_original' => (bool) ($data['is_original'] ?? false),
                    'is_dubbed' => (bool) ($data['is_dubbed'] ?? false), 'sort_order' => (int) ($data['sort_order'] ?? 0),
                    'original_name' => $filename, 'disk' => 'private', 'path' => $path,
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size_bytes' => $file->getSize(),
                ]);
                $audit->record($request->user(), 'media.track.added', MediaAssetTrack::class, $track->id, null, [
                    'media_asset_id' => $asset->id, 'kind' => $track->kind, 'language' => $track->language,
                ]);
                $job = null;
                $wasReady = $lockedAsset->status === 'ready' && filled($lockedAsset->rendition_path);
                if ($wasReady || $lockedAsset->status === 'failed') {
                    $job = MediaProcessingJob::query()->create([
                        'media_asset_id' => $lockedAsset->id, 'type' => 'transcode', 'status' => 'queued',
                        'idempotency_key' => hash('sha256', 'track-update:'.$lockedAsset->id.':'.$track->id),
                    ]);
                    if (! $wasReady) $lockedAsset->update(['status' => 'queued', 'failure_code' => null]);
                }
                return [$track, $job, $wasReady];
            });
        } catch (\Throwable $exception) {
            Storage::disk('private')->delete($path);
            throw $exception;
        }
        if ($job) {
            $published = \App\Services\EventPublisher::publish('media.transcode.requested', [
                'media_asset_id' => $asset->id, 'job_id' => $job->id,
            ], 'media-job-'.$job->id);
            if (!$published) {
                $job->update(['status' => 'failed', 'failure_code' => 'queue_unavailable', 'failure_message' => 'The processing queue did not accept the track update.', 'completed_at' => now()]);
                if (! $wasReady) $asset->update(['status' => 'failed', 'failure_code' => 'queue_unavailable']);
            }
        }
        return response()->json(['data' => [...$this->trackPayload($track), 'processing_queue' => $job ? ($published ? 'queued' : 'unavailable') : 'not_required']], 201);
    }

    public function deleteTrack(Request $request, MediaAsset $asset, int $track, AdminAuditLogger $audit): JsonResponse
    {
        $record = $asset->assetTracks()->whereKey($track)->firstOrFail();
        $permission = $record->kind === 'subtitle' ? 'media.subtitles.manage' : 'media.audio.manage';
        abort_unless(ExplicitPermission::allows($request->user(), $permission), 403);
        [$job, $wasReady] = DB::transaction(function () use ($request, $record, $asset, $audit): array {
            $lockedAsset = MediaAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $lockedRecord = $lockedAsset->assetTracks()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            $wasReady = $lockedAsset->status === 'ready' && filled($lockedAsset->rendition_path);
            $before = ['media_asset_id' => $lockedAsset->id, 'kind' => $lockedRecord->kind, 'language' => $lockedRecord->language, 'role' => $lockedRecord->role];
            $lockedRecord->delete();
            $job = null;
            if ($wasReady) {
                $job = MediaProcessingJob::query()->create([
                    'media_asset_id' => $lockedAsset->id, 'type' => 'transcode', 'status' => 'queued',
                    'idempotency_key' => hash('sha256', 'track-remove:'.$lockedAsset->id.':'.$lockedRecord->id.':'.now()->timestamp),
                ]);
            }
            $audit->record($request->user(), 'media.track.removed', MediaAssetTrack::class, $lockedRecord->id, $before, null);
            return [$job, $wasReady];
        });
        $published = null;
        if ($job) {
            $published = \App\Services\EventPublisher::publish('media.transcode.requested', ['media_asset_id' => $asset->id, 'job_id' => $job->id], 'media-job-'.$job->id);
            if (! $published) $job->update(['status' => 'failed', 'failure_code' => 'queue_unavailable', 'failure_message' => 'The processing queue did not accept the track update.', 'completed_at' => now()]);
        }
        return response()->json(['data' => ['deleted' => true, 'active_rendition_preserved' => $wasReady, 'processing_queue' => $job ? ($published ? 'queued' : 'unavailable') : 'not_required']]);
    }

    public function destroy(Request $request, MediaAsset $asset, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.delete'), 403);
        $data = $request->validate(['reason' => ['sometimes', 'nullable', 'string', 'max:500']]);
        DB::transaction(function () use ($request, $asset, $audit, $data): void {
            $locked = MediaAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, ['ready', 'failed', 'uploaded'], true), 409, 'Processing media cannot be removed.');
            abort_if($this->hasPublishedUsage($locked), 409, 'This asset is required by published content. Detach it through the content review workflow before archiving.');
            $locked->forceFill(['archived_at' => now(), 'archived_by' => $request->user()->id, 'archive_reason' => $data['reason'] ?? null]);
            $locked->save();
            $locked->delete(); // Soft-delete the registry record; retain source and rendition files for recovery/audit.
            $audit->record($request->user(), 'media.asset.archived', MediaAsset::class, $locked->id, ['status' => $locked->status], ['archived' => true, 'reason' => $data['reason'] ?? null]);
        });
        return response()->json(['data' => ['archived' => true]]);
    }

    public function drmConfiguration(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.drm.configure'), 403);
        return response()->json(['data' => [
            'provider' => config('media.drm_provider', 'none'),
            'license_server_configured' => filled(config('media.drm_license_url')),
            'key_material_configured' => filled(config('media.drm_secret')),
        ]]);
    }

    public function attachToContent(Request $request, MediaAsset $asset, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.update')
            && ExplicitPermission::allows($request->user(), 'content.update'), 403);
        $data = $request->validate([
            'content_type' => ['sometimes', 'string', 'in:entertainment,episode,video'],
            'content_id' => ['required', 'string', 'max:64'],
        ]);
        $contentType = $data['content_type'] ?? 'entertainment';
        $contentModel = match ($contentType) { 'episode' => Episode::class, 'video' => Video::class, default => Entertainment::class };
        $content = $contentModel::query()->findOrFail($data['content_id']);
        abort_if((bool) $content->status, 409, 'Published content must use the reviewed replacement workflow.');
        abort_unless(in_array($asset->status, ['queued', 'processing', 'ready', 'failed'], true), 409, 'Asset cannot be attached in its current state.');
        DB::transaction(function () use ($request, $asset, $content, $contentModel, $contentType, $audit): void {
            $lockedAsset = MediaAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lockedAsset->status, ['queued', 'processing', 'ready', 'failed'], true), 409);
            $lockedContent = $contentModel::query()->whereKey($content->getKey())->lockForUpdate()->firstOrFail();
            abort_if((bool) $lockedContent->status, 409, 'Published content must use the reviewed replacement workflow.');
            $before = $lockedContent->media_asset_id;
            $lockedContent->forceFill(['media_asset_id' => $lockedAsset->id])->save();
            $audit->record($request->user(), 'media.asset.attached', MediaAsset::class, $lockedAsset->id,
                ['content_type' => $contentType, 'content_id' => (string) $lockedContent->getKey(), 'media_asset_id' => $before],
                ['content_type' => $contentType, 'content_id' => (string) $lockedContent->getKey(), 'media_asset_id' => $lockedAsset->id]);
        });
        return response()->json(['data' => ['media_asset_id' => $asset->id, 'content_type' => $contentType, 'content_id' => (string) $content->getKey()]]);
    }

    private function ownedActiveSession(Request $request, string $id): MediaUploadSession
    {
        return MediaUploadSession::query()->whereKey($id)->where('user_id', $request->user()->id)
            ->where('status', 'uploading')->where('expires_at', '>', now())->firstOrFail();
    }

    private function previewUrl(MediaAsset $asset, string $file): string
    {
        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'admin.media.preview.file', now()->addSeconds(120), ['asset' => $asset->id, 'file' => $file]
        );
    }

    private function normalizePreviewPath(string $file): string
    {
        abort_if($file === '' || str_contains($file, "\0") || str_contains($file, '\\') || str_starts_with($file, '/'), 404);
        $segments = explode('/', $file);
        abort_if(in_array('.', $segments, true) || in_array('..', $segments, true) || in_array('', $segments, true), 404);
        abort_unless(in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['m3u8', 'ts', 'm4s', 'mp4', 'm4a', 'aac', 'vtt'], true), 404);
        return $file;
    }

    private function versionChain(MediaAsset $asset): \Illuminate\Support\Collection
    {
        $ids = [];
        $cursor = MediaAsset::withTrashed()->findOrFail($asset->id);
        while ($cursor && ! in_array($cursor->id, $ids, true) && count($ids) < 100) {
            $ids[] = $cursor->id;
            $cursor = $cursor->replaces_asset_id ? MediaAsset::withTrashed()->find($cursor->replaces_asset_id) : null;
        }
        $frontier = $ids;
        while ($frontier !== [] && count($ids) < 100) {
            $children = MediaAsset::withTrashed()->whereIn('replaces_asset_id', $frontier)->pluck('id')->all();
            $frontier = array_values(array_diff($children, $ids));
            $ids = array_merge($ids, $frontier);
        }
        return MediaAsset::withTrashed()->whereIn('id', $ids)->orderBy('version')->orderBy('id')->get();
    }

    private function setActiveVersion(
        Request $request,
        MediaAsset $contextAsset,
        MediaAsset $target,
        MediaReadinessService $readiness,
        AdminAuditLogger $audit,
        string $action
    ): JsonResponse {
        $versions = $this->versionChain($contextAsset);
        abort_unless($versions->contains('id', $target->id), 404);
        abort_unless($target->status === 'ready' && $target->quality_status === 'approved', 409, 'Only a ready, quality-approved version can be activated.');
        abort_unless($readiness->inspect($target)['ready'], 409, 'The selected version failed technical readiness checks.');
        $ids = $versions->pluck('id')->all();
        $updatedContent = 0;
        DB::transaction(function () use ($request, $target, $ids, $audit, $action, &$updatedContent): void {
            $lockedVersions = MediaAsset::withTrashed()->whereIn('id', $ids)->lockForUpdate()->get();
            abort_unless($lockedVersions->contains(fn (MediaAsset $item): bool => (int) $item->id === (int) $target->id), 404);
            foreach ([Entertainment::class, Episode::class, Video::class] as $model) {
                $contentRows = $model::query()->whereIn('media_asset_id', $ids)->lockForUpdate()->get(['id', 'status', 'media_asset_id']);
                abort_if($contentRows->contains(fn ($content): bool => (bool) $content->status), 409, 'Published titles require the content review and publication workflow before activating a different source version.');
                foreach ($contentRows as $content) {
                    if ((int) $content->media_asset_id !== (int) $target->id) {
                        $content->forceFill(['media_asset_id' => $target->id])->save();
                        $updatedContent++;
                    }
                }
            }
            MediaAsset::query()->whereIn('id', $ids)->update(['is_active_version' => false]);
            $target->newQuery()->whereKey($target->id)->update(['is_active_version' => true]);
            $audit->record($request->user(), $action, MediaAsset::class, $target->id,
                ['active_versions' => $lockedVersions->where('is_active_version', true)->pluck('id')->all()],
                ['active_version' => $target->id, 'draft_content_updated' => $updatedContent]);
        }, 3);
        return response()->json(['data' => ['active_version_id' => $target->id, 'draft_content_updated' => $updatedContent]]);
    }

    private function hasPublishedUsage(MediaAsset $asset): bool
    {
        foreach ([Entertainment::class, Episode::class, Video::class] as $model) {
            if ($model::query()->where('media_asset_id', $asset->id)->where('status', 1)->exists()) return true;
        }
        return false;
    }

    private function uploadPayload(MediaUploadSession $session): array
    {
        $directory = "media/uploads/{$session->id}";
        $files = Storage::disk('private')->files($directory);
        $received = collect($files)->map(fn (string $file): ?int => preg_match('/\/(\d+)\.part$/', $file, $matches) ? (int) $matches[1] : null)
            ->filter(fn ($index): bool => $index !== null)->sort()->values()->all();
        return [
            'id' => $session->id, 'chunk_size' => $session->chunk_size,
            'chunk_count' => $session->chunk_count, 'received_chunks' => $received,
            'chunk_checksums' => $session->chunk_checksums ?? [], 'category' => $session->category,
            'display_name' => $session->display_name, 'replaces_asset_id' => $session->replaces_asset_id,
            'expires_at' => $session->expires_at->toIso8601String(),
        ];
    }

    private function assetPayload(MediaAsset $asset, bool $includePreview = false): array
    {
        $attachments = collect();
        foreach (['entertainments' => 'entertainment', 'episodes' => 'episode', 'videos' => 'video'] as $relation => $type) {
            if ($asset->relationLoaded($relation)) {
                $attachments = $attachments->concat($asset->getRelation($relation)->map(fn ($content) => [
                    'id' => (string) $content->id, 'title' => (string) $content->name, 'type' => $type,
                    'status' => (bool) $content->status ? 'published' : 'draft',
                ]));
            }
        }
        $metadata = $asset->metadata ?? [];
        $videoStream = collect($metadata['Streams'] ?? [])->first(fn ($stream) => ($stream['codec_type'] ?? null) === 'video');
        $audioStream = collect($metadata['Streams'] ?? [])->first(fn ($stream) => ($stream['codec_type'] ?? null) === 'audio');
        $width = (int) ($videoStream['width'] ?? 0);
        $height = (int) ($videoStream['height'] ?? 0);
        $aspectRatio = $width > 0 && $height > 0 ? $this->aspectRatio($width, $height) : null;
        return [
            'id' => $asset->id, 'display_name' => $asset->display_name, 'category' => $asset->category,
            'description' => $asset->description, 'original_name' => $asset->original_name, 'mime_type' => $asset->mime_type,
            'extension' => $asset->extension, 'size_bytes' => $asset->size_bytes, 'status' => $asset->status,
            'thumbnail_url' => $includePreview && $asset->disk === 'private' && filled($asset->rendition_path)
                ? $this->previewUrl($asset, 'poster.jpg') : null,
            'version' => $asset->version, 'metadata' => $metadata, 'tracks' => $asset->tracks,
            'duration_seconds' => is_numeric($metadata['Format']['duration'] ?? null) ? (float) $metadata['Format']['duration'] : null,
            'resolution' => $videoStream ? ((int) ($videoStream['width'] ?? 0)).'x'.((int) ($videoStream['height'] ?? 0)) : null,
            'aspect_ratio' => $aspectRatio, 'frame_rate' => $videoStream['avg_frame_rate'] ?? $videoStream['r_frame_rate'] ?? null,
            'codec' => $videoStream['codec_name'] ?? null, 'audio_codec' => $audioStream['codec_name'] ?? null,
            'bitrate' => $metadata['Format']['bit_rate'] ?? null,
            'storage_provider' => $asset->disk,
            'checksum' => $asset->relationLoaded('processingJobs') ? $asset->sha256 : null,
            'quality_status' => $asset->quality_status,
            'quality_notes' => $asset->quality_notes,
            'quality_reviewed_at' => $asset->quality_reviewed_at?->toIso8601String(),
            'quality_reviewer' => $asset->relationLoaded('qualityReviewer') ? $asset->qualityReviewer?->only(['id', 'name', 'email']) : null,
            'updated_at' => $asset->updated_at?->toIso8601String(),
            'archived_at' => $asset->archived_at?->toIso8601String(),
            'archived_by' => $asset->relationLoaded('archiver') ? $asset->archiver?->only(['id', 'name', 'email']) : null,
            'archive_reason' => $asset->archive_reason, 'is_active_version' => (bool) $asset->is_active_version,
            'previous_asset_id' => $asset->replaces_asset_id,
            'failure_code' => $asset->failure_code, 'ready_at' => $asset->ready_at?->toIso8601String(),
            'uploader' => $asset->relationLoaded('uploader') ? $asset->uploader?->only(['id', 'name', 'email']) : null,
            'created_at' => $asset->created_at?->toIso8601String(),
            'content_attachments' => $attachments->values(),
            'latest_processing_job' => $asset->relationLoaded('latestProcessingJob') && $asset->latestProcessingJob ? [
                'id' => $asset->latestProcessingJob->id, 'type' => $asset->latestProcessingJob->type,
                'status' => $asset->latestProcessingJob->status, 'stage' => $asset->latestProcessingJob->stage,
                'progress' => $asset->latestProcessingJob->progress, 'failure_code' => $asset->latestProcessingJob->failure_code,
                'failure_message' => $asset->latestProcessingJob->failure_message,
            ] : null,
            'processing_jobs' => $asset->relationLoaded('processingJobs') ? $asset->processingJobs->map(fn (MediaProcessingJob $job) => [
                'id' => $job->id, 'type' => $job->type, 'status' => $job->status, 'attempts' => $job->attempts,
                'max_attempts' => $job->max_attempts, 'stage' => $job->stage, 'progress' => $job->progress,
                'worker_name' => $job->worker_name, 'failure_code' => $job->failure_code, 'failure_message' => $job->failure_message,
                'started_at' => $job->started_at?->toIso8601String(), 'completed_at' => $job->completed_at?->toIso8601String(),
            ])->values() : [],
            'asset_tracks' => $asset->relationLoaded('assetTracks') ? $asset->assetTracks->map(fn (MediaAssetTrack $track): array => $this->trackPayload($track))->values() : [],
        ];
    }

    private function trackPayload(MediaAssetTrack $track): array
    {
        return [
            'id' => $track->id, 'kind' => $track->kind, 'language' => $track->language,
            'display_name' => $track->display_name ?: $track->original_name, 'original_name' => $track->original_name,
            'mime_type' => $track->mime_type, 'size_bytes' => $track->size_bytes, 'is_default' => (bool) $track->is_default,
            'is_forced' => (bool) $track->is_forced, 'is_accessibility' => (bool) $track->is_accessibility,
            'is_original' => (bool) $track->is_original, 'is_dubbed' => (bool) $track->is_dubbed,
            'sort_order' => (int) $track->sort_order, 'processing_status' => $track->processing_status,
            'created_at' => $track->created_at?->toIso8601String(),
        ];
    }

    private function aspectRatio(int $width, int $height): string
    {
        $a = $width;
        $b = $height;
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }
        return (string) intdiv($width, $a).':'.(string) intdiv($height, $a);
    }
}
