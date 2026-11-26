<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LIMITATIONS = [
        'device-limit' => ['Device Limit', 'Maximum registered devices for a subscriber account.'],
        'profile-limit' => ['Profile Limit', 'Maximum profiles for a subscriber account.'],
        'simultaneous-streams' => ['Simultaneous Streams', 'Maximum concurrent playback sessions for an account.'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('planlimitation')) return;
        foreach (self::LIMITATIONS as $slug => [$title, $description]) {
            if (!DB::table('planlimitation')->where('slug', $slug)->exists()) {
                DB::table('planlimitation')->insert([
                    'title' => $title,
                    'slug' => $slug,
                    'description' => $description,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // These definitions may be referenced by plan mappings; preserve them on rollback.
    }
};
