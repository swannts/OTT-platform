<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('planlimitation')) return;
        $exists = DB::table('planlimitation')->where('slug', 'simultaneous-streams')->exists();
        if (!$exists) {
            DB::table('planlimitation')->insert([
                'title' => 'Simultaneous Streams',
                'slug' => 'simultaneous-streams',
                'description' => 'Maximum concurrent playback sessions for an account.',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Preserve plan definitions and mappings; remove them only through an explicit data migration.
    }
};
