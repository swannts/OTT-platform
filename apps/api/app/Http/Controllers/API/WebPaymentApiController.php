<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\Payments\CalculatePayPerViewPriceRequest;
use App\Http\Requests\API\Payments\CalculateSubscriptionPriceRequest;
use App\Http\Requests\API\Payments\CreateMobilePaymentIntentRequest;
use App\Http\Requests\API\Payments\ProcessPayPerViewPaymentRequest;
use App\Http\Requests\API\Payments\ProcessSubscriptionPaymentRequest;
use App\Http\Requests\API\Payments\ValidateCouponRequest;
use App\Http\Requests\API\Payments\VerifyMobilePaymentIntentRequest;
use App\Http\Requests\API\Payments\VerifyPayPerViewPaymentRequest;
use App\Http\Requests\API\Payments\VerifySubscriptionPaymentRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\Payments\CheckoutPricingService;
use App\Services\Payments\CouponReservationService;
use App\Services\Payments\PaymentFulfillmentService;
use App\Services\Payments\PaymentGatewayCheckoutService;
use App\Services\Payments\ProviderWebhookService;
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
use Modules\Subscriptions\Services\PlanPricingService;
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

    public function __construct(
        private readonly PlanPricingService $planPricing,
        private readonly CheckoutPricingService $checkoutPricing,
        private readonly CouponReservationService $couponReservations,
        private readonly PaymentFulfillmentService $fulfillment,
        private readonly PaymentGatewayCheckoutService $gatewayCheckout,
        private readonly ProviderWebhookService $providerWebhooks,
    ) {
    }

    /**
     * Get all active subscription plans with full limitations and pricing
     */
    public function getPlans(Request $request)
    {
        $plans = Plan::with(['planLimitation', 'regionalPrices'])
            ->where('status', 1)
            ->orderBy('level', 'asc')
            ->get();

        $plans->each(function (Plan $plan) use ($request): void {
            $resolved = $this->planPricing->resolve($plan, $request);
            $plan->setAttribute('display_price', $resolved['amount']);
            $plan->setAttribute('display_total_price', $resolved['discounted_amount']);
            $plan->setAttribute('display_currency', $resolved['currency']);
            $plan->setAttribute('pricing_country', $resolved['country_code']);
        });

        return ApiResponse::success(PlanResource::collection($plans), 'Plans retrieved successfully', 200);
    }

    /**
     * Calculate subscription price, applying discount, coupon, and taxes
     */
    public function calculateSubscriptionPrice(CalculateSubscriptionPriceRequest $request)
    {
        $plan = Plan::with('planLimitation')->where('id', $request->plan_id)->where('status', 1)->first();
        if (! $plan) {
            return ApiResponse::error('Selected plan is invalid or inactive.', 404);
        }

        $result = $this->checkoutPricing->calculateSubscription($plan, $request, Auth::id());
        if (! $result['ok']) {
            return ApiResponse::error($result['message'], $result['status']);
        }

        return ApiResponse::success($result['data'], 'Price calculated successfully', 200);
    }

    /** Create a Stripe PaymentIntent for the authenticated mobile checkout. */
    public function createMobilePaymentIntent(CreateMobilePaymentIntentRequest $request)
    {
        $result = $this->gatewayCheckout->createMobilePaymentIntent(Auth::user(), $request);
        if (! $result['ok']) {
            return ApiResponse::error($result['message'], $result['status']);
        }

        return ApiResponse::success($result['data'], $result['message'], 200);
    }

    /** Verify Stripe directly on the server and fulfill a mobile purchase once. */
    public function verifyMobilePaymentIntent(VerifyMobilePaymentIntentRequest $request)
    {
        $result = $this->gatewayCheckout->verifyMobilePaymentIntent(Auth::user(), (string) $request->payment_intent_id);
        if (! $result['ok']) {
            return ApiResponse::error($result['message'], $result['status']);
        }

        return ApiResponse::success($result['data'], $result['message'], 200);
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

        $stripeObject = $event->data->object;
        $stripeData = $stripeObject->toArray();
        $providerReference = (string) ($stripeData['payment_intent'] ?? $stripeData['id'] ?? '');
        $eventId = (string) $event->id;
        if (! $this->providerWebhooks->beginEvent('stripe', $eventId, (string) $event->type, $providerReference, $request->getContent())) {
            return response()->json(['received' => true, 'duplicate' => true], 200);
        }

        try {
            $object = $event->data->object;
            if ($event->type === 'payment_intent.succeeded') {
                $payment = $object->toArray();
                $this->providerWebhooks->fulfillStripeWebhookPayment($payment, (string) $payment['id'], (int) ($payment['amount_received'] ?? $payment['amount'] ?? 0));
            } elseif (in_array($event->type, ['checkout.session.expired', 'payment_intent.canceled'], true)) {
                $this->releaseCouponReservation((string) ($object->metadata->coupon_reservation ?? '') ?: null);
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
                    $this->providerWebhooks->fulfillStripeWebhookPayment([
                        'metadata' => $metadata,
                        'currency' => $session['currency'] ?? '',
                    ], $paymentReference, (int) ($session['amount_total'] ?? 0));
                }
            } elseif (in_array($event->type, ['refund.created', 'refund.updated', 'refund.failed'], true)) {
                $this->providerWebhooks->syncStripeRefundEvent($event->type, $object->toArray());
            } elseif (in_array($event->type, ['charge.dispute.created', 'charge.dispute.updated', 'charge.dispute.closed'], true)) {
                $this->providerWebhooks->syncStripeDisputeEvent($event->type, $object->toArray());
            }

            $this->providerWebhooks->finishEvent('stripe', $eventId, 'processed');
            return response()->json(['received' => true], 200);
        } catch (Exception $e) {
            $this->providerWebhooks->finishEvent('stripe', $eventId, 'failed', $e::class);
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
            $eventType = (string) ($event['event'] ?? 'unknown');
            $entityForReference = $event['payload']['payment']['entity']
                ?? $event['payload']['refund']['entity']
                ?? $event['payload']['dispute']['entity'] ?? [];
            $eventId = (string) $request->header('X-Razorpay-Event-Id', hash('sha256', $payload));
            if (! $this->providerWebhooks->beginEvent('razorpay', $eventId, $eventType, (string) ($entityForReference['id'] ?? ''), $payload)) {
                return response()->json(['received' => true, 'duplicate' => true], 200);
            }
            if (($event['event'] ?? '') === 'payment.failed') {
                $entity = $event['payload']['payment']['entity'] ?? [];
                $orderId = (string) ($entity['order_id'] ?? '');
                if ($orderId !== '') {
                    $api = new \Razorpay\Api\Api(GetpaymentMethod('razorpay_publickey'), GetpaymentMethod('razorpay_secretkey'));
                    $order = $api->order->fetch($orderId);
                    $notes = $order['notes'] ?? [];
                    $this->releaseCouponReservation(!empty($notes['coupon_reservation']) ? (string) $notes['coupon_reservation'] : null);
                }
                $this->providerWebhooks->finishEvent('razorpay', $eventId, 'processed');
                return response()->json(['received' => true], 200);
            }
            if (($event['event'] ?? '') !== 'payment.captured') {
                $this->providerWebhooks->syncRazorpayFinanceEvent($eventType, $event);
                $this->providerWebhooks->finishEvent('razorpay', $eventId, 'processed');
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
                || (isset($notes['amount_minor']) && (int) $notes['amount_minor'] !== (int) ($order['amount'] ?? 0))
                || (isset($notes['currency']) && strtoupper((string) $notes['currency']) !== strtoupper((string) ($order['currency'] ?? '')))
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
                (int) $payment->amount, strtoupper((string) $payment->currency), $promotionId, $contentType,
                !empty($notes['coupon_reservation']) ? (string) $notes['coupon_reservation'] : null
            );

            $this->providerWebhooks->finishEvent('razorpay', $eventId, 'processed');
            return response()->json(['received' => true], 200);
        } catch (Exception $e) {
            if (isset($eventId)) {
                $this->providerWebhooks->finishEvent('razorpay', $eventId, 'failed', $e::class);
            }
            Log::error('Razorpay webhook fulfillment failed.', ['exception' => $e::class]);
            return ApiResponse::error('Razorpay webhook processing failed.', 500);
        }
    }

    /** Verify provider signature, confirm through Paystack, then fulfill once. */
    public function paystackWebhook(Request $request)
    {
        $payload = $request->getContent();
        $secret = (string) GetpaymentMethod('paystack_secretkey');
        $signature = (string) $request->header('x-paystack-signature');
        if ($secret === '' || $signature === '' || !hash_equals(hash_hmac('sha512', $payload, $secret), $signature)) {
            return ApiResponse::error('Invalid Paystack webhook signature.', 400);
        }

        $eventId = null;
        try {
            $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            $type = (string) ($event['event'] ?? 'unknown');
            $data = $event['data'] ?? [];
            $reference = (string) ($data['reference'] ?? $data['transaction']['reference'] ?? '');
            $eventId = $type.':'.(string) (($data['id'] ?? '') ?: ($reference ?: hash('sha256', $payload)));
            if (! $this->providerWebhooks->beginEvent('paystack', $eventId, $type, $reference, $payload)) {
                return response()->json(['received' => true, 'duplicate' => true], 200);
            }

            if ($type === 'charge.success') {
                $this->providerWebhooks->fulfillPaystackWebhookPayment($data);
            } elseif (in_array($type, ['refund.pending', 'refund.processing', 'refund.processed', 'refund.failed', 'refund.needs-attention'], true)) {
                $this->providerWebhooks->syncPaystackRefundEvent($type, $data);
            } elseif (in_array($type, ['charge.dispute.create', 'charge.dispute.remind', 'charge.dispute.resolve'], true)) {
                $this->providerWebhooks->syncPaystackDisputeEvent($type, $data);
            }

            $this->providerWebhooks->finishEvent('paystack', $eventId, 'processed');
            return response()->json(['received' => true], 200);
        } catch (Exception $e) {
            if ($eventId) {
                $this->providerWebhooks->finishEvent('paystack', $eventId, 'failed', $e::class);
            }
            Log::error('Paystack webhook processing failed.', ['exception' => $e::class]);
            return ApiResponse::error('Paystack webhook processing failed.', 500);
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
            $result = $this->providerWebhooks->syncRevenueCatSubscription($user);
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
        } catch (\JsonException) {
            return ApiResponse::error('Invalid RevenueCat webhook payload.', 400);
        }

        $event = $payload['event'] ?? [];
        $eventId = (string) ($event['id'] ?? '');
        $eventType = (string) ($event['type'] ?? '');
        if ($eventId === '') {
            return ApiResponse::error('RevenueCat webhook event ID is required.', 400);
        }

        $providerReference = (string) ($event['transaction_id'] ?? $event['original_transaction_id'] ?? '');
        if (! $this->providerWebhooks->beginEvent('revenuecat', $eventId, $eventType ?: 'unknown', $providerReference, $request->getContent())) {
            return response()->json(['received' => true, 'duplicate' => true], 200);
        }

        try {
            if (!in_array($eventType, [
                'INITIAL_PURCHASE', 'RENEWAL', 'UNCANCELLATION', 'PRODUCT_CHANGE',
                'CANCELLATION', 'EXPIRATION', 'REFUND', 'REFUND_REVERSED',
                'SUBSCRIPTION_EXTENDED', 'TRANSFER',
            ], true)) {
                $this->providerWebhooks->finishEvent('revenuecat', $eventId, 'processed');
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
                $this->providerWebhooks->syncRevenueCatSubscription($user);
            }

            $this->providerWebhooks->finishEvent('revenuecat', $eventId, 'processed');
            return response()->json(['received' => true], 200);
        } catch (Exception $e) {
            $this->providerWebhooks->finishEvent('revenuecat', $eventId, 'failed', $e::class);
            Log::error('RevenueCat webhook reconciliation failed.', ['event_id' => $eventId, 'exception' => $e::class]);
            return ApiResponse::error('RevenueCat webhook processing failed.', 500);
        }
    }

    /**
     * Validate a coupon code for subscription plan or content
     */
    public function validateCoupon(ValidateCouponRequest $request)
    {
        $coupon = $this->couponReservations->findActiveCouponByCode($request->coupon_code);

        if (! $coupon) {
            return ApiResponse::error('Invalid or expired coupon code.', 404);
        }
        if (! $this->couponReservations->isUsageAvailable($coupon, Auth::id())) {
            return ApiResponse::error('This coupon has reached its redemption limit.', 422);
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
    public function processSubscriptionPayment(ProcessSubscriptionPaymentRequest $request)
    {
        $user = Auth::user();
        $plan = Plan::where('id', $request->plan_id)->where('status', 1)->first();
        if (! $plan) {
            return ApiResponse::error('Invalid or inactive plan.', 404);
        }

        if ((int) ($plan->trial_days ?? 0) > 0) {
            $trial = $this->startEligibleTrial($user, $plan);
            if (! $trial) {
                return ApiResponse::error('This free trial is available only to accounts with no prior subscription.', 409);
            }

            return ApiResponse::success([
                'success' => true,
                'is_trial' => true,
                'trial_days' => (int) $plan->trial_days,
                'subscription' => new SubscriptionResource($trial),
            ], 'Free trial activated successfully.', 200);
        }

        $pricing = $this->checkoutPricing->calculateSubscription($plan, $request, (int) $user->id);
        if (! $pricing['ok']) {
            return ApiResponse::error($pricing['message'], 400);
        }

        $calcData = $pricing['data'];
        $finalPrice = (float) $calcData['total_amount'];
        $promotionId = $calcData['coupon']['id'] ?? null;
        $paymentMethod = strtolower($request->payment_method);
        $couponReservationToken = null;
        if ($promotionId) {
            try {
                $couponReservationToken = $this->reserveCouponForCheckout((int) $promotionId, (int) $user->id);
            } catch (\RuntimeException $exception) {
                return ApiResponse::error($exception->getMessage(), 409);
            }
        }

        if ($paymentMethod === 'free' && $finalPrice > 0) {
            $this->releaseCouponReservation($couponReservationToken);
            return ApiResponse::error('This plan is not eligible for free checkout.', 422);
        }

        // If amount is 0, activate plan immediately
        if ($finalPrice <= 0) {
            $subscription = DB::transaction(function () use ($user, $plan, $promotionId, $couponReservationToken, $calcData) {
                $subscription = $this->activateUserSubscription(
                    $user->id, $plan, 0, 'free', 'FREE-' . strtoupper(\Illuminate\Support\Str::uuid()),
                    $promotionId, strtoupper((string) $calcData['currency'])
                );
                if ($promotionId) $this->consumeCouponReservation($couponReservationToken, (int) $promotionId, (int) $user->id, (int) $subscription->id);
                return $subscription;
            });

            return ApiResponse::success([
                'success' => true,
                'is_free' => true,
                'subscription' => new SubscriptionResource($subscription),
            ], 'Subscription activated successfully.', 200);
        }

        // Determine frontend callback URL
        $frontendUrl = rtrim((string) env('FRONTEND_URL', env('APP_URL')), '/');
        $currency = strtoupper((string) $calcData['currency']);

        $result = $this->gatewayCheckout->createSubscriptionSession(
            $paymentMethod,
            $plan,
            $finalPrice,
            $currency,
            $user,
            $promotionId,
            $frontendUrl,
            $couponReservationToken
        );

        if (! $result['ok']) {
            return ApiResponse::error($result['message'], $result['status']);
        }

        return ApiResponse::success($result['data'], $result['message'], 200);
    }

    /**
     * Verify completed gateway payment and activate subscription
     */
    public function verifySubscriptionPayment(VerifySubscriptionPaymentRequest $request)
    {
        $result = $this->gatewayCheckout->verifySubscriptionPayment(Auth::user(), $request->validated());
        if (! $result['ok']) {
            return ApiResponse::error($result['message'], $result['status']);
        }

        return ApiResponse::success($result['data'], $result['message'], 200);
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
    public function calculatePayPerViewPrice(CalculatePayPerViewPriceRequest $request)
    {
        $content = $this->checkoutPricing->resolveContentModel($request->type, (int) $request->movie_id);
        if (! $content) {
            return ApiResponse::error('Content not found.', 404);
        }

        return ApiResponse::success(
            $this->checkoutPricing->calculatePayPerView($content, $request->type),
            'Pay-per-view price calculated successfully.',
            200
        );
    }

    /**
     * Process Pay-Per-View checkout session
     */
    public function processPayPerViewPayment(ProcessPayPerViewPaymentRequest $request)
    {
        $user = Auth::user();
        $content = $this->checkoutPricing->resolveContentModel($request->type, (int) $request->movie_id);
        if (! $content) {
            return ApiResponse::error('Content not found.', 404);
        }

        $priceData = $this->checkoutPricing->calculatePayPerView($content, $request->type);
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
        $result = $this->gatewayCheckout->createPayPerViewSession(
            $paymentMethod,
            $content,
            $request->type,
            $finalPrice,
            $currency,
            $user,
            $frontendUrl
        );

        if (! $result['ok']) {
            return ApiResponse::error($result['message'], $result['status']);
        }

        return ApiResponse::success($result['data'], $result['message'], 200);
    }

    /** Verify and fulfill a PPV purchase from the provider record. */
    public function verifyPayPerViewPayment(VerifyPayPerViewPaymentRequest $request)
    {
        $result = $this->gatewayCheckout->verifyPayPerViewPayment(Auth::user(), $request->validated());
        if (! $result['ok']) {
            return ApiResponse::error($result['message'], $result['status']);
        }

        return ApiResponse::success($result['data'], $result['message'], 200);
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

    private function reserveCouponForCheckout(int $couponId, int $userId): string
    {
        return $this->couponReservations->reserve($couponId, $userId);
    }

    private function releaseCouponReservation(?string $token): void
    {
        $this->couponReservations->release($token);
    }

    private function consumeCouponReservation(?string $token, int $couponId, int $userId, int $subscriptionId): void
    {
        $this->couponReservations->consume($token, $couponId, $userId, $subscriptionId);
    }

    private function activateUserSubscription(int $userId, Plan $plan, float $amount, string $paymentType, string $transactionId, ?int $promotionId = null, string $currency = 'USD'): Subscription
    {
        return $this->fulfillment->activateUserSubscription($userId, $plan, $amount, $paymentType, $transactionId, $promotionId, $currency);
    }

    private function startEligibleTrial(User $user, Plan $plan): ?Subscription
    {
        return $this->fulfillment->startEligibleTrial($user, $plan);
    }

    private function activatePayPerViewAccess(int $userId, $content, string $type, float $amount, string $paymentType, string $transactionId): PayPerView
    {
        return $this->fulfillment->activatePayPerViewAccess($userId, $content, $type, $amount, $paymentType, $transactionId);
    }
}
