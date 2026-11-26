<?php

namespace Modules\Entertainment\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Artisan;
use Illuminate\Http\Request;
use Modules\Entertainment\Models\Watchlist;
use Modules\Entertainment\Services\WatchlistService;
use Modules\Entertainment\Services\WatchProgressService;
use Modules\Entertainment\Transformers\WatchlistResource;
use Modules\Entertainment\Transformers\WatchlistResourceV3;
use Modules\Entertainment\Models\ContinueWatch;
use Modules\Entertainment\Transformers\ContinueWatchResource;
use Modules\Entertainment\Models\Entertainment;
use Modules\Video\Models\Video;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Entertainment\Transformers\ContinueWatchResourceV3;
use Modules\Subscriptions\Models\Subscription;
use Modules\Frontend\Models\PayPerView;

class WatchlistController extends Controller
{
    public function __construct(
        private readonly WatchlistService $watchlistService,
        private readonly WatchProgressService $watchProgressService,
    ) {}

    public function watchList(Request $request)
    {
        $user_id = auth()->user()->id;
        $perPage = $request->input('per_page', 10);
        $type = $request->input('type', 'all');

        $profile_id = $this->resolveActiveProfile($user_id, $request);
        if (!$profile_id) return ApiResponse::error('Select an active viewing profile.', 403);
        
        
        $query = Watchlist::where('user_id', $user_id)->whereNull('deleted_at');
        
        if ($profile_id) {
            $query->where('profile_id', $profile_id);
        }

        if ($type === 'movie') {
            $query->where('type', 'movie')
                  ->whereHas('entertainment', function ($subQuery) {
                      $subQuery->where('status', 1)->where('deleted_at', null);
                  });
        } elseif ($type === 'tvshow') {
            $query->where('type', 'tvshow')
                  ->whereHas('entertainment', function ($subQuery) {
                      $subQuery->where('status', 1)->where('deleted_at', null);
                  });
        } elseif ($type === 'video') {
            $query->where('type', 'video')
                  ->whereHas('video', function ($subQuery) {
                      $subQuery->where('status', 1)->where('deleted_at', null);
                  });
        } else {
            $query->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereIn('type', ['movie', 'tvshow']) // Entertainment types
                      ->whereHas('entertainment', function ($subQuery) {
                          $subQuery->where('status', 1)->where('deleted_at', null);
                      });
                })
                ->orWhere(function ($q) {
                    $q->where('type', 'video') // Video type
                      ->whereHas('video', function ($subQuery) {
                          $subQuery->where('status', 1)->where('deleted_at', null);
                      });
                });
            });
        }


        $watchList = $query->with('entertainment', 'video') //add karvanu
        ->orderBy('updated_at', 'desc')
        ->paginate($perPage);

        $responseData = WatchlistResource::collection($watchList);

        if ($request->has('is_ajax') && $request->input('is_ajax') == 1) {
            $html = '';

            foreach($responseData->toArray($request) as $watchData) {
                $userId = auth()->id();
                if ($userId) {
                    $isInWatchList = Watchlist::where('entertainment_id', $watchData['entertainment_id'])
                                               ->where('user_id', $userId);
                    
                    if ($profile_id) {
                        $isInWatchList->where('profile_id', $profile_id);
                    } else {
                        $isInWatchList->whereNull('profile_id');
                    }
                    
                    $watchData['is_watch_list'] = $isInWatchList->exists();

                    if ($watchData['entertainment_type'] === 'video') {
                        $videoData = Video::find($watchData['entertainment_id']);
                        if ($videoData) {
                            $watchData['name'] = $videoData->name;
                            $watchData['description'] = $videoData->description;
                            $watchData['duration'] = $videoData->duration;
                            $watchData['poster_image'] = setBaseUrlWithFileName($videoData->poster_url,'image','video');
                            $watchData['access'] = $videoData->access;
                            $watchData['id'] = $watchData['entertainment_id'];
                            $watchData['slug'] = $videoData->slug;
                        }
                        $html .= view('frontend::components.card.card_video', ['values' => [$watchData]])->render();
                    } else {
                        $watchData['id'] = $watchData['entertainment_id'];
                        $html .= view('frontend::components.card.card_movie', ['values' => [$watchData]])->render();
                    }
                }
            }

            return ApiResponse::success(
                null,
                __('movie.watch_list'),
                200,
                ['html' => $html, 'hasMore' => method_exists($watchList, 'hasMorePages') ? $watchList->hasMorePages() : false]
            );
        }

        return ApiResponse::success($responseData, __('movie.watch_list'), 200);
    }


    public function watchListV3(Request $request)
    {
        $user_id = $request->user()->id;
        $device_type = getDeviceType($request);

        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $type = $request->input('type'); // optional: movie, tvshow, video, all

        $profile_id = $this->resolveActiveProfile($user_id, $request);
        if (!$profile_id) return ApiResponse::error('Select an active viewing profile.', 403);

        // Create unique cache key based on all relevant parameters including type and page
        $cacheKey = 'watchlist_v3_'. md5(json_encode([
            'user_id' => $user_id,
            'profile_id' => $profile_id,
            'per_page' => $perPage,
            'page' => $page,
            'type' => $type,
            'device_type' => $device_type,
            'is_ajax' => $request->input('is_ajax', 0)
        ]));

        $cacheVersionKey = 'watchlist_cache_version';
        $cacheVersion = \Illuminate\Support\Facades\Cache::get($cacheVersionKey, 0);
        $versionedCacheKey = $cacheKey . '_v' . $cacheVersion;

        // Use Redis caching with 5 minutes TTL
        $cachedResponse = cacheApiResponse($versionedCacheKey, 300, function () use ($request, $user_id, $profile_id, $perPage, $device_type, $type) {
            $query = Watchlist::with(['entertainment.season', 'video'])
                ->where('user_id', $user_id)
                ->where('profile_id', $profile_id)
                ->whereNull('deleted_at');

            // Handle bifurcation according to type
            if ($type && $type !== 'all') {
                $query->where('type', $type);
            }

            $watchList = $query->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereIn('type', ['movie', 'tvshow']) // Entertainment types
                      ->whereHas('entertainment', function ($subQuery) {
                          $subQuery->where('status', 1);
                      });
                })
                ->orWhere(function ($q) {
                    $q->where('type', 'video') // Video type
                      ->whereHas('video', function ($subQuery) {
                          $subQuery->where('status', 1);
                      });
                });
            })
            ->orderBy('updated_at', 'desc')
            ->paginate($perPage);

            $userId = $request->user()->id;
            $getDeviceTypeData = Subscription::checkPlanSupportDevice($userId, $device_type);
            $deviceTypeResponse = json_decode($getDeviceTypeData->getContent(), true);
            $userLevel = Subscription::select('plan_id')->where(['user_id' => $user_id, 'status' => 'active'])->latest()->first();
            $userPlanId = $userLevel->plan_id ?? 0;

            // Optimized: Fetch all purchased IDs grouped by type for setContentAccess
            $purchasedIds = PayPerView::where('user_id', $user_id)
                ->where(function($q) {
                    $q->whereNull('view_expiry_date')
                      ->orWhere('view_expiry_date', '>', now());
                })
                ->get()
                ->groupBy('type')
                ->map(function($items) {
                    return $items->pluck('movie_id')->toArray();
                })
                ->toArray();

            $watchList->getCollection()->transform(function($item) use ( $device_type, $deviceTypeResponse, $userPlanId, $profile_id, $user_id, $purchasedIds) {
                $entertainment = null;
                $video = null;

                if($item->type === 'movie' || $item->type === 'tvshow'){
                    $entertainment = $item->entertainment;
                    if ($entertainment) {
                        $item->access = $entertainment->movie_access;
                        $item->plan_id = $entertainment->plan_id ?? 0;
                        $item->imdb_rating = $entertainment->IMDb_rating ?? $entertainment->imdb_rating ?? null;
                    }
                } else {
                    $video = $item->video;
                    if ($video) {
                        $item->access = $video->access;
                        $item->plan_id = $video->plan_id ?? 0;
                        $item->imdb_rating = $video->IMDb_rating ?? $video->imdb_rating ?? null;
                    }
                }

                if ($item->type === 'video') {
                    // video type
                    if ($video) {
                        $posterUrl = $device_type == 'tv'
                            ? setBaseUrlWithFileName($video->poster_tv_url ,'image', $item->type)
                            : setBaseUrlWithFileName($video->poster_url, 'image', $item->type);
                        $poster_tv_url = setBaseUrlWithFileName($video->poster_tv_url ,'image', $item->type);
                    }
                } else {
                    // entertainment type
                    if ($entertainment) {
                        $posterUrl = $device_type == 'tv'
                            ? setBaseUrlWithFileName($entertainment->poster_tv_url, 'image', $item->type)
                            : setBaseUrlWithFileName($entertainment->poster_url, 'image', $item->type);
                        $poster_tv_url = setBaseUrlWithFileName($entertainment->poster_tv_url, 'image', $item->type);
                    }
                }

                $item->posterImage = $posterUrl ?? null;
                $item->poster_tv_image = $poster_tv_url ?? null;
            
                $item->isDeviceSupported = $deviceTypeResponse['isDeviceSupported'] == true ? 1 : 0;

                // Set id for setContentAccess function
                $item->id = $item->entertainment_id;

                if ($item->type === 'tvshow' && $entertainment) {
                    $item->season_data = $entertainment->season->map(function ($season) {
                        return [
                            'id'            => $season->id,
                            'name'          => $season->name,
                            'season_id'     => $season->id,
                            'total_episode' => $season->episodes()->count(),
                        ];
                    })->values();
                } elseif ($item->type === 'episode' && isset($item->seasondata)) {
                    $item->season_data = [
                        'id'            => $item->seasondata->id,
                        'name'          => $item->seasondata->name,
                        'season_id'     => $item->seasondata->id,
                        'total_episode' => $item->seasondata->episodes()->count(),
                    ];
                } else {
                    $item->season_data = null;
                }
                
                $itemArray = $item->toArray();
                $itemArray = setContentAccess($itemArray, $user_id, $userPlanId, $purchasedIds);

                $item->has_content_access = $itemArray['has_content_access'] ?? 0;
                $item->required_plan_level = $itemArray['required_plan_level'] ?? 0;
               
                return $item;
            });

            $responseData = WatchlistResourceV3::collection($watchList);

            if ($request->has('is_ajax') && $request->input('is_ajax') == 1) {
                $html = '';

                foreach($responseData->toArray($request) as $watchData) {
                    $userId = auth()->id();
                    if ($userId) {
                        $profile_id = $this->resolveActiveProfile($userId, $request);
                        $contentType = $watchData['entertainment_type'] ?? 'movie';
                        $isInWatchList = Watchlist::where('entertainment_id', $watchData['entertainment_id'])
                                                   ->where('user_id', $userId)
                                                   ->where('type', $contentType)
                                                   ->where('profile_id', $profile_id)
                                                   ->exists();
                        $watchData['is_watch_list'] = $isInWatchList;
                    }

                        if ($watchData['entertainment_type'] === 'video') {

                            $videoData = Video::find($watchData['entertainment_id']);
                            if ($videoData) {

                                $watchData['name'] = $videoData->name;
                                $watchData['description'] = $videoData->description;
                                $watchData['duration'] = $videoData->duration;
                                $watchData['poster_image'] = setBaseUrlWithFileName( $videoData->poster_url);
                                $watchData['access']=$videoData->access;
                                $watchData['id']=$watchData['entertainment_id'];

                            }
                            $html .= view('frontend::components.card.card_video', ['data' => $watchData])->render();

                        }
                        else{
                            $watchData['id']=$watchData['entertainment_id'];
                            $html .= view('frontend::components.card.card_entertainment', ['value' => $watchData])->render();

                        }
                    }

                $hasMore = $watchList->hasMorePages();

                return [
                    'status' => true,
                    'html' => $html,
                    'message' => __('movie.watch_list'),
                    'hasMore' => $hasMore,
                ];
            }

            return [
                'status' => true,
                'data' => $responseData,
                'message' => __('movie.watch_list'),
            ];
        });

        // Return cached response
        return ApiResponse::custom($cachedResponse['data'], 200);
    }


    public function saveWatchList(Request $request)
    {
        $user = auth()->user();
        $profileId = $this->resolveActiveProfile($user->id, $request);
        if (! $profileId) {
            return ApiResponse::error('Select an active viewing profile.', 403);
        }

        $result = $this->watchlistService->saveWatchlist($user->id, $profileId, $request);
        if (! $result['ok']) {
            return ApiResponse::error($result['message'], $result['status']);
        }

        return ApiResponse::success(null, $result['message'], 200);
    }

    public function deleteWatchList(Request $request)
    {
        try {
            $user = auth()->user();
            $profileId = $this->resolveActiveProfile($user->id, $request);
            if (! $profileId) {
                return ApiResponse::error('Select an active viewing profile.', 403);
            }

            $result = $this->watchlistService->deleteWatchlist($user->id, $profileId, $request);
            if (! $result['ok']) {
                return ApiResponse::error($result['message'], $result['status']);
            }

            return ApiResponse::success(null, $result['message'], 200);
        } catch (\Exception $e) {
            Log::error('Watchlist Delete Error: '.$e->getMessage());

            return ApiResponse::error('Error removing from watchlist', 500);
        }
    }

    public function removeWatchList(Request $request, int $entertainment_id)
    {
        try {
            $user = auth()->user();
            $profileId = $this->resolveActiveProfile($user->id, $request);
            if (! $profileId) {
                return ApiResponse::error('Select an active viewing profile.', 403);
            }

            $result = $this->watchlistService->removeWatchlist(
                $user->id,
                $profileId,
                $entertainment_id,
                $request->input('type')
            );
            if (! $result['ok']) {
                return ApiResponse::error($result['message'], $result['status']);
            }

            return ApiResponse::success(null, $result['message'], 200);
        } catch (\Exception $e) {
            Log::error('Watchlist Remove Error: '.$e->getMessage());

            return ApiResponse::error('Error removing from watchlist', 500);
        }
    }

    public function continuewatchList(Request $request)
    {
        $user_id = auth()->user()->id;

        $perPage = $request->input('per_page', 10);

        $continuewatch_base = ContinueWatch::query()
                ->whereNotNull('watched_time')
                ->whereNotNull('total_watched_time')
                
                ->where(function($query) {
                    $query->whereHas('entertainment', function ($q) {
                        $q->where('status', 1)->whereNull('deleted_at');
                    })
                    ->orWhereHas('episode', function ($q) {
                        $q->whereNull('deleted_at');
                    })
                    ->orWhereHas('video', function ($q) {
                        $q->where('status', 1)->whereNull('deleted_at');
                    });
                })  
                ->with(['entertainment', 'episode', 'video','episode.seasondata']);
                // ->where(function ($query) {
                //     // For movie and tvshow, check entertainment relationship
                //     $query->where(function ($q) {
                //         $q->where('entertainment_type', 'movie')
                //         ->whereHas('entertainment', function ($subQuery) {
                //             $subQuery->where('status', 1)
                //                         ->whereNull('deleted_at');
                //         });
                //     })
                //     // For episode, check episode relationship
                //     ->orWhere(function ($q) {
                //         $q->where('entertainment_type', 'tvshow')
                //         ->whereNotNull('episode_id')
                //         ->whereHas('episode', function ($subQuery) {
                //             $subQuery->where('status', 1)
                //                         ->whereNull('deleted_at');
                //         });
                //     })
                //     // For video, check video relationship
                //     ->orWhere(function ($q) {
                //         $q->where('entertainment_type', 'video')
                //         ->whereHas('video', function ($subQuery) {
                //             $subQuery->where('status', 1)
                //                         ->whereNull('deleted_at');
                //         });
                //     });
                // })
                // ->with(['entertainment', 'episode.seasondata', 'video']);


        $continuewatchList = $continuewatch_base
            ->whereNotNull('watched_time')
            ->whereNotNull('total_watched_time')
            ->with(['entertainment', 'episode', 'video']);

        $profile_id = $this->resolveActiveProfile($user_id, $request);
        if (!$profile_id) return ApiResponse::error('Select an active viewing profile.', 403);

        $continuewatch = $continuewatchList
            ->where('user_id', $user_id)
            ->where('profile_id', $profile_id)
            ->orderBy('updated_at', 'desc')
            ->paginate($perPage);

        $responseData = ContinueWatchResource::collection($continuewatch);

        if ($request->has('is_ajax') && $request->is_ajax == 1) {
            $html = '';
            foreach ($responseData->toArray($request) as $continuewatchData) {
                $html .= view('frontend::components.card.card_continue_watch', ['value' => $continuewatchData])->render();
            }

            $hasMore = $continuewatch->hasMorePages();

            return ApiResponse::success(
                null,
                __('movie.movie_list'),
                200,
                ['html' => $html, 'hasMore' => $hasMore]
            );
        }
        return ApiResponse::success($responseData, __('movie.watch_list'), 200);
    }

    public function saveContinueWatch(Request $request)
    {
        if ($request->watched_time == null || $request->watched_time == '00:00:00') {
            return;
        }
        $user = auth()->user();
        $profileId = $this->resolveActiveProfile($user->id, $request);
        if (! $profileId) {
            return ApiResponse::error('Select an active viewing profile.', 403);
        }

        $this->watchProgressService->saveContinueWatch($user, $profileId, $request);

        return ApiResponse::success(null, __('movie.save_msg'), 200);
    }

    public function deleteContinueWatch(Request $request)
    {
        $userId = auth()->id();
        $profileId = $this->resolveActiveProfile($userId, $request);
        if (! $profileId) {
            return ApiResponse::error('Select an active viewing profile.', 403);
        }

        $this->watchProgressService->deleteContinueWatch($userId, $profileId, $request);

        return ApiResponse::success(null, 'Continue watching item removed', 200);
    }

    public function continuewatchListV3(Request $request){
        $user_id = auth()->user()->id;
        $perPage = $request->input('per_page', 10);

        // Create cache key based on all request parameters
        // $cacheKey = 'v3_continuewatch_list_' . md5(json_encode($request->all()) . '_' . $user_id);


        // Use Redis caching with 5 minutes TTL
        // $cachedResponse = cacheApiResponse($cacheKey, 300, function () use ($request, $user_id, $perPage, $type) {
            $continuewatchList = ContinueWatch::query()
             ->whereNotNull('watched_time')
             ->whereNotNull('total_watched_time')
             ->where(function ($query) {
                 // For movie and tvshow, check entertainment relationship
                 $query->where(function ($q) {
                     $q->where('entertainment_type', 'movie')
                       ->whereHas('entertainment', function ($subQuery) {
                           $subQuery->where('status', 1)
                                    ->whereNull('deleted_at');
                       });
                 })
                 // For episode, check episode relationship
                 ->orWhere(function ($q) {
                     $q->where('entertainment_type', 'tvshow')
                       ->whereNotNull('episode_id')
                       ->whereHas('episode', function ($subQuery) {
                           $subQuery->where('status', 1)
                                    ->whereNull('deleted_at');
                       });
                 })
                 // For video, check video relationship
                 ->orWhere(function ($q) {
                     $q->where('entertainment_type', 'video')
                       ->whereHas('video', function ($subQuery) {
                           $subQuery->where('status', 1)
                                    ->whereNull('deleted_at');
                       });
                 });
             })
             ->with(['entertainment', 'episode.seasondata', 'video']);

            $profile_id = $this->resolveActiveProfile($user_id, $request);
            if (!$profile_id) return ApiResponse::error('Select an active viewing profile.', 403);

            $continuewatch = $continuewatchList
                ->where('user_id', $user_id)
                ->where('profile_id', $profile_id)
                ->orderBy('updated_at', 'desc')
                ->paginate($perPage);


            // Process each item to add tv_show_data for episodes
            $continuewatch->getCollection()->transform(function ($item) {
                // Prefer episode poster when continue watch entry is for an episode under a TV show
                if ($item->entertainment_type == 'tvshow' && $item->episode) {
                    $item->thumbnail_url = $item->episode->poster_url ?? null;
                } elseif ($item->entertainment_type == 'tvshow' && $item->entertainment) {
                    $item->thumbnail_url = $item->entertainment->thumbnail_url ?? null;
                } elseif ($item->entertainment_type == 'movie' && $item->entertainment) {
                    $item->thumbnail_url = $item->entertainment->thumbnail_url ?? null;
                } elseif ($item->entertainment_type == 'video' && $item->video) {
                    $item->thumbnail_url = $item->video->thumbnail_url ?? null;
                } else {
                    $item->thumbnail_url = null;
                }

                if ($item->entertainment_type == 'tvshow' && $item->episode && $item->episode->seasondata) {
                    $item->tv_show_data = [
                        'id' => $item->episode->seasondata->id,
                        'episode_name' => $item->episode->name,
                        'season_name' => $item->episode->seasondata->name,
                        'season_id' => $item->episode->seasondata->id,
                    ];
                } else {
                    $item->tv_show_data = null;
                }
                return $item;
            });

            $responseData = ContinueWatchResourceV3::collection($continuewatch);

            if ($request->has('is_ajax') && $request->is_ajax == 1) {
                $html = '';
                foreach ($responseData->toArray($request) as $continuewatchData) {
                    $html .= view('frontend::components.card.card_continue_watch', ['value' => $continuewatchData])->render();
                }

                $hasMore = $continuewatch->hasMorePages();

                return [
                    'status' => true,
                    'html' => $html,
                    'message' => __('movie.movie_list'),
                    'hasMore' => $hasMore,
                ];
            }

            return [
                'status' => true,
                'data' => $responseData,
                'message' => __('movie.watch_list'),
            ];
        // });

        // Return cached response
        // return response()->json($cachedResponse['data'], 200);
    }

    private function resolveActiveProfile(int $userId, Request $request): ?int
    {
        return $this->watchlistService->resolveActiveProfile($userId, $request);
    }
}
