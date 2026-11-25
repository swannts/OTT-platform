<?php

namespace Modules\Entertainment\Services;

use App\Services\Cache\ApiCacheInvalidationService;
use Illuminate\Http\Request;
use Modules\Entertainment\Models\Entertainment;
use Modules\Entertainment\Models\EntertainmentDownload;
use Modules\Entertainment\Models\EntertainmentView;
use Modules\Entertainment\Models\UserReminder;

class ContentEngagementService
{
    public function __construct(
        private readonly ApiCacheInvalidationService $cacheInvalidation,
    ) {}

    public function saveDownload(int $userId, Request $request): string
    {
        $download = EntertainmentDownload::where('entertainment_id', $request->entertainment_id)
            ->where('user_id', $userId)
            ->where('entertainment_type', $request->entertainment_type)
            ->first();

        if ($download) {
            return __('movie.already_download');
        }

        $downloadData = $request->all();
        $downloadData['user_id'] = $userId;
        EntertainmentDownload::create($downloadData);

        if ($request->entertainment_type === 'movie') {
            $this->cacheInvalidation->clearRelated(['movie_'.$request->entertainment_id], 'entertainment');
        } elseif ($request->entertainment_type === 'episode') {
            $this->cacheInvalidation->clearRelated('episode_'.$request->entertainment_id, 'episode');
        }

        return __('movie.movie_download');
    }

    public function deleteDownload(int $userId, string $idParam): bool
    {
        $ids = array_filter(array_map('trim', explode(',', $idParam)));
        if ($ids === []) {
            return false;
        }

        $deleted = EntertainmentDownload::whereIn('id', $ids)
            ->where('user_id', $userId)
            ->forceDelete();

        return $deleted !== null;
    }

    public function saveReminder(int $userId, Request $request): string
    {
        $reminderData = $request->all();
        $reminderData['user_id'] = $userId;

        $profileId = $request->has('profile_id') && $request->profile_id
            ? $request->profile_id
            : getCurrentProfile($userId, $request);
        $reminderData['profile_id'] = $profileId;

        $entertainment = $request->entertainment_id
            ? Entertainment::where('id', $request->entertainment_id)->first()
            : null;
        if ($entertainment !== null) {
            $reminderData['release_date'] = $request->release_date ?? $entertainment->release_date;
        }

        $reminder = UserReminder::updateOrCreate(
            ['entertainment_id' => $request->entertainment_id, 'user_id' => $userId, 'profile_id' => $profileId],
            $reminderData
        );

        return $reminder->wasRecentlyCreated ? __('movie.reminder_add') : __('movie.reminder_update');
    }

    public function deleteReminder(int $userId, mixed $idParam, bool $isAjax): bool
    {
        $ids = $isAjax ? (array) $idParam : explode(',', (string) $idParam);
        $deleted = UserReminder::whereIn('entertainment_id', $ids)
            ->where('user_id', $userId)
            ->forceDelete();

        return $deleted !== null;
    }

    public function saveView(int $userId, Request $request): string
    {
        $existing = EntertainmentView::where('entertainment_id', $request->entertainment_id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            return __('movie.already_added');
        }

        $data = $request->all();
        $data['user_id'] = $userId;
        EntertainmentView::create($data);

        return __('movie.view_add');
    }
}
