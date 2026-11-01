<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Entertainment\Models\Entertainment;
use Modules\Banner\Models\Banner;
use Modules\LiveTV\Models\LiveTvChannel;
use App\Models\MobileSetting;

class NoorContentSeeder extends Seeder
{
    public function run()
    {
        // 1. Seed / Update Hero Series in Entertainment
        $heroShow = Entertainment::firstOrNew(['id' => 1]);
        $heroShow->name = 'The Life of Prophet Muhammad';
        $heroShow->type = 'tvshow';
        $heroShow->description = 'A journey of mercy, wisdom and guidance for all humanity.';
        $heroShow->poster_url = 'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1920&auto=format&fit=crop&q=85';
        $heroShow->thumbnail_url = 'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=800&auto=format&fit=crop&q=85';
        $heroShow->poster_tv_url = 'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1920&auto=format&fit=crop&q=85';
        $heroShow->movie_access = 'free';
        $heroShow->IMDb_rating = 9.9;
        $heroShow->duration = '45:20';
        $heroShow->release_date = '2026-01-01';
        $heroShow->status = 1;
        $heroShow->save();

        // 2. Seed NOOR Trending items in Entertainment with specific IDs for exact ordering
        // Stories of the Prophets (10), Understanding the Quran (9), Ramadan Reflections (8), Bengali Waz (7), Islamic History (6)
        $trendingItems = [
            [
                'id' => 10,
                'name' => 'Stories of the Prophets',
                'type' => 'tvshow',
                'description' => 'The inspiring and sacred chronicles of the prophets and messengers across historical sands.',
                'poster_url' => 'https://images.unsplash.com/photo-1509316975850-ff9c5deb0cd9?w=800&auto=format&fit=crop&q=80',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1509316975850-ff9c5deb0cd9?w=800&auto=format&fit=crop&q=80',
                'poster_tv_url' => 'https://images.unsplash.com/photo-1509316975850-ff9c5deb0cd9?w=800&auto=format&fit=crop&q=80',
                'movie_access' => 'free',
                'duration' => 'Series • 8 Episodes',
                'IMDb_rating' => 9.8,
                'release_date' => '2026-01-01',
                'status' => 1,
            ],
            [
                'id' => 9,
                'name' => 'Understanding the Quran',
                'type' => 'movie',
                'description' => 'Comprehensive guidance, context and profound reflections of the Noble Quran.',
                'poster_url' => 'https://images.unsplash.com/photo-1584551246679-0daf3d275d0f?w=800&auto=format&fit=crop&q=80',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1584551246679-0daf3d275d0f?w=800&auto=format&fit=crop&q=80',
                'poster_tv_url' => 'https://images.unsplash.com/photo-1584551246679-0daf3d275d0f?w=800&auto=format&fit=crop&q=80',
                'movie_access' => 'free',
                'duration' => 'Documentary',
                'IMDb_rating' => 9.7,
                'release_date' => '2026-01-01',
                'status' => 1,
            ],
            [
                'id' => 8,
                'name' => 'Ramadan Reflections',
                'type' => 'movie',
                'description' => 'Spiritual renewal, night prayers, and heartfelt du\'a insights during the holy month.',
                'poster_url' => 'https://images.unsplash.com/photo-1533038590840-1cde6e668a91?w=800&auto=format&fit=crop&q=80',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1533038590840-1cde6e668a91?w=800&auto=format&fit=crop&q=80',
                'poster_tv_url' => 'https://images.unsplash.com/photo-1533038590840-1cde6e668a91?w=800&auto=format&fit=crop&q=80',
                'movie_access' => 'free',
                'duration' => 'Talk Show',
                'IMDb_rating' => 9.5,
                'release_date' => '2026-01-01',
                'status' => 1,
            ],
            [
                'id' => 7,
                'name' => 'Bengali Waz Collection',
                'type' => 'movie',
                'description' => 'Heartwarming lectures and prophetic teachings presented by revered Islamic scholars.',
                'poster_url' => 'https://images.unsplash.com/photo-1564769625905-50e93615e769?w=800&auto=format&fit=crop&q=80',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1564769625905-50e93615e769?w=800&auto=format&fit=crop&q=80',
                'poster_tv_url' => 'https://images.unsplash.com/photo-1564769625905-50e93615e769?w=800&auto=format&fit=crop&q=80',
                'movie_access' => 'free',
                'duration' => 'Lectures',
                'IMDb_rating' => 9.4,
                'release_date' => '2026-01-01',
                'status' => 1,
            ],
            [
                'id' => 6,
                'name' => 'Islamic History',
                'type' => 'movie',
                'description' => 'Exploration of civilizational achievements, monumental landmarks, and timeless heritage.',
                'poster_url' => 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?w=800&auto=format&fit=crop&q=80',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?w=800&auto=format&fit=crop&q=80',
                'poster_tv_url' => 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?w=800&auto=format&fit=crop&q=80',
                'movie_access' => 'free',
                'duration' => 'Documentary',
                'IMDb_rating' => 9.6,
                'release_date' => '2026-01-01',
                'status' => 1,
            ],
        ];

        foreach ($trendingItems as $itemData) {
            $ent = Entertainment::firstOrNew(['id' => $itemData['id']]);
            $ent->fill($itemData);
            $ent->save();
        }

        // 3. Update Home Banner
        $homeBanner = Banner::firstOrNew(['id' => 1]);
        $homeBanner->title = 'The Life of Prophet Muhammad';
        $homeBanner->type = 'tvshow';
        $homeBanner->type_id = 1;
        $homeBanner->type_name = 'The Life of Prophet Muhammad';
        $homeBanner->description = 'A journey of mercy, wisdom and guidance for all humanity.';
        $homeBanner->poster_url = 'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1920&auto=format&fit=crop&q=85';
        $homeBanner->poster_tv_url = 'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1920&auto=format&fit=crop&q=85';
        $homeBanner->file_url = 'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1920&auto=format&fit=crop&q=85';
        $homeBanner->banner_for = 'home';
        $homeBanner->status = 1;
        $homeBanner->save();

        // 4. Update Live TV channels in database
        $channels = [
            [
                'id' => 1,
                'name' => 'Jumma khutba from Masjid Al-Haram',
                'description' => 'Makkah, Saudi Arabia',
                'poster_url' => 'https://images.unsplash.com/photo-1564769625905-50e93615e769?w=800&auto=format&fit=crop&q=80',
                'thumb_url' => 'https://images.unsplash.com/photo-1564769625905-50e93615e769?w=800&auto=format&fit=crop&q=80',
                'poster_tv_url' => 'https://images.unsplash.com/photo-1564769625905-50e93615e769?w=800&auto=format&fit=crop&q=80',
                'access' => 'free',
                'status' => 1,
            ],
            [
                'id' => 2,
                'name' => 'Peace TV',
                'description' => 'Live Channel',
                'poster_url' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=400&auto=format&fit=crop&q=80',
                'thumb_url' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=400&auto=format&fit=crop&q=80',
                'poster_tv_url' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=400&auto=format&fit=crop&q=80',
                'access' => 'free',
                'status' => 1,
            ],
            [
                'id' => 3,
                'name' => 'Huda TV',
                'description' => 'Live Channel',
                'poster_url' => 'https://images.unsplash.com/photo-1519817650390-64a93db51149?w=400&auto=format&fit=crop&q=80',
                'thumb_url' => 'https://images.unsplash.com/photo-1519817650390-64a93db51149?w=400&auto=format&fit=crop&q=80',
                'poster_tv_url' => 'https://images.unsplash.com/photo-1519817650390-64a93db51149?w=400&auto=format&fit=crop&q=80',
                'access' => 'free',
                'status' => 1,
            ],
            [
                'id' => 4,
                'name' => 'iTV',
                'description' => 'Live Channel',
                'poster_url' => 'https://images.unsplash.com/photo-1584551246679-0daf3d275d0f?w=400&auto=format&fit=crop&q=80',
                'thumb_url' => 'https://images.unsplash.com/photo-1584551246679-0daf3d275d0f?w=400&auto=format&fit=crop&q=80',
                'poster_tv_url' => 'https://images.unsplash.com/photo-1584551246679-0daf3d275d0f?w=400&auto=format&fit=crop&q=80',
                'access' => 'free',
                'status' => 1,
            ],
            [
                'id' => 5,
                'name' => 'ATN Bangla',
                'description' => 'Live Channel',
                'poster_url' => 'https://images.unsplash.com/photo-1509316975850-ff9c5deb0cd9?w=400&auto=format&fit=crop&q=80',
                'thumb_url' => 'https://images.unsplash.com/photo-1509316975850-ff9c5deb0cd9?w=400&auto=format&fit=crop&q=80',
                'poster_tv_url' => 'https://images.unsplash.com/photo-1509316975850-ff9c5deb0cd9?w=400&auto=format&fit=crop&q=80',
                'access' => 'free',
                'status' => 1,
            ],
        ];

        foreach ($channels as $chData) {
            $ch = LiveTvChannel::firstOrNew(['id' => $chData['id']]);
            $ch->fill($chData);
            $ch->save();
        }

        // 5. Update MobileSetting for latest-movies (Trending Now)
        $latestSetting = MobileSetting::firstOrNew(['slug' => 'latest-movies']);
        $latestSetting->name = 'Trending Now';
        $latestSetting->value = json_encode(["10", "9", "8", "7", "6"]);
        $latestSetting->save();

        // Also update top-10 setting
        $topSetting = MobileSetting::firstOrNew(['slug' => 'top-10']);
        $topSetting->name = 'Top 10 Today';
        $topSetting->value = json_encode(["1", "10", "9", "8", "7", "6"]);
        $topSetting->save();

        // 6. Update Live TV Banner
        $liveBanner = Banner::firstOrNew(['id' => 3]);
        $liveBanner->title = 'Jumma khutba from Masjid Al-Haram';
        $liveBanner->type = 'livetv';
        $liveBanner->type_id = 1;
        $liveBanner->type_name = 'Jumma khutba from Masjid Al-Haram';
        $liveBanner->description = 'Makkah, Saudi Arabia';
        $liveBanner->banner_for = 'livetv';
        $liveBanner->poster_url = 'https://images.unsplash.com/photo-1564769625905-50e93615e769?w=800&auto=format&fit=crop&q=80';
        $liveBanner->poster_tv_url = 'https://images.unsplash.com/photo-1564769625905-50e93615e769?w=800&auto=format&fit=crop&q=80';
        $liveBanner->file_url = 'https://images.unsplash.com/photo-1564769625905-50e93615e769?w=800&auto=format&fit=crop&q=80';
        $liveBanner->status = 1;
        $liveBanner->save();
    }
}
