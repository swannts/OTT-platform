<?php

namespace App\Services\Payments;

use App\Models\PaymentDispute;
use App\Models\RefundRequest;
use App\Models\User;
use App\Models\VerifiedPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Subscription;
use Modules\Subscriptions\Transformers\SubscriptionResource;
use RuntimeException;

class ProviderWebhookService
{
    public function __construct(
        private readonly PaymentFulfillmentService $fulfillment,
    ) {}

    public function beginEvent(string $gateway, string $eventId, string $eventType, string $providerReference, string $payload): bool
    {
        DB::table('payment_provider_events')->insertOrIgnore([
            'gateway' => $gateway,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'provider_reference' => $providerReference !== '' ? $providerReference : null,
            'payload_sha256' => hash('sha256', $payload),
            'status' => 'received',
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::transaction(function () use ($gateway, $eventId): bool {
            $event = DB::table('payment_provider_events')
                ->where('gateway', $gateway)
                ->where('event_id', $eventId)
                ->lockForUpdate()
                ->first();

            if ($event->status === 'processed') {
                return false;
            }
            if ($event->status === 'processing' && Carbon::parse($event->updated_at)->gt(now()->subMinutes(5))) {
                abort(409, 'This provider event is already being processed.');
            }

            DB::table('payment_provider_events')->where('id', $event->id)->update([
                'status' => 'processing',
                'attempts' => (int) $event->attempts + 1,
                'failure_code' => null,
                'updated_at' => now(),
            ]);

            return true;
        }, 3);
    }

    public function finishEvent(string $gateway, string $eventId, string $status, ?string $failureCode = null): void
    {
        DB::table('payment_provider_events')
            ->where('gateway', $gateway)
            ->where('event_id', $eventId)
            ->update([
                'status' => $status,
                'failure_code' => $failureCode ? substr($failureCode, 0, 100) : null,
                'processed_at' => $status === 'processed' ? now() : null,
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    public function fulfillStripeWebhookPayment(array $payment, string $providerReference, int $amountMinor): void
    {
        $metadata = $payment['metadata'] ?? [];
        $userId = (int) ($metadata['user_id'] ?? 0);
        $purchaseType = (string) ($metadata['purchase_type'] ?? '');
        $purchaseId = (int) ($metadata['purchase_id'] ?? 0);
        $currency = strtoupper((string) ($payment['currency'] ?? ''));

        if ($userId <= 0 || $purchaseId <= 0 || $amountMinor <= 0 || $currency === ''
            || ! in_array($purchaseType, ['subscription', 'pay_per_view'], true)
            || (isset($metadata['currency']) && strtoupper((string) $metadata['currency']) !== $currency)
            || (isset($metadata['amount_minor']) && (int) $metadata['amount_minor'] !== $amountMinor)) {
            throw new RuntimeException('Stripe payment metadata is incomplete.');
        }

        $this->fulfillment->fulfillVerifiedPayment(
            'stripe',
            $providerReference,
            $userId,
            $purchaseType,
            $purchaseId,
            $amountMinor,
            $currency,
            ! empty($metadata['promotion_id']) ? (int) $metadata['promotion_id'] : null,
            (string) ($metadata['content_type'] ?? ''),
            ! empty($metadata['coupon_reservation']) ? (string) $metadata['coupon_reservation'] : null
        );
    }

    /**
     * @param  array<string, mixed>  $refund
     */
    public function syncStripeRefundEvent(string $eventType, array $refund): void
    {
        $local = null;
        if (! empty($refund['metadata']['refund_request_id'])) {
            $local = RefundRequest::query()->find((int) $refund['metadata']['refund_request_id']);
        }
        $local ??= RefundRequest::query()->where('provider_refund_id', $refund['id'] ?? '')->first();
        if (! $local) {
            return;
        }

        $local->forceFill([
            'status' => $this->mapRefundStatus((string) ($refund['status'] ?? $eventType)),
            'provider_refund_id' => $refund['id'] ?? $local->provider_refund_id,
            'executed_at' => $local->executed_at ?? now(),
            'failure_code' => $refund['failure_reason'] ?? null,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $dispute
     */
    public function syncStripeDisputeEvent(string $eventType, array $dispute): void
    {
        $paymentIntent = $dispute['payment_intent'] ?? null;
        if (is_array($paymentIntent)) {
            $paymentIntent = $paymentIntent['id'] ?? null;
        }
        $payment = is_string($paymentIntent)
            ? VerifiedPayment::query()->where('gateway', 'stripe')->where('provider_reference', $paymentIntent)->first()
            : null;
        $status = (string) ($dispute['status'] ?? str_replace('charge.dispute.', '', $eventType));

        PaymentDispute::query()->updateOrCreate(
            ['gateway' => 'stripe', 'provider_dispute_id' => (string) $dispute['id']],
            [
                'provider_reference' => is_string($paymentIntent) ? $paymentIntent : null,
                'verified_payment_id' => $payment?->id,
                'amount_minor' => isset($dispute['amount']) ? (int) $dispute['amount'] : null,
                'currency' => isset($dispute['currency']) ? strtoupper((string) $dispute['currency']) : null,
                'status' => $status,
                'reason' => $dispute['reason'] ?? null,
                'evidence_due_at' => isset($dispute['evidence_details']['due_by'])
                    ? Carbon::createFromTimestamp((int) $dispute['evidence_details']['due_by'])
                    : null,
                'resolved_at' => in_array($status, ['won', 'lost'], true) ? now() : null,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function syncRazorpayFinanceEvent(string $eventType, array $event): void
    {
        if (str_starts_with($eventType, 'refund.')) {
            $refund = $event['payload']['refund']['entity'] ?? [];
            $notes = $refund['notes'] ?? [];
            $local = ! empty($notes['refund_request_id'])
                ? RefundRequest::query()->find((int) $notes['refund_request_id'])
                : null;
            $local ??= RefundRequest::query()->where('provider_refund_id', $refund['id'] ?? '')->first();
            if (! $local) {
                return;
            }
            $local->forceFill([
                'status' => $this->mapRefundStatus((string) ($refund['status'] ?? $eventType)),
                'provider_refund_id' => $refund['id'] ?? $local->provider_refund_id,
                'executed_at' => $local->executed_at ?? now(),
            ])->save();

            return;
        }

        if (! str_starts_with($eventType, 'payment.dispute.')) {
            return;
        }

        $dispute = $event['payload']['dispute']['entity'] ?? [];
        $reference = (string) ($dispute['payment_id'] ?? '');
        $payment = $reference !== ''
            ? VerifiedPayment::query()->where('gateway', 'razorpay')->where('provider_reference', $reference)->first()
            : null;
        $status = (string) ($dispute['status'] ?? str_replace('payment.dispute.', '', $eventType));

        PaymentDispute::query()->updateOrCreate(
            ['gateway' => 'razorpay', 'provider_dispute_id' => (string) ($dispute['id'] ?? '')],
            [
                'provider_reference' => $reference ?: null,
                'verified_payment_id' => $payment?->id,
                'amount_minor' => isset($dispute['amount']) ? (int) $dispute['amount'] : null,
                'currency' => isset($dispute['currency']) ? strtoupper((string) $dispute['currency']) : null,
                'status' => $status,
                'reason' => $dispute['reason_code'] ?? null,
                'resolved_at' => in_array($status, ['won', 'lost', 'closed'], true) ? now() : null,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $webhookData
     */
    public function fulfillPaystackWebhookPayment(array $webhookData): void
    {
        $reference = (string) ($webhookData['reference'] ?? '');
        $metadata = $webhookData['metadata'] ?? [];
        $userId = (int) ($metadata['user_id'] ?? 0);
        $planId = (int) ($metadata['plan_id'] ?? 0);

        if ($reference === '' || $userId <= 0 || $planId <= 0 || ($metadata['purchase_type'] ?? 'subscription') !== 'subscription') {
            throw new RuntimeException('Paystack subscription metadata is incomplete.');
        }

        $response = Http::withToken((string) GetpaymentMethod('paystack_secretkey'))
            ->get('https://api.paystack.co/transaction/verify/'.rawurlencode($reference));
        $verified = $response->json('data', []);
        $user = User::query()->find($userId);

        if (! $response->successful() || ! ($response->json('status') ?? false) || ($verified['status'] ?? '') !== 'success'
            || (string) ($verified['reference'] ?? '') !== $reference
            || (string) ($verified['metadata']['user_id'] ?? '') !== (string) $userId
            || (int) ($verified['amount'] ?? 0) !== (int) ($webhookData['amount'] ?? -1)
            || strtoupper((string) ($verified['currency'] ?? '')) !== strtoupper((string) ($webhookData['currency'] ?? ''))
            || (isset($metadata['amount_minor']) && (int) $metadata['amount_minor'] !== (int) ($verified['amount'] ?? 0))
            || (isset($metadata['currency']) && strtoupper((string) $metadata['currency']) !== strtoupper((string) ($verified['currency'] ?? '')))
            || ! $user || strtolower((string) ($verified['customer']['email'] ?? '')) !== strtolower((string) $user->email)) {
            throw new RuntimeException('Paystack transaction could not be independently verified.');
        }

        Plan::query()->whereKey($planId)->where('status', 1)->firstOrFail();
        $this->fulfillment->fulfillVerifiedPayment(
            'paystack',
            $reference,
            $userId,
            'subscription',
            $planId,
            (int) $verified['amount'],
            strtoupper((string) $verified['currency']),
            ! empty($metadata['promotion_id']) ? (int) $metadata['promotion_id'] : null,
            null,
            ! empty($metadata['coupon_reservation']) ? (string) $metadata['coupon_reservation'] : null
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function syncPaystackRefundEvent(string $eventType, array $data): void
    {
        $reference = (string) ($data['reference'] ?? '');
        $token = str_starts_with($reference, 'refund-') ? substr($reference, 7) : '';
        $local = $token !== '' ? RefundRequest::query()->where('idempotency_key', $token)->first() : null;
        $local ??= isset($data['id']) ? RefundRequest::query()->where('provider_refund_id', (string) $data['id'])->first() : null;
        if (! $local) {
            return;
        }

        $local->forceFill([
            'status' => $this->mapRefundStatus((string) ($data['status'] ?? $eventType)),
            'provider_refund_id' => isset($data['id']) ? (string) $data['id'] : $local->provider_refund_id,
            'executed_at' => $local->executed_at ?? now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function syncPaystackDisputeEvent(string $eventType, array $data): void
    {
        $transaction = $data['transaction'] ?? [];
        if (is_numeric($transaction)) {
            $reference = (string) $transaction;
        } elseif (is_array($transaction)) {
            $reference = (string) ($transaction['reference'] ?? '');
        } else {
            $reference = '';
        }

        $payment = $reference !== ''
            ? VerifiedPayment::query()->where('gateway', 'paystack')->where('provider_reference', $reference)->first()
            : null;
        $disputeId = (string) ($data['id'] ?? $data['dispute_id'] ?? '');
        if ($disputeId === '') {
            return;
        }
        $status = (string) ($data['status'] ?? str_replace('charge.dispute.', '', $eventType));

        PaymentDispute::query()->updateOrCreate(
            ['gateway' => 'paystack', 'provider_dispute_id' => $disputeId],
            [
                'provider_reference' => $reference ?: null,
                'verified_payment_id' => $payment?->id,
                'amount_minor' => isset($data['amount']) ? (int) $data['amount'] : null,
                'currency' => isset($data['currency']) ? strtoupper((string) $data['currency']) : null,
                'status' => $status,
                'reason' => $data['reason'] ?? null,
                'resolved_at' => in_array($status, ['resolved', 'won', 'lost'], true) ? now() : null,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function syncRevenueCatSubscription(User $user): array
    {
        $apiKey = (string) config('services.revenuecat.api_key', '');
        $entitlementId = (string) GetpaymentMethod('iap_entitlement_id');
        if ($apiKey === '' || $entitlementId === '') {
            throw new RuntimeException('RevenueCat API key or entitlement ID is not configured.');
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(8)
            ->get('https://api.revenuecat.com/v1/subscribers/'.rawurlencode((string) $user->id));

        if ($response->status() === 404) {
            return $this->deactivateRevenueCatSubscription($user);
        }
        if (! $response->successful()) {
            throw new RuntimeException('RevenueCat subscriber lookup failed with HTTP '.$response->status().'.');
        }

        $subscriber = $response->json('subscriber', []);
        $entitlement = $subscriber['entitlements'][$entitlementId] ?? null;
        $expiresAt = ! empty($entitlement['expires_date']) ? Carbon::parse($entitlement['expires_date']) : null;
        if (! $entitlement || ($expiresAt && ! $expiresAt->isFuture())) {
            return $this->deactivateRevenueCatSubscription($user);
        }

        $productIdentifier = (string) ($entitlement['product_identifier'] ?? '');
        if ($productIdentifier === '') {
            throw new RuntimeException('RevenueCat entitlement has no product identifier.');
        }

        $productBaseId = explode(':', $productIdentifier, 2)[0];
        $plan = Plan::where('status', 1)
            ->where(function ($query) use ($productIdentifier, $productBaseId) {
                $query->where('apple_identifier', $productIdentifier)
                    ->orWhere('android_identifier', $productIdentifier)
                    ->orWhere('android_identifier', $productBaseId);
            })
            ->first();
        if (! $plan) {
            throw new RuntimeException('No active subscription plan maps to the RevenueCat product.');
        }

        $expiresKey = $expiresAt?->utc()->format('YmdHis') ?? 'lifetime';
        $providerReference = 'rc_'.$user->id.'_'.substr(hash('sha256', $productIdentifier.'|'.$expiresKey), 0, 32);
        $subscription = $this->fulfillment->fulfillVerifiedPayment(
            'revenuecat',
            $providerReference,
            $user->id,
            'subscription',
            (int) $plan->id,
            0,
            'USD'
        );

        if (! empty($entitlement['purchase_date'])) {
            $subscription->start_date = Carbon::parse($entitlement['purchase_date']);
        }
        $subscription->end_date = $expiresAt;
        $subscription->save();

        return [
            'has_active_subscription' => true,
            'subscription' => new SubscriptionResource($subscription->fresh()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deactivateRevenueCatSubscription(User $user): array
    {
        Subscription::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereHas('subscription_transaction', fn ($query) => $query->whereIn('payment_type', ['in_app_purchase', 'revenuecat']))
            ->update(['status' => 'inactive']);

        $hasActiveSubscription = Subscription::where('user_id', $user->id)->where('status', 'active')->exists();
        User::whereKey($user->id)->update(['is_subscribe' => $hasActiveSubscription ? 1 : 0]);
        Cache::flush();

        $activeSubscription = $hasActiveSubscription
            ? Subscription::where('user_id', $user->id)->where('status', 'active')->latest('id')->first()
            : null;

        return [
            'has_active_subscription' => $activeSubscription !== null,
            'subscription' => $activeSubscription ? new SubscriptionResource($activeSubscription) : null,
        ];
    }

    private function mapRefundStatus(string $status): string
    {
        return match (strtolower($status)) {
            'succeeded', 'processed', 'success' => 'succeeded',
            'failed', 'canceled', 'cancelled', 'rejected', 'needs-attention' => 'failed',
            default => 'processing',
        };
    }
}
