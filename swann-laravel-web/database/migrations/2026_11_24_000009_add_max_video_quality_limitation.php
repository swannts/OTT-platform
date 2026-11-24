<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('planlimitation') && !DB::table('planlimitation')->where('slug', 'max-video-quality')->exists()) {
            DB::table('planlimitation')->insert([
                'title' => 'Maximum Video Quality',
                'slug' => 'max-video-quality',
                'description' => 'Maximum video rendition height allowed for playback.',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Preserve subscription plan definitions and mappings on rollback.
    }
};
