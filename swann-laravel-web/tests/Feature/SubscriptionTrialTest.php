<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Subscription;
use Tests\TestCase;

class SubscriptionTrialTest extends TestCase
{
    use DatabaseTransactions;

    public function test_first_subscription_can_start_an_idempotent_trial_without_a_payment_record(): void
    {
        $user = User::factory()->create();
        $plan = $this->trialPlan();
        $payload = ['plan_id' => $plan->id, 'payment_method' => 'stripe'];

        $first = $this->actingAs($user, 'sanctum')->postJson('/api/v3/subscription/process-payment', $payload)
            ->assertOk()->assertJsonPath('data.is_trial', true);
        $subscriptionId = $first->json('data.subscription.id');
        $this->assertDatabaseHas('subscriptions', ['id' => $subscriptionId, 'user_id' => $user->id, 'is_trial' => 1, 'total_amount' => 0]);
        $this->assertDatabaseMissing('subscriptions_transactions', ['subscriptions_id' => $subscriptionId]);

        $again = $this->postJson('/api/v3/subscription/process-payment', $payload)
            ->assertOk()->assertJsonPath('data.is_trial', true);
        $this->assertSame($subscriptionId, $again->json('data.subscription.id'));
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_trial_is_denied_after_any_prior_subscription_and_mobile_checkout_supports_trials(): void
    {
        $user = User::factory()->create();
        $plan = $this->trialPlan();
        Subscription::query()->create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'name' => 'Earlier plan', 'identifier' => 'earlier',
            'status' => 'inactive', 'start_date' => now()->subYear(), 'end_date' => now()->subMonths(11),
            'amount' => 10, 'total_amount' => 10, 'level' => 1,
        ]);

        $this->actingAs($user, 'sanctum')->postJson('/api/v3/subscription/process-payment', [
            'plan_id' => $plan->id, 'payment_method' => 'stripe',
        ])->assertStatus(409);

        $newUser = User::factory()->create();
        $this->actingAs($newUser, 'sanctum')->postJson('/api/v3/mobile/payment-intent', [
            'purchase_type' => 'subscription', 'plan_id' => $plan->id,
        ])->assertOk()->assertJsonPath('data.is_trial', true);
    }

    private function trialPlan(): Plan
    {
        return Plan::query()->create([
            'name' => 'Trial plan', 'identifier' => 'trial-'.Str::lower(Str::random(8)),
            'price' => 12, 'level' => 2, 'duration' => 'month', 'duration_value' => 1,
            'trial_days' => 7, 'status' => 1,
        ]);
    }
}
