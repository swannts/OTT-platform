<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/** Creates bilingual, clearly labeled series cards for local UI development. */
class LocalDemoTvShowsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Local TV-show demo data can only be seeded in local or testing environments.');
        }

        $now = now();
        $adminId = DB::table('users')->where('email', 'admin@swann.com')->value('id');
        $genres = [
            [
                'slug' => 'demo-seerah-prophets',
                'name' => 'Demo: Seerah & Prophets',
                'name_bn' => 'ডেমো: সীরাত ও নবীগণ',
            ],
            [
                'slug' => 'demo-quran-reflection',
                'name' => 'Demo: Quran & Reflection',
                'name_bn' => 'ডেমো: কুরআন ও আত্মচিন্তা',
            ],
            [
                'slug' => 'demo-islamic-history',
                'name' => 'Demo: Islamic History',
                'name_bn' => 'ডেমো: ইসলামের ইতিহাস',
            ],
            [
                'slug' => 'demo-faith-family',
                'name' => 'Demo: Faith & Family',
                'name_bn' => 'ডেমো: ঈমান ও পরিবার',
            ],
        ];

        $genreIds = [];
        foreach ($genres as $genre) {
            $existing = DB::table('genres')->where('slug', $genre['slug'])->first();
            $genreId = $existing->id ?? (string) Str::uuid7();
            DB::table('genres')->updateOrInsert(
                ['slug' => $genre['slug']],
                [
                    'id' => $genreId,
                    'name' => $genre['name'],
                    'name_bn' => $genre['name_bn'],
                    'description' => 'Clearly labeled sample genre for local development.',
                    'description_bn' => 'স্থানীয় উন্নয়নের জন্য স্পষ্টভাবে চিহ্নিত নমুনা বিভাগ।',
                    'status' => 1,
                    'created_at' => $existing->created_at ?? $now,
                    'updated_at' => $now,
                ],
            );
            $genreIds[$genre['slug']] = $genreId;
        }

        $shows = [
            [
                'slug' => 'demo-prophetic-chronicles',
                'name' => 'Demo: Prophetic Chronicles',
                'name_bn' => 'ডেমো: নবীদের জীবন থেকে শিক্ষা',
                'description' => 'Sample series metadata about lessons of patience, mercy, and trust. No playable video is included.',
                'description_bn' => 'ধৈর্য, দয়া ও আল্লাহর ওপর ভরসার শিক্ষা নিয়ে নমুনা সিরিজের তথ্য। এতে চালানোর মতো ভিডিও নেই।',
                'poster' => 'https://images.unsplash.com/photo-1519817650390-64a93db51149?auto=format&fit=crop&w=1200&q=85',
                'genre' => 'demo-seerah-prophets',
                'rating' => '8.6',
            ],
            [
                'slug' => 'demo-quran-and-reflection-series',
                'name' => 'Demo: Quran & Reflection',
                'name_bn' => 'ডেমো: কুরআনের আলো — জীবনপথের নির্দেশনা',
                'description' => 'Sample series metadata about reflecting on Quranic guidance in everyday life. No playable video is included.',
                'description_bn' => 'দৈনন্দিন জীবনে কুরআনের নির্দেশনা নিয়ে ভাবনার নমুনা সিরিজ। এতে চালানোর মতো ভিডিও নেই।',
                'poster' => 'https://images.unsplash.com/photo-1584551246679-0daf3d275d0f?auto=format&fit=crop&w=1200&q=85',
                'genre' => 'demo-quran-reflection',
                'rating' => '8.9',
            ],
            [
                'slug' => 'demo-footprints-of-history',
                'name' => 'Demo: Footprints of History',
                'name_bn' => 'ডেমো: ইতিহাসের পাতায় মুসলিম সভ্যতা',
                'description' => 'Sample series metadata about scholarship, architecture, and cultural heritage. No playable video is included.',
                'description_bn' => 'জ্ঞানচর্চা, স্থাপত্য ও সাংস্কৃতিক ঐতিহ্য নিয়ে নমুনা সিরিজের তথ্য। এতে চালানোর মতো ভিডিও নেই।',
                'poster' => 'https://images.unsplash.com/photo-1512632578888-169bbbc64f33?auto=format&fit=crop&w=1200&q=85',
                'genre' => 'demo-islamic-history',
                'rating' => '8.7',
            ],
            [
                'slug' => 'demo-ramadan-reflections-series',
                'name' => 'Demo: Ramadan Reflections',
                'name_bn' => 'ডেমো: রমজানের শিক্ষা ও আত্মশুদ্ধি',
                'description' => 'Sample series metadata on generosity, worship, and spiritual renewal. No playable video is included.',
                'description_bn' => 'দানশীলতা, ইবাদত ও আত্মশুদ্ধি নিয়ে নমুনা সিরিজের তথ্য। এতে চালানোর মতো ভিডিও নেই।',
                'poster' => 'https://images.unsplash.com/photo-1533038590840-1cde6e668a91?auto=format&fit=crop&w=1200&q=85',
                'genre' => 'demo-faith-family',
                'rating' => '8.4',
            ],
            [
                'slug' => 'demo-lessons-in-kindness-series',
                'name' => 'Demo: Lessons in Kindness',
                'name_bn' => 'ডেমো: পরিবারে দয়া ও উত্তম চরিত্র',
                'description' => 'Sample family-friendly series metadata about kindness, honesty, and respect. No playable video is included.',
                'description_bn' => 'দয়া, সততা ও সম্মান নিয়ে পরিবারবান্ধব নমুনা সিরিজ। এতে চালানোর মতো ভিডিও নেই।',
                'poster' => 'https://images.unsplash.com/photo-1511895426328-dc8714191300?auto=format&fit=crop&w=1200&q=85',
                'genre' => 'demo-faith-family',
                'rating' => '8.5',
            ],
            [
                'slug' => 'demo-stories-of-the-prophets-series',
                'name' => 'Demo: Stories of the Prophets',
                'name_bn' => 'ডেমো: নবীদের অনুপ্রেরণামূলক কাহিনি',
                'description' => 'Sample series metadata about lessons of faith, courage, and compassion. No playable video is included.',
                'description_bn' => 'ঈমান, সাহস ও সহমর্মিতার শিক্ষা নিয়ে নমুনা সিরিজের তথ্য। এতে চালানোর মতো ভিডিও নেই।',
                'poster' => 'https://images.unsplash.com/photo-1564769625905-50e93615e769?auto=format&fit=crop&w=1200&q=85',
                'genre' => 'demo-seerah-prophets',
                'rating' => '8.8',
            ],
        ];

        foreach ($shows as $show) {
            $existing = DB::table('entertainments')->where('slug', $show['slug'])->first();
            $showId = $existing->id ?? (string) Str::uuid7();
            DB::table('entertainments')->updateOrInsert(
                ['slug' => $show['slug']],
                [
                    'id' => $showId,
                    'name' => $show['name'],
                    'name_bn' => $show['name_bn'],
                    'type' => 'tvshow',
                    'description' => $show['description'],
                    'description_bn' => $show['description_bn'],
                    'short_description' => $show['description'],
                    'poster_url' => $show['poster'],
                    'thumbnail_url' => $show['poster'],
                    'poster_tv_url' => $show['poster'],
                    'movie_access' => 'free',
                    'language' => 'Bangla',
                    'IMDb_rating' => $show['rating'],
                    'content_rating' => 'family',
                    'duration' => 'Series',
                    'release_date' => $now->toDateString(),
                    'is_restricted' => 0,
                    'status' => 1,
                    'created_by' => $existing->created_by ?? $adminId,
                    'updated_by' => $adminId,
                    'created_at' => $existing->created_at ?? $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ],
            );

            $genreId = $genreIds[$show['genre']];
            $mapping = DB::table('entertainment_gener_mapping')
                ->where('entertainment_id', $showId)
                ->where('genre_id', $genreId)
                ->first();

            if (! $mapping) {
                DB::table('entertainment_gener_mapping')->insert([
                    'id' => (string) Str::uuid7(),
                    'entertainment_id' => $showId,
                    'genre_id' => $genreId,
                    'created_by' => $adminId,
                    'updated_by' => $adminId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } elseif ($mapping->deleted_at !== null) {
                DB::table('entertainment_gener_mapping')->where('id', $mapping->id)->update([
                    'deleted_at' => null,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
