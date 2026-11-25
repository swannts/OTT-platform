<?php

namespace Modules\Entertainment\Services;

use App\Services\Cache\ApiCacheInvalidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Entertainment\Models\ContinueWatch;
use Modules\Entertainment\Models\Entertainment;
use Modules\Entertainment\Models\Watchlist;
use Modules\Episode\Models\Episode;
use Modules\Video\Models\Video;

class WatchlistAndContinueWatchService
{
    public function __construct(
        private readonly ApiCacheInvalidationService $cacheInvalidation,
    ) {}

    public function resolveActiveProfile(int $userId, Request $request): ?int
    {
        $activeProfileId = getCurrentProfile($userId, $request);
        if (! $activeProfileId || ($request->filled('profile_id') && (string) $request->profile_id !== (string) $activeProfileId)) {
            return null;
        }

        return (int) $activeProfileId;
    }

    /**
     * @return array{ok: true, message: string}|array{ok: false, message: string, status: int}
     */
    public function saveWatchlist(int $userId, int $profileId, Request $request): array
    {
        $entertainmentId = $request->input('entertainment_id');
        $watchlistData = $request->except('user_id');
        $watchlistData['profile_id'] = $profileId;

        if ($request->has('type') && $request->type === 'video') {
            $type = 'video';
            $entertainment = Video::find($entertainmentId);
        } else {
            $entertainment = Entertainment::find($entertainmentId);
            $type = $request->input('entertainment_type');
            if (! $type && $entertainment) {
                $type = $entertainment->type ?? 'movie';
            }
        }

        if (! $entertainment) {
            return ['ok' => false, 'message' => __('movie.entertainment_not_found'), 'status' => 404];
        }

        $watchlistData['user_id'] = $userId;
        $watchlistData['type'] = $type;

        Watchlist::updateOrCreate(
            ['entertainment_id' => $entertainmentId, 'user_id' => $userId, 'profile_id' => $profileId, 'type' => $type],
            $watchlistData
        );

        Artisan::call('cache:clear');
        Cache::flush();
        $this->cacheInvalidation->clearWatchlist();

        return ['ok' => true, 'message' => __('movie.watchlist_add')];
    }

    /**
     * @return array{ok: true, message: string}|array{ok: false, message: string, status: int}
     */
    public function deleteWatchlist(int $userId, int $profileId, Request $request): array
    {
        $idParam = $request->id;
        $ids = is_array($idParam) ? $idParam : explode(',', (string) $idParam);
        $ids = array_filter(array_map('trim', $ids));

        if ($ids === []) {
            return ['ok' => false, 'message' => __('movie.id_required'), 'status' => 400];
        }

        $watchlists = Watchlist::whereIn('id', $ids)
            ->where('user_id', $userId)
            ->where('profile_id', $profileId)
            ->get();

        if ($watchlists->isEmpty() || $request->is_ajax == 1) {
            $query = Watchlist::whereIn('entertainment_id', $ids)
                ->where('user_id', $userId)
                ->where('profile_id', $profileId);

            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            $fallbackWatchlists = $query->get();
            if ($fallbackWatchlists->isNotEmpty()) {
                $watchlists = $watchlists->merge($fallbackWatchlists);
            }
        }

        if ($watchlists->isEmpty()) {
            return ['ok' => false, 'message' => __('movie.watchlist_notfound'), 'status' => 404];
        }

        Watchlist::whereIn('id', $watchlists->pluck('id'))->forceDelete();

        Artisan::call('cache:clear');
        Cache::flush();
        $this->cacheInvalidation->clearWatchlist();

        return ['ok' => true, 'message' => 'Removed from watchlist'];
    }

