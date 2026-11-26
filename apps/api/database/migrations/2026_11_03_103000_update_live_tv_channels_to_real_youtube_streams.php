<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    /**
     * Real YouTube live stream channels
     */
    private array $channels = [
        [
            'id' => '01940000-0008-7000-8000-000000000001',
            'name' => 'Makkah Live - Masjid Al-Haram',
            'name_bn' => 'মক্কা লাইভ - মসজিদুল হারাম',
            'description' => 'Makkah, Saudi Arabia • Live 24/7 panoramic broadcast from the Holy Kaaba.',
            'description_bn' => 'মক্কা, সৌদি আরব • পবিত্র কাবা শরীফ থেকে ২৪/৭ সরাসরি সম্প্রচার।',
            'youtube_id' => 'D6UqW6TuY74',
        ],
        [
            'id' => '01940000-0008-7000-8000-000000000002',
            'name' => 'Peace TV',
            'name_bn' => 'পিস টিভি',
            'description' => 'Global Islamic television network broadcasting comparative religion, peace discourses and lectures.',
            'description_bn' => 'তুলনামূলক ধর্মতত্ত্ব ও শান্তির বাণী প্রচারকারী আন্তর্জাতিক ইসলামিক টিভি নেটওয়ার্ক।',
            'youtube_id' => '2fCzMatlp8c',
        ],
        [
            'id' => '01940000-0008-7000-8000-000000000003',
            'name' => 'Huda TV',
            'name_bn' => 'হুদা টিভি',
            'description' => 'Family-safe satellite broadcast broadcasting in English with international scholars.',
            'description_bn' => 'আন্তর্জাতিক আলেমদের অংশগ্রহণে ইংরেজি ভাষার ইসলামিক স্যাটেলাইট সম্প্রচার।',
            'youtube_id' => '_3Wz8XYJTl0',
        ],
        [
            'id' => '01940000-0008-7000-8000-000000000004',
            'name' => 'Somoy TV Live',
            'name_bn' => 'সময় টিভি লাইভ',
            'description' => 'Continuous live satellite news, current affairs and international coverage.',
            'description_bn' => '২৪ ঘণ্টা সরাসরি সংবাদ ও সাম্প্রতিক আন্তর্জাতিক ঘটনাবলীর সম্প্রচার।',
            'youtube_id' => 'eNqoneA3ISM',
        ],
        [
            'id' => '01940000-0008-7000-8000-000000000005',
            'name' => 'ATN Bangla Islamic',
            'name_bn' => 'এটিএন বাংলা ইসলামিক',
            'description' => 'Bengali Islamic satellite broadcasting educational lectures and question-answer sessions.',
            'description_bn' => 'বাংলা ইসলামিক অনুষ্ঠান, প্রশ্নোত্তর ও বয়ান সম্প্রচার।',
            'youtube_id' => 'ai1M6MeSnz4',
        ],
        [
            'id' => '01940000-0008-7000-8000-000000000006',
            'name' => 'Madinah Live - Al-Masjid An-Nabawi',
            'name_bn' => 'মদিনা লাইভ - মসজিদে নববী',
            'description' => '24/7 direct broadcast from the Prophet\'s Mosque and Rawdah Ash-Sharifah.',
            'description_bn' => 'মসজিদে নববী ও রওজা মোবারক থেকে ২৪ ঘণ্টা সরাসরি সম্প্রচার।',
            'youtube_id' => 'Rs7St51oDDc',
        ],
        [
            'id' => '01940000-0008-7000-8000-000000000007',
            'name' => 'Quran Kareem TV',
            'name_bn' => 'কুরআন কারীম টিভি',
            'description' => 'Continuous live Holy Quran recitation accompanied by breathtaking Haramain visuals.',
            'description_bn' => 'পবিত্র মক্কার মনোরম দৃশ্যাবলীসহ অবিরাম পবিত্র কুরআন তিলাওয়াত সম্প্রচার।',
            'youtube_id' => 'eC4LfEVxvKg',
        ],
        [
            'id' => '01940000-0008-7000-8000-000000000008',
            'name' => 'Islam Channel UK',
            'name_bn' => 'ইসলাম চ্যানেল ইউকে',
            'description' => 'London-based international broadcaster delivering news, current affairs, and family talk shows.',
            'description_bn' => 'লন্ডন ভিত্তিক আন্তর্জাতিক ইসলামিক চ্যানেল, সংবাদ ও পরিবারবিষয়ক অনুষ্ঠান।',
            'youtube_id' => 'E36zeBnKPKM',
        ],
        [
            'id' => '01940000-0008-7000-8000-000000000009',
            'name' => 'Masjid Al-Aqsa Live Broadcast',
            'name_bn' => 'মসজিদুল আকসা সরাসরি সম্প্রচার',
            'description' => 'Al-Quds (Jerusalem) • Continuous peaceful panoramic transmission from the revered third holy sanctuary.',
            'description_bn' => 'আল-কুদস (জেরুজালেম) • ইসলামের তৃতীয় পবিত্রতম স্থান মসজিদুল আকসা থেকে সরাসরি সম্প্রচার।',
            'youtube_id' => '-_DYtXyeWjo',
        ],
        [
            'id' => '01940000-0008-7000-8000-00000000000a',
            'name' => 'Eman Channel',
            'name_bn' => 'ইমান চ্যানেল',
            'description' => 'Inspiring educational network featuring thought-provoking lectures, youth segments and recitations.',
            'description_bn' => 'জ্ঞানগর্ভ ইসলামিক আলোচনা, যুব সমাজের জন্য দিকনির্দেশনা ও তিলাওয়াত।',
            'youtube_id' => 'YvcIzux51y8',
        ],
        [
            'id' => '01940000-0008-7000-8000-00000000000b',
            'name' => 'Bayan TV (Waz & Tilawah)',
            'name_bn' => 'বায়ান টিভি (ওয়াজ ও তিলাওয়াত)',
            'description' => 'Dedicated channel showcasing uplifting spiritual discourses, Bengali bayans and Quran recitations.',
            'description_bn' => 'আত্মশুদ্ধিমূলক ইসলামিক বয়ান, বাংলা ওয়াজ ও সুললিত কুরআন তিলাওয়াত।',
            'youtube_id' => 'ztQhCuC_uKc',
        ],
        [
            'id' => '01940000-0008-7000-8000-00000000000c',
            'name' => 'Noor TV International',
            'name_bn' => 'নূর টিভি ইন্টারন্যাশনাল',
            'description' => 'Global Islamic television focusing on educational, cultural and spiritual development of Muslim communities.',
            'description_bn' => 'বিশ্বজুড়ে মুসলিম সমাজের শিক্ষা, সংস্কৃতি ও আধ্যাত্মিক উন্নয়নে নিবেদিত টিভি।',
            'youtube_id' => 'I4QAB26YqSI',
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->channels as $ch) {
            $youtubeUrl = "https://www.youtube.com/watch?v={$ch['youtube_id']}";
            $posterUrl = "https://i.ytimg.com/vi/{$ch['youtube_id']}/hqdefault.jpg";

            // 1. Update channel details in live_tv_channel
            DB::table('live_tv_channel')
                ->where('id', $ch['id'])
                ->update([
                    'name' => $ch['name'],
                    'name_bn' => $ch['name_bn'],
                    'description' => $ch['description'],
                    'description_bn' => $ch['description_bn'],
                    'poster_url' => $posterUrl,
                    'thumb_url' => $posterUrl,
                    'poster_tv_url' => $posterUrl,
                    'updated_at' => now(),
                ]);

            // 2. Update or insert YouTube mapping in live_tv_stream_content_mapping
            $existing = DB::table('live_tv_stream_content_mapping')
                ->where('tv_channel_id', $ch['id'])
                ->first();

            if ($existing) {
                DB::table('live_tv_stream_content_mapping')
                    ->where('id', $existing->id)
                    ->update([
                        'type' => 't_url',
                        'stream_type' => 'YouTube',
                        'server_url' => $youtubeUrl,
                        'embedded' => null,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('live_tv_stream_content_mapping')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid7(),
                    'tv_channel_id' => $ch['id'],
                    'type' => 't_url',
                    'stream_type' => 'YouTube',
                    'server_url' => $youtubeUrl,
                    'embedded' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Clear caches
        Cache::flush();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->channels as $ch) {
            DB::table('live_tv_stream_content_mapping')
                ->where('tv_channel_id', $ch['id'])
                ->update([
                    'type' => 'hls',
                    'stream_type' => 'HLS',
                    'server_url' => 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
                    'updated_at' => now(),
                ]);
        }

        Cache::flush();
    }
};
