<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/** Seeds one playable, clearly labeled local Live TV fixture. */
class LocalDemoLiveTvSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Local Live TV demo data can only be seeded in local or testing environments.');
        }

        $now = now();
        $adminId = DB::table('users')->where('email', 'admin@swann.com')->value('id');
        $categorySlug = 'demo-live-channels';
        $category = DB::table('live_tv_category')->where('slug', $categorySlug)->first();
        $categoryId = $category->id ?? (string) Str::uuid7();

        DB::table('live_tv_category')->updateOrInsert(
            ['slug' => $categorySlug],
            [
                'id' => $categoryId,
                'name' => 'Demo Live Channels',
                'description' => 'Local demo channels using publicly available broadcasts.',
                'status' => 1,
                'created_by' => $adminId,
                'updated_by' => $adminId,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
        );

        $channelSlug = 'demo-live-channel';
        $existingChannel = DB::table('live_tv_channel')->where('slug', $channelSlug)->first();
        $channelId = $existingChannel?->id ?: (string) Str::uuid7();
        $streamUrl = 'https://www.youtube.com/watch?v=XcjKDB1bj1w';
        $posterUrl = 'https://i.ytimg.com/vi/XcjKDB1bj1w/hqdefault.jpg';

        DB::table('live_tv_channel')->updateOrInsert(
            ['slug' => $channelSlug],
            [
                'id' => $channelId,
                'name' => 'Demo: KSA Qur’an TV — Makkah',
                'name_bn' => 'ডেমো: সৌদি কুরআন টিভি — মক্কা',
                'category_id' => $categoryId,
                'poster_url' => $posterUrl,
                'thumb_url' => $posterUrl,
                'poster_tv_url' => $posterUrl,
                'description' => 'Local playback fixture using a public live broadcast from the official KSA Qur’an TV YouTube channel.',
                'description_bn' => 'অফিশিয়াল KSA Qur’an TV ইউটিউব চ্যানেলের প্রকাশ্য সরাসরি সম্প্রচার ব্যবহার করা স্থানীয় প্লেব্যাক নমুনা।',
                'access' => 'free',
                'status' => 1,
                'created_by' => $adminId,
                'updated_by' => $adminId,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
        );

        $existingMappingId = DB::table('live_tv_stream_content_mapping')
            ->where('tv_channel_id', $channelId)
            ->value('id');

        DB::table('live_tv_stream_content_mapping')->updateOrInsert(
            ['tv_channel_id' => $channelId],
            [
                'id' => $existingMappingId ?: (string) Str::uuid7(),
                'type' => 't_url',
                'stream_type' => 'YouTube',
                'server_url' => $streamUrl,
                'embedded' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
        );

        if (function_exists('clearLiveTvDashboardCache')) {
            clearLiveTvDashboardCache();
        }
    }
}
