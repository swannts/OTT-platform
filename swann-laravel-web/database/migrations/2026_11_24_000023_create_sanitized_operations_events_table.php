<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_code', 80);
            $table->string('service', 40);
            $table->string('severity', 16);
            $table->string('summary', 160);
            $table->timestamp('window_started_at');
            $table->unsignedInteger('occurrence_count')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['created_at', 'id']);
            $table->index(['event_code', 'created_at']);
            $table->unique(['event_code', 'window_started_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('operations_events') && DB::table('operations_events')->exists()) {
            throw new RuntimeException('Refusing to drop operations event history. Preserve or archive it before rollback.');
        }

        Schema::dropIfExists('operations_events');
    }
};
