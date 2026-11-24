<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PaymentWebhookSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private const WEBHOOK_SECRET = 'revenuecat-test-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.revenuecat.webhook_secret' => self::WEBHOOK_SECRET]);
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
}
