<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TITLE = 'The Holy Kaaba: Sanctuary of Monotheism';

    private const IMAGE = 'https://images.unsplash.com/photo-1693590614566-1d3ea9ef32f7?auto=format&fit=crop&w=2400&q=90';

    private const PREVIOUS_IMAGE = 'https://images.unsplash.com/photo-1509316975850-ff9c5deb0cd9?auto=format&fit=crop&w=2400&q=90';

    public function up(): void
    {
        if (! Schema::hasTable('banners')) {
            return;
        }

        DB::table('banners')
            ->where('title', self::TITLE)
            ->where('banner_for', 'home')
            ->update([
                'poster_url' => self::IMAGE,
                'poster_tv_url' => self::IMAGE,
                'file_url' => self::IMAGE,
                'updated_at' => now(),
            ]);

        clearBannerDataCache();
    }

    public function down(): void
    {
        if (! Schema::hasTable('banners')) {
            return;
        }

        DB::table('banners')
            ->where('title', self::TITLE)
            ->where('banner_for', 'home')
            ->update([
                'poster_url' => self::PREVIOUS_IMAGE,
                'poster_tv_url' => self::PREVIOUS_IMAGE,
                'file_url' => self::PREVIOUS_IMAGE,
                'updated_at' => now(),
            ]);

        clearBannerDataCache();
    }
};
