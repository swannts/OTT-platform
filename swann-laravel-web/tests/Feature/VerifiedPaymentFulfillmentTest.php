<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Payments\FulfillVerifiedPayment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class VerifiedPaymentFulfillmentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unsupported_purchase_rolls_back_receipt(): void
    {
        $user = User::factory()->create();
        try {
            app(FulfillVerifiedPayment::class)->execute('stripe', 'phase1-unsupported', $user->id, 'unsupported', 1, 100, 'USD');
            $this->fail('Unsupported purchases must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unsupported verified payment purchase type.', $exception->getMessage());
        }
        $this->assertDatabaseMissing('verified_payments', ['gateway' => 'stripe', 'provider_reference' => 'phase1-unsupported']);
    }

    public function test_conflicting_reference_is_rejected_without_modifying_receipt(): void
    {
        $user = User::factory()->create();
        DB::table('verified_payments')->insert([
            'gateway' => 'stripe', 'provider_reference' => 'phase1-conflict',
            'user_id' => $user->id, 'purchase_type' => 'subscription',
            'purchase_id' => 1, 'amount_minor' => 100, 'currency' => 'USD',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        try {
            app(FulfillVerifiedPayment::class)->execute('stripe', 'phase1-conflict', $user->id, 'subscription', 1, 200, 'USD');
            $this->fail('Conflicting payments must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Provider transaction was already associated with a different purchase.', $exception->getMessage());
        }
        $this->assertDatabaseHas('verified_payments', ['provider_reference' => 'phase1-conflict', 'amount_minor' => 100, 'fulfilled_at' => null]);
    }

    public function test_gateway_currency_rules_are_preserved(): void
    {
        $action = app(FulfillVerifiedPayment::class);
        $this->assertSame(1, $action->currencyMultiplier('JPY', 'stripe'));
        $this->assertSame(100, $action->currencyMultiplier('XOF', 'paystack'));
        $this->assertSame(100, $action->currencyMultiplier('UGX', 'stripe'));
        $this->assertSame(1, $action->currencyMultiplier('UGX', 'razorpay'));
    }
}
