<?php

namespace App\Services\Payments;

use App\Models\User;
use App\Models\VerifiedPayment;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Transformers\SubscriptionResource;
use Razorpay\Api\Api as RazorpayApi;
use RuntimeException;
use Stripe\StripeClient;

class PaymentGatewayCheckoutService
{
    public function __construct(
        private readonly CheckoutPricingService $checkoutPricing,
        private readonly CouponReservationService $couponReservations,
        private readonly PaymentFulfillmentService $fulfillment,
    ) {}

    /**
     * @return array{ok: true, data: array<string, mixed>, message: string}|array{ok: false, message: string, status: int}
     */
    public function createMobilePaymentIntent(User $user, Request $request): array
    {
        $purchaseType = $request->string('purchase_type')->toString();
        if ($purchaseType === 'subscription') {
            $trialPlan = Plan::query()->whereKey($request->integer('plan_id'))->where('status', 1)->first();
            if ($trialPlan && (int) ($trialPlan->trial_days ?? 0) > 0) {
                $trial = $this->fulfillment->startEligibleTrial($user, $trialPlan);
                if (! $trial) {
                    return ['ok' => false, 'message' => 'This free trial is available only to accounts with no prior subscription.', 'status' => 409];
                }

                return [
                    'ok' => true,
                    'data' => [
                        'purchase_type' => 'subscription',
                        'is_trial' => true,
                        'trial_days' => (int) $trialPlan->trial_days,
                        'subscription' => new SubscriptionResource($trial),
                    ],
                    'message' => 'Free trial activated successfully.',
                ];
            }

            $plan = Plan::with('planLimitation')->where('id', $request->integer('plan_id'))->where('status', 1)->first();
            if (! $plan) {
                return ['ok' => false, 'message' => 'Selected plan is invalid or inactive.', 'status' => 404];
            }
            $pricing = $this->checkoutPricing->calculateSubscription($plan, $request, (int) $user->id);
            if (! $pricing['ok']) {
                return ['ok' => false, 'message' => $pricing['message'], 'status' => 422];
            }
            $pricingData = $pricing['data'];
        } else {
            $content = $this->checkoutPricing->resolveContentModel((string) $request->input('type'), $request->integer('movie_id'));
            if (! $content) {
                return ['ok' => false, 'message' => 'Content not found.', 'status' => 422];
            }
            $pricingData = $this->checkoutPricing->calculatePayPerView($content, (string) $request->input('type'));
        }

        $amount = (float) $pricingData['total_amount'];
        $currency = strtoupper((string) ($pricingData['currency'] ?? GetcurrentCurrency() ?: 'USD'));
        $amountMinor = $this->checkoutPricing->toMinorUnits($amount, $currency);
        if ($amountMinor <= 0) {
            return ['ok' => false, 'message' => 'A payment intent is not required for a zero-cost purchase.', 'status' => 422];
        }

        $purchaseId = $purchaseType === 'subscription' ? $request->integer('plan_id') : $request->integer('movie_id');
        $promotionId = $purchaseType === 'subscription' ? (int) ($pricingData['coupon']['id'] ?? 0) : 0;
        $reservationToken = null;
        if ($promotionId > 0) {
            try {
                $reservationToken = $this->couponReservations->reserve($promotionId, (int) $user->id);
            } catch (RuntimeException $exception) {
                return ['ok' => false, 'message' => $exception->getMessage(), 'status' => 409];
            }
        }

        $stripe = new StripeClient(GetpaymentMethod('stripe_secretkey'));
        try {
            $intent = $stripe->paymentIntents->create([
                'amount' => $amountMinor,
                'currency' => strtolower($currency),
                'automatic_payment_methods' => ['enabled' => true],
                'receipt_email' => $user->email,
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'purchase_type' => $purchaseType,
                    'purchase_id' => (string) $purchaseId,
                    'content_type' => (string) $request->input('type', ''),
                    'promotion_id' => (string) ($promotionId ?: ''),
                    'coupon_reservation' => (string) ($reservationToken ?? ''),
                ],
            ]);

            $this->couponReservations->bind($reservationToken, 'stripe', $intent->id);

            VerifiedPayment::query()->firstOrCreate(
                ['gateway' => 'stripe', 'provider_reference' => $intent->id],
                [
                    'user_id' => $user->id,
                    'purchase_type' => $purchaseType,
                    'purchase_id' => $purchaseId,
                    'amount_minor' => $amountMinor,
                    'currency' => $currency,
                ]
            );

            return [
                'ok' => true,
                'data' => [
                    'payment_intent_id' => $intent->id,
                    'client_secret' => $intent->client_secret,
                    'publishable_key' => GetpaymentMethod('stripe_publickey'),
                    'amount' => $amountMinor,
                    'currency' => $currency,
                ],
                'message' => 'Payment intent created.',
            ];
        } catch (Exception $e) {
            $this->couponReservations->release($reservationToken);
            Log::error('Mobile Stripe payment intent creation failed.', ['exception' => $e::class]);

            return ['ok' => false, 'message' => 'Unable to start payment. Please try again.', 'status' => 502];
        }
    }

    /**
     * @return array{ok: true, data: array<string, mixed>, message: string}|array{ok: false, message: string, status: int}
     */
    public function verifyMobilePaymentIntent(User $user, string $paymentIntentId): array
    {
        try {
            $stripe = new StripeClient(GetpaymentMethod('stripe_secretkey'));
            $intent = $stripe->paymentIntents->retrieve($paymentIntentId);
            $metadata = $intent->metadata;
            $receipt = VerifiedPayment::query()
                ->where('gateway', 'stripe')
                ->where('provider_reference', $intent->id)
                ->where('user_id', $user->id)
                ->first();

            if (! $receipt || (string) ($metadata->user_id ?? '') !== (string) $user->id
                || (string) ($metadata->purchase_type ?? '') !== $receipt->purchase_type
                || (int) ($metadata->purchase_id ?? 0) !== (int) $receipt->purchase_id
                || (int) $intent->amount !== (int) $receipt->amount_minor
                || strtoupper((string) $intent->currency) !== strtoupper($receipt->currency)
                || $intent->status !== 'succeeded') {
                return ['ok' => false, 'message' => 'Payment is incomplete or does not match this account and purchase.', 'status' => 422];
            }

            $purchase = $this->fulfillment->fulfillVerifiedPayment(
                'stripe',
                $intent->id,
                $user->id,
                $receipt->purchase_type,
                (int) $receipt->purchase_id,
                (int) $receipt->amount_minor,
                $receipt->currency,
                ! empty($metadata->promotion_id) ? (int) $metadata->promotion_id : null,
                (string) ($metadata->content_type ?? ''),
                ! empty($metadata->coupon_reservation) ? (string) $metadata->coupon_reservation : null
            );

            $data = $receipt->purchase_type === 'subscription'
                ? ['purchase_type' => 'subscription', 'subscription' => new SubscriptionResource($purchase)]
                : ['purchase_type' => 'pay_per_view', 'ppv' => $purchase];

            return ['ok' => true, 'data' => $data, 'message' => 'Payment verified and purchase fulfilled.'];
        } catch (Exception $e) {
            Log::error('Mobile Stripe payment verification failed.', ['exception' => $e::class]);

            return ['ok' => false, 'message' => 'Payment verification failed. Please contact support if you were charged.', 'status' => 400];
        }
    }

    /**
     * @return array{ok: true, data: array<string, mixed>, message: string}|array{ok: false, message: string, status: int}
     */
    public function createSubscriptionSession(
        string $paymentMethod,
        Plan $plan,
        float $price,
        string $currency,
        User $user,
        ?int $promotionId,
        string $frontendUrl,
        ?string $reservationToken = null
    ): array {
        return match ($paymentMethod) {
            'stripe' => $this->createStripeSubscriptionSession($plan, $price, $currency, $user, $promotionId, $frontendUrl, $reservationToken),
            'razorpay' => $this->createRazorpaySubscriptionOrder($plan, $price, $currency, $user, $promotionId, $reservationToken),
            'paystack' => $this->createPaystackSubscriptionTransaction($plan, $price, $currency, $user, $promotionId, $frontendUrl, $reservationToken),
            'paypal' => $this->rejectUnsupportedGateway($reservationToken, 'PayPal direct approval redirect requires standard client-side PayPal SDK order ID integration.', 400),
            default => $this->rejectUnsupportedGateway($reservationToken, "Payment method [{$paymentMethod}] is not supported for web checkout.", 400),
        };
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{ok: true, data: array<string, mixed>, message: string}|array{ok: false, message: string, status: int}
     */
    public function verifySubscriptionPayment(User $user, array $input): array
    {
        $gateway = strtolower((string) $input['gateway']);

        try {
            if ($gateway === 'stripe') {
                $stripe = new StripeClient(GetpaymentMethod('stripe_secretkey'));
                $session = $stripe->checkout->sessions->retrieve((string) $input['session_id']);
                $metadata = $session->metadata;
                $expectedCurrency = strtoupper((string) ($metadata->currency ?? (GetcurrentCurrency() ?: 'USD')));
                $expectedAmount = isset($metadata->amount_minor) ? (int) $metadata->amount_minor : null;

                if ($session->payment_status !== 'paid'
                    || (string) ($metadata->user_id ?? '') !== (string) $user->id
                    || strtoupper((string) $session->currency) !== $expectedCurrency
                    || ($expectedAmount !== null && (int) $session->amount_total !== $expectedAmount)) {
                    return ['ok' => false, 'message' => 'Stripe payment is incomplete or does not match this account.', 'status' => 400];
                }

                $plan = Plan::where('id', (int) ($metadata->plan_id ?? 0))->where('status', 1)->first();
                if (! $plan) {
                    return ['ok' => false, 'message' => 'The plan associated with this payment is unavailable.', 'status' => 404];
                }

                $subscription = $this->fulfillment->fulfillVerifiedPayment(
                    'stripe',
                    (string) ($session->payment_intent ?: $session->id),
                    $user->id,
                    'subscription',
                    $plan->id,
                    (int) $session->amount_total,
                    strtoupper((string) $session->currency),
                    ! empty($metadata->promotion_id) ? (int) $metadata->promotion_id : null,
                    null,
                    ! empty($metadata->coupon_reservation) ? (string) $metadata->coupon_reservation : null
                );

                return ['ok' => true, 'data' => ['subscription' => new SubscriptionResource($subscription)], 'message' => 'Subscription activated successfully.'];
            }

            if ($gateway === 'razorpay') {
                $api = new RazorpayApi(GetpaymentMethod('razorpay_publickey'), GetpaymentMethod('razorpay_secretkey'));
                $api->utility->verifyPaymentSignature([
                    'razorpay_order_id' => $input['order_id'],
                    'razorpay_payment_id' => $input['payment_id'],
                    'razorpay_signature' => $input['signature'],
                ]);

                $order = $api->order->fetch((string) $input['order_id']);
                $notes = $order['notes'] ?? [];
                $expectedCurrency = strtoupper((string) ($notes['currency'] ?? (GetcurrentCurrency() ?: 'USD')));
                $expectedAmount = isset($notes['amount_minor']) ? (int) $notes['amount_minor'] : null;

                if ((string) ($notes['user_id'] ?? '') !== (string) $user->id
                    || ($notes['purchase_type'] ?? '') !== 'subscription'
                    || strtoupper((string) ($order['currency'] ?? '')) !== $expectedCurrency
                    || ($expectedAmount !== null && (int) ($order['amount'] ?? 0) !== $expectedAmount)) {
                    return ['ok' => false, 'message' => 'Razorpay order does not belong to this account.', 'status' => 403];
                }

                $payment = $api->payment->fetch((string) $input['payment_id']);
                if ($payment->status !== 'captured'
                    || (string) ($payment->order_id ?? '') !== (string) $input['order_id']
                    || (int) $payment->amount !== (int) ($order['amount'] ?? 0)
                    || strtoupper((string) $payment->currency) !== strtoupper((string) ($order['currency'] ?? ''))) {
                    return ['ok' => false, 'message' => 'Razorpay payment is not captured for this order.', 'status' => 400];
                }

                $planId = (int) ($notes['plan_id'] ?? 0);
                if (! empty($input['plan_id']) && (int) $input['plan_id'] !== $planId) {
                    return ['ok' => false, 'message' => 'Requested plan does not match the paid order.', 'status' => 422];
                }
                $plan = Plan::where('id', $planId)->where('status', 1)->first();
                if (! $plan) {
                    return ['ok' => false, 'message' => 'The plan associated with this order is unavailable.', 'status' => 404];
                }

                $subscription = $this->fulfillment->fulfillVerifiedPayment(
                    'razorpay',
                    (string) $payment->id,
                    $user->id,
                    'subscription',
                    $plan->id,
                    (int) $payment->amount,
                    strtoupper((string) $payment->currency),
                    ! empty($notes['promotion_id']) ? (int) $notes['promotion_id'] : null,
                    null,
                    ! empty($notes['coupon_reservation']) ? (string) $notes['coupon_reservation'] : null
                );

                return ['ok' => true, 'data' => ['subscription' => new SubscriptionResource($subscription)], 'message' => 'Subscription activated successfully.'];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.GetpaymentMethod('paystack_secretkey'),
            ])->get('https://api.paystack.co/transaction/verify/'.urlencode((string) $input['reference']));
            $body = $response->json();
            $data = $body['data'] ?? [];
            $metadata = $data['metadata'] ?? [];
            $expectedCurrency = strtoupper((string) ($metadata['currency'] ?? (GetcurrentCurrency() ?: 'USD')));
            $expectedAmount = isset($metadata['amount_minor']) ? (int) $metadata['amount_minor'] : null;

            if (! $response->successful() || ! ($body['status'] ?? false) || ($data['status'] ?? '') !== 'success'
                || (string) ($metadata['user_id'] ?? '') !== (string) $user->id
                || strtolower((string) ($data['customer']['email'] ?? '')) !== strtolower((string) $user->email)
                || strtoupper((string) ($data['currency'] ?? '')) !== $expectedCurrency
                || ($expectedAmount !== null && (int) ($data['amount'] ?? 0) !== $expectedAmount)) {
                return ['ok' => false, 'message' => 'Paystack payment could not be verified for this account.', 'status' => 400];
            }

            $plan = Plan::where('id', (int) ($metadata['plan_id'] ?? 0))->where('status', 1)->first();
            if (! $plan) {
                return ['ok' => false, 'message' => 'The plan associated with this payment is unavailable.', 'status' => 404];
            }

            $subscription = $this->fulfillment->fulfillVerifiedPayment(
                'paystack',
                (string) $input['reference'],
                $user->id,
                'subscription',
                $plan->id,
                (int) $data['amount'],
                strtoupper((string) $data['currency']),
                ! empty($metadata['promotion_id']) ? (int) $metadata['promotion_id'] : null,
                null,
                ! empty($metadata['coupon_reservation']) ? (string) $metadata['coupon_reservation'] : null
            );

            return ['ok' => true, 'data' => ['subscription' => new SubscriptionResource($subscription)], 'message' => 'Subscription activated successfully.'];
        } catch (Exception $e) {
            Log::error('Subscription payment verification failed.', ['gateway' => $gateway, 'exception' => $e::class]);

            return ['ok' => false, 'message' => 'Payment verification failed. Please contact support if you were charged.', 'status' => 500];
        }
    }

    /**
     * @return array{ok: true, data: array<string, mixed>, message: string}|array{ok: false, message: string, status: int}
     */
    public function createPayPerViewSession(
        string $paymentMethod,
        object $content,
        string $contentType,
        float $finalPrice,
        string $currency,
        User $user,
        string $frontendUrl
    ): array {
        $amountMinor = $this->checkoutPricing->toMinorUnits($finalPrice, $currency, $paymentMethod);

        if ($paymentMethod === 'stripe') {
            try {
                $stripe = new StripeClient(GetpaymentMethod('stripe_secretkey'));
                $checkoutSession = $stripe->checkout->sessions->create([
                    'payment_method_types' => ['card'],
                    'customer_email' => $user->email,
                    'line_items' => [[
                        'price_data' => [
                            'currency' => strtolower($currency),
                            'product_data' => ['name' => 'Pay-Per-View: '.($content->name ?? 'Content')],
                            'unit_amount' => $amountMinor,
                        ],
                        'quantity' => 1,
                    ]],
                    'mode' => 'payment',
                    'metadata' => [
                        'movie_id' => (string) $content->id,
                        'type' => $contentType,
                        'is_ppv' => '1',
                        'user_id' => (string) $user->id,
                    ],
                    'success_url' => "{$frontendUrl}/payment-success?gateway=stripe&session_id={CHECKOUT_SESSION_ID}&is_ppv=1",
                    'cancel_url' => "{$frontendUrl}/movies",
                ]);

                return [
                    'ok' => true,
                    'data' => [
                        'redirect_url' => $checkoutSession->url,
                        'session_id' => $checkoutSession->id,
                    ],
                    'message' => 'Stripe checkout initiated.',
                ];
            } catch (Exception $e) {
                Log::error('Stripe PPV checkout creation failed.', ['exception' => $e::class]);

                return ['ok' => false, 'message' => 'Unable to start payment. Please try again.', 'status' => 502];
            }
        }

        try {
            $razorpayKey = GetpaymentMethod('razorpay_publickey');
            $api = new RazorpayApi($razorpayKey, GetpaymentMethod('razorpay_secretkey'));
            $order = $api->order->create([
                'receipt' => 'ppv_'.bin2hex(random_bytes(12)),
                'amount' => $amountMinor,
                'currency' => $currency,
                'payment_capture' => 1,
                'notes' => [
                    'purchase_type' => 'pay_per_view',
                    'user_id' => (string) $user->id,
                    'content_id' => (string) $content->id,
                    'content_type' => $contentType,
                ],
            ]);

            return [
                'ok' => true,
                'data' => [
                    'key' => $razorpayKey,
                    'amount' => $amountMinor,
                    'currency' => $currency,
                    'name' => config('app.name'),
                    'description' => 'Rental: '.($content->name ?? 'Content'),
                    'order_id' => $order['id'],
                    'prefill' => ['name' => $user->name, 'email' => $user->email, 'contact' => $user->phone ?? ''],
                ],
                'message' => 'Razorpay order created successfully.',
            ];
        } catch (Exception $e) {
            Log::error('Razorpay PPV order creation failed.', ['exception' => $e::class]);

            return ['ok' => false, 'message' => 'Unable to start payment. Please try again.', 'status' => 502];
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{ok: true, data: array<string, mixed>, message: string}|array{ok: false, message: string, status: int}
     */
    public function verifyPayPerViewPayment(User $user, array $input): array
    {
        $gateway = strtolower((string) $input['gateway']);

        try {
            if ($gateway === 'stripe') {
                $stripe = new StripeClient(GetpaymentMethod('stripe_secretkey'));
                $session = $stripe->checkout->sessions->retrieve((string) $input['session_id']);
                $metadata = $session->metadata;
                if ($session->payment_status !== 'paid' || (string) ($metadata->is_ppv ?? '') !== '1'
                    || (string) ($metadata->user_id ?? '') !== (string) $user->id
                    || strtoupper((string) $session->currency) !== strtoupper(GetcurrentCurrency() ?: 'USD')) {
                    return ['ok' => false, 'message' => 'Payment is incomplete or does not match this account.', 'status' => 400];
                }

                $contentId = (int) ($metadata->movie_id ?? 0);
                $contentType = (string) ($metadata->type ?? '');
                $content = $this->checkoutPricing->resolveContentModel($contentType, $contentId);
                if (! $content) {
                    return ['ok' => false, 'message' => 'Content associated with this payment was not found.', 'status' => 404];
                }

                $ppv = $this->fulfillment->fulfillVerifiedPayment(
                    'stripe',
                    (string) ($session->payment_intent ?: $session->id),
                    $user->id,
                    'pay_per_view',
                    $content->id,
                    (int) $session->amount_total,
                    strtoupper((string) $session->currency),
                    null,
                    $contentType
                );

                return ['ok' => true, 'data' => ['ppv' => $ppv], 'message' => 'Pay-per-view rental unlocked successfully.'];
            }

            $api = new RazorpayApi(GetpaymentMethod('razorpay_publickey'), GetpaymentMethod('razorpay_secretkey'));
            $api->utility->verifyPaymentSignature([
                'razorpay_order_id' => $input['order_id'],
                'razorpay_payment_id' => $input['payment_id'],
                'razorpay_signature' => $input['signature'],
            ]);
            $order = $api->order->fetch((string) $input['order_id']);
            $notes = $order['notes'] ?? [];
            if ((string) ($notes['user_id'] ?? '') !== (string) $user->id || ($notes['purchase_type'] ?? '') !== 'pay_per_view') {
                return ['ok' => false, 'message' => 'Razorpay order does not belong to this account.', 'status' => 403];
            }

            $contentId = (int) ($notes['content_id'] ?? 0);
            $contentType = (string) ($notes['content_type'] ?? '');
            $content = $this->checkoutPricing->resolveContentModel($contentType, $contentId);
            if (! $content) {
                return ['ok' => false, 'message' => 'Content associated with this order was not found.', 'status' => 404];
            }

            $payment = $api->payment->fetch((string) $input['payment_id']);
            if ($payment->status !== 'captured'
                || (string) ($payment->order_id ?? '') !== (string) $input['order_id']
                || (int) $payment->amount !== (int) ($order['amount'] ?? 0)
                || strtoupper((string) $payment->currency) !== strtoupper((string) ($order['currency'] ?? ''))) {
                return ['ok' => false, 'message' => 'Razorpay payment is not captured for this order.', 'status' => 400];
            }

            $ppv = $this->fulfillment->fulfillVerifiedPayment(
                'razorpay',
                (string) $payment->id,
                $user->id,
                'pay_per_view',
                $content->id,
                (int) $payment->amount,
                strtoupper((string) $payment->currency),
                null,
                $contentType
            );

            return ['ok' => true, 'data' => ['ppv' => $ppv], 'message' => 'Pay-per-view rental unlocked successfully.'];
        } catch (Exception $e) {
            Log::error('PPV payment verification failed.', ['gateway' => $gateway, 'exception' => $e::class]);

            return ['ok' => false, 'message' => 'Payment verification failed. Please contact support if you were charged.', 'status' => 500];
        }
    }

    /**
     * @return array{ok: true, data: array<string, mixed>, message: string}|array{ok: false, message: string, status: int}
     */
    private function createStripeSubscriptionSession(
        Plan $plan,
        float $price,
        string $currency,
        User $user,
        ?int $promotionId,
        string $frontendUrl,
        ?string $reservationToken = null
    ): array {
        $stripe = new StripeClient(GetpaymentMethod('stripe_secretkey'));
        $unitAmount = $this->checkoutPricing->toMinorUnits($price, $currency);

        try {
            $session = $stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'customer_email' => $user->email,
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'product_data' => ['name' => 'Subscription Plan: '.$plan->name],
                        'unit_amount' => $unitAmount,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'metadata' => [
                    'plan_id' => (string) $plan->id,
                    'promotion_id' => (string) ($promotionId ?? ''),
                    'coupon_reservation' => (string) ($reservationToken ?? ''),
                    'user_id' => (string) $user->id,
                    'amount_minor' => (string) $unitAmount,
                    'currency' => strtoupper($currency),
                ],
                'payment_intent_data' => ['metadata' => [
                    'purchase_type' => 'subscription',
                    'purchase_id' => (string) $plan->id,
                    'plan_id' => (string) $plan->id,
                    'promotion_id' => (string) ($promotionId ?? ''),
                    'coupon_reservation' => (string) ($reservationToken ?? ''),
                    'user_id' => (string) $user->id,
                    'amount_minor' => (string) $unitAmount,
                    'currency' => strtoupper($currency),
                ]],
                'success_url' => "{$frontendUrl}/payment-success?gateway=stripe&session_id={CHECKOUT_SESSION_ID}",
                'cancel_url' => "{$frontendUrl}/pricing",
            ]);

            $this->couponReservations->bind($reservationToken, 'stripe', $session->id);

            return [
                'ok' => true,
                'data' => ['redirect_url' => $session->url, 'session_id' => $session->id],
                'message' => 'Stripe checkout session created successfully.',
            ];
        } catch (Exception) {
            $this->couponReservations->release($reservationToken);
            Log::error('Sensitive or user-provided values omitted from logs');

            return ['ok' => false, 'message' => 'Unable to start payment. Please try again.', 'status' => 502];
        }
    }

    /**
     * @return array{ok: true, data: array<string, mixed>, message: string}|array{ok: false, message: string, status: int}
     */
    private function createRazorpaySubscriptionOrder(
        Plan $plan,
        float $price,
        string $currency,
        User $user,
        ?int $promotionId,
        ?string $reservationToken = null
    ): array {
        $razorpayKey = GetpaymentMethod('razorpay_publickey');
        $api = new RazorpayApi($razorpayKey, GetpaymentMethod('razorpay_secretkey'));
        $amountMinor = $this->checkoutPricing->toMinorUnits($price, $currency, 'razorpay');

        try {
            $order = $api->order->create([
                'receipt' => 'sub_'.bin2hex(random_bytes(12)),
                'amount' => $amountMinor,
                'currency' => strtoupper($currency),
                'payment_capture' => 1,
                'notes' => [
                    'purchase_type' => 'subscription',
                    'user_id' => (string) $user->id,
                    'plan_id' => (string) $plan->id,
                    'promotion_id' => (string) ($promotionId ?? ''),
                    'coupon_reservation' => (string) ($reservationToken ?? ''),
                    'amount_minor' => (string) $amountMinor,
                    'currency' => strtoupper($currency),
                ],
            ]);

            $this->couponReservations->bind($reservationToken, 'razorpay', (string) $order['id']);

            return [
                'ok' => true,
                'data' => [
                    'key' => $razorpayKey,
                    'amount' => $amountMinor,
                    'currency' => strtoupper($currency),
                    'name' => config('app.name'),
                    'description' => "Subscription Plan: {$plan->name}",
                    'order_id' => $order['id'],
                    'plan_id' => $plan->id,
                    'promotion_id' => $promotionId,
                    'prefill' => ['name' => $user->name, 'email' => $user->email, 'contact' => $user->phone ?? ''],
                ],
                'message' => 'Razorpay order created successfully.',
            ];
        } catch (Exception $e) {
            $this->couponReservations->release($reservationToken);
            Log::error('Razorpay subscription order creation failed.', ['exception' => $e::class]);

            return ['ok' => false, 'message' => 'Unable to start payment. Please try again.', 'status' => 502];
        }
    }

    /**
     * @return array{ok: true, data: array<string, mixed>, message: string}|array{ok: false, message: string, status: int}
     */
    private function createPaystackSubscriptionTransaction(
        Plan $plan,
        float $price,
        string $currency,
        User $user,
        ?int $promotionId,
        string $frontendUrl,
        ?string $reservationToken = null
    ): array {
        $secretKey = GetpaymentMethod('paystack_secretkey');
        $amountMinor = $this->checkoutPricing->toMinorUnits($price, $currency, 'paystack');
        $callbackUrl = "{$frontendUrl}/payment-success?gateway=paystack&plan_id={$plan->id}";

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$secretKey,
        ])->post('https://api.paystack.co/transaction/initialize', [
            'email' => $user->email,
            'amount' => $amountMinor,
            'currency' => strtoupper($currency),
            'callback_url' => $callbackUrl,
            'metadata' => [
                'purchase_type' => 'subscription',
                'plan_id' => $plan->id,
                'promotion_id' => $promotionId,
                'coupon_reservation' => $reservationToken,
                'user_id' => $user->id,
                'amount_minor' => $amountMinor,
                'currency' => strtoupper($currency),
            ],
        ]);

        $body = $response->json();
        if ($body['status'] ?? false) {
            $this->couponReservations->bind($reservationToken, 'paystack', (string) $body['data']['reference']);

            return [
                'ok' => true,
                'data' => [
                    'redirect_url' => $body['data']['authorization_url'],
                    'reference' => $body['data']['reference'],
                ],
                'message' => 'Paystack checkout initiated.',
            ];
        }

        $this->couponReservations->release($reservationToken);

        return ['ok' => false, 'message' => $body['message'] ?? 'Paystack initiation failed.', 'status' => 400];
    }

    /**
     * @return array{ok: false, message: string, status: int}
     */
    private function rejectUnsupportedGateway(?string $reservationToken, string $message, int $status): array
    {
        $this->couponReservations->release($reservationToken);

        return ['ok' => false, 'message' => $message, 'status' => $status];
    }
}
