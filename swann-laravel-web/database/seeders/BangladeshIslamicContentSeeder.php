<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Curated Bangladeshi Bangla sources. Only public YouTube metadata and links
 * are stored; videos are played from YouTube and are not copied locally.
 */
class BangladeshIslamicContentSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'entertainment_stream_content_mapping', 'entertainment_gener_mapping',
            'entertainment_talent_mapping', 'episode_stream_content_mapping',
            'episodes', 'seasons', 'entertainments', 'genres', 'cast_crew',
            'live_tv_stream_content_mapping', 'live_tv_channel', 'live_tv_category',
            'clips', 'banners', 'video_stream_content_mapping', 'videos',
        ] as $table) {
            DB::table($table)->truncate();
        }

        $now = now();
        $genres = [
            ['name' => 'Waz & Bayan', 'slug' => 'waz-bayan', 'description' => 'Bangla Islamic lectures and sermons.'],
            ['name' => 'Quran & Tafsir', 'slug' => 'quran-tafsir', 'description' => 'Bangla Quran recitation, translation and tafsir.'],
            ['name' => 'Prophetic Chronicles', 'slug' => 'prophetic-chronicles', 'description' => 'Seerah, prophets and companions.'],
            ['name' => 'History & Islamic Education', 'slug' => 'history-islamic-education', 'description' => 'Bangla history and educational content.'],
            ['name' => 'Kids & Family', 'slug' => 'kids-family', 'description' => 'Family-safe Bangla Islamic stories and cartoons.'],
            ['name' => 'Nasheed & Audio', 'slug' => 'nasheed-audio', 'description' => 'Bangla hamd, naat, nasheed and audio lectures.'],
            ['name' => 'Spiritual Wisdom', 'slug' => 'spiritual-wisdom', 'description' => 'Short Bangla reminders and practical spiritual guidance.'],
            ['name' => 'Documentaries & Journal', 'slug' => 'documentaries-journal', 'description' => 'Bangla history, culture and Islamic cinema insights.'],
            ['name' => 'Podcasts & Discussions', 'slug' => 'podcasts-discussions', 'description' => 'Long-form Bangla conversations and scholarly discussions.'],
            ['name' => 'Live TV', 'slug' => 'live-tv', 'description' => 'Public Bangla Islamic live broadcasts.'],
        ];
        foreach ($genres as $index => $genre) {
            DB::table('genres')->insert(array_merge($genre, [
                'id' => $index + 1, 'status' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]));
        }

        $sources = [
            ['name' => 'Abu Tawha TV', 'url' => 'https://abutawhatv.com/', 'category' => 1, 'description' => 'Bangla waz, Islamic lectures and educational da’wah content.'],
            ['name' => 'Quraner Alo', 'url' => 'https://www.youtube.com/channel/UCoBecruubyIVf6MU4MY6NmQ', 'category' => 2, 'description' => 'Bangla waz, Quran tafsir, Hadith and prophetic stories.'],
            ['name' => 'Islamic Channel Bangla', 'url' => 'https://www.youtube.com/channel/UC6aLFvIs_gmrQsJcS-zKBMw', 'category' => 6, 'description' => 'Bangla Islamic waz, Quran recitation, nasheed and learning.'],
            ['name' => 'Paradise TV', 'url' => 'https://www.youtube.com/@paradisetv77', 'category' => 1, 'description' => 'Bangla waz, tafsir and Islamic programs.'],
            ['name' => 'HEAL App', 'url' => 'https://www.youtube.com/@healappbd', 'category' => 2, 'description' => 'Bengali Islamic lectures and Quranic teaching.'],
            ['name' => 'DeenPriyo', 'url' => 'https://www.youtube.com/@deenpriyo', 'category' => 2, 'description' => 'Bangla Quran, Hadith explanations and Islamic discussion.'],
            ['name' => 'Yahia Amin', 'url' => 'https://www.youtube.com/@yahiaamin', 'category' => 4, 'description' => 'Bangla Islamic commentary, history and educational discussion.'],
            ['name' => 'Sajal Araf', 'url' => 'https://www.youtube.com/@sajal_araf', 'category' => 4, 'description' => 'Bangla history, civilizations and religion-focused education.'],
            ['name' => 'SiraatMedia', 'url' => 'https://www.youtube.com/@siraatmediabd', 'category' => 3, 'description' => 'Bangla prophetic history, Islamic stories and children’s reminders.'],
            ['name' => 'Sakiber Patshala', 'url' => 'https://www.youtube.com/watch?v=0hEKRBATKfw', 'category' => 5, 'description' => 'Bangla 3D Islamic cartoon for children, published from Dhaka.'],
            ['name' => 'Jannat TV', 'url' => 'https://www.inditubedb.com/creator/jannat-tv', 'category' => 6, 'description' => 'Bangla waz, mahfil, hamd, naat and Islamic songs.'],
            ['name' => 'Safa Islamic Voice', 'url' => 'https://vling.net/en/channel/UC5Nbs8kO-1a8_-U6rFpdpvw/channel-info', 'category' => 1, 'description' => 'Bangla lectures, tafsir, waz and Friday khutbah.'],
            ['name' => 'Islam House Bangla', 'url' => 'https://www.youtube.com/@IslamHouseBn/videos', 'category' => 2, 'description' => 'Bangla Quran, Sunnah and Islamic educational material.'],
            ['name' => 'Ahlehadeeth Andolon Bangladesh', 'url' => 'https://www.youtube.com/@AhlehadeethAndolonBangladesh/videos', 'category' => 1, 'description' => 'Bangla lectures and educational programs from a Bangladeshi Islamic organization.'],
            ['name' => 'New Bangla Waz Live', 'url' => 'https://www.youtube.com/@NewBanglawazlive/videos', 'category' => 1, 'description' => 'Bangla waz mahfil and live religious programs.'],
            ['name' => 'Salafi Dawah Bangla', 'url' => 'https://www.youtube.com/@SalafiDawahB/videos', 'category' => 1, 'description' => 'Bangla Islamic reminders and dawah lectures.'],
            ['name' => 'Tawhid Media Bangladesh', 'url' => 'https://www.youtube.com/@TawhidMediabd/videos', 'category' => 3, 'description' => 'Bangla Quran, Hadith, seerah and Islamic reminders.'],
            ['name' => 'Madani Channel Bangla', 'url' => 'https://madanichannelbangla.tv/', 'category' => 6, 'description' => 'Bengali spiritual, educational and social guidance programming.'],
            ['name' => 'Islamic TV Bangla Live', 'url' => 'https://www.youtube.com/channel/UCPJKL0fmqzgQWLn-nTS8y8Q', 'category' => 1, 'description' => 'Bangladeshi Bangla Islamic channel with lectures and live programming.'],
            ['name' => 'Bangla Islamic Television', 'url' => 'https://www.youtube.com/@Bangla_Islamic_Television', 'category' => 1, 'description' => 'Bangla lessons on kalema, prayer, fasting, zakat, hajj and dua.'],
        ];

        foreach ($sources as $source) {
            DB::table('videos')->insert([
                'name' => $source['name'], 'poster_url' => null,
                'trailer_url_type' => 'youtube', 'trailer_url' => $source['url'],
                'access' => 'free', 'type' => 'video', 'content_rating' => 'family',
                'short_desc' => $source['description'], 'description' => $source['description'],
                'video_upload_type' => 'youtube', 'video_url_input' => $source['url'],
                'status' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $series = [
            ['name' => 'বাংলা সীরাত পাঠ', 'url' => 'https://www.youtube.com/@siraatmediabd/videos', 'description' => 'A curated Bangla series on the life and lessons of the Prophets and companions.'],
            ['name' => 'কুরআন তাফসীর ধারাবাহিক', 'url' => 'https://www.youtube.com/@QuranerAlo-fh1hp', 'description' => 'Episode-based Bangla Quran translation and tafsir lessons.'],
            ['name' => 'ইসলামের ইতিহাস বাংলা', 'url' => 'https://www.youtube.com/@sajal_araf/videos', 'description' => 'Bangla history and civilization episodes for family viewing.'],
            ['name' => 'শিশুদের ইসলামিক গল্প', 'url' => 'https://www.youtube.com/watch?v=0hEKRBATKfw', 'description' => 'Safe Bangla animated stories and Islamic lessons for children.'],
            ['name' => 'বাংলা ওয়াজ মাহফিল সিরিজ', 'url' => 'https://www.youtube.com/@NewBanglawazlive/videos', 'description' => 'Multi-episode Bangla waz and mahfil recordings.'],
            ['name' => 'দাওয়াহ ও আত্মশুদ্ধি আলোচনা', 'url' => 'https://abutawhatv.com/', 'description' => 'Bangla spiritual reminders and practical Islamic discussions.'],
            ['name' => 'হাদীসের আলো', 'url' => 'https://www.youtube.com/@IslamHouseBn/videos', 'description' => 'Episode-based Bangla lessons from Quran and authentic Hadith.'],
            ['name' => 'সুন্নাহর পথে', 'url' => 'https://www.youtube.com/@AhlehadeethAndolonBangladesh/videos', 'description' => 'Bangla lessons on worship, character and following the Sunnah.'],
            ['name' => 'নামাজ ও ইবাদত শিক্ষা', 'url' => 'https://www.youtube.com/@Bangla_Islamic_Television', 'description' => 'Practical Bangla guidance on prayer, fasting, zakat and hajj.'],
            ['name' => 'সাহাবীদের জীবনকথা', 'url' => 'https://www.youtube.com/@TawhidMediabd/videos', 'description' => 'Bangla stories and lessons from the lives of the Companions.'],
            ['name' => 'ইসলামী জ্ঞানকোষ', 'url' => 'https://www.youtube.com/@deenpriyo/videos', 'description' => 'Bangla explanatory episodes on Islamic beliefs and daily life.'],
            ['name' => 'তাফসীর মাহফিল বাংলা', 'url' => 'https://www.youtube.com/@paradisetv77', 'description' => 'Bangla tafsir and Quran-focused mahfil recordings.'],
            ['name' => 'ইসলামিক প্রশ্নোত্তর', 'url' => 'https://www.youtube.com/@healappbd/videos', 'description' => 'Bangla question-and-answer discussions on faith and practice.'],
            ['name' => 'বাংলা নসীহত', 'url' => 'https://www.youtube.com/@SalafiDawahB/videos', 'description' => 'Short and long Bangla reminders for spiritual growth.'],
            ['name' => 'কুরআন তিলাওয়াত ও নাশীদ', 'url' => 'https://www.youtube.com/channel/UC6aLFvIs_gmrQsJcS-zKBMw', 'description' => 'Bangla Quran recitation, hamd, naat and nasheed collections.'],
            ['name' => 'মাদানী বাংলা শিক্ষা সিরিজ', 'url' => 'https://madanichannelbangla.tv/', 'description' => 'Bengali spiritual, educational and family programming from Madani Channel.'],
        ];
        // Additional curated collections. These are catalog entries pointing
        // to publisher collections; episode availability remains controlled by
        // the original publisher.
        $collectionNames = [
            'নবীদের কাহিনী', 'রাসূলের সুন্নাহ', 'সাহাবাদের আদর্শ', 'কুরআনের সহজ পাঠ', 'সূরা শিক্ষা বাংলা',
            'হাদীস শিক্ষা বাংলা', 'আকীদা ও ঈমান', 'ফিকহ ও মাসআলা', 'রমজান শিক্ষা', 'দোয়া ও যিকির',
            'নামাজের শিক্ষা', 'পরিবার ও ইসলাম', 'যুবকদের জন্য দাওয়াহ', 'নারীদের ইসলামী শিক্ষা', 'শিশুদের নৈতিক গল্প',
            'ইসলামী ইতিহাসের পাতা', 'বাংলার মুসলিম ঐতিহ্য', 'মুসলিম বিজ্ঞানীদের গল্প', 'ইসলামী সভ্যতা', 'সীরাতের শিক্ষা',
            'আত্মশুদ্ধির পথ', 'তাওবার শিক্ষা', 'সবর ও শোকর', 'আখিরাতের প্রস্তুতি', 'জীবন গঠনের নসীহত',
            'বাংলা ইসলামিক পডকাস্ট', 'আলেমদের প্রশ্নোত্তর', 'সমসাময়িক আলোচনা', 'ইসলামী তরুণ মজলিস', 'খুতবা বাংলা',
            'ওয়াজ মাহফিল সংগ্রহ', 'তাফসীর পর্ব', 'কুরআন তিলাওয়াত', 'হামদ ও নাত', 'বাংলা নাশীদ',
            'ইসলামী কার্টুন', 'শিশুদের কুরআন শিক্ষা', 'পরিবারের সাপ্তাহিক পাঠ', 'ইসলামী কুইজ', 'মাদরাসা শিক্ষা',
            'সুন্দর চরিত্র', 'হালাল জীবনধারা', 'ইসলামী অর্থনীতি', 'দাওয়াহর ইতিহাস', 'সত্যের সন্ধান',
            'প্রশ্ন করুন আলেমকে', 'বাংলা ইসলামী সংলাপ', 'ইসলামী বই আলোচনা', 'জুমার বিশেষ অনুষ্ঠান', 'বাংলা লাইভ ইসলামিক অনুষ্ঠান',
        ];
        $collectionSources = [
            'https://www.youtube.com/@siraatmediabd/videos', 'https://www.youtube.com/@IslamHouseBn/videos',
            'https://www.youtube.com/@TawhidMediabd/videos', 'https://www.youtube.com/@QuranerAlo-fh1hp',
            'https://www.youtube.com/@healappbd/videos', 'https://www.youtube.com/@deenpriyo/videos',
            'https://www.youtube.com/@NewBanglawazlive/videos', 'https://www.youtube.com/@Bangla_Islamic_Television',
            'https://www.youtube.com/@SalafiDawahB/videos', 'https://madanichannelbangla.tv/',
        ];
        foreach ($collectionNames as $index => $name) {
            $series[] = [
                'name' => $name,
                'url' => $collectionSources[$index % count($collectionSources)],
                'description' => 'Curated Bangla Islamic collection from a public publisher source; episode titles and availability are maintained by the publisher.',
            ];
        }
        foreach ($series as $show) {
            DB::table('entertainments')->insert([
                'name' => $show['name'], 'description' => $show['description'],
                'trailer_url_type' => 'youtube', 'trailer_url' => $show['url'],
                'type' => 'tvshow', 'movie_access' => 'free', 'language' => 'Bangla',
                'content_rating' => 'family', 'release_date' => $now->toDateString(), 'video_upload_type' => 'youtube',
                'video_url_input' => $show['url'], 'status' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // A real public Bangla Islamic live broadcast. The YouTube live URL is
        // embedded by the existing player; no stream is copied to this server.
        DB::table('live_tv_category')->insert([
            'id' => 1, 'name' => 'Bangla Islamic Live', 'slug' => 'bangla-islamic-live',
            'description' => 'Public Bangla Islamic live broadcasts.', 'status' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('live_tv_channel')->insert([
            'id' => 1, 'name' => 'Muslim Bangla Live', 'poster_url' => 'https://i.ytimg.com/vi/RWErRp6i4Pg/hqdefault.jpg',
            'category_id' => 1, 'thumb_url' => 'https://i.ytimg.com/vi/RWErRp6i4Pg/hqdefault.jpg',
            'access' => 'free', 'description' => 'Bangla Islamic live programming from the public Muslim Bangla channel.',
            'status' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('live_tv_stream_content_mapping')->insert([
            'id' => 1, 'tv_channel_id' => 1, 'type' => 'youtube', 'stream_type' => 'youtube',
            'server_url' => 'https://www.youtube.com/live/RWErRp6i4Pg',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        Schema::enableForeignKeyConstraints();
    }
}
