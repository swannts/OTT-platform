<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plan') && !Schema::hasColumn('plan', 'trial_days')) {
            Schema::table('plan', function (Blueprint $table): void {
                $table->unsignedSmallInteger('trial_days')->default(0)->after('duration_value');
            });
        }

        if (Schema::hasTable('subscriptions') && !Schema::hasColumn('subscriptions', 'is_trial')) {
            Schema::table('subscriptions', function (Blueprint $table): void {
                $table->boolean('is_trial')->default(false)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions', 'is_trial')) {
            if (DB::table('subscriptions')->where('is_trial', true)->exists()) {
                throw new RuntimeException('Cannot remove is_trial while trial subscription history exists.');
            }
            Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn('is_trial'));
        }

        if (Schema::hasTable('plan') && Schema::hasColumn('plan', 'trial_days')) {
            if (DB::table('plan')->where('trial_days', '>', 0)->exists()) {
                throw new RuntimeException('Cannot remove trial_days while trial policies are configured.');
            }
            Schema::table('plan', fn (Blueprint $table) => $table->dropColumn('trial_days'));
        }
    }
};
