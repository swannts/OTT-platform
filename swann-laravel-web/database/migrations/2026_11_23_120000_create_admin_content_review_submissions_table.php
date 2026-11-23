<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_content_review_submissions', function (Blueprint $table): void {
            $table->id();
            $table->string('content_type', 32)->default('entertainment');
            $table->string('content_id', 64);
            $table->unsignedInteger('revision');
            $table->string('status', 24)->default('in_review');
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('snapshot');
            $table->char('snapshot_hash', 64);
            $table->text('review_note')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['content_type', 'content_id', 'status'], 'acr_content_status_idx');
            $table->index(['status', 'submitted_at'], 'acr_status_submitted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_content_review_submissions');
    }
};
