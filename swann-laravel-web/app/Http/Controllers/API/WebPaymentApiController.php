<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Models\VerifiedPayment;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Coupon\Models\Coupon;
use Modules\Entertainment\Models\Entertainment;
use Modules\Episode\Models\Episode;
use Modules\Frontend\Models\PayPerView;
use Modules\Frontend\Models\PayperviewTransaction;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Subscription;
use Modules\Subscriptions\Models\SubscriptionTransactions;
use Modules\Subscriptions\Trait\SubscriptionTrait;
use Modules\Subscriptions\Transformers\PlanResource;
use Modules\Subscriptions\Transformers\SubscriptionResource;
use Modules\Tax\Models\Tax;
use Modules\Video\Models\Video;
use Stripe\StripeClient;
use Stripe\Webhook;

class WebPaymentApiController extends Controller
{
    use SubscriptionTrait;

    /**
     * Get all active subscription plans with full limitations and pricing
     */
    public function getPlans(Request $request)
    {
        $plans = Plan::with('planLimitation')
            ->where('status', 1)
            ->orderBy('level', 'asc')
            ->get();

        return ApiResponse::success(PlanResource::collection($plans), 'Plans retrieved successfully', 200);
    }

    /**
     * Calculate subscription price, applying discount, coupon, and taxes
     */
    public function calculateSubscriptionPrice(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plan,id',
            'coupon_code' => 'nullable|string',
            'coupon_id' => 'nullable|integer',
        ]);

        $plan = Plan::with('planLimitation')->where('id', $request->plan_id)->where('status', 1)->first();
        if (! $plan) {
            return ApiResponse::error('Selected plan is invalid or inactive.', 404);
        }

        $basePrice = $plan->price;
        $planDiscountAmount = 0;
        if ($plan->discount_percentage > 0) {
            $planDiscountAmount = ($basePrice * $plan->discount_percentage) / 100;
            $basePrice = $basePrice - $planDiscountAmount;
        }

        $couponDiscountAmount = 0;
        $couponData = null;

        // Check coupon if provided
        $couponQuery = Coupon::where('status', 1)
            ->where('start_date', '<=', now())
            ->where('expire_date', '>=', now())
            ->whereHas('CouponPlanMappings', function ($q) use ($plan) {
                $q->where('subscription_plan_id', $plan->id);
            });

        if ($request->filled('coupon_code')) {
            $couponQuery->where('code', $request->coupon_code);
        } elseif ($request->filled('coupon_id')) {
            $couponQuery->where('id', $request->coupon_id);
        }

        $coupon = $couponQuery->first();
        if ($coupon) {
            if ($coupon->discount_type === 'fixed') {
                $couponDiscountAmount = min($basePrice, (float) $coupon->discount);
            } else {
                $couponDiscountAmount = ($basePrice * (float) $coupon->discount) / 100;
            }
            $couponData = [
                'id' => $coupon->id,
                'coupon_code' => $coupon->code,
                'discount_type' => $coupon->discount_type,
                'discount' => $coupon->discount,
                'discount_amount' => round($couponDiscountAmount, 2),
            ];
        } elseif ($request->filled('coupon_code')) {
            return ApiResponse::error('The provided coupon code is invalid or has expired.', 422);
        }

        $subtotalAfterDiscounts = max(0, $basePrice - $couponDiscountAmount);

        // Calculate active taxes
        $taxes = Tax::active()->get();
        $totalTax = 0;
        $taxBreakdown = [];

        foreach ($taxes as $tax) {
            $taxAmount = 0;
            if (strtolower($tax->type) === 'fixed') {
                $taxAmount = (float) $tax->value;
            } else {
                $taxAmount = ($subtotalAfterDiscounts * (float) $tax->value) / 100;
            }
            $totalTax += $taxAmount;
            $taxBreakdown[] = [
                'title' => $tax->title,
                'type' => $tax->type,
                'value' => $tax->value,
                'tax_amount' => round($taxAmount, 2),
            ];
        }

        $finalTotal = round($subtotalAfterDiscounts + $totalTax, 2);

        return ApiResponse::success([
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'original_price' => (float) $plan->price,
            'plan_discount_percentage' => (float) $plan->discount_percentage,
            'plan_discount_amount' => round($planDiscountAmount, 2),
            'price_after_plan_discount' => round($basePrice, 2),
            'coupon' => $couponData,
            'coupon_discount_amount' => round($couponDiscountAmount, 2),
            'subtotal' => round($subtotalAfterDiscounts, 2),
            'total_tax' => round($totalTax, 2),
            'taxes' => $taxBreakdown,
            'total_amount' => $finalTotal,
            'currency' => GetcurrentCurrency() ?: 'USD',
        ], 'Price calculated successfully', 200);
    }

    /** Create a Stripe PaymentIntent for the authenticated mobile checkout. */
    public function createMobilePaymentIntent(Request $request)
    {
        $request->validate([
            'purchase_type' => 'required|string|in:subscription,pay_per_view',
            'plan_id' => 'required_if:purchase_type,subscription|nullable|integer',
            'movie_id' => 'required_if:purchase_type,pay_per_view|nullable|integer',
            'type' => 'required_if:purchase_type,pay_per_view|nullable|string|in:movie,tvshow,video,episode,season',
            'coupon_code' => 'nullable|string',
            'coupon_id' => 'nullable|integer',
        ]);

        $user = Auth::user();
        $purchaseType = $request->string('purchase_type')->toString();
        $pricingRequest = $purchaseType === 'subscription'
            ? new Request(['plan_id' => $request->integer('plan_id'), 'coupon_code' => $request->input('coupon_code'), 'coupon_id' => $request->input('coupon_id')])
            : new Request(['movie_id' => $request->integer('movie_id'), 'type' => $request->input('type')]);
        $pricingResponse = $purchaseType === 'subscription'
            ? $this->calculateSubscriptionPrice($pricingRequest)
            : $this->calculatePayPerViewPrice($pricingRequest);
        $pricing = $pricingResponse->getData(true);

        if (!($pricing['status'] ?? false)) {
            return ApiResponse::error($pricing['message'] ?? 'Unable to calculate the current price.', 422);
        }

        $pricingData = $pricing['data'];
        $amount = (float) $pricingData['total_amount'];
        $currency = strtoupper($pricingData['currency'] ?? GetcurrentCurrency() ?: 'USD');
        $amountMinor = $this->toMinorUnits($amount, $currency);
        if ($amountMinor <= 0) {
            return ApiResponse::error('A payment intent is not required for a zero-cost purchase.', 422);
        }

        $purchaseId = $purchaseType === 'subscription'
            ? (int) $request->plan_id
            : (int) $request->movie_id;
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
                    'promotion_id' => (string) ($pricingData['coupon']['id'] ?? ''),
                ],
            ]);

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

            return ApiResponse::success([
                'payment_intent_id' => $intent->id,
                'client_secret' => $intent->client_secret,
                'publishable_key' => GetpaymentMethod('stripe_publickey'),
                'amount' => $amountMinor,
                'currency' => $currency,
            ], 'Payment intent created.', 200);
        } catch (Exception $e) {
            Log::error('Mobile Stripe payment intent creation failed.', ['exception' => $e::class]);
            return ApiResponse::error('Unable to start payment. Please try again.', 502);
        }
    }

    /** Verify Stripe directly on the server and fulfill a mobile purchase once. */
    public function verifyMobilePaymentIntent(Request $request)
    {
        $request->validate(['payment_intent_id' => 'required|string|max:191']);
        $user = Auth::user();

        try {
            $stripe = new StripeClient(GetpaymentMethod('stripe_secretkey'));
            $intent = $stripe->paymentIntents->retrieve($request->payment_intent_id);
            $metadata = $intent->metadata;
            $receipt = VerifiedPayment::query()
                ->where('gateway', 'stripe')
                ->where('provider_reference', $intent->id)
                ->where('user_id', $user->id)
                ->first();

            if (!$receipt || (string) ($metadata->user_id ?? '') !== (string) $user->id
                || (string) ($metadata->purchase_type ?? '') !== $receipt->purchase_type
                || (int) ($metadata->purchase_id ?? 0) !== (int) $receipt->purchase_id
                || (int) $intent->amount !== (int) $receipt->amount_minor
                || strtoupper((string) $intent->currency) !== strtoupper($receipt->currency)
                || $intent->status !== 'succeeded') {
                return ApiResponse::error('Payment is incomplete or does not match this account and purchase.', 422);
            }

            $purchase = $this->fulfillVerifiedPayment(
                'stripe',
                $intent->id,
                $user->id,
                $receipt->purchase_type,
                (int) $receipt->purchase_id,
                (int) $receipt->amount_minor,
                $receipt->currency,
                !empty($metadata->promotion_id) ? (int) $metadata->promotion_id : null,
                (string) ($metadata->content_type ?? '')
            );

            $data = $receipt->purchase_type === 'subscription'
                ? ['purchase_type' => 'subscription', 'subscription' => new SubscriptionResource($purchase)]
                : ['purchase_type' => 'pay_per_view', 'ppv' => $purchase];

            return ApiResponse::success($data, 'Payment verified and purchase fulfilled.', 200);
        } catch (Exception $e) {
            Log::error('Mobile Stripe payment verification failed.', ['exception' => $e::class]);
            return ApiResponse::error('Payment verification failed. Please contact support if you were charged.', 400);
        }
    }

    private function toMinorUnits(float $amount, string $currency): int
    {
        return (int) round($amount * $this->currencyMultiplier($currency));
    }

    /** Receive provider-confirmed Stripe payments even if the client disconnects. */
    public function stripeWebhook(Request $request)
    {
        $secret = (string) config('services.stripe.webhook_secret', env('STRIPE_WEBHOOK_SECRET', ''));
        if ($secret === '') {
            return ApiResponse::error('Stripe webhook is not configured.', 503);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $secret
            );
        } catch (\UnexpectedValueException | \Stripe\Exception\SignatureVerificationException $e) {
            return ApiResponse::error('Invalid Stripe webhook signature or payload.', 400);
        }

        try {
            $object = $event->data->object;
            if ($event->type === 'payment_intent.succeeded') {
                $payment = $object->toArray();
                $this->fulfillStripeWebhookPayment($payment, (string) $payment['id'], (int) ($payment['amount_received'] ?? $payment['amount'] ?? 0));
            } elseif (in_array($event->type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
                $session = $object->toArray();
                if (($session['payment_status'] ?? '') === 'paid' && ($session['mode'] ?? '') === 'payment') {
                    $paymentReference = $session['payment_intent'] ?? $session['id'] ?? null;
                    if (is_array($paymentReference)) {
                        $paymentReference = $paymentReference['id'] ?? null;
                    }
                    if (!is_string($paymentReference) || $paymentReference === '') {
                        throw new \RuntimeException('Stripe checkout session has no payment reference.');
                    }

                    $metadata = $session['metadata'] ?? [];
                    if (($metadata['is_ppv'] ?? '') === '1') {
                        $metadata['purchase_type'] = 'pay_per_view';
                        $metadata['purchase_id'] = $metadata['movie_id'] ?? 0;
                        $metadata['content_type'] = $metadata['type'] ?? '';
                    } else {
                        $metadata['purchase_type'] = 'subscription';
                        $metadata['purchase_id'] = $metadata['plan_id'] ?? 0;
                    }
                    $this->fulfillStripeWebhookPayment([
                        'metadata' => $metadata,
                        'currency' => $session['currency'] ?? '',
                    ], $paymentReference, (int) ($session['amount_total'] ?? 0));
                }
            }

            return response()->json(['received' => true], 200);
        } catch (Exception $e) {
            Log::error('Stripe webhook fulfillment failed.', [
                'event_id' => $event->id,
                'event_type' => $event->type,
                'exception' => $e::class,
            ]);
            return ApiResponse::error('Stripe webhook processing failed.', 500);
        }
    }

    /** Receive captured Razorpay payments using their signed raw request body. */
    public function razorpayWebhook(Request $request)
    {
        $secret = (string) config('services.razorpay.webhook_secret', '');
        $signature = (string) $request->header('X-Razorpay-Signature');
        $payload = $request->getContent();
        if ($secret === '' || $signature === '' || !hash_equals(hash_hmac('sha256', $payload, $secret), $signature)) {
            return ApiResponse::error('Invalid Razorpay webhook signature.', 400);
        }

        try {
            $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            if (($event['event'] ?? '') !== 'payment.captured') {
                return response()->json(['received' => true], 200);
            }

            $entity = $event['payload']['payment']['entity'] ?? [];
            $paymentId = (string) ($entity['id'] ?? '');
            $orderId = (string) ($entity['order_id'] ?? '');
            if ($paymentId === '' || $orderId === '') {
                throw new \RuntimeException('Razorpay capture event is missing its payment or order ID.');
            }

            $api = new \Razorpay\Api\Api(GetpaymentMethod('razorpay_publickey'), GetpaymentMethod('razorpay_secretkey'));
            $payment = $api->payment->fetch($paymentId);
            $order = $api->order->fetch($orderId);
            $notes = $order['notes'] ?? [];
            if ($payment->status !== 'captured'
                || (string) ($payment->order_id ?? '') !== $orderId
                || (int) $payment->amount !== (int) ($order['amount'] ?? 0)
                || strtoupper((string) $payment->currency) !== strtoupper((string) ($order['currency'] ?? ''))
                || !ctype_digit((string) ($notes['user_id'] ?? ''))) {
                throw new \RuntimeException('Razorpay payment does not match its captured order.');
            }

            $userId = (int) $notes['user_id'];
            $purchaseType = (string) ($notes['purchase_type'] ?? '');
            if ($purchaseType === 'subscription') {
                $purchaseId = (int) ($notes['plan_id'] ?? 0);
                $promotionId = !empty($notes['promotion_id']) ? (int) $notes['promotion_id'] : null;
                $contentType = null;
            } elseif ($purchaseType === 'pay_per_view') {
                $purchaseId = (int) ($notes['content_id'] ?? 0);
                $promotionId = null;
                $contentType = (string) ($notes['content_type'] ?? '');
            } else {
                throw new \RuntimeException('Razorpay order has an unsupported purchase type.');
            }

            if ($purchaseId <= 0) {
                throw new \RuntimeException('Razorpay order has no valid purchase ID.');
            }

            $this->fulfillVerifiedPayment(
                'razorpay', $paymentId, $userId, $purchaseType, $purchaseId,
                (int) $payment->amount, strtoupper((string) $payment->currency), $promotionId, $contentType
            );

            return response()->json(['received' => true], 200);
        } catch (Exception $e) {
            Log::error('Razorpay webhook fulfillment failed.', ['exception' => $e::class]);
            return ApiResponse::error('Razorpay webhook processing failed.', 500);
        }
    }

    /** Reconcile the signed-in user's app-store entitlement against RevenueCat. */
    public function syncInAppPurchaseSubscription()
    {
        $user = Auth::user();
        if (!$user) {
            return ApiResponse::error('Authentication is required.', 401);
        }

        try {
            $result = $this->syncRevenueCatSubscription($user);
            return ApiResponse::success($result, 'In-app subscription status synchronized.', 200);
        } catch (Exception $e) {
            Log::error('RevenueCat subscription synchronization failed.', ['exception' => $e::class, 'user_id' => $user->id]);
            return ApiResponse::error('Unable to verify the current store subscription.', 502);
        }
    }

    /** Verify and reconcile RevenueCat lifecycle events using the raw body HMAC. */
    public function revenueCatWebhook(Request $request)
    {
        $secret = (string) config('services.revenuecat.webhook_secret', '');
        $header = (string) $request->header('X-RevenueCat-Webhook-Signature');
        $parts = [];
        foreach (explode(',', $header) as $part) {
            if (str_contains($part, '=')) {
                [$key, $value] = explode('=', $part, 2);
                $parts[trim($key)] = trim($value);
            }
        }

        $timestamp = $parts['t'] ?? '';
        $signature = $parts['v1'] ?? '';
        if ($secret === '' || !ctype_digit($timestamp) || $signature === ''
            || abs(time() - (int) $timestamp) > 300
            || !hash_equals(hash_hmac('sha256', $timestamp . '.' . $request->getContent(), $secret), $signature)) {
            return ApiResponse::error('Invalid RevenueCat webhook signature.', 401);
        }

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $event = $payload['event'] ?? [];
            $eventType = (string) ($event['type'] ?? '');
            if (!in_array($eventType, [
                'INITIAL_PURCHASE', 'RENEWAL', 'UNCANCELLATION', 'PRODUCT_CHANGE',
                'CANCELLATION', 'EXPIRATION', 'REFUND', 'REFUND_REVERSED',
                'SUBSCRIPTION_EXTENDED', 'TRANSFER',
            ], true)) {
                return response()->json(['received' => true], 200);
            }

            $candidateIds = array_values(array_unique(array_filter(array_merge(
                [(string) ($event['app_user_id'] ?? '')],
                is_array($event['aliases'] ?? null) ? $event['aliases'] : [],
                is_array($event['transferred_from'] ?? null) ? $event['transferred_from'] : [],
                is_array($event['transferred_to'] ?? null) ? $event['transferred_to'] : []
            ), static fn ($value) => is_string($value) && $value !== '')));

            $users = User::query()
                ->whereIn('id', array_values(array_filter($candidateIds, 'ctype_digit')))
                ->orWhereIn('email', $candidateIds)
                ->get()
                ->unique('id');

            foreach ($users as $user) {
                $this->syncRevenueCatSubscription($user);
            }

            return response()->json(['received' => true], 200);
        } catch (Exception $e) {
            Log::error('RevenueCat webhook reconciliation failed.', ['exception' => $e::class]);
            return ApiResponse::error('RevenueCat webhook processing failed.', 500);
        }
    }

    private function syncRevenueCatSubscription(User $user): array
    {
        $apiKey = (string) config('services.revenuecat.api_key', '');
        $entitlementId = (string) GetpaymentMethod('iap_entitlement_id');
        if ($apiKey === '' || $entitlementId === '') {
            throw new \RuntimeException('RevenueCat API key or entitlement ID is not configured.');
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(8)
            ->get('https://api.revenuecat.com/v1/subscribers/' . rawurlencode((string) $user->id));

        if ($response->status() === 404) {
            return $this->deactivateRevenueCatSubscription($user);
        }
        if (!$response->successful()) {
            throw new \RuntimeException('RevenueCat subscriber lookup failed with HTTP ' . $response->status() . '.');
        }

        $subscriber = $response->json('subscriber', []);
        $entitlement = $subscriber['entitlements'][$entitlementId] ?? null;
        $expiresAt = !empty($entitlement['expires_date']) ? Carbon::parse($entitlement['expires_date']) : null;
        if (!$entitlement || ($expiresAt && !$expiresAt->isFuture())) {
            return $this->deactivateRevenueCatSubscription($user);
        }

        $productIdentifier = (string) ($entitlement['product_identifier'] ?? '');
        if ($productIdentifier === '') {
            throw new \RuntimeException('RevenueCat entitlement has no product identifier.');
        }

        $productBaseId = explode(':', $productIdentifier, 2)[0];
        $plan = Plan::where('status', 1)
            ->where(function ($query) use ($productIdentifier, $productBaseId) {
                $query->where('apple_identifier', $productIdentifier)
                    ->orWhere('android_identifier', $productIdentifier)
                    ->orWhere('android_identifier', $productBaseId);
            })
            ->first();
        if (!$plan) {
            throw new \RuntimeException('No active subscription plan maps to the RevenueCat product.');
        }

        $expiresKey = $expiresAt?->utc()->format('YmdHis') ?? 'lifetime';
        $providerReference = 'rc_' . $user->id . '_' . substr(hash('sha256', $productIdentifier . '|' . $expiresKey), 0, 32);
        $subscription = $this->fulfillVerifiedPayment(
            'revenuecat', $providerReference, $user->id, 'subscription', (int) $plan->id,
            0, 'USD'
        );

        if (!empty($entitlement['purchase_date'])) {
            $subscription->start_date = Carbon::parse($entitlement['purchase_date']);
        }
        $subscription->end_date = $expiresAt;
        $subscription->save();

        return [
            'has_active_subscription' => true,
            'subscription' => new SubscriptionResource($subscription->fresh()),
        ];
    }

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

    private function fulfillStripeWebhookPayment(array $payment, string $providerReference, int $amountMinor): void
    {
        $metadata = $payment['metadata'] ?? [];
        $userId = (int) ($metadata['user_id'] ?? 0);
        $purchaseType = (string) ($metadata['purchase_type'] ?? '');
        $purchaseId = (int) ($metadata['purchase_id'] ?? 0);
        $currency = strtoupper((string) ($payment['currency'] ?? ''));

        if ($userId <= 0 || $purchaseId <= 0 || $amountMinor <= 0 || $currency === ''
            || !in_array($purchaseType, ['subscription', 'pay_per_view'], true)) {
            throw new \RuntimeException('Stripe payment metadata is incomplete.');
        }

        $this->fulfillVerifiedPayment(
            'stripe', $providerReference, $userId, $purchaseType, $purchaseId, $amountMinor,
            $currency, !empty($metadata['promotion_id']) ? (int) $metadata['promotion_id'] : null,
            (string) ($metadata['content_type'] ?? '')
        );
    }

    /**
     * Validate a coupon code for subscription plan or content
     */
    public function validateCoupon(Request $request)
    {
        $request->validate([
            'coupon_code' => 'required|string',
            'plan_id' => 'nullable|integer',
            'content_id' => 'nullable|integer',
            'content_type' => 'nullable|string',
        ]);

        $code = trim($request->coupon_code);

        $coupon = Coupon::where('code', $code)
            ->where('status', 1)
            ->where('start_date', '<=', now())
            ->where('expire_date', '>=', now())
            ->first();

        if (! $coupon) {
            return ApiResponse::error('Invalid or expired coupon code.', 404);
        }

        // If plan_id is provided, verify mapping
        if ($request->filled('plan_id')) {
            $planValid = $coupon->CouponPlanMappings()
                ->where('subscription_plan_id', $request->plan_id)
                ->exists();

            if (! $planValid) {
                return ApiResponse::error('This coupon is not applicable to the selected subscription plan.', 422);
            }
        }

        return ApiResponse::success([
            'id' => $coupon->id,
            'coupon_code' => $coupon->code,
            'discount' => (float) $coupon->discount,
            'discount_type' => $coupon->discount_type,
            'expire_date' => $coupon->expire_date,
        ], 'Coupon is valid.', 200);
    }

    /**
     * Process subscription payment and initiate gateway checkout session
     */
    public function processSubscriptionPayment(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plan,id',
            'payment_method' => 'required|string|in:stripe,razorpay,paystack,free',
            'coupon_code' => 'nullable|string',
            'coupon_id' => 'nullable|integer',
        ]);

        $user = Auth::user();
        $plan = Plan::where('id', $request->plan_id)->where('status', 1)->first();
        if (! $plan) {
            return ApiResponse::error('Invalid or inactive plan.', 404);
        }

        // Calculate pricing
        $calcRequest = new Request([
            'plan_id' => $plan->id,
            'coupon_code' => $request->coupon_code,
            'coupon_id' => $request->coupon_id,
        ]);
        $priceCalculation = $this->calculateSubscriptionPrice($calcRequest)->getData(true);

        if (! ($priceCalculation['status'] ?? false)) {
            return ApiResponse::error($priceCalculation['message'] ?? 'Unable to calculate price.', 400);
        }

        $calcData = $priceCalculation['data'];
        $finalPrice = (float) $calcData['total_amount'];
        $promotionId = $calcData['coupon']['id'] ?? null;
        $paymentMethod = strtolower($request->payment_method);

        if ($paymentMethod === 'free' && $finalPrice > 0) {
            return ApiResponse::error('This plan is not eligible for free checkout.', 422);
        }

        // If amount is 0, activate plan immediately
        if ($finalPrice <= 0) {
            $subscription = $this->activateUserSubscription(
                $user->id,
                $plan,
                0,
                'free',
                'FREE-' . strtoupper(uniqid()),
                $promotionId
            );

            return ApiResponse::success([
                'success' => true,
                'is_free' => true,
                'subscription' => new SubscriptionResource($subscription),
            ], 'Subscription activated successfully.', 200);
        }

        // Determine frontend callback URL
        $frontendUrl = rtrim((string) env('FRONTEND_URL', env('APP_URL')), '/');
        $currency = GetcurrentCurrency() ?: 'USD';

        switch ($paymentMethod) {
            case 'stripe':
                return $this->createStripeSubscriptionSession($plan, $finalPrice, $currency, $user, $promotionId, $frontendUrl);

            case 'razorpay':
                return $this->createRazorpaySubscriptionOrder($plan, $finalPrice, $currency, $user, $promotionId, $frontendUrl);

            case 'paystack':
                return $this->createPaystackSubscriptionTransaction($plan, $finalPrice, $currency, $user, $promotionId, $frontendUrl);

            case 'paypal':
                return $this->createPayPalSubscriptionPayment($plan, $finalPrice, $currency, $user, $promotionId, $frontendUrl);

            default:
                return ApiResponse::error("Payment method [{$paymentMethod}] is not supported for web checkout.", 400);
        }
    }

    /**
     * Verify completed gateway payment and activate subscription
     */
    public function verifySubscriptionPayment(Request $request)
    {
        $request->validate([
            'gateway' => 'required|string|in:stripe,razorpay,paystack',
            'session_id' => 'required_if:gateway,stripe|nullable|string|max:191',
            'payment_id' => 'required_if:gateway,razorpay|nullable|string|max:191',
            'order_id' => 'required_if:gateway,razorpay|nullable|string|max:191',
            'signature' => 'required_if:gateway,razorpay|nullable|string|max:512',
            'reference' => 'required_if:gateway,paystack|nullable|string|max:191',
        ]);

        $user = Auth::user();
        $gateway = strtolower($request->gateway);

        try {
            if ($gateway === 'stripe') {
                $stripe = new StripeClient(GetpaymentMethod('stripe_secretkey'));
                $session = $stripe->checkout->sessions->retrieve($request->session_id);
                $metadata = $session->metadata;
                if ($session->payment_status !== 'paid'
                    || (string) ($metadata->user_id ?? '') !== (string) $user->id
                    || strtoupper((string) $session->currency) !== strtoupper(GetcurrentCurrency() ?: 'USD')) {
                    return ApiResponse::error('Stripe payment is incomplete or does not match this account.', 400);
                }

                $planId = (int) ($metadata->plan_id ?? 0);
                $plan = Plan::where('id', $planId)->where('status', 1)->first();
                if (!$plan) {
                    return ApiResponse::error('The plan associated with this payment is unavailable.', 404);
                }

                $transactionId = (string) ($session->payment_intent ?: $session->id);
                $subscription = $this->fulfillVerifiedPayment(
                    'stripe', $transactionId, $user->id, 'subscription', $plan->id,
                    (int) $session->amount_total, strtoupper((string) $session->currency),
                    !empty($metadata->promotion_id) ? (int) $metadata->promotion_id : null
                );

                return ApiResponse::success(['subscription' => new SubscriptionResource($subscription)], 'Subscription activated successfully.', 200);
            }

            if ($gateway === 'razorpay') {
                $api = new \Razorpay\Api\Api(GetpaymentMethod('razorpay_publickey'), GetpaymentMethod('razorpay_secretkey'));
                $api->utility->verifyPaymentSignature([
                    'razorpay_order_id' => $request->order_id,
                    'razorpay_payment_id' => $request->payment_id,
                    'razorpay_signature' => $request->signature,
                ]);

                $order = $api->order->fetch($request->order_id);
                $notes = $order['notes'] ?? [];
                if ((string) ($notes['user_id'] ?? '') !== (string) $user->id
                    || ($notes['purchase_type'] ?? '') !== 'subscription') {
                    return ApiResponse::error('Razorpay order does not belong to this account.', 403);
                }

                $payment = $api->payment->fetch($request->payment_id);
                if ($payment->status !== 'captured'
                    || (string) ($payment->order_id ?? '') !== (string) $request->order_id
                    || (int) $payment->amount !== (int) ($order['amount'] ?? 0)
                    || strtoupper((string) $payment->currency) !== strtoupper((string) ($order['currency'] ?? ''))) {
                    return ApiResponse::error('Razorpay payment is not captured for this order.', 400);
                }

                $planId = (int) ($notes['plan_id'] ?? 0);
                if ($request->filled('plan_id') && (int) $request->plan_id !== $planId) {
                    return ApiResponse::error('Requested plan does not match the paid order.', 422);
                }
                $plan = Plan::where('id', $planId)->where('status', 1)->first();
                if (!$plan) {
                    return ApiResponse::error('The plan associated with this order is unavailable.', 404);
                }

                $subscription = $this->fulfillVerifiedPayment(
                    'razorpay', (string) $payment->id, $user->id, 'subscription', $plan->id,
                    (int) $payment->amount, strtoupper((string) $payment->currency),
                    !empty($notes['promotion_id']) ? (int) $notes['promotion_id'] : null
                );

                return ApiResponse::success(['subscription' => new SubscriptionResource($subscription)], 'Subscription activated successfully.', 200);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . GetpaymentMethod('paystack_secretkey'),
            ])->get('https://api.paystack.co/transaction/verify/' . urlencode($request->reference));
            $body = $response->json();
            $data = $body['data'] ?? [];
            if (!$response->successful() || !($body['status'] ?? false) || ($data['status'] ?? '') !== 'success'
                || (string) ($data['metadata']['user_id'] ?? '') !== (string) $user->id
                || strtolower((string) ($data['customer']['email'] ?? '')) !== strtolower((string) $user->email)
                || strtoupper((string) ($data['currency'] ?? '')) !== strtoupper(GetcurrentCurrency() ?: 'USD')) {
                return ApiResponse::error('Paystack payment could not be verified for this account.', 400);
            }

            $planId = (int) ($data['metadata']['plan_id'] ?? 0);
            $plan = Plan::where('id', $planId)->where('status', 1)->first();
            if (!$plan) {
                return ApiResponse::error('The plan associated with this payment is unavailable.', 404);
            }

            $subscription = $this->fulfillVerifiedPayment(
                'paystack', (string) $request->reference, $user->id, 'subscription', $plan->id,
                (int) $data['amount'], strtoupper((string) $data['currency']),
                !empty($data['metadata']['promotion_id']) ? (int) $data['metadata']['promotion_id'] : null
            );

            return ApiResponse::success(['subscription' => new SubscriptionResource($subscription)], 'Subscription activated successfully.', 200);
        } catch (Exception $e) {
            Log::error('Subscription payment verification failed.', ['gateway' => $gateway, 'exception' => $e::class]);
            return ApiResponse::error('Payment verification failed. Please contact support if you were charged.', 500);
        }
    }

    /**
     * Get the authenticated user's current active subscription details
     */
    public function getUserSubscription(Request $request)
    {
        $user = Auth::user();
        $subscription = Subscription::with(['plan.planLimitation.limitation_data', 'subscription_transaction'])
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if (! $subscription) {
            return ApiResponse::success([
                'has_active_subscription' => false,
                'subscription' => null,
            ], 'No active subscription found.', 200);
        }

        $now = Carbon::now();
        $endDate = Carbon::parse($subscription->end_date);
        $daysLeft = (int) max(0, ceil($now->diffInRealHours($endDate, false) / 24));

        return ApiResponse::success([
            'has_active_subscription' => true,
            'days_left' => $daysLeft,
            'is_expired' => $endDate->isPast(),
            'subscription' => new SubscriptionResource($subscription),
        ], 'Active subscription retrieved successfully.', 200);
    }

    /**
     * Cancel the user's active subscription
     */
    public function cancelSubscription(Request $request)
    {
        $user = Auth::user();
        $subscription = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $subscription) {
            return ApiResponse::error('No active subscription found to cancel.', 404);
        }

        $subscription->update(['status' => 'inactive']);
        $user->update(['is_subscribe' => 0]);
        Cache::flush();

        return ApiResponse::success(null, 'Subscription cancelled successfully.', 200);
    }

    /**
     * Get user billing and transaction history
     */
    public function getBillingHistory(Request $request)
    {
        $user = Auth::user();

        $subscriptions = Subscription::with(['plan', 'subscription_transaction'])
            ->where('user_id', $user->id)
            ->latest('id')
            ->get()
            ->map(function ($sub) {
                return [
                    'id' => $sub->id,
                    'type' => 'subscription',
                    'plan_name' => $sub->plan?->name ?? 'Standard Plan',
                    'amount' => $sub->subscription_transaction?->amount ?? $sub->plan?->price ?? 0,
                    'currency' => '$',
                    'start_date' => $sub->start_date,
                    'end_date' => $sub->end_date,
                    'status' => $sub->status,
                    'payment_type' => $sub->subscription_transaction?->payment_type ?? 'online',
                    'invoice_id' => $sub->id,
                ];
            });

        $ppv = PayperviewTransaction::where('user_id', $user->id)
            ->latest('id')
            ->get()
            ->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'type' => 'pay_per_view',
                    'content_name' => $tx->entertainment?->name ?? 'Movie Rental',
                    'amount' => $tx->amount ?? 0,
                    'currency' => '$',
                    'date' => $tx->created_at?->format('Y-m-d H:i:s'),
                    'status' => 'paid',
                    'payment_type' => $tx->payment_type ?? 'online',
                    'invoice_id' => $tx->id,
                ];
            });

        return ApiResponse::success([
            'subscriptions' => $subscriptions,
            'pay_per_view' => $ppv,
        ], 'Billing history retrieved successfully.', 200);
    }

    /**
     * Calculate Pay-Per-View rental price for content
     */
    public function calculatePayPerViewPrice(Request $request)
    {
        $request->validate([
            'movie_id' => 'required|integer',
            'type' => 'required|string|in:movie,tvshow,video,episode,season',
            'coupon_code' => 'nullable|string',
        ]);

        $content = $this->resolveContentModel($request->type, $request->movie_id);
        if (! $content) {
            return ApiResponse::error('Content not found.', 404);
        }

        $basePrice = (float) ($content->price ?? 0);
        $discountAmount = 0;

        if (! empty($content->discount_percentage) && $content->discount_percentage > 0) {
            $discountAmount = ($basePrice * (float) $content->discount_percentage) / 100;
        }

        $subtotal = max(0, $basePrice - $discountAmount);

        // Taxes
        $taxes = Tax::active()->get();
        $totalTax = 0;
        $taxBreakdown = [];

        foreach ($taxes as $tax) {
            $taxAmount = 0;
            if (strtolower($tax->type) === 'fixed') {
                $taxAmount = (float) $tax->value;
            } else {
                $taxAmount = ($subtotal * (float) $tax->value) / 100;
            }
            $totalTax += $taxAmount;
            $taxBreakdown[] = [
                'title' => $tax->title,
                'type' => $tax->type,
                'value' => $tax->value,
                'tax_amount' => round($taxAmount, 2),
            ];
        }

        $finalTotal = round($subtotal + $totalTax, 2);

        return ApiResponse::success([
            'content_id' => $content->id,
            'content_title' => $content->name ?? $content->title ?? 'Video',
            'content_type' => $request->type,
            'available_for_days' => (int) ($content->available_for ?? 2),
            'access_duration_days' => (int) ($content->access_duration ?? 2),
            'original_price' => $basePrice,
            'discount_amount' => round($discountAmount, 2),
            'subtotal' => round($subtotal, 2),
            'total_tax' => round($totalTax, 2),
            'taxes' => $taxBreakdown,
            'total_amount' => $finalTotal,
            'currency' => GetcurrentCurrency() ?: 'USD',
        ], 'Pay-per-view price calculated successfully.', 200);
    }

    /**
     * Process Pay-Per-View checkout session
     */
    public function processPayPerViewPayment(Request $request)
    {
        $request->validate([
            'movie_id' => 'required|integer',
            'type' => 'required|string|in:movie,tvshow,video,episode',
            'payment_method' => 'required|string|in:stripe,razorpay,free',
        ]);

        $user = Auth::user();
        $content = $this->resolveContentModel($request->type, $request->movie_id);
        if (!$content) {
            return ApiResponse::error('Content not found.', 404);
        }

        $pricing = $this->calculatePayPerViewPrice(new Request([
            'movie_id' => $content->id,
            'type' => $request->type,
        ]))->getData(true);
        if (!($pricing['status'] ?? false)) {
            return ApiResponse::error($pricing['message'] ?? 'Unable to calculate the current price.', 422);
        }

        $priceData = $pricing['data'];
        $finalPrice = (float) $priceData['total_amount'];
        $currency = strtoupper($priceData['currency'] ?? GetcurrentCurrency() ?: 'USD');
        $paymentMethod = strtolower($request->payment_method);

        if ($paymentMethod === 'free' && $finalPrice > 0) {
            return ApiResponse::error('This content is not eligible for free checkout.', 422);
        }
        if ($finalPrice <= 0) {
            $ppv = $this->activatePayPerViewAccess($user->id, $content, $request->type, 0, 'free', 'FREE-' . bin2hex(random_bytes(12)));
            return ApiResponse::success(['success' => true, 'ppv' => $ppv], 'Content rented successfully.', 200);
        }

        $frontendUrl = rtrim((string) env('FRONTEND_URL', env('APP_URL')), '/');
        $amountMinor = $this->toMinorUnits($finalPrice, $currency);

        if ($paymentMethod === 'stripe') {
            try {
                $stripe = new StripeClient(GetpaymentMethod('stripe_secretkey'));
                $checkoutSession = $stripe->checkout->sessions->create([
                    'payment_method_types' => ['card'],
                    'customer_email' => $user->email,
                    'line_items' => [[
                        'price_data' => [
                            'currency' => strtolower($currency),
                            'product_data' => ['name' => 'Pay-Per-View: ' . ($content->name ?? 'Content')],
                            'unit_amount' => $amountMinor,
                        ],
                        'quantity' => 1,
                    ]],
                    'mode' => 'payment',
                    'metadata' => [
                        'movie_id' => (string) $content->id,
                        'type' => $request->type,
                        'is_ppv' => '1',
                        'user_id' => (string) $user->id,
                    ],
                    'success_url' => "{$frontendUrl}/payment-success?gateway=stripe&session_id={CHECKOUT_SESSION_ID}&is_ppv=1",
                    'cancel_url' => "{$frontendUrl}/movies",
                ]);

                return ApiResponse::success([
                    'redirect_url' => $checkoutSession->url,
                    'session_id' => $checkoutSession->id,
                ], 'Stripe checkout initiated.', 200);
            } catch (Exception $e) {
                Log::error('Stripe PPV checkout creation failed.', ['exception' => $e::class]);
                return ApiResponse::error('Unable to start payment. Please try again.', 502);
            }
        }

        try {
            $razorpayKey = GetpaymentMethod('razorpay_publickey');
            $api = new \Razorpay\Api\Api($razorpayKey, GetpaymentMethod('razorpay_secretkey'));
            $order = $api->order->create([
                'receipt' => 'ppv_' . bin2hex(random_bytes(12)),
                'amount' => $amountMinor,
                'currency' => $currency,
                'payment_capture' => 1,
                'notes' => [
                    'purchase_type' => 'pay_per_view',
                    'user_id' => (string) $user->id,
                    'content_id' => (string) $content->id,
                    'content_type' => $request->type,
                ],
            ]);

            return ApiResponse::success([
                'key' => $razorpayKey,
                'amount' => $amountMinor,
                'currency' => $currency,
                'name' => config('app.name'),
                'description' => 'Rental: ' . ($content->name ?? 'Content'),
                'order_id' => $order['id'],
                'prefill' => ['name' => $user->name, 'email' => $user->email, 'contact' => $user->phone ?? ''],
            ], 'Razorpay order created successfully.', 200);
        } catch (Exception $e) {
            Log::error('Razorpay PPV order creation failed.', ['exception' => $e::class]);
            return ApiResponse::error('Unable to start payment. Please try again.', 502);
        }
    }

    /** Verify and fulfill a PPV purchase from the provider record. */
    public function verifyPayPerViewPayment(Request $request)
    {
        $request->validate([
            'gateway' => 'required|string|in:stripe,razorpay',
            'session_id' => 'required_if:gateway,stripe|nullable|string|max:191',
            'payment_id' => 'required_if:gateway,razorpay|nullable|string|max:191',
            'order_id' => 'required_if:gateway,razorpay|nullable|string|max:191',
            'signature' => 'required_if:gateway,razorpay|nullable|string|max:512',
        ]);

        $user = Auth::user();
        try {
            if ($request->gateway === 'stripe') {
                $stripe = new StripeClient(GetpaymentMethod('stripe_secretkey'));
                $session = $stripe->checkout->sessions->retrieve($request->session_id);
                $metadata = $session->metadata;
                if ($session->payment_status !== 'paid' || (string) ($metadata->is_ppv ?? '') !== '1'
                    || (string) ($metadata->user_id ?? '') !== (string) $user->id
                    || strtoupper((string) $session->currency) !== strtoupper(GetcurrentCurrency() ?: 'USD')) {
                    return ApiResponse::error('Payment is incomplete or does not match this account.', 400);
                }

                $contentId = (int) ($metadata->movie_id ?? 0);
                $contentType = (string) ($metadata->type ?? '');
                $content = $this->resolveContentModel($contentType, $contentId);
                if (!$content) {
                    return ApiResponse::error('Content associated with this payment was not found.', 404);
                }

                $transactionId = (string) ($session->payment_intent ?: $session->id);
                $ppv = $this->fulfillVerifiedPayment(
                    'stripe', $transactionId, $user->id, 'pay_per_view', $content->id,
                    (int) $session->amount_total, strtoupper((string) $session->currency), null, $contentType
                );
                return ApiResponse::success(['ppv' => $ppv], 'Pay-per-view rental unlocked successfully.', 200);
            }

            $api = new \Razorpay\Api\Api(GetpaymentMethod('razorpay_publickey'), GetpaymentMethod('razorpay_secretkey'));
            $api->utility->verifyPaymentSignature([
                'razorpay_order_id' => $request->order_id,
                'razorpay_payment_id' => $request->payment_id,
                'razorpay_signature' => $request->signature,
            ]);
            $order = $api->order->fetch($request->order_id);
            $notes = $order['notes'] ?? [];
            if ((string) ($notes['user_id'] ?? '') !== (string) $user->id || ($notes['purchase_type'] ?? '') !== 'pay_per_view') {
                return ApiResponse::error('Razorpay order does not belong to this account.', 403);
            }

            $contentId = (int) ($notes['content_id'] ?? 0);
            $contentType = (string) ($notes['content_type'] ?? '');
            $content = $this->resolveContentModel($contentType, $contentId);
            if (!$content) {
                return ApiResponse::error('Content associated with this order was not found.', 404);
            }

            $payment = $api->payment->fetch($request->payment_id);
            if ($payment->status !== 'captured'
                || (string) ($payment->order_id ?? '') !== (string) $request->order_id
                || (int) $payment->amount !== (int) ($order['amount'] ?? 0)
                || strtoupper((string) $payment->currency) !== strtoupper((string) ($order['currency'] ?? ''))) {
                return ApiResponse::error('Razorpay payment is not captured for this order.', 400);
            }

            $ppv = $this->fulfillVerifiedPayment(
                'razorpay', (string) $payment->id, $user->id, 'pay_per_view', $content->id,
                (int) $payment->amount, strtoupper((string) $payment->currency), null, $contentType
            );
            return ApiResponse::success(['ppv' => $ppv], 'Pay-per-view rental unlocked successfully.', 200);
        } catch (Exception $e) {
            Log::error('PPV payment verification failed.', ['gateway' => $request->gateway, 'exception' => $e::class]);
            return ApiResponse::error('Payment verification failed. Please contact support if you were charged.', 500);
        }
    }

    /**
     * Decrypt protected video stream URL for authorized player sessions
     */
    public function decryptUrl(Request $request)
    {
        $request->validate([
            'encrypted_url' => 'required|string',
        ]);

        try {
            $decrypted = Crypt::decryptString($request->encrypted_url);
            return ApiResponse::success(['url' => $decrypted], 'URL decrypted successfully.', 200);
        } catch (Exception $e) {
            return ApiResponse::error('Invalid or corrupted encrypted URL.', 400);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal Helper Methods
    // ─────────────────────────────────────────────────────────────────────────

    private function fulfillVerifiedPayment(
        string $gateway,
        string $providerReference,
        int $userId,
        string $purchaseType,
        int $purchaseId,
        int $amountMinor,
        string $currency,
        ?int $promotionId = null,
        ?string $contentType = null
    ) {
        return DB::transaction(function () use ($gateway, $providerReference, $userId, $purchaseType, $purchaseId, $amountMinor, $currency, $promotionId, $contentType) {
            $receipt = VerifiedPayment::query()->firstOrCreate(
                ['gateway' => $gateway, 'provider_reference' => $providerReference],
                [
                    'user_id' => $userId,
                    'purchase_type' => $purchaseType,
                    'purchase_id' => $purchaseId,
                    'amount_minor' => $amountMinor,
                    'currency' => strtoupper($currency),
                ]
            );
            $receipt = VerifiedPayment::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();

            if ((int) $receipt->user_id !== $userId
                || $receipt->purchase_type !== $purchaseType
                || (int) $receipt->purchase_id !== $purchaseId
                || (int) $receipt->amount_minor !== $amountMinor
                || strtoupper($receipt->currency) !== strtoupper($currency)) {
                throw new \RuntimeException('Provider transaction was already associated with a different purchase.');
            }

            if ($receipt->fulfilled_at) {
                return $receipt->fulfillment_type === 'subscription'
                    ? Subscription::findOrFail($receipt->fulfillment_id)
                    : PayPerView::findOrFail($receipt->fulfillment_id);
            }

            $amount = $amountMinor / $this->currencyMultiplier($currency);
            if ($purchaseType === 'subscription') {
                $plan = Plan::where('id', $purchaseId)->where('status', 1)->firstOrFail();
                $fulfillment = $this->activateUserSubscription($userId, $plan, $amount, $gateway, $providerReference, $promotionId);
                $receipt->fulfillment_type = 'subscription';
            } elseif ($purchaseType === 'pay_per_view') {
                $content = $this->resolveContentModel((string) $contentType, $purchaseId);
                if (!$content) {
                    throw new \RuntimeException('The content associated with the verified payment no longer exists.');
                }
                $fulfillment = $this->activatePayPerViewAccess($userId, $content, (string) $contentType, $amount, $gateway, $providerReference);
                $receipt->fulfillment_type = 'pay_per_view';
            } else {
                throw new \RuntimeException('Unsupported verified payment purchase type.');
            }

            $receipt->fulfillment_id = $fulfillment->id;
            $receipt->fulfilled_at = now();
            $receipt->save();

            return $fulfillment;
        });
    }

    private function currencyMultiplier(string $currency): int
    {
        $zeroDecimalCurrencies = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

        return in_array(strtoupper($currency), $zeroDecimalCurrencies, true) ? 1 : 100;
    }

    private function activateUserSubscription(int $userId, Plan $plan, float $amount, string $paymentType, string $transactionId, ?int $promotionId = null): Subscription
    {
        // Deactivate previous active subscriptions
        Subscription::where('user_id', $userId)
            ->where('status', 'active')
            ->update(['status' => 'inactive']);

        $startDate = now()->toDateTimeString();
        $endDate = $this->get_plan_expiration_date($startDate, $plan->duration, $plan->duration_value);

        $limitationData = PlanResource::collection([$plan]);

        $subscription = Subscription::create([
            'plan_id' => $plan->id,
            'user_id' => $userId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'active',
            'amount' => $plan->price,
            'discount_percentage' => $plan->discount_percentage,
            'total_amount' => $amount,
            'name' => $plan->name,
            'identifier' => $plan->identifier,
            'type' => $plan->duration,
            'duration' => $plan->duration_value,
            'level' => $plan->level,
            'plan_type' => json_encode($limitationData),
            'coupon_id' => $promotionId,
        ]);

        SubscriptionTransactions::create([
            'subscriptions_id' => $subscription->id,
            'user_id' => $userId,
            'amount' => $amount,
            'payment_type' => $paymentType,
            'payment_status' => 'paid',
            'transaction_id' => $transactionId,
        ]);

        User::where('id', $userId)->update(['is_subscribe' => 1]);
        Cache::flush();

        return $subscription;
    }

    private function activatePayPerViewAccess(int $userId, $content, string $type, float $amount, string $paymentType, string $transactionId): PayPerView
    {
        $accessDuration = (int) ($content->available_for ?? $content->access_duration ?? 2);
        $viewExpiry = now()->addDays($accessDuration);

        $ppv = PayPerView::create([
            'user_id' => $userId,
            'movie_id' => $content->id,
            'type' => $type,
            'content_price' => $content->price ?? 0,
            'price' => $amount,
            'discount_percentage' => $content->discount_percentage ?? 0,
            'view_expiry_date' => $viewExpiry,
            'access_duration' => $accessDuration,
            'available_for' => $accessDuration,
        ]);

        PayperviewTransaction::create([
            'user_id' => $userId,
            'amount' => $amount,
            'payment_type' => $paymentType,
            'payment_status' => 'paid',
            'transaction_id' => $transactionId,
            'pay_per_view_id' => $ppv->id,
        ]);

        return $ppv;
    }

    private function resolveContentModel(string $type, int $id)
    {
        switch ($type) {
            case 'movie':
            case 'tvshow':
                return Entertainment::find($id);
            case 'video':
                return Video::find($id);
            case 'episode':
                return Episode::find($id);
            default:
                return null;
        }
    }

    private function createStripeSubscriptionSession(Plan $plan, float $price, string $currency, User $user, ?int $promotionId, string $frontendUrl)
    {
        $stripeSecret = GetpaymentMethod('stripe_secretkey');
        $stripe = new StripeClient($stripeSecret);

        $unitAmount = $this->toMinorUnits($price, $currency);

        try {
            $session = $stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'customer_email' => $user->email,
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'product_data' => [
                            'name' => 'Subscription Plan: ' . $plan->name,
                        ],
                        'unit_amount' => $unitAmount,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'metadata' => [
                    'plan_id' => (string) $plan->id,
                    'promotion_id' => (string) ($promotionId ?? ''),
                    'user_id' => (string) $user->id,
                ],
                'success_url' => "{$frontendUrl}/payment-success?gateway=stripe&session_id={CHECKOUT_SESSION_ID}",
                'cancel_url' => "{$frontendUrl}/pricing",
            ]);

            return ApiResponse::success([
                'redirect_url' => $session->url,
                'session_id' => $session->id,
            ], 'Stripe checkout session created successfully.', 200);
        } catch (Exception $e) {
            Log::error('Stripe session creation error', ['error' => $e->getMessage()]);
            return ApiResponse::error('Unable to start payment. Please try again.', 502);
        }
    }

    private function createRazorpaySubscriptionOrder(Plan $plan, float $price, string $currency, User $user, ?int $promotionId, string $frontendUrl)
    {
        $razorpayKey = GetpaymentMethod('razorpay_publickey');
        $razorpaySecret = GetpaymentMethod('razorpay_secretkey');
        $api = new \Razorpay\Api\Api($razorpayKey, $razorpaySecret);

        try {
            $order = $api->order->create([
                'receipt' => 'sub_' . bin2hex(random_bytes(12)),
                'amount' => $this->toMinorUnits($price, $currency),
                'currency' => strtoupper($currency),
                'payment_capture' => 1,
                'notes' => [
                    'purchase_type' => 'subscription',
                    'user_id' => (string) $user->id,
                    'plan_id' => (string) $plan->id,
                    'promotion_id' => (string) ($promotionId ?? ''),
                ],
            ]);

            return ApiResponse::success([
                'key' => $razorpayKey,
                'amount' => $this->toMinorUnits($price, $currency),
                'currency' => strtoupper($currency),
                'name' => config('app.name'),
                'description' => "Subscription Plan: {$plan->name}",
                'order_id' => $order['id'],
                'plan_id' => $plan->id,
                'promotion_id' => $promotionId,
                'prefill' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'contact' => $user->phone ?? '',
                ],
            ], 'Razorpay order created successfully.', 200);
        } catch (Exception $e) {
            Log::error('Razorpay subscription order creation failed.', ['exception' => $e::class]);
            return ApiResponse::error('Unable to start payment. Please try again.', 502);
        }
    }

    private function createPaystackSubscriptionTransaction(Plan $plan, float $price, string $currency, User $user, ?int $promotionId, string $frontendUrl)
    {
        $secretKey = GetpaymentMethod('paystack_secretkey');
        $callbackUrl = "{$frontendUrl}/payment-success?gateway=paystack&plan_id={$plan->id}";

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $secretKey,
        ])->post('https://api.paystack.co/transaction/initialize', [
            'email' => $user->email,
            'amount' => $this->toMinorUnits($price, $currency),
            'currency' => strtoupper($currency),
            'callback_url' => $callbackUrl,
            'metadata' => [
                'plan_id' => $plan->id,
                'promotion_id' => $promotionId,
                'user_id' => $user->id,
            ],
        ]);

        $body = $response->json();
        if ($body['status'] ?? false) {
            return ApiResponse::success([
                'redirect_url' => $body['data']['authorization_url'],
                'reference' => $body['data']['reference'],
            ], 'Paystack checkout initiated.', 200);
        }

        return ApiResponse::error($body['message'] ?? 'Paystack initiation failed.', 400);
    }

    private function createPayPalSubscriptionPayment(Plan $plan, float $price, string $currency, User $user, ?int $promotionId, string $frontendUrl)
    {
        return ApiResponse::error('PayPal direct approval redirect requires standard client-side PayPal SDK order ID integration.', 400);
    }
}

