<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('entertainments') || Schema::hasColumn('entertainments', 'media_asset_id')) {
            return;
        }

        Schema::table('entertainments', function (Blueprint $table): void {
            $table->foreignId('media_asset_id')->nullable()->after('id')->constrained('media_assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('entertainments') || !Schema::hasColumn('entertainments', 'media_asset_id')) {
            return;
        }
        if (DB::table('entertainments')->whereNotNull('media_asset_id')->exists()) {
            throw new RuntimeException('Content is linked to media assets; retain this column and roll back application code only.');
        }
        Schema::table('entertainments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('media_asset_id');
        });
    }
};
