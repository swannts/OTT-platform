<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('verified_payment_id')->constrained('verified_payments')->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->text('reason');
            $table->string('status', 24)->default('pending')->index();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->string('provider_refund_id')->nullable()->unique();
            $table->string('failure_code', 100)->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();
            $table->index(['verified_payment_id', 'status']);
        });

        Schema::create('payment_provider_events', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway', 32);
            $table->string('event_id', 191);
            $table->string('event_type', 100);
            $table->string('provider_reference', 191)->nullable();
            $table->char('payload_sha256', 64);
            $table->string('status', 20)->default('received')->index();
            $table->unsignedInteger('attempts')->default(1);
            $table->string('failure_code', 100)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'event_id']);
        });

        Schema::create('payment_disputes', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway', 32);
            $table->string('provider_dispute_id', 191);
            $table->string('provider_reference', 191)->nullable()->index();
            $table->foreignId('verified_payment_id')->nullable()->constrained('verified_payments')->nullOnDelete();
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('status', 32)->index();
            $table->string('reason', 100)->nullable();
            $table->timestamp('evidence_due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'provider_dispute_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_disputes');
        Schema::dropIfExists('payment_provider_events');
        Schema::dropIfExists('refund_requests');
    }
};
