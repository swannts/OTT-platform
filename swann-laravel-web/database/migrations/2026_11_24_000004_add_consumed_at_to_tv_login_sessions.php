<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tv_login_sessions') && !Schema::hasColumn('tv_login_sessions', 'consumed_at')) {
            Schema::table('tv_login_sessions', function (Blueprint $table): void {
                $table->timestamp('consumed_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tv_login_sessions') && Schema::hasColumn('tv_login_sessions', 'consumed_at')) {
            Schema::table('tv_login_sessions', function (Blueprint $table): void {
                $table->dropColumn('consumed_at');
            });
        }
    }
};
