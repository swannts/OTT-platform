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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Entertainment\Models\Entertainment;

class AdminMediaController extends Controller
{
    private const MAX_SIZE = 10 * 1024 * 1024 * 1024;
    private const CHUNK_SIZE = 8 * 1024 * 1024;

    public function index(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.view'), 403);
        $filters = $request->validate([
            'status' => ['sometimes', 'string', 'in:uploaded,queued,processing,ready,failed'],
            'search' => ['sometimes', 'string', 'max:120'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = MediaAsset::query()->with(['processingJobs', 'assetTracks'])->withCount('processingJobs')->latest('created_at');
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $query->where('original_name', 'like', '%'.addcslashes($filters['search'], '%_\\').'%');
        }

        $assets = $query->paginate($filters['per_page'] ?? 25);
        return response()->json([
            'data' => $assets->getCollection()->map(fn (MediaAsset $asset): array => $this->assetPayload($asset)),
            'meta' => ['current_page' => $assets->currentPage(), 'last_page' => $assets->lastPage(), 'total' => $assets->total()],
        ]);
    }

    public function show(Request $request, MediaAsset $asset): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.view'), 403);
        return response()->json(['data' => $this->assetPayload($asset->load(['processingJobs', 'assetTracks']))]);
    }

    public function quality(Request $request, MediaAsset $asset, MediaReadinessService $readiness): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.view'), 403);
        return response()->json(['data' => [
            'asset_id' => $asset->id, 'status' => $asset->status,
            ...$readiness->inspect($asset),
        ]]);
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
        ]);
        $replacesAssetId = isset($data['replaces_asset_id']) ? (int) $data['replaces_asset_id'] : null;
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

        $chunkCount = (int) ceil($data['size_bytes'] / self::CHUNK_SIZE);
        abort_if($chunkCount > 2048, 422, 'Media file has too many upload parts.');
        if (!empty($data['session_id'])) {
            $session = MediaUploadSession::query()->whereKey($data['session_id'])
                ->where('user_id', $request->user()->id)->firstOrFail();
            abort_unless($session->status === 'uploading' && !$session->expires_at->isPast()
                && $session->original_name === $name && (int) $session->total_size === (int) $data['size_bytes']
                && $session->replaces_asset_id === $replacesAssetId, 409, 'Upload session cannot be resumed for this file.');
            return response()->json(['data' => $this->uploadPayload($session)], 200);
        }
        $session = MediaUploadSession::query()->create([
            'id' => (string) Str::uuid(), 'user_id' => $request->user()->id,
            'original_name' => $name, 'total_size' => $data['size_bytes'],
            'chunk_size' => self::CHUNK_SIZE, 'chunk_count' => $chunkCount,
            'expected_sha256' => isset($data['sha256']) ? strtolower($data['sha256']) : null,
            'replaces_asset_id' => $replacesAssetId,
            'expires_at' => now()->addHours(24),
        ]);
        return response()->json(['data' => $this->uploadPayload($session)], 201);
    }

    public function uploadChunk(Request $request, string $sessionId, int $index): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.upload'), 403);
        $session = $this->ownedActiveSession($request, $sessionId);
        abort_if($index < 0 || $index >= $session->chunk_count, 422, 'Chunk index is out of range.');
        $request->validate(['chunk' => ['required', 'file', 'max:8192']]);
        /** @var UploadedFile $chunk */
        $chunk = $request->file('chunk');
        $expectedBytes = $index === $session->chunk_count - 1
            ? $session->total_size - ($index * $session->chunk_size)
            : $session->chunk_size;
        abort_unless($chunk->getSize() === $expectedBytes, 422, 'Chunk size does not match the upload session.');

        $disk = Storage::disk('private');
        $path = "media/uploads/{$session->id}/{$index}.part";
        $existing = $disk->path($path);
        if ($disk->exists($path)) {
            abort_unless(hash_file('sha256', $existing) === hash_file('sha256', $chunk->getRealPath()), 409, 'Chunk index already contains different data.');
            return response()->json(['data' => ['index' => $index, 'received' => true]]);
        }
        $disk->putFileAs("media/uploads/{$session->id}", $chunk, "{$index}.part");
        return response()->json(['data' => ['index' => $index, 'received' => true]], 201);
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
                    'uploaded_by' => $request->user()->id, 'original_name' => $session->original_name,
                    'disk' => 'private', 'source_path' => $sourcePath, 'mime_type' => $mime,
                    'extension' => strtolower(pathinfo($session->original_name, PATHINFO_EXTENSION)),
                    'size_bytes' => $total, 'sha256' => $sha, 'status' => 'queued', 'version' => 1,
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
                $job->update(['status' => 'failed', 'failure_code' => 'queue_unavailable', 'completed_at' => now()]);
                $asset->update(['status' => 'failed', 'failure_code' => 'queue_unavailable']);
                $audit->record($request->user(), 'media.transcode.enqueue_failed', MediaAsset::class, $asset->id, ['status' => 'queued'], ['status' => 'failed'], 'failure');
            }
            $disk->deleteDirectory("media/uploads/{$session->id}");
            return response()->json(['data' => $this->assetPayload($asset->fresh()->load(['processingJobs', 'assetTracks']))], 201);
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
            abort_unless($lockedAsset->status === 'failed', 409, 'Only failed media processing can be retried.');
            $previous = $lockedAsset->processingJobs()->where('type', 'transcode')->latest()->lockForUpdate()->firstOrFail();
            $attemptsUsed = (int) $lockedAsset->processingJobs()->where('type', 'transcode')->sum('attempts');
            abort_if($attemptsUsed >= $previous->max_attempts, 409, 'Media job retry limit has been reached.');
            $retryNumber = $lockedAsset->processingJobs()->where('type', 'transcode')->count();
            $job = MediaProcessingJob::query()->create([
                'media_asset_id' => $lockedAsset->id, 'type' => 'transcode', 'status' => 'queued',
                'attempts' => 0, 'max_attempts' => $previous->max_attempts,
                'idempotency_key' => hash('sha256', 'transcode:'.$lockedAsset->id.':retry:'.$retryNumber),
            ]);
            $lockedAsset->update(['status' => 'queued', 'failure_code' => null]);
            $audit->record($request->user(), 'media.transcode.retry_requested', MediaAsset::class, $asset->id, ['status' => 'failed'], ['status' => 'queued', 'job_id' => $job->id]);
            return $job;
        });
        $published = \App\Services\EventPublisher::publish('media.transcode.requested', ['media_asset_id' => $asset->id, 'job_id' => $job->id], 'media-job-'.$job->id);
        if (!$published) {
            $job->update(['status' => 'failed', 'failure_code' => 'queue_unavailable', 'completed_at' => now()]);
            $asset->update(['status' => 'failed', 'failure_code' => 'queue_unavailable']);
        }
        return response()->json(['data' => ['job_id' => $job->id, 'status' => $job->fresh()->status]], 202);
    }

    public function addTrack(Request $request, MediaAsset $asset, FileUploadValidationService $uploads, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:subtitle,audio'],
            'language' => ['required', 'string', 'regex:/^[a-zA-Z]{2,3}([-_][a-zA-Z0-9]{2,8})?$/'],
            'file' => ['required', 'file', 'max:51200'],
        ]);
        $permission = $data['kind'] === 'subtitle' ? 'media.subtitles.manage' : 'media.audio.manage';
        abort_unless(ExplicitPermission::allows($request->user(), $permission), 403);
        abort_unless(in_array($asset->status, ['uploaded', 'ready', 'failed'], true), 409, 'Wait for current media processing to finish before changing tracks.');
        abort_if($asset->assetTracks()->where('kind', $data['kind'])->where('language', strtolower($data['language']))->exists(), 409, 'A track of this type and language already exists.');
        abort_if($asset->assetTracks()->where('kind', $data['kind'])->count() >= 8, 422, 'The maximum number of tracks for this type has been reached.');
        $file = $request->file('file');
        $uploads->validate($file, $data['kind']);
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $path = "media/tracks/{$asset->id}/".(string) Str::uuid().".{$extension}";
        Storage::disk('private')->putFileAs(dirname($path), $file, basename($path));

        [$track, $job] = DB::transaction(function () use ($request, $asset, $data, $file, $filename, $path, $audit): array {
            $lockedAsset = MediaAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lockedAsset->status, ['uploaded', 'ready', 'failed'], true), 409, 'Wait for current media processing to finish before changing tracks.');
            abort_if($lockedAsset->assetTracks()->where('kind', $data['kind'])->where('language', strtolower($data['language']))->exists(), 409, 'A track of this type and language already exists.');
            abort_if($lockedAsset->assetTracks()->where('kind', $data['kind'])->count() >= 8, 422, 'The maximum number of tracks for this type has been reached.');
            $track = MediaAssetTrack::query()->create([
                'media_asset_id' => $asset->id, 'uploaded_by' => $request->user()->id,
                'kind' => $data['kind'], 'language' => strtolower($data['language']),
                'original_name' => $filename, 'disk' => 'private', 'path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size_bytes' => $file->getSize(),
            ]);
            $audit->record($request->user(), 'media.track.added', MediaAssetTrack::class, $track->id, null, [
                'media_asset_id' => $asset->id, 'kind' => $track->kind, 'language' => $track->language,
            ]);
            $job = null;
            if ($lockedAsset->status === 'ready') {
                $job = MediaProcessingJob::query()->create([
                    'media_asset_id' => $lockedAsset->id, 'type' => 'transcode', 'status' => 'queued',
                    'idempotency_key' => hash('sha256', 'track-update:'.$lockedAsset->id.':'.$track->id),
                ]);
                $lockedAsset->update(['status' => 'queued', 'failure_code' => null]);
            }
            return [$track, $job];
        });
        if ($job) {
            $published = \App\Services\EventPublisher::publish('media.transcode.requested', [
                'media_asset_id' => $asset->id, 'job_id' => $job->id,
            ], 'media-job-'.$job->id);
            if (!$published) {
                $job->update(['status' => 'failed', 'failure_code' => 'queue_unavailable', 'completed_at' => now()]);
                $asset->update(['status' => 'failed', 'failure_code' => 'queue_unavailable']);
            }
        }
        return response()->json(['data' => $this->trackPayload($track)], 201);
    }

    public function deleteTrack(Request $request, MediaAsset $asset, int $track, AdminAuditLogger $audit): JsonResponse
    {
        $record = $asset->assetTracks()->whereKey($track)->firstOrFail();
        $permission = $record->kind === 'subtitle' ? 'media.subtitles.manage' : 'media.audio.manage';
        abort_unless(ExplicitPermission::allows($request->user(), $permission), 403);
        DB::transaction(function () use ($request, $record, $asset, $audit): void {
            $record->delete();
            $audit->record($request->user(), 'media.track.removed', MediaAssetTrack::class, $record->id, [
                'media_asset_id' => $asset->id, 'kind' => $record->kind, 'language' => $record->language,
            ], null);
        });
        return response()->json(['data' => ['deleted' => true]]);
    }

    public function destroy(Request $request, MediaAsset $asset, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'media.delete'), 403);
        DB::transaction(function () use ($request, $asset, $audit): void {
            $locked = MediaAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, ['ready', 'failed', 'uploaded'], true), 409, 'Processing media cannot be removed.');
            $locked->delete(); // Soft-delete the registry record; retain source and rendition files for recovery/audit.
            $audit->record($request->user(), 'media.asset.archived', MediaAsset::class, $locked->id, ['status' => $locked->status], ['status' => 'archived']);
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
        $data = $request->validate(['content_id' => ['required', 'string', 'max:64']]);
        $content = Entertainment::query()->findOrFail($data['content_id']);
        abort_if((bool) $content->status, 409, 'Published content must use the reviewed replacement workflow.');
        DB::transaction(function () use ($request, $asset, $content, $audit): void {
            $lockedAsset = MediaAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lockedAsset->status, ['queued', 'processing', 'ready', 'failed'], true), 409);
            $lockedContent = Entertainment::query()->whereKey($content->getKey())->lockForUpdate()->firstOrFail();
            abort_if((bool) $lockedContent->status, 409, 'Published content must use the reviewed replacement workflow.');
            $before = $lockedContent->media_asset_id;
            $lockedContent->forceFill(['media_asset_id' => $lockedAsset->id])->save();
            $audit->record($request->user(), 'media.asset.attached', MediaAsset::class, $lockedAsset->id,
                ['content_id' => (string) $lockedContent->getKey(), 'media_asset_id' => $before],
                ['content_id' => (string) $lockedContent->getKey(), 'media_asset_id' => $lockedAsset->id]);
        });
        return response()->json(['data' => ['media_asset_id' => $asset->id, 'content_id' => (string) $content->getKey()]]);
    }

    private function ownedActiveSession(Request $request, string $id): MediaUploadSession
    {
        return MediaUploadSession::query()->whereKey($id)->where('user_id', $request->user()->id)
            ->where('status', 'uploading')->where('expires_at', '>', now())->firstOrFail();
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
            'expires_at' => $session->expires_at->toIso8601String(),
        ];
    }

    private function assetPayload(MediaAsset $asset): array
    {
        return [
            'id' => $asset->id, 'original_name' => $asset->original_name, 'mime_type' => $asset->mime_type,
            'extension' => $asset->extension, 'size_bytes' => $asset->size_bytes, 'status' => $asset->status,
            'version' => $asset->version, 'metadata' => $asset->metadata, 'tracks' => $asset->tracks,
            'previous_asset_id' => $asset->replaces_asset_id,
            'failure_code' => $asset->failure_code, 'ready_at' => $asset->ready_at?->toIso8601String(),
            'created_at' => $asset->created_at?->toIso8601String(),
            'processing_jobs' => $asset->relationLoaded('processingJobs') ? $asset->processingJobs->map(fn (MediaProcessingJob $job) => [
                'id' => $job->id, 'type' => $job->type, 'status' => $job->status, 'attempts' => $job->attempts,
                'max_attempts' => $job->max_attempts, 'failure_code' => $job->failure_code,
                'started_at' => $job->started_at?->toIso8601String(), 'completed_at' => $job->completed_at?->toIso8601String(),
            ])->values() : [],
            'asset_tracks' => $asset->relationLoaded('assetTracks') ? $asset->assetTracks->map(fn (MediaAssetTrack $track): array => $this->trackPayload($track))->values() : [],
        ];
    }

    private function trackPayload(MediaAssetTrack $track): array
    {
        return [
            'id' => $track->id, 'kind' => $track->kind, 'language' => $track->language,
            'original_name' => $track->original_name, 'mime_type' => $track->mime_type,
            'size_bytes' => $track->size_bytes, 'created_at' => $track->created_at?->toIso8601String(),
        ];
    }
}
