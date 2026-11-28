<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('web_homepage_configs')->insertOrIgnore([
            'id' => 1,
            'config' => json_encode([
                'sections' => [
                    ['key' => 'latest-movies', 'enabled' => true, 'title' => 'Trending Now', 'title_bn' => 'এখন ট্রেন্ডিং', 'content_ids' => null],
                    ['key' => 'live-now', 'enabled' => true, 'title' => 'Live Now', 'title_bn' => 'সরাসরি সম্প্রচার', 'content_ids' => null],
                    ['key' => 'top-10', 'enabled' => true, 'title' => 'Top 10 Today', 'title_bn' => 'আজকের শীর্ষ ১০', 'content_ids' => null],
                    ['key' => 'popular-tvshows', 'enabled' => true, 'title' => 'Popular TV Shows & Series', 'title_bn' => 'জনপ্রিয় টিভি শো ও সিরিজ', 'content_ids' => null],
                    ['key' => 'popular-movies', 'enabled' => true, 'title' => 'Popular Movies', 'title_bn' => 'জনপ্রিয় সিনেমা', 'content_ids' => null],
                    ['key' => 'pay-per-view', 'enabled' => true, 'title' => 'Pay-Per-View Exclusives', 'title_bn' => 'পে-পার-ভিউ এক্সক্লুসিভ', 'content_ids' => null],
                ],
                'premium_card' => [
                    'enabled' => true,
                    'title' => 'Go Premium',
                    'description' => 'Unlock exclusive content, ad-free experience and more.',
                    'image_url' => '',
                    'cta' => 'Upgrade Now',
                    'url' => '/pricing',
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'updated_by' => null,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Keep this row on rollback because an administrator may have edited it
        // after installation; deleting it would discard user-managed settings.
    }
};
