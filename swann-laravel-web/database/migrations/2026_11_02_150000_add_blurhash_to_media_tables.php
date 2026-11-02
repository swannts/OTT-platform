<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = ['entertainments', 'episodes', 'banners', 'videos', 'filemanagers'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'blurhash')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->string('blurhash', 64)->nullable()->after('updated_at');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['entertainments', 'episodes', 'banners', 'videos', 'filemanagers'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'blurhash')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('blurhash');
                });
            }
        }
    }
};
