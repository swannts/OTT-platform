<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ApiCacheInvalidationTest extends TestCase
{
    public function test_cache_invalidation_helpers_bump_domain_versions_and_forget_static_keys(): void
    {
        Cache::flush();

        Cache::put('genres', ['action'], 60);
        Cache::put('genres_v2', ['action'], 60);
        Cache::put('banner_list', ['b1'], 60);
        Cache::put('home_banners', ['b2'], 60);

        clearRelatedCache(['custom_key'], 'banner');
        $this->assertNull(Cache::get('banner_list'));
        $this->assertNull(Cache::get('home_banners'));
        $this->assertSame(1, Cache::get('banner_data_cache_version'));

        clearBannerDataCache();
        $this->assertSame(2, Cache::get('banner_data_cache_version'));

        clearSearchV3Cache();
        $this->assertSame(1, Cache::get('search_v3_cache_version'));

        clearDashboardCache();
        $this->assertSame(1, Cache::get('dashboard_cache_version'));

        clearPayPerViewCache();
        $this->assertSame(1, Cache::get('pay_per_view_cache_version'));

        clearLiveTvDashboardCache();
        $this->assertSame(1, Cache::get('livetv_dashboard_cache_version'));

        clearWatchlistCache();
        $this->assertSame(1, Cache::get('watchlist_cache_version'));
        $this->assertSame(2, Cache::get('dashboard_cache_version'));
        $this->assertSame(3, Cache::get('banner_data_cache_version'));

        clearRelatedCache(['genres'], 'entertainment');
        $this->assertNull(Cache::get('genres'));
        $this->assertNull(Cache::get('genres_v2'));
    }
}
