<?php

namespace Modules\Entertainment\Services;

use App\Services\Cache\ApiCacheInvalidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Modules\Entertainment\Models\Entertainment;
use Modules\Entertainment\Models\Watchlist;
use Modules\Video\Models\Video;

class WatchlistService
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

}
