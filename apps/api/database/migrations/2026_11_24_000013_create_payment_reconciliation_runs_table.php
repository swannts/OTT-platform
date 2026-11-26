<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_reconciliation_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('verified_payment_id')->constrained('verified_payments')->restrictOnDelete();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->string('provider_status', 32)->nullable();
            $table->unsignedBigInteger('provider_amount_minor')->nullable();
            $table->char('provider_currency', 3)->nullable();
            $table->boolean('amount_matches')->nullable();
            $table->boolean('currency_matches')->nullable();
            $table->boolean('status_matches')->nullable();
            $table->string('result', 24)->index();
            $table->string('failure_code', 100)->nullable();
            $table->timestamps();
            $table->index(['verified_payment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliation_runs');
    }
};
