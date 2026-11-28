<?php

namespace App\Services\Content;

use App\Models\AdminAuditLog;
use App\Models\AdminContentReviewSubmission;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\Media\MediaReadinessService;
use App\Support\Content\AdminContentSnapshot;
use App\Support\Rbac\ExplicitPermission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Entertainment\Models\Entertainment;
use Modules\Video\Models\Video;

final class AdminContentPublicationService
{
    /** @return array{0:AdminContentReviewSubmission,1:Model} */
    public function publish(User $publisher, int $submissionId, ?string $requestId = null): array
    {
        abort_unless(ExplicitPermission::allows($publisher, 'content.publish'), 403);

        return DB::transaction(function () use ($publisher, $submissionId, $requestId): array {
            $record = AdminContentReviewSubmission::query()->lockForUpdate()->findOrFail($submissionId);
            abort_if($record->status !== 'approved', 409, 'Only approved submissions can be published.');
            abort_unless(in_array($record->content_type, ['entertainment', 'video'], true), 409, 'This content type cannot be published through this workflow.');
            $model = $record->content_type === 'video' ? Video::class : Entertainment::class;
            $item = $model::query()->lockForUpdate()->findOrFail($record->content_id);
            $snapshotData = $record->content_type === 'video'
                ? AdminContentSnapshot::forVideo($item)
                : AdminContentSnapshot::forEntertainment($item);
            $snapshot = json_encode($snapshotData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            abort_unless(hash_equals($record->snapshot_hash, hash('sha256', $snapshot)), 409, 'Content changed after approval; submit a new revision for review.');
            $poster = $item->getAttribute('poster_url') ?: $item->getAttribute('thumbnail_url') ?: $item->getAttribute('poster_tv_url');
            abort_unless($item->getAttribute('name') && ($record->content_type === 'video' ? $item->getAttribute('type') === 'video' : $item->getAttribute('type')) && $poster, 422, 'Content requires a title, type, and artwork before publication.');
            if ($record->content_type === 'video') {
                abort_unless($item->getAttribute('media_asset_id') || $item->getAttribute('video_url_input') || $item->getAttribute('bunny_video_url'), 422, 'Attach a processed media asset or configure a playback source before publishing this video.');
            }
            if ($item->getAttribute('media_asset_id')) {
                $asset = MediaAsset::query()->find($item->getAttribute('media_asset_id'));
                abort_unless($asset && app(MediaReadinessService::class)->inspect($asset)['ready'], 409, 'The attached media asset is not fully processed and ready for publication.');
            }
            $item->forceFill(['status' => true])->save();
            $record->forceFill(['status' => 'published'])->save();
            AdminAuditLog::query()->create([
                'actor_id' => $publisher->getKey(), 'action' => 'content.published',
                'resource_type' => 'content_review_submission', 'resource_id' => (string) $record->id,
                'request_id' => $requestId, 'before_values' => ['status' => 'approved'],
                'after_values' => ['status' => 'published', 'revision' => $record->revision],
                'outcome' => 'success', 'created_at' => now('UTC'),
            ]);
            return [$record, $item];
        });
    }
}
