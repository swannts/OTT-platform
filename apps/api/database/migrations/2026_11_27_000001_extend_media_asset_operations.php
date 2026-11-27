<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('media_assets')) {
            Schema::table('media_assets', function (Blueprint $table): void {
                if (! Schema::hasColumn('media_assets', 'display_name')) $table->string('display_name', 255)->nullable();
                if (! Schema::hasColumn('media_assets', 'category')) $table->string('category', 32)->default('video')->index();
                if (! Schema::hasColumn('media_assets', 'description')) $table->text('description')->nullable();
                if (! Schema::hasColumn('media_assets', 'quality_status')) $table->string('quality_status', 32)->default('pending_inspection')->index();
                if (! Schema::hasColumn('media_assets', 'quality_notes')) $table->text('quality_notes')->nullable();
                if (! Schema::hasColumn('media_assets', 'quality_reviewed_by')) $table->foreignId('quality_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                if (! Schema::hasColumn('media_assets', 'quality_reviewed_at')) $table->timestamp('quality_reviewed_at')->nullable();
                if (! Schema::hasColumn('media_assets', 'archived_at')) $table->timestamp('archived_at')->nullable()->index();
                if (! Schema::hasColumn('media_assets', 'archived_by')) $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
                if (! Schema::hasColumn('media_assets', 'archive_reason')) $table->string('archive_reason', 500)->nullable();
                if (! Schema::hasColumn('media_assets', 'is_active_version')) $table->boolean('is_active_version')->default(false)->index();
            });
        }

        if (Schema::hasTable('media_processing_jobs')) {
            Schema::table('media_processing_jobs', function (Blueprint $table): void {
                if (! Schema::hasColumn('media_processing_jobs', 'stage')) $table->string('stage', 64)->nullable();
                if (! Schema::hasColumn('media_processing_jobs', 'progress')) $table->unsignedTinyInteger('progress')->default(0);
                if (! Schema::hasColumn('media_processing_jobs', 'priority')) $table->unsignedSmallInteger('priority')->default(100)->index();
                if (! Schema::hasColumn('media_processing_jobs', 'worker_name')) $table->string('worker_name', 128)->nullable();
                if (! Schema::hasColumn('media_processing_jobs', 'failure_message')) $table->text('failure_message')->nullable();
            });
        }

        if (Schema::hasTable('media_upload_sessions')) {
            Schema::table('media_upload_sessions', function (Blueprint $table): void {
                if (! Schema::hasColumn('media_upload_sessions', 'chunk_checksums')) $table->json('chunk_checksums')->nullable();
                if (! Schema::hasColumn('media_upload_sessions', 'category')) $table->string('category', 32)->default('video');
                if (! Schema::hasColumn('media_upload_sessions', 'display_name')) $table->string('display_name', 255)->nullable();
            });
        }

        if (Schema::hasTable('media_asset_tracks')) {
            Schema::table('media_asset_tracks', function (Blueprint $table): void {
                if (! Schema::hasColumn('media_asset_tracks', 'display_name')) $table->string('display_name', 255)->nullable();
                if (! Schema::hasColumn('media_asset_tracks', 'role')) $table->string('role', 24)->default('main');
                if (! Schema::hasColumn('media_asset_tracks', 'is_default')) $table->boolean('is_default')->default(false);
                if (! Schema::hasColumn('media_asset_tracks', 'is_forced')) $table->boolean('is_forced')->default(false);
                if (! Schema::hasColumn('media_asset_tracks', 'is_accessibility')) $table->boolean('is_accessibility')->default(false);
                if (! Schema::hasColumn('media_asset_tracks', 'is_original')) $table->boolean('is_original')->default(false);
                if (! Schema::hasColumn('media_asset_tracks', 'is_dubbed')) $table->boolean('is_dubbed')->default(false);
                if (! Schema::hasColumn('media_asset_tracks', 'sort_order')) $table->unsignedSmallInteger('sort_order')->default(0);
                if (! Schema::hasColumn('media_asset_tracks', 'processing_status')) $table->string('processing_status', 24)->default('ready');
            });
        }

        foreach (['episodes', 'videos'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'media_asset_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->foreignId('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
                });
            }
        }

        if (Schema::hasTable('media_assets')) {
            DB::table('media_assets')->whereNull('display_name')->update(['display_name' => DB::raw('original_name')]);
            foreach (['entertainments', 'episodes', 'videos'] as $contentTable) {
                if (Schema::hasTable($contentTable) && Schema::hasColumn($contentTable, 'media_asset_id')) {
                    $references = DB::table($contentTable)->select('media_asset_id')->whereNotNull('media_asset_id');
                    if (Schema::hasColumn($contentTable, 'deleted_at')) $references->whereNull('deleted_at');
                    DB::table('media_assets')->whereIn('id', $references)
                        ->update(['is_active_version' => true]);
                }
            }
        }
    }

    public function down(): void
    {
        // Retain operational metadata and links during rollback to avoid losing audit or
        // playback state. Rollback is intentionally forward-only for populated systems.
    }
};
