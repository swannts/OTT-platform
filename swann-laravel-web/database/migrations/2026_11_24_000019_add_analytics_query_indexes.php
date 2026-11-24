<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $indexes = [
        'entertainment_views' => ['analytics_ent_views_date_content_idx', 'analytics_ent_views_date_user_idx'],
        'continue_watch' => ['analytics_continue_watch_updated_idx'],
        'user_search_histories' => ['analytics_search_histories_date_idx'],
        'subscriptions' => ['analytics_subscriptions_start_trial_user_idx', 'analytics_subscriptions_end_status_trial_idx'],
        'subscriptions_transactions' => ['analytics_sub_tx_created_status_currency_idx', 'analytics_sub_tx_subscription_status_idx'],
        'payperviewstransactions' => ['analytics_ppv_tx_created_status_idx'],
        'devices' => ['analytics_devices_activity_platform_idx'],
    ];

    public function up(): void
    {
        if (Schema::hasTable('entertainment_views')) Schema::table('entertainment_views', function (Blueprint $table): void {
            $table->index(['created_at', 'entertainment_id'], 'analytics_ent_views_date_content_idx');
            $table->index(['created_at', 'user_id'], 'analytics_ent_views_date_user_idx');
        });
        if (Schema::hasTable('continue_watch')) Schema::table('continue_watch', fn (Blueprint $table) => $table->index(['updated_at', 'deleted_at'], 'analytics_continue_watch_updated_idx'));
        if (Schema::hasTable('user_search_histories')) Schema::table('user_search_histories', fn (Blueprint $table) => $table->index(['created_at'], 'analytics_search_histories_date_idx'));
        if (Schema::hasTable('subscriptions')) Schema::table('subscriptions', function (Blueprint $table): void {
            $table->index(['start_date', 'is_trial', 'user_id'], 'analytics_subscriptions_start_trial_user_idx');
            $table->index(['end_date', 'status', 'is_trial'], 'analytics_subscriptions_end_status_trial_idx');
        });
        if (Schema::hasTable('subscriptions_transactions')) Schema::table('subscriptions_transactions', function (Blueprint $table): void {
            $table->index(['created_at', 'payment_status', 'currency_code'], 'analytics_sub_tx_created_status_currency_idx');
            $table->index(['subscriptions_id', 'payment_status', 'id'], 'analytics_sub_tx_subscription_status_idx');
        });
        if (Schema::hasTable('payperviewstransactions')) Schema::table('payperviewstransactions', fn (Blueprint $table) => $table->index(['created_at', 'payment_status'], 'analytics_ppv_tx_created_status_idx'));
        if (Schema::hasTable('devices')) Schema::table('devices', fn (Blueprint $table) => $table->index(['last_activity', 'platform', 'user_id'], 'analytics_devices_activity_platform_idx'));
    }

    public function down(): void
    {
        foreach ($this->indexes as $tableName => $indexes) {
            if (!Schema::hasTable($tableName)) continue;
            Schema::table($tableName, function (Blueprint $table) use ($indexes): void {
                foreach ($indexes as $index) $table->dropIndex($index);
            });
        }
    }
};
