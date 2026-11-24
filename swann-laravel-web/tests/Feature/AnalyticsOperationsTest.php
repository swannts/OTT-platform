<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RbacFoundationSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Entertainment\Models\ContinueWatch;
use Modules\Entertainment\Models\EntertainmentView;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Subscription;
use Tests\TestCase;

class AnalyticsOperationsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacFoundationSeeder::class);
    }

    public function test_analytics_manager_gets_real_range_filtered_privacy_aware_aggregates(): void
    {
        Carbon::setTestNow('2026-11-24 12:00:00');
        $manager = User::factory()->create(); $manager->assignRole('analytics_manager');
        $viewers = User::factory()->count(5)->create(['country_code' => 'BD']);
        $movieId = 123456789;
        DB::table('entertainments')->insert(['id' => (string) $movieId, 'name' => 'Public Feature', 'type' => 'movie', 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
        foreach ($viewers as $viewer) {
            EntertainmentView::query()->create(['entertainment_id' => $movieId, 'user_id' => $viewer->id, 'created_at' => now(), 'updated_at' => now()]);
            \App\Models\UserSearchHistory::query()->create(['user_id' => $viewer->id, 'search_query' => 'popular title', 'created_at' => now(), 'updated_at' => now()]);
        }
        ContinueWatch::query()->create(['user_id' => $viewers[0]->id, 'entertainment_id' => $movieId, 'watched_time' => '7200', 'total_watched_time' => '10800', 'updated_at' => now()]);
        ContinueWatch::query()->create(['user_id' => $viewers[1]->id, 'entertainment_id' => $movieId, 'watched_time' => '3600', 'total_watched_time' => '3600', 'updated_at' => now()]);
        Device::query()->create(['user_id' => $viewers[0]->id, 'device_id' => 'device-analytics', 'device_name' => 'TV', 'platform' => 'Android TV', 'last_activity' => now()]);
        DB::table('playback_diagnostic_events')->insert(['user_id' => $viewers[0]->id, 'event_code' => 'playback.startup_failed', 'duration_ms' => 1400, 'quality' => '1080p', 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $plan = Plan::query()->create(['name' => 'Monthly analytics', 'identifier' => 'monthly-analytics', 'price' => 20, 'duration' => 'month', 'duration_value' => 1, 'status' => 1]);
        $subscription = Subscription::query()->create(['user_id' => $viewers[0]->id, 'plan_id' => $plan->id, 'name' => $plan->name, 'identifier' => $plan->identifier, 'status' => 'active', 'start_date' => now()->subDays(2), 'end_date' => now()->addDays(28), 'amount' => 20, 'total_amount' => 20, 'level' => 1]);
        DB::table('subscriptions_transactions')->insert(['subscriptions_id' => $subscription->id, 'user_id' => $viewers[0]->id, 'amount' => 20, 'currency_code' => 'USD', 'payment_type' => 'stripe', 'payment_status' => 'paid', 'transaction_id' => 'analytics-success', 'created_at' => now(), 'updated_at' => now()]);
        Subscription::query()->create(['user_id' => $viewers[0]->id, 'plan_id' => $plan->id, 'name' => 'Trial', 'identifier' => 'trial-analytics', 'status' => 'expired', 'is_trial' => true, 'start_date' => now()->subDays(14), 'end_date' => now()->subDays(7), 'amount' => 0, 'total_amount' => 0, 'level' => 1]);
        Subscription::query()->create(['user_id' => $viewers[2]->id, 'plan_id' => $plan->id, 'name' => 'Expired', 'identifier' => 'expired-analytics', 'status' => 'expired', 'start_date' => now()->subDays(21), 'end_date' => now()->subDays(3), 'amount' => 20, 'total_amount' => 20, 'level' => 1]);

        $response = $this->actingAs($manager, 'sanctum')->getJson('/api/admin/analytics/overview?from=2026-11-01&to=2026-11-24')->assertOk();
        $response->assertJsonPath('data.audience.active_accounts', 5)
            ->assertJsonPath('data.audience.new_accounts', 5)
            ->assertJsonPath('data.audience.geography.0.country', 'BD')
            ->assertJsonPath('data.audience.popular_search_terms.0.term', 'popular title')
            ->assertJsonPath('data.subscriptions.trial_to_paid_conversions', 1)
            ->assertJsonPath('data.subscriptions.churned_subscriptions', 1)
            ->assertJsonPath('data.subscriptions.new_subscribers', 2)
            ->assertJsonPath('data.content.watch_hours', 3)
            ->assertJsonPath('data.content.completion_rate_percent', 50)
            ->assertJsonPath('data.content.popular_content.0.title', 'Public Feature')
            ->assertJsonPath('data.playback.startup_failures', 1)
            ->assertJsonPath('data.playback.quality_distribution.0.quality', '1080p')
            ->assertJsonPath('data.playback.platform_distribution.0.devices', 1)
            ->assertJsonPath('data.revenue.paid_payment_count', 1)
            ->assertJsonPath('data.subscriptions.mrr_by_currency.0.mrr', 20)
            ->assertJsonPath('data.subscriptions.mrr_by_currency.0.arr', 240);

        $this->assertStringNotContainsString((string) $viewers[0]->email, $response->getContent());
        Carbon::setTestNow();
    }

    public function test_analytics_dashboard_and_export_require_group_and_export_permissions(): void
    {
        $platformAdmin = User::factory()->create(); $platformAdmin->assignRole('platform_admin');
        $this->actingAs($platformAdmin, 'sanctum')->getJson('/api/admin/analytics/overview')->assertOk()
            ->assertJsonStructure(['data' => ['audience', 'subscriptions']])->assertJsonMissingPath('data.revenue');
        $this->get('/api/admin/analytics/export?group=audience')->assertForbidden();

        $analytics = User::factory()->create(); $analytics->assignRole('analytics_manager');
        $response = $this->actingAs($analytics, 'sanctum')->get('/api/admin/analytics/export?group=audience&from=2026-11-01&to=2026-11-24')->assertOk();
        $this->assertStringContainsString('metric,value', $response->streamedContent());
    }

    public function test_scheduled_reports_recheck_permissions_and_only_creator_can_read_results(): void
    {
        Carbon::setTestNow('2026-11-24 10:00:00');
        $manager = User::factory()->create(); $manager->assignRole('analytics_manager');
        $report = $this->actingAs($manager, 'sanctum')->postJson('/api/admin/analytics/reports', [
            'group' => 'audience', 'from' => '2026-11-01', 'to' => '2026-11-24', 'scheduled_at' => now()->addMinute()->toIso8601String(),
        ])->assertCreated()->json('data');
        Carbon::setTestNow('2026-11-24 10:02:00');
        Artisan::call('analytics:generate-scheduled-reports');
        $this->assertDatabaseHas('analytics_report_runs', ['id' => $report['id'], 'status' => 'ready']);
        $this->getJson('/api/admin/analytics/reports/'.$report['id'])->assertOk()->assertJsonPath('data.status', 'ready');

        $other = User::factory()->create(); $other->assignRole('analytics_manager');
        $this->actingAs($other, 'sanctum')->getJson('/api/admin/analytics/reports/'.$report['id'])->assertNotFound();
        Carbon::setTestNow();
    }

    public function test_scheduled_report_fails_if_group_permission_is_revoked_before_run(): void
    {
        Carbon::setTestNow('2026-11-24 10:00:00');
        $manager = User::factory()->create(); $manager->assignRole('analytics_manager');
        $report = $this->actingAs($manager, 'sanctum')->postJson('/api/admin/analytics/reports', [
            'group' => 'revenue', 'from' => '2026-11-01', 'to' => '2026-11-24', 'scheduled_at' => now()->addMinute()->toIso8601String(),
        ])->assertCreated()->json('data');
        $manager->removeRole('analytics_manager');
        $manager->givePermissionTo(['admin.access', 'analytics.export']);
        Carbon::setTestNow('2026-11-24 10:02:00');
        Artisan::call('analytics:generate-scheduled-reports');
        $this->assertDatabaseHas('analytics_report_runs', ['id' => $report['id'], 'status' => 'failed', 'error_code' => 'permission_revoked']);
        $this->getJson('/api/admin/analytics/reports/'.$report['id'])->assertForbidden();
        Carbon::setTestNow();
    }

    public function test_analytics_period_is_bounded_and_requires_a_metric_permission(): void
    {
        $user = User::factory()->create(); $user->assignRole('customer_support');
        $this->actingAs($user, 'sanctum')->getJson('/api/admin/analytics/overview')->assertForbidden();
        $analytics = User::factory()->create(); $analytics->assignRole('analytics_manager');
        $this->actingAs($analytics, 'sanctum')->getJson('/api/admin/analytics/overview?from=2024-01-01&to=2026-11-24')->assertUnprocessable();
    }
}