    /**
     * @return array{ok: true, message: string}|array{ok: false, message: string, status: int}
     */
    public function removeWatchlist(int $userId, int $profileId, int $entertainmentId, ?string $type): array
    {
        $query = Watchlist::query()
            ->where('entertainment_id', $entertainmentId)
            ->where('user_id', $userId)
            ->where('profile_id', $profileId);

        if (! empty($type)) {
            $query->where('type', $type);
        }

        $watchlists = $query->get();
        if ($watchlists->isEmpty()) {
            return ['ok' => false, 'message' => __('movie.watchlist_notfound'), 'status' => 404];
        }

        Watchlist::whereIn('id', $watchlists->pluck('id'))->forceDelete();

        return ['ok' => true, 'message' => __('movie.watchlist_delete')];
    }

    public function saveContinueWatch(object $user, int $profileId, Request $request): void
    {
        $watchData = $request->all();
        $type = $request->entertainment_type === 'episode' ? 'tvshow' : $request->entertainment_type;
        $watchData['total_watched_time'] = $watchData['total_watched_time'] ?? '00:00:01';
        $watchData['watched_time'] = $watchData['watched_time'] ?? '00:00:01';
        $watchData['user_id'] = $user->id;
        $watchData['entertainment_type'] = $type;
        $watchData['profile_id'] = $profileId;

        $result = ContinueWatch::updateOrCreate(
            [
                'entertainment_id' => $request->entertainment_id,
                'user_id' => $user->id,
                'entertainment_type' => $type,
                'profile_id' => $profileId,
                'episode_id' => $request->episode_id,
            ],
            $watchData
        );

        if ($result && intval(setting('continue_watch')) === 0 && $result->watched_time < $result->total_watched_time) {
            $cacheKey = "instant_continue_watch_{$user->id}_{$result->entertainment_id}_{$result->episode_id}_".date('Y-m-d');
            if (! Cache::has($cacheKey)) {
                Cache::put($cacheKey, true, now()->endOfDay());
                $this->notifyContinueWatch($user, $result, (string) $type);
            }
        }

        $this->cacheInvalidation->clearDashboard();
    }

    public function deleteContinueWatch(int $userId, int $profileId, Request $request): void
    {
        $idParam = $request->id;
        if (! empty($idParam)) {
            $ids = is_array($idParam) ? $idParam : explode(',', (string) $idParam);

            $watchlists = ContinueWatch::whereIn('id', $ids)
                ->where('user_id', $userId)
                ->where('profile_id', $profileId)
                ->get();

            if ($watchlists->isNotEmpty()) {
                ContinueWatch::whereIn('id', $watchlists->pluck('id'))->forceDelete();
            } else {
                $type = $request->input('entertainment_type');
                if ($type === 'episode') {
                    $type = 'tvshow';
                }

                $fallbackQuery = ContinueWatch::whereIn('entertainment_id', $ids)
                    ->where('user_id', $userId)
                    ->where('profile_id', $profileId);

                if ($request->filled('episode_id')) {
                    $fallbackQuery->where('episode_id', $request->episode_id);
                }
                if (! empty($type)) {
                    $fallbackQuery->where('entertainment_type', $type);
                }

                $fallbackRecords = $fallbackQuery->get();
                if ($fallbackRecords->isNotEmpty()) {
                    ContinueWatch::whereIn('id', $fallbackRecords->pluck('id'))->forceDelete();
                }
            }
        }

        $this->cacheInvalidation->clearDashboard();
    }

    private function notifyContinueWatch(object $user, ContinueWatch $result, string $type): void
    {
        $content = null;
        $contentType = 'movie';

        if ($type === 'movie') {
            $content = Entertainment::find($result->entertainment_id);
            $contentType = 'movie';
        } elseif ($type === 'tvshow' && $result->episode_id) {
            $content = Episode::find($result->episode_id);
            $contentType = 'episode';
        } elseif ($type === 'video') {
            $content = Video::find($result->entertainment_id);
            $contentType = 'video';
        }

        if (! $content) {
            return;
        }

        try {
            sendNotification([
                'notification_type' => 'continue_watch',
                'id' => $content->id,
                'name' => $content->name,
                'content_type' => $contentType,
                'posterimage' => $content->poster_url,
                'user_id' => $user->id,
                'user_name' => $user->full_name,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Continue-watch notification failed.', ['exception' => $e::class]);
        }
    }
}
