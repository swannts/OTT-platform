<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plan') && ! Schema::hasColumn('plan', 'name_translations')) {
            Schema::table('plan', function (Blueprint $table): void {
                $table->json('name_translations')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('plan') && Schema::hasColumn('plan', 'name_translations')) {
            Schema::table('plan', function (Blueprint $table): void {
                $table->dropColumn('name_translations');
            });
        }
    }
};
