<?php

namespace App\Services\Dashboard;

use App\Models\MobileSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Entertainment\Models\Entertainment;
use Modules\Entertainment\Models\Watchlist;
use Modules\Entertainment\Transformers\EpisodeResource;
use Modules\Entertainment\Transformers\MoviesResource;
use Modules\Entertainment\Transformers\MoviesResourceV3;
use Modules\Entertainment\Transformers\SeasonResource;
use Modules\Entertainment\Transformers\TvshowResource;
use Modules\Entertainment\Transformers\TvshowResourceV3;
use Modules\Episode\Models\Episode;
use Modules\Frontend\Models\PayPerView;
use Modules\LiveTV\Models\LiveTvChannel;
use Modules\LiveTV\Transformers\LiveTvChannelResource;
use Modules\LiveTV\Transformers\LiveTvChannelResourceV3;
use Modules\Season\Models\Season;
use Modules\Subscriptions\Models\Subscription;
use Modules\Video\Models\Video;
use Modules\Video\Transformers\VideoResource;
use Modules\Video\Transformers\VideoResourceV3;

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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOtherSectionData(Request $request): array
    {
        $user_id = $request->user_id ?? null;
        $device_type = getDeviceType($request);

        $userPlanId = 0;
        $deviceTypeResponse = ['isDeviceSupported' => false];
        $purchasedIds = [];

        if ($user_id) {
            $deviceTypeResponse = cache()->remember(
                "device_support_{$user_id}_{$device_type}",
                600,
                function () use ($user_id, $device_type) {
                    $getDeviceTypeData = Subscription::checkPlanSupportDevice($user_id, $device_type);

                    return json_decode($getDeviceTypeData->getContent(), true);
                }
            );
            $userLevel = Subscription::select('plan_id')->where(['user_id' => $user_id, 'status' => 'active'])->latest()->first();
            $userPlanId = $userLevel->plan_id ?? 0;

            $purchasedIds = PayPerView::where('user_id', $user_id)
                ->pluck('movie_id')
                ->toArray();
        }

        $sections = MobileSetting::select('id', 'slug', 'type', 'value', 'name')
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->take(20)
            ->get();

        if ($sections->isEmpty()) {
            return [];
        }

        $response = [];

        foreach ($sections as $section) {
            $ids = json_decode($section->value, true);
            if (empty($ids) || ! is_array($ids)) {
                continue;
            }

            $ids = array_slice($ids, 0, 50);

            switch ($section->type) {
                case 'movie':
                    $data = Entertainment::with([
                        'plan:id,level',
                        'genresdata:id,name',
                    ])
                        ->whereIn('id', $ids)
                        ->select(['id', 'name', 'slug', 'type', 'video_upload_type', 'release_date', 'trailer_url', 'trailer_url_type', 'is_restricted', 'imdb_rating', 'poster_url', 'thumbnail_url', 'poster_tv_url', 'blurhash', 'trailer_url as base_url', 'trailer_url', 'video_url_input', 'movie_access', 'plan_id'])
                        ->where('type', 'movie')
                        ->released()
                        ->where('status', 1)
                        ->whereNull('deleted_at')
                        ->get();

                    $data->each(function ($movie) use ($user_id, $userPlanId, $deviceTypeResponse, $device_type, $purchasedIds) {
                        $movie->user_id = $user_id;
                        $movie->isDeviceSupported = $deviceTypeResponse['isDeviceSupported'] == true ? 1 : 0;
                        $movie->poster_image = $device_type == 'tv' ? $movie->poster_tv_url : $movie->poster_url ?? null;
                        $movie->trailer_url = $movie->trailer_url_type == 'Local' ? setBaseUrlWithFileName($movie->trailer_url, 'video', $movie->type) : $movie->trailer_url;
                        $movie->access = $movie->movie_access;
                        setContentAccess($movie, $user_id, $userPlanId, $purchasedIds ?? []);
                    });

                    $data = MoviesResourceV3::collection($data)->toArray($request);
                    break;

                case 'tvshow':
                    $data = Entertainment::with([
                        'plan:id,level',
                        'genresdata:id,name',
                    ])
                        ->whereIn('id', $ids)
                        ->select(['id', 'name', 'slug', 'type', 'video_upload_type', 'release_date', 'trailer_url', 'trailer_url_type', 'is_restricted', 'imdb_rating', 'poster_url', 'thumbnail_url', 'poster_tv_url', 'blurhash', 'trailer_url as base_url', 'trailer_url', 'video_url_input', 'movie_access', 'plan_id'])
                        ->where('type', 'tvshow')
                        ->released()
                        ->where('status', 1)
                        ->whereNull('deleted_at')
                        ->get();

                    if ($user_id) {
                        $data->each(function ($tvshow) use ($user_id, $userPlanId, $deviceTypeResponse, $device_type, $purchasedIds) {
                            $tvshow->user_id = $user_id;
                            $tvshow->isDeviceSupported = $deviceTypeResponse['isDeviceSupported'] == true ? 1 : 0;
                            $tvshow->poster_image = $device_type == 'tv' ? $tvshow->poster_tv_url : $tvshow->poster_url ?? null;
                            $tvshow->trailer_url = $tvshow->trailer_url_type == 'Local' ? setBaseUrlWithFileName($tvshow->trailer_url, 'video', $tvshow->type) : $tvshow->trailer_url;
                            $tvshow->access = $tvshow->movie_access;
                            setContentAccess($tvshow, $user_id, $userPlanId, $purchasedIds ?? []);
                        });
                    } else {
                        $data->each(function ($tvshow) use ($device_type) {
                            $tvshow->isDeviceSupported = 0;
                            $tvshow->poster_image = $device_type == 'tv' ? $tvshow->poster_tv_url : $tvshow->poster_url ?? null;
                            $tvshow->trailer_url = $tvshow->trailer_url_type == 'Local' ? setBaseUrlWithFileName($tvshow->trailer_url, 'video', $tvshow->type) : $tvshow->trailer_url;
                            $tvshow->access = $tvshow->movie_access;
                            setContentAccess($tvshow, null, null, []);
                        });
                    }

                    $data = TvshowResourceV3::collection($data)->toArray($request);
                    break;

                case 'video':
                    $data = Video::with([
                        'plan:id,level',
                    ])
                        ->whereIn('id', $ids)
                        ->select(['id', 'name', 'slug', 'type', 'video_upload_type', 'release_date', 'trailer_url', 'trailer_url_type', 'is_restricted', 'IMDb_rating', 'poster_url', 'thumbnail_url', 'poster_tv_url', 'trailer_url as base_url', 'trailer_url', 'video_url_input', 'access', 'plan_id'])
                        ->whereDate('release_date', '<=', now())
                        ->where('status', 1)
                        ->whereNull('deleted_at')
                        ->get();

                    if ($user_id) {
                        $data->each(function ($video) use ($user_id, $userPlanId, $deviceTypeResponse, $device_type, $purchasedIds) {
                            $video->user_id = $user_id;
                            $video->isDeviceSupported = $deviceTypeResponse['isDeviceSupported'] == true ? 1 : 0;
                            setContentAccess($video, $user_id, $userPlanId, $purchasedIds ?? []);
                            $video->poster_image = $device_type == 'tv' ? $video->poster_tv_url : $video->poster_url ?? null;
                        });
                    } else {
                        $data->each(function ($video) use ($device_type) {
                            $video->isDeviceSupported = 0;
                            $video->poster_image = $device_type == 'tv' ? $video->poster_tv_url : $video->poster_url ?? null;
                            $video->access = $video->access;
                            setContentAccess($video, null, null, []);
                        });
                    }

                    $data = VideoResourceV3::collection($data)->toArray($request);
                    break;

                case 'channel':
                    $data = LiveTvChannel::with([
                        'plan:id,level',
                        'TvCategory:id,name',
                    ])
                        ->whereIn('id', $ids)
                        ->select(['id', 'name', 'slug', 'plan_id', 'poster_url', 'thumb_url', 'poster_tv_url', 'trailer_url', 'access', 'category_id'])
                        ->where('status', 1)
                        ->whereNull('deleted_at')
                        ->get();

                    $data->each(function ($channel) use ($user_id, $userPlanId, $deviceTypeResponse, $device_type, $purchasedIds) {
                        $channel->user_id = $user_id;
                        $channel->isDeviceSupported = $deviceTypeResponse['isDeviceSupported'] == true ? 1 : 0;
                        $channel->poster_image = $device_type == 'tv'
                            ? setBaseUrlWithFileName($channel->poster_tv_url, 'image', 'livetv')
                            : setBaseUrlWithFileName($channel->poster_url, 'image', 'livetv');
                        $channel->access = $channel->access;
                        setContentAccess($channel, $user_id, $userPlanId, $purchasedIds ?? []);
                    });

                    $data = LiveTvChannelResourceV3::collection($data)->toArray($request);
                    break;

                default:
                    continue 2;
            }

            if (! empty($data)) {
                $response[] = [
                    'slug' => $section->slug,
                    'name' => $section->name,
                    'type' => $section->type,
                    'data' => $data,
                ];
            }
        }

        return $response;
    }
}
