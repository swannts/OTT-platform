<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RbacFoundationSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Subscription;
use Modules\Coupon\Models\Coupon;
use Tests\TestCase;

class AdminSubscriptionManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacFoundationSeeder::class);
    }

    public function test_public_api_no_longer_exposes_plan_mutations_and_public_catalog_remains_available(): void
    {
        $this->getJson('/api/plans')->assertNotFound();
        $this->postJson('/api/plans', [])->assertNotFound();
        $this->getJson('/api/plan-list')->assertOk();
    }

    public function test_subscription_manager_can_create_edit_and_archive_without_rewriting_subscriber_snapshots(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('subscription_manager');
        $sessionPermissions = $this->actingAs($manager, 'sanctum')->getJson('/api/admin/me')
            ->assertOk()->json('user.permissions');
        $this->assertContains('subscriptions.plans.update', $sessionPermissions);
        $this->assertContains('subscriptions.plans.archive', $sessionPermissions);
        $created = $this->actingAs($manager, 'sanctum')->postJson('/api/admin/subscriptions/plans', [
            'name' => 'Family monthly', 'name_translations' => ['en' => 'Family monthly', 'bn' => 'পরিবার মাসিক'],
            'identifier' => 'family-monthly-'.Str::lower(Str::random(5)),
            'price' => 9.99, 'level' => 3, 'duration' => 'month', 'duration_value' => 1,
            'description' => 'Family access', 'status' => true,
            'limits' => ['device_limit' => 4, 'profile_limit' => 5, 'simultaneous_streams' => 2],
        ])->assertCreated()->assertJsonPath('data.name_translations.bn', 'পরিবার মাসিক')->assertJsonPath('data.entitlements.device-limit', 4)
            ->assertJsonPath('data.entitlements.profile-limit', 5)
            ->assertJsonPath('data.entitlements.simultaneous-streams', 2)->json('data');

        $this->getJson('/api/v3/subscription/plans', ['app-locale' => 'bn'])
            ->assertOk()->assertJsonFragment(['localized_name' => 'পরিবার মাসিক']);

        $subscriber = User::factory()->create();
        Subscription::query()->create([
            'user_id' => $subscriber->id, 'plan_id' => $created['id'], 'name' => 'Family monthly',
            'identifier' => $created['identifier'], 'status' => 'active', 'start_date' => now(),
            'end_date' => now()->addMonth(), 'amount' => 9.99, 'total_amount' => 9.99,
            'level' => 3, 'plan_type' => '[{"slug":"profile-limit","limit":{"value":4}}]',
        ]);

        $this->putJson('/api/admin/subscriptions/plans/'.$created['id'], ['price' => 12.99, 'level' => 4])
            ->assertOk()->assertJsonPath('data.price', 12.99);
        $this->assertDatabaseHas('plan', ['id' => $created['id'], 'name_translations->bn' => 'পরিবার মাসিক']);
        $this->assertDatabaseHas('subscriptions', ['plan_id' => $created['id'], 'name' => 'Family monthly', 'amount' => 9.99, 'level' => 3]);

        $this->postJson('/api/admin/subscriptions/plans/'.$created['id'].'/archive')->assertOk();
        $this->assertDatabaseHas('plan', ['id' => $created['id'], 'status' => 0]);
        $this->assertDatabaseHas('subscriptions', ['plan_id' => $created['id'], 'name' => 'Family monthly']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'subscription.plan.created']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'subscription.plan.updated']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'subscription.plan.archived']);
    }

    public function test_customer_support_can_view_only_the_summary_not_the_subscriber_directory(): void
    {
        $support = User::factory()->create();
        $support->assignRole('customer_support');

        $this->actingAs($support, 'sanctum')->getJson('/api/admin/subscriptions/overview')->assertOk();
        $this->getJson('/api/admin/subscriptions/subscribers')->assertForbidden();
    }

    public function test_entitlement_simulation_uses_the_selected_plan_level(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('subscription_manager');
        $plan = Plan::query()->create([
            'name' => 'Simulation plan', 'identifier' => 'simulation-'.Str::lower(Str::random(6)),
            'price' => 5, 'level' => 3, 'duration' => 'month', 'duration_value' => 1, 'status' => 1,
        ]);

        $this->actingAs($manager, 'sanctum')->postJson('/api/admin/subscriptions/entitlement-simulation', [
            'plan_id' => $plan->id, 'required_plan_level' => 2,
        ])->assertOk()->assertJsonPath('data.eligible', true);
        $this->postJson('/api/admin/subscriptions/entitlement-simulation', [
            'plan_id' => $plan->id, 'required_plan_level' => 4,
        ])->assertOk()->assertJsonPath('data.eligible', false)->assertJsonPath('data.checks.content_plan_level', false);
    }

    public function test_subscription_manager_can_view_comparison_and_verified_transaction_performance(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('subscription_manager');
        $plan = Plan::query()->create([
            'name' => 'Performance plan', 'identifier' => 'performance-'.Str::lower(Str::random(6)),
            'price' => 12, 'level' => 2, 'duration' => 'month', 'duration_value' => 3, 'status' => 1,
        ]);
        $subscriber = User::factory()->create();
        $subscription = Subscription::query()->create([
            'user_id' => $subscriber->id, 'plan_id' => $plan->id, 'name' => $plan->name,
            'identifier' => $plan->identifier, 'status' => 'active', 'start_date' => now(),
            'end_date' => now()->addMonths(3), 'amount' => 12, 'total_amount' => 12, 'level' => 2,
        ]);
        DB::table('subscriptions_transactions')->insert([
            'subscriptions_id' => $subscription->id, 'user_id' => $subscriber->id,
            'amount' => 12, 'currency_code' => 'USD', 'payment_type' => 'stripe', 'payment_status' => 'paid',
            'transaction_id' => 'performance-'.Str::random(10), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($manager, 'sanctum')->getJson('/api/admin/subscriptions/performance')
            ->assertOk()->assertJsonPath('data.0.active_subscribers', 1)
            ->assertJsonPath('data.0.paid_transactions', 1)
            ->assertJsonPath('data.0.revenue_by_currency.USD', 12);
    }

    public function test_coupon_management_is_permissioned_and_keeps_the_plan_mapping_when_deactivated(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('subscription_manager');
        $support = User::factory()->create();
        $support->assignRole('customer_support');
        $plan = Plan::query()->create([
            'name' => 'Coupon target', 'identifier' => 'coupon-target-'.Str::lower(Str::random(6)),
            'price' => 10, 'level' => 1, 'duration' => 'month', 'duration_value' => 1, 'status' => 1,
        ]);

        $this->actingAs($support, 'sanctum')->getJson('/api/admin/subscriptions/coupons')->assertForbidden();
        $this->postJson('/api/store-coupons', [])->assertForbidden();
        $coupon = $this->actingAs($manager, 'sanctum')->postJson('/api/admin/subscriptions/coupons', [
            'code' => 'testsave10', 'discount' => 10, 'discount_type' => 'percentage',
            'start_date' => now()->toDateString(), 'expire_date' => now()->addMonth()->toDateString(),
            'status' => true, 'subscription_plan_ids' => [$plan->id],
        ])->assertCreated()->json('data');

        $this->putJson('/api/admin/subscriptions/coupons/'.$coupon['id'], [
            'code' => 'testsave10', 'discount' => 15, 'discount_type' => 'percentage',
            'status' => true, 'subscription_plan_ids' => [$plan->id],
        ])->assertOk();
        $this->postJson('/api/admin/subscriptions/coupons/'.$coupon['id'].'/archive')->assertOk();
        $this->assertDatabaseHas('coupons', ['id' => $coupon['id'], 'discount' => 15, 'status' => 0]);
        $this->assertDatabaseHas('coupon_subscription_plan', ['coupon_id' => $coupon['id'], 'subscription_plan_id' => $plan->id]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'subscription.coupon.archived']);
    }

    public function test_coupon_total_and_per_account_limits_are_consumed_by_successful_subscription_activation(): void
    {
        $plan = Plan::query()->create([
            'name' => 'Coupon limit target', 'identifier' => 'coupon-limit-'.Str::lower(Str::random(6)),
            'price' => 10, 'level' => 1, 'duration' => 'month', 'duration_value' => 1, 'status' => 1,
        ]);
        $coupon = Coupon::query()->create([
            'code' => 'LIMITONE', 'discount' => 100, 'discount_type' => 'percentage',
            'max_redemptions' => 1, 'per_user_limit' => 1, 'start_date' => today(),
            'expire_date' => today()->addDays(7), 'status' => true,
        ]);
        $coupon->subscriptionPlans()->attach($plan->id);

        $firstUser = User::factory()->create();
        $this->actingAs($firstUser, 'sanctum')->postJson('/api/v3/subscription/process-payment', [
            'plan_id' => $plan->id, 'payment_method' => 'free', 'coupon_code' => $coupon->code,
        ])->assertOk()->assertJsonPath('data.is_free', true);
        $this->assertDatabaseHas('user_coupon_redeem', ['coupon_id' => $coupon->id, 'user_id' => $firstUser->id]);
        $this->assertDatabaseHas('subscription_coupon_reservations', ['coupon_id' => $coupon->id, 'status' => 'redeemed']);

        $secondUser = User::factory()->create();
        $this->actingAs($secondUser, 'sanctum')->postJson('/api/v3/subscription/process-payment', [
            'plan_id' => $plan->id, 'payment_method' => 'free', 'coupon_code' => $coupon->code,
        ])->assertStatus(400);
    }

    public function test_pending_coupon_reservation_prevents_another_checkout_from_using_the_last_global_slot(): void
    {
        $plan = Plan::query()->create([
            'name' => 'Reserved coupon target', 'identifier' => 'coupon-reserve-'.Str::lower(Str::random(6)),
            'price' => 15, 'level' => 1, 'duration' => 'month', 'duration_value' => 1, 'status' => 1,
        ]);
        $coupon = Coupon::query()->create([
            'code' => 'RESERVED1', 'discount' => 10, 'discount_type' => 'percentage',
            'max_redemptions' => 1, 'start_date' => today(), 'expire_date' => today()->addDays(7), 'status' => true,
        ]);
        $coupon->subscriptionPlans()->attach($plan->id);
        $reservedUser = User::factory()->create();
        DB::table('subscription_coupon_reservations')->insert([
            'token' => (string) Str::uuid(), 'coupon_id' => $coupon->id, 'user_id' => $reservedUser->id,
            'status' => 'pending', 'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/v3/subscription/calculate-price', [
            'plan_id' => $plan->id, 'coupon_code' => $coupon->code,
        ])->assertStatus(422);
    }
}
