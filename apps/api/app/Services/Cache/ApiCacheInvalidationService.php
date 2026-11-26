<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ApiCacheInvalidationService
{
    private const VERSION_TTL_SECONDS = 86400;

    /**
     * @param  string|array<int, string>  $keys
     */
    public function clearRelated(string|array $keys, ?string $module = null): void
    {
        foreach ((array) $keys as $key) {
            Cache::forget($key);
        }

        if (! $module) {
            return;
        }

        match ($module) {
            'banner' => $this->forgetKeys(['banner_list', 'home_banners']),
            'entertainment', 'movie', 'tvshow' => $this->clearEntertainmentRelated(),
            'genres' => $this->forgetKeys(['genres', 'genres_v2']),
            'livetv', 'livetvchannel' => $this->clearLiveTvDashboard(),
            'search' => $this->clearSearchV3(),
            default => null,
        };
    }

    public function clearByPattern(string $pattern): void
    {
        $commonKeys = [
            'genres',
            'home_banners',
            'banner_list',
            'module_enablement_checks',
            'setting_v2',
            'app_datetime_settings',
        ];

        foreach ($commonKeys as $key) {
            if (str_starts_with($key, $pattern) || str_starts_with($pattern, $key)) {
                Cache::forget($key);
            }
        }
    }

    public function clearBannerData(): void
    {
        $this->invalidateDomain(
            'banner_data_cache_version',
            ['entertainment_data_v3_*', 'entertainment_data_v3_*_v*'],
            false,
            'banner data'
        );
    }

    public function clearWatchlist(): void
    {
        try {
            $this->bumpVersion('watchlist_cache_version');
            $this->clearDashboard();
            $this->clearBannerData();
        } catch (Throwable $e) {
            Log::error('Failed to clear watchlist cache.', ['exception' => $e::class]);
        }
    }

    public function clearSearchV3(): void
    {
        $this->invalidateDomain(
            'search_v3_cache_version',
            ['search_v3_*', 'search_v3_*_v*'],
            false,
            'search_v3'
        );
    }

    public function clearDashboard(): void
    {
        $this->invalidateDomain(
            'dashboard_cache_version',
            ['dashboard_detail_v3_*', 'dashboard_detail_data_v3_*'],
            false,
            'dashboard'
        );
    }

    public function clearPayPerView(): void
    {
        $this->invalidateDomain(
            'pay_per_view_cache_version',
            ['pay_per_view_content_v3_*'],
            true,
            'pay-per-view'
        );
    }

    public function clearLiveTvDashboard(): void
    {
        $this->invalidateDomain(
            'livetv_dashboard_cache_version',
            ['livetv_dashboard_v3_*'],
            true,
            'live-TV dashboard'
        );
    }

    private function clearEntertainmentRelated(): void
    {
        $this->forgetKeys(['genres', 'genres_v2']);
        $this->clearDashboard();
        $this->clearPayPerView();
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function forgetKeys(array $keys): void
    {
        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * @param  array<int, string>  $keyPatterns
     */
    private function invalidateDomain(string $versionKey, array $keyPatterns, bool $alwaysBumpVersion, string $label): void
    {
        try {
            if ($alwaysBumpVersion) {
                $this->bumpVersion($versionKey);
            }

            if (config('cache.default') !== 'redis') {
                if (! $alwaysBumpVersion) {
                    $this->bumpVersion($versionKey);
                }

                return;
            }

            $redis = $this->resolveRedisClient();
            if (! $redis) {
                if (! $alwaysBumpVersion) {
                    $this->bumpVersion($versionKey);
                }

                return;
            }

            $prefix = (string) config('cache.prefix', '');
            $keysToDelete = $this->scanMatchingKeys($redis, $prefix, $keyPatterns);

            if ($keysToDelete !== []) {
                $this->deleteRedisKeys($redis, $prefix, $keysToDelete);
            } elseif (! $alwaysBumpVersion) {
                $this->bumpVersion($versionKey);
            }
        } catch (Throwable $e) {
            Log::error("Failed to clear {$label} cache.", ['exception' => $e::class]);
        }
    }

    private function bumpVersion(string $versionKey): void
    {
        $currentVersion = (int) Cache::get($versionKey, 0);
        Cache::put($versionKey, $currentVersion + 1, self::VERSION_TTL_SECONDS);
    }

    private function resolveRedisClient(): ?object
    {
        $store = Cache::getStore();
        if (method_exists($store, 'getRedis')) {
            return $store->getRedis();
        }
        if (method_exists($store, 'connection')) {
            $connection = $store->connection();
            if (method_exists($connection, 'client')) {
                return $connection->client();
            }
            if (is_object($connection) && method_exists($connection, 'scan')) {
                return $connection;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $keyPatterns
     * @return array<int, string>
     */
    private function scanMatchingKeys(object $redis, string $prefix, array $keyPatterns): array
    {
        $keysToDelete = [];

        foreach ($keyPatterns as $rawPattern) {
            $pattern = $prefix.$rawPattern;
            $cursor = 0;
            $iteration = 0;

            do {
                try {
                    $result = $redis->scan($cursor, ['match' => $pattern, 'count' => 500]);
                    if (! is_array($result) || count($result) < 2) {
                        break;
                    }
                    $cursor = (int) $result[0];
                    $keys = $result[1];
                    if (is_array($keys)) {
                        foreach ($keys as $key) {
                            $keysToDelete[] = $key;
                        }
                    }
                    $iteration++;
                } catch (Throwable) {
                    break;
                }
            } while ($cursor !== 0 && $iteration < 1000);
        }

        return array_values(array_unique($keysToDelete));
    }

    /**
     * @param  array<int, string>  $keysToDelete
     */
    private function deleteRedisKeys(object $redis, string $prefix, array $keysToDelete): void
    {
        foreach (array_chunk($keysToDelete, 1000) as $batch) {
            try {
                $redis->del($batch);
            } catch (Throwable) {
                foreach ($batch as $key) {
                    try {
                        $cacheKey = ($prefix !== '' && str_starts_with($key, $prefix))
                            ? substr($key, strlen($prefix))
                            : $key;
                        Cache::forget($cacheKey);
                    } catch (Throwable) {
                        // Ignore individual key deletion failures
                    }
                }
            }
        }
    }
}
