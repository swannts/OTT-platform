<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentWebhookSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private const WEBHOOK_SECRET = 'revenuecat-test-secret';

    private const STRIPE_WEBHOOK_SECRET = 'stripe-test-secret';

    private const RAZORPAY_WEBHOOK_SECRET = 'razorpay-test-secret';

    private const PAYSTACK_SECRET = 'paystack-test-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.revenuecat.webhook_secret' => self::WEBHOOK_SECRET]);
        config(['services.stripe.webhook_secret' => self::STRIPE_WEBHOOK_SECRET]);
        config(['services.razorpay.webhook_secret' => self::RAZORPAY_WEBHOOK_SECRET]);
        DB::table('settings')->insert([
            'name' => 'paystack_secretkey',
            'val' => self::PAYSTACK_SECRET,
            'type' => 'string',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_stripe_webhook_rejects_invalid_signature_and_deduplicates_signed_events(): void
    {
        $payload = $this->stripeEventPayload('evt_stripe_replay');
        $this->postRaw('/api/webhooks/stripe', $payload, ['Stripe-Signature' => 't='.time().',v1=invalid'])
            ->assertBadRequest();
        $this->assertDatabaseCount('payment_provider_events', 0);

        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, self::STRIPE_WEBHOOK_SECRET);
        $headers = ['Stripe-Signature' => "t={$timestamp},v1={$signature}"];
        $this->postRaw('/api/webhooks/stripe', $payload, $headers)->assertOk()->assertJsonPath('received', true);
        $this->postRaw('/api/webhooks/stripe', $payload, $headers)->assertOk()->assertJsonPath('duplicate', true);

        $this->assertDatabaseHas('payment_provider_events', [
            'gateway' => 'stripe', 'event_id' => 'evt_stripe_replay', 'status' => 'processed', 'attempts' => 1,
        ]);
    }

    public function test_razorpay_webhook_rejects_invalid_signature_and_deduplicates_signed_events(): void
    {
        $payload = json_encode(['event' => 'account.updated', 'payload' => []], JSON_THROW_ON_ERROR);
        $this->postRaw('/api/webhooks/razorpay', $payload, ['X-Razorpay-Signature' => 'invalid'])
            ->assertBadRequest();
        $this->assertDatabaseCount('payment_provider_events', 0);

        $signature = hash_hmac('sha256', $payload, self::RAZORPAY_WEBHOOK_SECRET);
        $headers = ['X-Razorpay-Signature' => $signature, 'X-Razorpay-Event-Id' => 'evt_razorpay_replay'];
        $this->postRaw('/api/webhooks/razorpay', $payload, $headers)->assertOk()->assertJsonPath('received', true);
        $this->postRaw('/api/webhooks/razorpay', $payload, $headers)->assertOk()->assertJsonPath('duplicate', true);

        $this->assertDatabaseHas('payment_provider_events', [
            'gateway' => 'razorpay', 'event_id' => 'evt_razorpay_replay', 'status' => 'processed', 'attempts' => 1,
        ]);
    }

    public function test_paystack_webhook_rejects_invalid_signature_and_deduplicates_signed_events(): void
    {
        $payload = json_encode([
            'event' => 'customeridentification.success',
            'data' => ['id' => 88271],
        ], JSON_THROW_ON_ERROR);
        $this->postRaw('/api/webhooks/paystack', $payload, ['x-paystack-signature' => 'invalid'])
            ->assertBadRequest();
        $this->assertDatabaseCount('payment_provider_events', 0);

        $signature = hash_hmac('sha512', $payload, self::PAYSTACK_SECRET);
        $headers = ['x-paystack-signature' => $signature];
        $this->postRaw('/api/webhooks/paystack', $payload, $headers)->assertOk()->assertJsonPath('received', true);
        $this->postRaw('/api/webhooks/paystack', $payload, $headers)->assertOk()->assertJsonPath('duplicate', true);

        $this->assertDatabaseHas('payment_provider_events', [
            'gateway' => 'paystack', 'event_id' => 'customeridentification.success:88271', 'status' => 'processed', 'attempts' => 1,
        ]);
    }

    public function test_revenuecat_webhook_rejects_invalid_and_stale_signatures_without_recording_events(): void
    {
        $event = ['id' => 'evt_invalid_signature', 'type' => 'INITIAL_PURCHASE'];
        $this->postRevenueCatEvent($event, time(), 'v1=invalid-signature')->assertUnauthorized();
        $this->postRevenueCatEvent($event, time() - 301)->assertUnauthorized();

        $this->assertDatabaseCount('payment_provider_events', 0);
    }

    public function test_revenuecat_webhook_records_and_deduplicates_a_signed_event(): void
    {
        $event = [
            'id' => 'evt_revenuecat_idempotency',
            'type' => 'INITIAL_PURCHASE',
            'app_user_id' => '999999999999',
            'aliases' => [],
        ];

        $this->postRevenueCatEvent($event)->assertOk()->assertJsonPath('received', true);
        $this->postRevenueCatEvent($event)->assertOk()->assertJsonPath('duplicate', true);

        $this->assertDatabaseHas('payment_provider_events', [
            'gateway' => 'revenuecat',
            'event_id' => 'evt_revenuecat_idempotency',
            'status' => 'processed',
            'attempts' => 1,
        ]);
        $this->assertDatabaseCount('payment_provider_events', 1);
    }

    private function postRevenueCatEvent(array $event, ?int $timestamp = null, ?string $signature = null)
    {
        $timestamp ??= time();
        $payload = json_encode(['event' => $event], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $signature ??= 'v1='.hash_hmac('sha256', $timestamp.'.'.$payload, self::WEBHOOK_SECRET);

        return $this->call('POST', '/api/webhooks/revenuecat', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_REVENUECAT_WEBHOOK_SIGNATURE' => 't='.$timestamp.','.$signature,
        ], $payload);
    }

    private function stripeEventPayload(string $eventId): string
    {
        return json_encode([
            'id' => $eventId,
            'object' => 'event',
            'api_version' => '2020-08-27',
            'created' => time(),
            'data' => ['object' => ['object' => 'customer', 'id' => 'cus_test_webhook']],
            'livemode' => false,
            'pending_webhooks' => 1,
            'request' => ['id' => null, 'idempotency_key' => null],
            'type' => 'customer.created',
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function postRaw(string $uri, string $payload, array $headers)
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', $uri, [], [], [], $server, $payload);
    }
}
