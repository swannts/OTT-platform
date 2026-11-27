<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Adds clearly labeled sample catalog rows for local UI development.
 * This seeder is additive and safe to run more than once.
 */
class LocalDemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Local demo catalog data can only be seeded in local or testing environments.');
        }

        $now = now();
        $genres = [
            ['name' => 'Demo: Seerah & Prophets', 'name_bn' => 'ডেমো: সীরাত ও নবীগণ', 'slug' => 'demo-seerah-prophets'],
            ['name' => 'Demo: Quran & Reflection', 'name_bn' => 'ডেমো: কুরআন ও আত্মচিন্তা', 'slug' => 'demo-quran-reflection'],
            ['name' => 'Demo: Islamic History', 'name_bn' => 'ডেমো: ইসলামের ইতিহাস', 'slug' => 'demo-islamic-history'],
            ['name' => 'Demo: Faith & Family', 'name_bn' => 'ডেমো: ঈমান ও পরিবার', 'slug' => 'demo-faith-family'],
        ];

        $genreIds = [];
        foreach ($genres as $genre) {
            $existing = DB::table('genres')->where('slug', $genre['slug'])->first();
            $genreId = $existing->id ?? (string) Str::uuid7();

            if (! $existing) {
                DB::table('genres')->insert([
                    'id' => $genreId,
                    ...$genre,
                    'description' => 'Clearly labeled sample genre for local development.',
                    'description_bn' => 'স্থানীয় উন্নয়নের জন্য নমুনা বিভাগ।',
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $genreIds[$genre['slug']] = $genreId;
        }

        $catalog = [
            [
                'slug' => 'demo-sacred-sanctuary',
                'name' => 'Demo: The Sacred Sanctuary',
                'name_bn' => 'ডেমো: পবিত্র কাবা — তাওহীদের কেন্দ্রস্থল',
                'description' => 'Sample catalog entry about the Kaaba and the meaning of worship. This is demo metadata, not a published film.',
                'description_bn' => 'কাবা ও ইবাদতের তাৎপর্য নিয়ে একটি নমুনা ক্যাটালগ এন্ট্রি। এটি ডেমো তথ্য, প্রকাশিত চলচ্চিত্র নয়।',
                'poster' => 'https://images.unsplash.com/photo-1564769625905-50e93615e769?w=900&auto=format&fit=crop&q=85',
                'genre' => 'demo-seerah-prophets',
                'rating' => '8.8',
                'duration' => 'Demo feature',
            ],
            [
                'slug' => 'demo-stories-of-the-prophets',
                'name' => 'Demo: Stories of the Prophets',
                'name_bn' => 'ডেমো: নবীদের অনুপ্রেরণামূলক কাহিনি',
                'description' => 'Sample series metadata exploring lessons of patience, mercy and trust. This is demo content.',
                'description_bn' => 'ধৈর্য, দয়া ও আল্লাহর ওপর ভরসার শিক্ষা নিয়ে নমুনা সিরিজের তথ্য। এটি ডেমো কনটেন্ট।',
                'poster' => 'https://images.unsplash.com/photo-1519817650390-64a93db51149?w=900&auto=format&fit=crop&q=85',
                'genre' => 'demo-seerah-prophets',
                'rating' => '8.6',
                'duration' => 'Demo series',
            ],
            [
                'slug' => 'demo-quran-and-reflection',
                'name' => 'Demo: Quran & Reflection',
                'name_bn' => 'ডেমো: কুরআনের আলো — জীবনপথের নির্দেশনা',
                'description' => 'Sample documentary metadata about reflecting on Quranic guidance in everyday life. This is demo content.',
                'description_bn' => 'দৈনন্দিন জীবনে কুরআনের নির্দেশনা নিয়ে ভাবনার নমুনা তথ্য। এটি ডেমো কনটেন্ট।',
                'poster' => 'https://images.unsplash.com/photo-1584551246679-0daf3d275d0f?w=900&auto=format&fit=crop&q=85',
                'genre' => 'demo-quran-reflection',
                'rating' => '8.9',
                'duration' => 'Demo documentary',
            ],
            [
                'slug' => 'demo-ramadan-reflections',
                'name' => 'Demo: Ramadan Reflections',
                'name_bn' => 'ডেমো: রমজানের শিক্ষা ও আত্মশুদ্ধি',
                'description' => 'Sample short-film metadata about fasting, generosity and spiritual renewal. This is demo content.',
                'description_bn' => 'রোজা, দানশীলতা ও আত্মশুদ্ধি নিয়ে নমুনা স্বল্পদৈর্ঘ্য চলচ্চিত্রের তথ্য। এটি ডেমো কনটেন্ট।',
                'poster' => 'https://images.unsplash.com/photo-1533038590840-1cde6e668a91?w=900&auto=format&fit=crop&q=85',
                'genre' => 'demo-faith-family',
                'rating' => '8.4',
                'duration' => 'Demo feature',
            ],
            [
                'slug' => 'demo-islamic-golden-age',
                'name' => 'Demo: The Islamic Golden Age',
                'name_bn' => 'ডেমো: ইসলামের স্বর্ণযুগ',
                'description' => 'Sample documentary metadata about scholarship, science and culture across Muslim history. This is demo content.',
                'description_bn' => 'মুসলিম ইতিহাসের জ্ঞানচর্চা, বিজ্ঞান ও সংস্কৃতি নিয়ে নমুনা তথ্য। এটি ডেমো কনটেন্ট।',
                'poster' => 'https://images.unsplash.com/photo-1512632578888-169bbbc64f33?w=900&auto=format&fit=crop&q=85',
                'genre' => 'demo-islamic-history',
                'rating' => '8.7',
                'duration' => 'Demo documentary',
            ],
            [
                'slug' => 'demo-kindness-at-home',
                'name' => 'Demo: Lessons in Kindness',
                'name_bn' => 'ডেমো: পরিবারে দয়া ও উত্তম চরিত্র',
                'description' => 'Sample family-friendly story metadata about kindness, honesty and respect. This is demo content.',
                'description_bn' => 'দয়া, সততা ও সম্মান নিয়ে পরিবারবান্ধব গল্পের নমুনা তথ্য। এটি ডেমো কনটেন্ট।',
                'poster' => 'https://images.unsplash.com/photo-1511895426328-dc8714191300?w=900&auto=format&fit=crop&q=85',
                'genre' => 'demo-faith-family',
                'rating' => '8.5',
                'duration' => 'Demo feature',
            ],
        ];

        $contentIds = [];
        foreach ($catalog as $item) {
            $existing = DB::table('entertainments')->where('slug', $item['slug'])->first();
            $contentId = $existing->id ?? (string) Str::uuid7();
            $poster = $item['poster'];

            if (! $existing) {
                DB::table('entertainments')->insert([
                    'id' => $contentId,
                    'name' => $item['name'],
                    'name_bn' => $item['name_bn'],
                    'slug' => $item['slug'],
                    'type' => 'movie',
                    'description' => $item['description'],
                    'description_bn' => $item['description_bn'],
                    'short_description' => $item['description'],
                    'poster_url' => $poster,
                    'thumbnail_url' => $poster,
                    'poster_tv_url' => $poster,
                    'movie_access' => 'free',
                    'language' => 'Bangla',
                    'IMDb_rating' => $item['rating'],
                    'content_rating' => 'family',
                    'duration' => $item['duration'],
                    'release_date' => $now->toDateString(),
                    'is_restricted' => 0,
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $contentIds[] = $contentId;
            $genreId = $genreIds[$item['genre']];
            $mappingExists = DB::table('entertainment_gener_mapping')
                ->where('entertainment_id', $contentId)
                ->where('genre_id', $genreId)
                ->whereNull('deleted_at')
                ->exists();

            if (! $mappingExists) {
                DB::table('entertainment_gener_mapping')->insert([
                    'id' => (string) Str::uuid7(),
                    'entertainment_id' => $contentId,
                    'genre_id' => $genreId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $this->seedReferenceData($now);
        $this->seedSeriesAndEditorialData($now, $genreIds);
        $this->call(LocalDemoTvShowsSeeder::class);
        $this->seedCatalogAvailability($now);
        $this->call(LocalDemoLiveTvSeeder::class);
        $this->call(LocalDemoBlogSeeder::class);

        $first = $catalog[0];
        $firstId = $contentIds[0];
        $bannerExists = DB::table('banners')->where('type_id', $firstId)->where('banner_for', 'home')->exists();
        if (! $bannerExists) {
            DB::table('banners')->insert([
                'id' => (string) Str::uuid7(),
                'title' => $first['name'],
                'file_url' => $first['poster'],
                'poster_url' => $first['poster'],
                'poster_tv_url' => $first['poster'],
                'type' => 'movie',
                'type_id' => $firstId,
                'type_name' => $first['name'],
                'description' => $first['description'],
                'banner_for' => 'home',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('settings')->where('name', 'movie')->whereNull('deleted_at')->exists()) {
            DB::table('settings')->insert([
                'name' => 'movie',
                'val' => '1',
                'type' => 'string',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ([
            'top-10' => ['Top 10 Today', array_slice($contentIds, 0, 5), 3],
            'latest-movies' => ['New Releases', $contentIds, 5],
            'popular-movies' => ['Popular Demo Picks', array_slice($contentIds, 0, 5), 7],
        ] as $slug => [$name, $ids, $position]) {
            $setting = DB::table('mobile_settings')->where('slug', $slug)->first();
            $configuredIds = json_decode((string) ($setting->value ?? '[]'), true);
            $hasActiveConfiguredContent = is_array($configuredIds) && DB::table('entertainments')
                ->whereIn('id', $configuredIds)
                ->where('type', 'movie')
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->where(fn ($query) => $query->whereNull('release_date')->orWhere('release_date', '<=', $now->toDateString()))
                ->exists();

            // Keep working admin curation intact. Repair only missing or stale
            // local selections so seeded demo titles can populate the rows.
            if (! $setting) {
                DB::table('mobile_settings')->insert([
                    'name' => $name,
                    'slug' => $slug,
                    'position' => $position,
                    'value' => json_encode($ids, JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } elseif (! $hasActiveConfiguredContent && empty($setting->type)) {
                DB::table('mobile_settings')->where('id', $setting->id)->update([
                    'value' => json_encode($ids, JSON_THROW_ON_ERROR),
                    'updated_at' => $now,
                ]);
            }
        }

        $bannerSetting = DB::table('mobile_settings')->where('slug', 'banner')->first();
        if (! $bannerSetting) {
            DB::table('mobile_settings')->insert([
                'name' => 'Home Banners',
                'slug' => 'banner',
                'position' => 1,
                'value' => '1',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } elseif (empty($bannerSetting->value) && empty($bannerSetting->type)) {
            DB::table('mobile_settings')->where('id', $bannerSetting->id)->update([
                'value' => '1',
                'updated_at' => $now,
            ]);
        }

        \Illuminate\Support\Facades\Cache::forget('setting');
        \Illuminate\Support\Facades\Cache::forget('home_banners');
        \App\Models\Setting::flushCache();
        clearDashboardCache();
        clearPayPerViewCache();
    }

    private function seedReferenceData(\Illuminate\Support\Carbon $now): void
    {
        DB::table('currencies')->updateOrInsert(
            ['currency_code' => 'BDT'],
            [
                'currency_name' => 'Bangladeshi Taka',
                'currency_symbol' => '৳',
                'is_primary' => true,
                'currency_position' => 'left',
                'no_of_decimal' => 2,
                'thousand_separator' => ',',
                'decimal_separator' => '.',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
        );

        DB::table('countries')->updateOrInsert(
            ['code' => 'BD'],
            [
                'name' => 'Bangladesh',
                'dial_code' => '+880',
                'currency_name' => 'Bangladeshi Taka',
                'symbol' => '৳',
                'currency_code' => 'BDT',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
        );
        $countryId = DB::table('countries')->where('code', 'BD')->value('id');

        DB::table('states')->updateOrInsert(
            ['name' => 'Dhaka Division', 'country_id' => $countryId],
            ['status' => 1, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null],
        );
        $stateId = DB::table('states')->where('name', 'Dhaka Division')->where('country_id', $countryId)->value('id');
        DB::table('cities')->updateOrInsert(
            ['name' => 'Dhaka', 'state_id' => $stateId],
            ['status' => 1, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null],
        );
        DB::table('taxes')->updateOrInsert(
            ['title' => 'Demo VAT'],
            ['type' => 'percentage', 'value' => 0, 'status' => 0, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null],
        );
    }

    /** @param array<string, string> $genreIds */
    private function seedSeriesAndEditorialData(\Illuminate\Support\Carbon $now, array $genreIds): void
    {
        $adminId = DB::table('users')->where('email', 'admin@swann.com')->value('id');
        $seriesSlug = 'demo-prophetic-chronicles';
        $series = DB::table('entertainments')->where('slug', $seriesSlug)->first();
        $seriesId = $series->id ?? (string) Str::uuid7();
        if (! $series) {
            DB::table('entertainments')->insert([
                'id' => $seriesId,
                'name' => 'Demo: Prophetic Chronicles',
                'name_bn' => 'ডেমো: নবীদের জীবন থেকে শিক্ষা',
                'slug' => $seriesSlug,
                'type' => 'tvshow',
                'description' => 'Demo series metadata. Episodes are not playable until licensed video assets are added.',
                'description_bn' => 'নমুনা সিরিজের তথ্য। লাইসেন্সকৃত ভিডিও যুক্ত না করা পর্যন্ত পর্বগুলো চালানো যাবে না।',
                'short_description' => 'Demo series; media not attached.',
                'poster_url' => 'https://images.unsplash.com/photo-1519817650390-64a93db51149?w=900&auto=format&fit=crop&q=85',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1519817650390-64a93db51149?w=900&auto=format&fit=crop&q=85',
                'poster_tv_url' => 'https://images.unsplash.com/photo-1519817650390-64a93db51149?w=900&auto=format&fit=crop&q=85',
                'movie_access' => 'free',
                'language' => 'Bangla',
                'content_rating' => 'family',
                'duration' => 'Series',
                'release_date' => $now->toDateString(),
                'is_restricted' => 0,
                'status' => 0,
                'created_by' => $adminId,
                'updated_by' => $adminId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $genreId = $genreIds['demo-seerah-prophets'];
        if (! DB::table('entertainment_gener_mapping')->where('entertainment_id', $seriesId)->where('genre_id', $genreId)->whereNull('deleted_at')->exists()) {
            DB::table('entertainment_gener_mapping')->insert([
                'id' => (string) Str::uuid7(), 'entertainment_id' => $seriesId, 'genre_id' => $genreId,
                'created_by' => $adminId, 'updated_by' => $adminId, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $seasonSlug = 'demo-prophetic-chronicles-season-1';
        $season = DB::table('seasons')->where('slug', $seasonSlug)->first();
        $seasonId = $season->id ?? (string) Str::uuid7();
        if (! $season) {
            DB::table('seasons')->insert([
                'id' => $seasonId, 'name' => 'Demo Season 1', 'slug' => $seasonSlug, 'season_number' => 1,
                'season_index' => 1, 'entertainment_id' => $seriesId, 'poster_url' => 'https://images.unsplash.com/photo-1519817650390-64a93db51149?w=900&auto=format&fit=crop&q=85',
                'short_description' => 'Demo season; no video assets attached.', 'description' => 'Sample season metadata for local admin workflows.',
                'access' => 'free', 'status' => 0, 'created_by' => $adminId, 'updated_by' => $adminId,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach ([1 => 'The First Revelation', 2 => 'A Message of Mercy'] as $number => $episodeName) {
            $episodeSlug = 'demo-prophetic-chronicles-episode-'.$number;
            if (DB::table('episodes')->where('slug', $episodeSlug)->exists()) {
                continue;
            }
            DB::table('episodes')->insert([
                'id' => (string) Str::uuid7(), 'name' => 'Demo: '.$episodeName, 'name_bn' => 'ডেমো: '.$episodeName,
                'slug' => $episodeSlug, 'entertainment_id' => $seriesId, 'season_id' => $seasonId,
                'episode_number' => $number, 'tmdb_season' => 1, 'duration' => 'Demo episode',
                'description' => 'Sample episode metadata. No playable media is attached.',
                'short_description' => 'Demo episode; media not attached.', 'poster_url' => 'https://images.unsplash.com/photo-1519817650390-64a93db51149?w=900&auto=format&fit=crop&q=85',
                'access' => 'free', 'status' => 0, 'is_restricted' => 0,
                'created_by' => $adminId, 'updated_by' => $adminId, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $person = DB::table('cast_crew')->where('name', 'Demo Narrator')->where('type', 'crew')->first();
        $personId = $person->id ?? (string) Str::uuid7();
        if (! $person) {
            DB::table('cast_crew')->insert([
                'id' => $personId, 'name' => 'Demo Narrator', 'type' => 'crew', 'designation' => 'Narrator',
                'bio' => 'Sample crew profile for local metadata testing; not a real person.',
                'status' => 0, 'created_by' => $adminId, 'updated_by' => $adminId, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        if (! DB::table('entertainment_talent_mapping')->where('entertainment_id', $seriesId)->where('talent_id', $personId)->whereNull('deleted_at')->exists()) {
            DB::table('entertainment_talent_mapping')->insert([
                'id' => (string) Str::uuid7(), 'entertainment_id' => $seriesId, 'talent_id' => $personId,
                'created_by' => $adminId, 'updated_by' => $adminId, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $blogSlug = 'demo-reflection-and-kindness';
        DB::table('blogs')->updateOrInsert(
            ['slug' => $blogSlug],
            [
                'title' => 'Demo: Reflection and Kindness', 'summary' => 'A clearly labeled sample editorial entry for local testing.',
                'content' => 'This is sample editorial content for local development. Replace it with reviewed, sourced material before publication.',
                'category' => 'Demo', 'tags' => json_encode(['demo', 'reflection'], JSON_THROW_ON_ERROR),
                'author_name' => 'NOOR Demo Editorial', 'reading_time' => 2, 'views_count' => 0,
                'is_featured' => false, 'status' => 0, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
            ],
        );

        DB::table('faqs')->updateOrInsert(
            ['question' => 'Demo: How do I watch a title?'],
            ['answer' => 'Choose a title and select Play when licensed playback is available for your account.', 'status' => 1, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null],
        );
        DB::table('pages')->updateOrInsert(
            ['slug' => 'demo-about-noor'],
            ['name' => 'Demo About NOOR', 'sequence' => 99, 'description' => 'Sample about page for local testing. Replace with approved platform copy before publication.', 'status' => 0, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null],
        );

        DB::table('seo')->updateOrInsert(
            ['canonical_url' => 'http://localhost:3000/demo'],
            ['meta_title' => 'NOOR Demo Catalog', 'meta_keywords' => 'demo, islamic cinema', 'meta_description' => 'Local demo metadata. Not public editorial content.', 'short_description' => 'Local demo SEO metadata.', 'created_at' => $now, 'updated_at' => $now],
        );
    }

    private function seedCatalogAvailability(\Illuminate\Support\Carbon $now): void
    {
        $adminId = DB::table('users')->where('email', 'admin@swann.com')->value('id');

        DB::table('videos')->updateOrInsert(
            ['slug' => 'demo-short-reflection'],
            [
                'name' => 'Demo: A Short Reflection', 'name_bn' => 'ডেমো: সংক্ষিপ্ত আত্মচিন্তা',
                'type' => 'video', 'description' => 'Draft video metadata only. A licensed source file is not attached.',
                'short_description' => 'Draft demo metadata; no playback asset.',
                'poster_url' => 'https://images.unsplash.com/photo-1584551246679-0daf3d275d0f?w=900&auto=format&fit=crop&q=85',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1584551246679-0daf3d275d0f?w=900&auto=format&fit=crop&q=85',
                'access' => 'free', 'status' => 0, 'is_restricted' => 0, 'created_by' => $adminId,
                'updated_by' => $adminId, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
            ],
        );

        $customerId = DB::table('users')->where('email', 'demo.subscriber@example.test')->value('id');
        if ($customerId) {
            DB::table('user_multi_profiles')->updateOrInsert(
                ['user_id' => $customerId, 'name' => 'Amina'],
                ['is_child_profile' => false, 'created_by' => $customerId, 'updated_by' => $customerId, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null],
            );
        }
        $profileId = DB::table('user_multi_profiles')->where('user_id', $customerId)->where('name', 'Amina')->value('id');
        $contentId = DB::table('entertainments')->where('slug', 'demo-sacred-sanctuary')->value('id');
        if ($customerId && $profileId && $contentId) {
            $review = DB::table('reviews')->where('user_id', $customerId)->where('entertainment_id', $contentId)->first();
            if (! $review) {
                DB::table('reviews')->insert([
                    'entertainment_id' => $contentId, 'user_id' => $customerId, 'rating' => 5,
                    'review' => 'Demo review: sample customer feedback for local interface testing.',
                    'created_by' => $customerId, 'updated_by' => $customerId, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            DB::table('likes')->updateOrInsert(
                ['entertainment_id' => $contentId, 'user_id' => $customerId, 'profile_id' => $profileId, 'type' => 'movie'],
                ['is_like' => true, 'created_by' => $customerId, 'updated_by' => $customerId, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null],
            );
            DB::table('entertainment_views')->updateOrInsert(
                ['entertainment_id' => $contentId, 'user_id' => $customerId, 'profile_id' => $profileId],
                ['created_by' => $customerId, 'updated_by' => $customerId, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null],
            );
        }
    }
}
