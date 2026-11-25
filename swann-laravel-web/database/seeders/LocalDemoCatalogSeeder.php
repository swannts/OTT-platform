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
            if (! $setting) {
                DB::table('mobile_settings')->insert([
                    'name' => $name,
                    'slug' => $slug,
                    'position' => $position,
                    'value' => json_encode($ids, JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } elseif (empty($setting->value) && empty($setting->type)) {
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
    }
}
