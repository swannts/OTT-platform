<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verified_payments', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 32);
            $table->string('provider_reference', 191);
            $table->unsignedBigInteger('user_id');
            $table->string('purchase_type', 32);
            $table->unsignedBigInteger('purchase_id');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('fulfillment_type', 32)->nullable();
            $table->unsignedBigInteger('fulfillment_id')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'provider_reference']);
            $table->index(['user_id', 'purchase_type', 'purchase_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verified_payments');
    }
};
