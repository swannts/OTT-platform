<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            $table->unsignedInteger('max_redemptions')->nullable()->after('discount');
            $table->unsignedInteger('per_user_limit')->nullable()->after('max_redemptions');
        });

        Schema::create('subscription_coupon_reservations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('provider', 32)->nullable();
            $table->string('provider_reference')->nullable()->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('redeemed_at')->nullable();
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_coupon_reservations');
        Schema::table('coupons', function (Blueprint $table): void {
            $table->dropColumn(['max_redemptions', 'per_user_limit']);
        });
    }
};
