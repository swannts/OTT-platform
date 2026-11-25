<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Payments\CouponReservationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Coupon\Models\Coupon;
use Modules\Subscriptions\Models\Plan;
use Tests\TestCase;

class CheckoutPricingAndCouponTest extends TestCase
{
    use DatabaseTransactions;

    public function test_checkout_and_verify_endpoints_require_authentication(): void
    {
        $this->postJson('/api/v3/subscription/process-payment', ['plan_id' => 1, 'payment_method' => 'free'])->assertUnauthorized();
        $this->postJson('/api/v3/subscription/verify-payment', ['gateway' => 'stripe', 'session_id' => 'cs_test'])->assertUnauthorized();
        $this->postJson('/api/v3/pay-per-view/process-payment', ['movie_id' => 1, 'type' => 'movie', 'payment_method' => 'free'])->assertUnauthorized();
        $this->postJson('/api/v3/pay-per-view/verify-payment', ['gateway' => 'stripe', 'session_id' => 'cs_test'])->assertUnauthorized();
    }

    public function test_subscription_price_calculation_and_free_coupon_checkout_redeem_coupon_atomically(): void
    {
        $user = User::factory()->create();
        $plan = Plan::query()->create([
            'name' => 'Gold Plan',
            'identifier' => 'gold-'.Str::lower(Str::random(6)),
            'price' => 100,
            'discount' => 1,
            'discount_percentage' => 10,
            'level' => 1,
            'duration' => 'month',
            'duration_value' => 1,
            'status' => 1,
        ]);

        $coupon = Coupon::query()->create([
            'code' => 'FREE100_'.Str::upper(Str::random(4)),
            'description' => '100 percent off',
            'discount' => 100,
            'discount_type' => 'percentage',
            'start_date' => today()->subDay()->toDateString(),
            'expire_date' => today()->addDays(5)->toDateString(),
            'status' => 1,
            'max_redemptions' => 1,
            'per_user_limit' => 1,
        ]);

        DB::table('coupon_subscription_plan')->insert([
            'coupon_id' => $coupon->id,
            'subscription_plan_id' => $plan->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v3/coupon/validate', [
                'coupon_code' => $coupon->code,
                'plan_id' => $plan->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.coupon_code', $coupon->code);

        $this->postJson('/api/v3/subscription/calculate-price', [
            'plan_id' => $plan->id,
            'coupon_code' => $coupon->code,
        ])
            ->assertOk()
            ->assertJsonPath('data.plan_id', $plan->id)
            ->assertJsonPath('data.total_amount', 0);

        $checkout = $this->postJson('/api/v3/subscription/process-payment', [
            'plan_id' => $plan->id,
            'payment_method' => 'free',
            'coupon_code' => $coupon->code,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_free', true);

        $subscriptionId = (int) $checkout->json('data.subscription.id');
        $this->assertDatabaseHas('user_coupon_redeem', [
            'coupon_id' => $coupon->id,
            'user_id' => $user->id,
            'booking_id' => $subscriptionId,
        ]);
        $this->assertDatabaseHas('subscription_coupon_reservations', [
            'coupon_id' => $coupon->id,
            'user_id' => $user->id,
            'subscription_id' => $subscriptionId,
            'status' => 'redeemed',
        ]);

        // Second redemption attempt must be rejected because max_redemptions = 1
        $this->postJson('/api/v3/coupon/validate', [
            'coupon_code' => $coupon->code,
            'plan_id' => $plan->id,
        ])->assertStatus(422);
    }

    public function test_paid_plan_rejects_free_checkout_and_releases_coupon_reservation(): void
    {
        $user = User::factory()->create();
        $plan = Plan::query()->create([
            'name' => 'Silver Plan',
            'identifier' => 'silver-'.Str::lower(Str::random(6)),
            'price' => 50,
            'level' => 1,
            'duration' => 'month',
            'duration_value' => 1,
            'status' => 1,
        ]);

        $coupon = Coupon::query()->create([
            'code' => 'SAVE20_'.Str::upper(Str::random(4)),
            'description' => '20 percent off',
            'discount' => 20,
            'discount_type' => 'percentage',
            'start_date' => today()->subDay()->toDateString(),
            'expire_date' => today()->addDays(5)->toDateString(),
            'status' => 1,
            'max_redemptions' => 1,
            'per_user_limit' => 1,
        ]);

        DB::table('coupon_subscription_plan')->insert([
            'coupon_id' => $coupon->id,
            'subscription_plan_id' => $plan->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v3/subscription/process-payment', [
                'plan_id' => $plan->id,
                'payment_method' => 'free',
                'coupon_code' => $coupon->code,
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('subscription_coupon_reservations', [
            'coupon_id' => $coupon->id,
            'user_id' => $user->id,
            'status' => 'released',
        ]);

        // Coupon remains available after the failed checkout released its reservation
        $this->assertTrue(app(CouponReservationService::class)->isUsageAvailable($coupon, $user->id));
    }

    public function test_invalid_coupon_code_fails_predictably(): void
    {
        $user = User::factory()->create();
        $plan = Plan::query()->create([
            'name' => 'Basic Plan',
            'identifier' => 'basic-'.Str::lower(Str::random(6)),
            'price' => 25,
            'level' => 1,
            'duration' => 'month',
            'duration_value' => 1,
            'status' => 1,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v3/coupon/validate', ['coupon_code' => 'NONEXISTENT'])
            ->assertNotFound();

        $this->postJson('/api/v3/subscription/calculate-price', [
            'plan_id' => $plan->id,
            'coupon_code' => 'NONEXISTENT',
        ])->assertStatus(422);
    }
}
