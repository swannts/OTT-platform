<?php

namespace Tests\Feature;

use App\Models\RefundRequest;
use App\Models\PaymentProviderEvent;
use App\Models\User;
use App\Models\VerifiedPayment;
use App\Support\Rbac\ExplicitPermission;
use Database\Seeders\RbacFoundationSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Subscription;
use Tests\TestCase;

class FinanceOperationsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacFoundationSeeder::class);
    }

    public function test_refund_maker_checker_prevents_self_approval_and_limits_total_refund_amount(): void
    {
        $maker = User::factory()->create();
        $maker->assignRole('finance_manager');
        $checker = User::factory()->create();
        $checker->assignRole('finance_manager');
        $payment = VerifiedPayment::query()->create([
            'gateway' => 'stripe', 'provider_reference' => 'pi_'.Str::random(20),
            'user_id' => User::factory()->create()->id, 'purchase_type' => 'subscription',
            'purchase_id' => 1, 'amount_minor' => 10000, 'currency' => 'USD',
            'fulfillment_type' => 'subscription', 'fulfillment_id' => 1, 'fulfilled_at' => now(),
        ]);

        $refund = $this->actingAs($maker, 'sanctum')->postJson('/api/admin/finance/refunds', [
            'verified_payment_id' => $payment->id, 'amount_minor' => 6000,
            'reason' => 'Customer was charged twice.', 'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated()->json('data');

        $this->postJson('/api/admin/finance/refunds/'.$refund['id'].'/review', ['decision' => 'approve'])
            ->assertForbidden();

        $this->actingAs($checker, 'sanctum')->postJson('/api/admin/finance/refunds/'.$refund['id'].'/review', [
            'decision' => 'approve', 'review_note' => 'Verified duplicate charge.',
        ])->assertOk()->assertJsonPath('data.status', 'approved');

        $this->actingAs($maker, 'sanctum')->postJson('/api/admin/finance/refunds', [
            'verified_payment_id' => $payment->id, 'amount_minor' => 5000,
            'reason' => 'Additional refund requested.', 'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(422);

        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'finance.refund.requested', 'resource_id' => (string) $refund['id']]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'finance.refund.approved', 'resource_id' => (string) $refund['id']]);
        $this->assertDatabaseMissing('subscriptions_transactions', ['transaction_id' => $payment->provider_reference]);
    }

    public function test_super_admin_does_not_receive_refund_approval_automatically(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');
        $this->assertFalse(ExplicitPermission::allows($superAdmin, 'finance.refunds.approve'));
    }

    public function test_finance_manager_can_review_failed_provider_events(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole('finance_manager');
        PaymentProviderEvent::query()->create([
            'gateway' => 'stripe', 'event_id' => 'evt_failed_1', 'event_type' => 'payment_intent.succeeded',
            'provider_reference' => 'pi_failed_1', 'payload_sha256' => hash('sha256', '{}'),
            'status' => 'failed', 'attempts' => 2, 'failure_code' => 'RuntimeException',
        ]);

        $this->actingAs($finance, 'sanctum')->getJson('/api/admin/finance/failed-events')
            ->assertOk()->assertJsonPath('data.0.gateway', 'stripe')->assertJsonPath('data.0.attempts', 2);
    }

    public function test_finance_payment_feed_includes_verified_and_legacy_rows_without_duplication(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole('finance_manager');
        $customer = User::factory()->create();
        $plan = Plan::query()->create([
            'name' => 'Ledger test', 'identifier' => 'ledger-'.Str::lower(Str::random(6)),
            'price' => 10, 'level' => 1, 'duration' => 'month', 'duration_value' => 1, 'status' => 1,
        ]);
        $subscription = Subscription::query()->create([
            'user_id' => $customer->id, 'plan_id' => $plan->id, 'name' => $plan->name,
            'identifier' => $plan->identifier, 'status' => 'active', 'start_date' => now(),
            'end_date' => now()->addMonth(), 'amount' => 10, 'total_amount' => 10, 'level' => 1,
        ]);
        VerifiedPayment::query()->create([
            'gateway' => 'stripe', 'provider_reference' => 'pi_'.Str::random(20),
            'user_id' => $customer->id, 'purchase_type' => 'subscription', 'purchase_id' => $plan->id,
            'amount_minor' => 1000, 'currency' => 'USD', 'fulfillment_type' => 'subscription',
            'fulfillment_id' => $subscription->id, 'fulfilled_at' => now(),
        ]);
        \DB::table('subscriptions_transactions')->insert([
            'subscriptions_id' => $subscription->id, 'user_id' => $customer->id, 'amount' => 10,
            'currency_code' => 'USD', 'payment_type' => 'razorpay', 'payment_status' => 'paid',
            'transaction_id' => 'pay_'.Str::random(20), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($finance, 'sanctum')->getJson('/api/admin/finance/payments?per_page=100')->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertSame(['legacy_subscription', 'verified'], collect($response->json('data'))->pluck('source')->sort()->values()->all());
    }
}
