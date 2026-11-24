<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_upload_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_name', 255);
            $table->unsignedBigInteger('total_size');
            $table->unsignedInteger('chunk_size');
            $table->unsignedInteger('chunk_count');
            $table->unsignedBigInteger('replaces_asset_id')->nullable();
            $table->char('expected_sha256', 64)->nullable();
            $table->string('status', 24)->default('uploading')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('media_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('replaces_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('original_name', 255);
            $table->string('disk', 32)->default('local');
            $table->string('source_path', 1024);
            $table->string('rendition_path', 1024)->nullable();
            $table->string('mime_type', 127);
            $table->string('extension', 16);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('status', 24)->default('uploaded')->index();
            $table->json('metadata')->nullable();
            $table->json('tracks')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['uploaded_by', 'created_at']);
            $table->index(['sha256', 'version']);
        });

        Schema::create('media_processing_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('status', 24)->default('queued')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->char('processing_token', 32)->nullable()->index();
            $table->string('idempotency_key', 96)->unique();
            $table->string('failure_code', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['media_asset_id', 'status']);
        });

        Schema::create('media_asset_tracks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 16);
            $table->string('language', 12);
            $table->string('original_name', 255);
            $table->string('disk', 32)->default('private');
            $table->string('path', 1024);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['media_asset_id', 'kind', 'language']);
        });

        if (Schema::hasTable('entertainments') && !Schema::hasColumn('entertainments', 'media_asset_id')) {
            Schema::table('entertainments', function (Blueprint $table): void {
                $table->foreignId('media_asset_id')->nullable()->after('id')->constrained('media_assets')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('media_assets') && DB::table('media_assets')->exists()) {
            throw new RuntimeException('Media assets exist; refusing to roll back and delete platform media records.');
        }
        if (Schema::hasTable('media_upload_sessions') && DB::table('media_upload_sessions')->exists()) {
            throw new RuntimeException('Media upload sessions exist; refusing to roll back and delete upload state.');
        }
        Schema::dropIfExists('media_asset_tracks');
        if (Schema::hasTable('entertainments') && Schema::hasColumn('entertainments', 'media_asset_id')) {
            Schema::table('entertainments', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('media_asset_id');
            });
        }
        Schema::dropIfExists('media_processing_jobs');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('media_upload_sessions');
    }
};
