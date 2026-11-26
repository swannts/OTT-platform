<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playback_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_id')->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('device_token_id')->index();
            $table->unsignedBigInteger('profile_id')->index();
            $table->string('content_id', 64);
            $table->string('content_type', 16);
            $table->timestamp('last_heartbeat_at')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('ended_at')->nullable()->index();
            $table->timestamps();
            $table->index(['user_id', 'ended_at', 'expires_at'], 'playback_sessions_active_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('playback_sessions') && DB::table('playback_sessions')->exists()) {
            throw new RuntimeException('Playback sessions exist; retain the table and roll back application code only.');
        }
        Schema::dropIfExists('playback_sessions');
    }
};
