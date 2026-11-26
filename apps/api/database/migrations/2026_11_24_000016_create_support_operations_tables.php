<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('created_by')->index();
            $table->unsignedBigInteger('assigned_to')->nullable()->index();
            $table->string('subject', 180);
            $table->text('description');
            $table->string('category', 40)->default('general');
            $table->string('priority', 16)->default('normal')->index();
            $table->string('status', 24)->default('open')->index();
            $table->string('escalation_target', 32)->nullable()->index();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority', 'created_at']);
        });

        Schema::create('support_ticket_notes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('ticket_id')->index();
            $table->unsignedBigInteger('author_id')->index();
            $table->text('note');
            $table->boolean('is_internal')->default(true);
            $table->timestamps();
        });

        Schema::create('playback_diagnostic_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('device_id')->nullable()->index();
            $table->uuid('session_id')->nullable()->index();
            $table->string('event_code', 48)->index();
            $table->string('content_type', 16)->nullable();
            $table->string('content_id', 64)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['support_ticket_notes', 'support_tickets', 'playback_diagnostic_events'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Support records exist; retain support operations data and roll back application code only.');
            }
        }
        foreach (['support_ticket_notes', 'support_tickets', 'playback_diagnostic_events'] as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
            }
        }
    }
};
