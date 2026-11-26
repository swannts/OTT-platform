<?php

namespace App\Support\Staging;

use App\Models\AdminContentReviewSubmission;
use App\Models\User;
use App\Services\Content\AdminContentPublicationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Entertainment\Models\Entertainment;
use RuntimeException;

final class StagingPlayableContentPublisher
{
    /** @return array{published_titles:int,ready_media_assets:int} */
    public function publish(StagingGenerationContext $context, AdminContentPublicationService $publication): array
    {
        $staff = DB::table('staging_dataset_records as owners')
            ->join('users', 'users.email', '=', 'owners.entity_key')
            ->join('model_has_roles as assignments', function ($join): void {
                $join->on('assignments.model_id', '=', 'users.id')->where('assignments.model_type', '=', User::class);
            })
            ->join('roles', 'roles.id', '=', 'assignments.role_id')
            ->where('owners.dataset_id', $context->datasetId)->where('owners.entity_type', 'staff_user')
            ->where('roles.name', 'content_manager')->where('users.status', 1)->orderBy('users.id')
            ->first(['users.id']);

        $referenceDate = CarbonImmutable::parse($context->referenceDate)->endOfDay();
        $windowStart = $referenceDate->startOfDay();
        $linked = DB::table('staging_dataset_records as owners')
            ->join('entertainments', 'entertainments.id', '=', 'owners.record_id')
            ->join('media_assets', 'media_assets.id', '=', 'entertainments.media_asset_id')
            ->join('content_license_windows as licenses', function ($join): void {
                $join->on('licenses.content_id', '=', 'entertainments.id')->where('licenses.content_type', '=', 'entertainment');
            })
            ->join('content_license_territories as territories', function ($join): void {
                $join->on('territories.content_id', '=', 'entertainments.id')
                    ->where('territories.content_type', '=', 'entertainment')->where('territories.country_code', '=', 'BD');
            })
            ->where('owners.dataset_id', $context->datasetId)->where('owners.entity_type', 'entertainment')
            ->where('entertainments.type', 'movie')->whereNull('entertainments.deleted_at')
            ->where(function ($query) use ($referenceDate): void {
                $query->whereNull('entertainments.release_date')->orWhere('entertainments.release_date', '<=', $referenceDate->toDateString());
            })
            ->where('media_assets.status', 'ready')->where('media_assets.disk', 'private')
            ->where('entertainments.movie_access', 'free')
            ->where(function ($query): void {
                $query->whereNotNull('entertainments.poster_url')->where('entertainments.poster_url', '!=', '')
                    ->orWhere(function ($fallback): void {
                        $fallback->whereNull('entertainments.poster_url')->orWhere('entertainments.poster_url', '');
                        $fallback->whereNotNull('entertainments.thumbnail_url')->where('entertainments.thumbnail_url', '!=', '');
                    });
            })
            ->where(function ($query) use ($windowStart): void {
                $query->whereNull('licenses.starts_at')->orWhere('licenses.starts_at', '<=', $windowStart);
            })
            ->where(function ($query) use ($referenceDate): void {
                $query->whereNull('licenses.ends_at')->orWhere('licenses.ends_at', '>=', $referenceDate);
            })
            ->orderBy('entertainments.release_date')->orderBy('entertainments.id')
            ->get(['entertainments.id', 'entertainments.media_asset_id']);

        if ($linked->isNotEmpty() && ! $staff) {
            throw new RuntimeException('Publishing staging playback titles requires an owned content manager.');
        }

        $published = 0;
        foreach ($linked as $candidate) {
            $item = Entertainment::query()->find($candidate->id);
            if (! $item || (int) $item->media_asset_id !== (int) $candidate->media_asset_id) {
                throw new RuntimeException("Staging playback title {$candidate->id} lost its media asset link.");
            }
            $latest = AdminContentReviewSubmission::query()->where('content_type', 'entertainment')
                ->where('content_id', (string) $candidate->id)->latest('revision')->first();
            if (! $latest) throw new RuntimeException("Staging playback title {$candidate->id} has no content review record.");
            if ($latest->status === 'published' && (int) $item->status === 1) {
                $published++;
                continue;
            }
            if ($latest->status !== 'approved' || (int) $item->status !== 0) continue;

            $publication->publish(User::query()->findOrFail($staff->id), (int) $latest->id);
            $published++;
        }

        return [
            'published_titles' => $published,
            'ready_media_assets' => DB::table('staging_dataset_records as owners')
                ->join('media_assets', 'media_assets.id', '=', 'owners.record_id')
                ->where('owners.dataset_id', $context->datasetId)->where('owners.entity_type', 'media_asset')->where('media_assets.status', 'ready')->count(),
        ];
    }
}
