<?php

namespace App\Services\Dashboard;

use App\Models\MobileSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Entertainment\Models\Entertainment;
use Modules\Entertainment\Models\Watchlist;
use Modules\Entertainment\Transformers\EpisodeResource;
use Modules\Entertainment\Transformers\MoviesResource;
use Modules\Entertainment\Transformers\SeasonResource;
use Modules\Entertainment\Transformers\TvshowResource;
use Modules\Episode\Models\Episode;
use Modules\LiveTV\Models\LiveTvChannel;
use Modules\LiveTV\Transformers\LiveTvChannelResource;
use Modules\Season\Models\Season;
use Modules\Video\Models\Video;
use Modules\Video\Transformers\VideoResource;

class DashboardSectionService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPayPerViewUnlockedContent(Request $request): array
    {
        $userId = $request->query('user_id');

        $movies = $this->formatPpvCollection(
            MoviesResource::collection(
                $this->applyProfileRestrictions(
                    Entertainment::where('movie_access', 'pay-per-view')
                        ->where('type', 'movie')
                        ->where('status', 1)
                        ->whereNull('deleted_at'),
                    $request
                )->get()
            ),
            $userId,
            $request
        );

        $tvshows = $this->formatPpvCollection(
            TvshowResource::collection(
                Entertainment::where('movie_access', 'pay-per-view')
                    ->where('type', 'tvshow')
                    ->where('status', 1)
                    ->whereNull('deleted_at')
                    ->get()
            ),
            $userId,
            $request
        );

        $videos = $this->formatPpvCollection(
            VideoResource::collection(
                $this->applyProfileRestrictions(
                    Video::where('access', 'pay-per-view')
                        ->where('status', 1)
                        ->whereNull('deleted_at'),
                    $request
                )->get()
            ),
            $userId,
            $request
        );

        $seasons = $this->formatPpvCollection(
            SeasonResource::collection(
                Season::where('access', 'pay-per-view')
                    ->where('status', 1)
                    ->whereNull('deleted_at')
                    ->get()
            ),
            $userId,
            $request
        );

        $episodes = $this->formatPpvCollection(
            EpisodeResource::collection(
                $this->applyProfileRestrictions(
                    Episode::where('access', 'pay-per-view')
                        ->where('status', 1)
                        ->whereNull('deleted_at'),
                    $request
                )->get()
            ),
            $userId,
            $request
        );

        return array_merge($movies, $tvshows, $videos, $seasons, $episodes);
    }

    public function getSliderData(string $type, int|string $typeId, mixed $userId = null, ?Request $request = null): mixed
    {
        switch ($type) {
            case 'movie':
            case 'tvshow':
                $query = $this->applyProfileRestrictions(Entertainment::with('plan')->select('*'), $request);
                $entertainment = $query->where('id', $typeId)->first();
                if (! $entertainment) {
                    return null;
                }

                $entertainment['is_watch_list'] = Watchlist::where('entertainment_id', $typeId)
                    ->where('user_id', $userId)
                    ->where('profile_id', $request?->profile_id)
                    ->exists();
                $entertainment->user_id = $userId;

                return $type === 'movie'
                    ? new MoviesResource($entertainment)
                    : new TvshowResource($entertainment);

            case 'livetv':
                $livetv = LiveTvChannel::find($typeId);

                return $livetv ? new LiveTvChannelResource($livetv) : null;

            case 'video':
                $video = $this->applyProfileRestrictions(Video::select('*'), $request)
                    ->where('id', $typeId)
                    ->first();
                if (! $video) {
                    return null;
                }

                $video->user_id = $userId;

                return new VideoResource($video);

            default:
                return null;
        }
    }

    /**
     * @param  array<string, string>  $slugsWithDefaults
     * @return array<string, string>
     */
    public function getLocalizedSectionNames(array $slugsWithDefaults): array
    {
        if (app()->getLocale() === 'bn') {
            $bengaliDefaults = [
                'latest-movies' => 'এখন ট্রেন্ডিং',
                'top-10' => 'আজকের শীর্ষ ১০',
                'enjoy-in-your-native-tongue' => 'আপনার মাতৃভাষায় উপভোগ করুন',
                'popular-movies' => 'জনপ্রিয় সিনেমা',
                'top-channels' => 'সরাসরি সম্প্রচার',
                'your-favorite-personality' => 'সম্মানিত আলেম ও ব্যক্তিত্ব',
                '500-free-movies' => 'ফ্রি সিনেমা',
                'popular-tvshows' => 'জনপ্রিয় টিভি শো ও সিরিজ',
                'genre' => 'ক্যাটাগরি ও ধরন',
                'popular-videos' => 'জনপ্রিয় ভিডিও ও রিমাইন্ডার',
                'continue-watching' => 'দেখা চালিয়ে যান',
                'pay-per-view' => 'পে-পার-ভিউ এক্সক্লুসিভ',
                'trending-now' => 'এখন ট্রেন্ডিং',
            ];

            $sectionNames = [];
            foreach ($slugsWithDefaults as $slug => $default) {
                $sectionNames[$slug] = $bengaliDefaults[$slug] ?? $default;
            }

            return $sectionNames;
        }

        $settings = MobileSetting::whereIn('slug', array_keys($slugsWithDefaults))->pluck('name', 'slug');
        $sectionNames = [];
        foreach ($slugsWithDefaults as $slug => $default) {
            $sectionNames[$slug] = $settings[$slug] ?? $default;
        }

        return $sectionNames;
    }

    private function applyProfileRestrictions(Builder $query, ?Request $request): Builder
    {
        $hasExplicit = $request ? $request->has('is_restricted') : request()->has('is_restricted');
        $explicitValue = $request ? $request->is_restricted : request()->is_restricted;

        if ($hasExplicit && $explicitValue !== null) {
            $query->where('is_restricted', $explicitValue);
        }

        if (! empty(getCurrentProfileSession('is_child_profile')) && getCurrentProfileSession('is_child_profile') != 0) {
            $query->where('is_restricted', 0);
        }

        return $query;
    }

    /**
     * @param  mixed  $resourceCollection
     * @return array<int, array<string, mixed>>
     */
    private function formatPpvCollection($resourceCollection, mixed $userId, Request $request): array
    {
        return $resourceCollection->map(function ($item) use ($userId) {
            $item->user_id = $userId;

            return $item;
        })->toArray($request);
    }
}
