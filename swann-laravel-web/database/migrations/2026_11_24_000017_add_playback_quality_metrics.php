<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('playback_diagnostic_events')) return;
        Schema::table('playback_diagnostic_events', function (Blueprint $table): void {
            if (!Schema::hasColumn('playback_diagnostic_events', 'duration_ms')) $table->unsignedInteger('duration_ms')->nullable();
            if (!Schema::hasColumn('playback_diagnostic_events', 'quality')) $table->string('quality', 16)->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('playback_diagnostic_events')) return;
        if (DB::table('playback_diagnostic_events')->whereNotNull('duration_ms')->orWhereNotNull('quality')->exists()) {
            throw new RuntimeException('Playback quality metrics exist; retain analytics data and roll back application code only.');
        }
        Schema::table('playback_diagnostic_events', function (Blueprint $table): void {
            if (Schema::hasColumn('playback_diagnostic_events', 'duration_ms')) $table->dropColumn('duration_ms');
            if (Schema::hasColumn('playback_diagnostic_events', 'quality')) $table->dropColumn('quality');
        });
    }
};
