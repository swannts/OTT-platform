<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
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
            'payment_method' => 'required|string|in:stripe,razorpay,paystack,paypal,midtrans,flutterwave,cinet,sadad,airtel,phonepe,free',
            'coupon_code' => 'nullable|string',
            'coupon_id' => 'nullable|integer',
            'redirect_url' => 'nullable|url',
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

        // If amount is 0, activate plan immediately
        if ($finalPrice <= 0 || $paymentMethod === 'free') {
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
        $frontendUrl = rtrim($request->input('redirect_url', env('FRONTEND_URL', env('APP_URL'))), '/');
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
            'gateway' => 'required|string|in:stripe,razorpay,paystack,paypal,midtrans',
            'session_id' => 'nullable|string',
            'payment_id' => 'nullable|string',
            'order_id' => 'nullable|string',
            'signature' => 'nullable|string',
            'reference' => 'nullable|string',
            'token' => 'nullable|string',
        ]);

        $user = Auth::user();
        $gateway = strtolower($request->gateway);

        try {
            switch ($gateway) {
                case 'stripe':
                    $sessionId = $request->session_id;
                    if (! $sessionId) {
                        return ApiResponse::error('Stripe session_id is required.', 422);
                    }

                    $stripeSecret = GetpaymentMethod('stripe_secretkey');
                    $stripe = new StripeClient($stripeSecret);
                    $session = $stripe->checkout->sessions->retrieve($sessionId);

                    if ($session->payment_status !== 'paid') {
                        return ApiResponse::error('Stripe payment has not been completed.', 400);
                    }

                    $planId = (int) ($session->metadata->plan_id ?? 0);
                    $promotionId = ! empty($session->metadata->promotion_id) ? (int) $session->metadata->promotion_id : null;
                    $transactionId = $session->payment_intent ?: $sessionId;
                    $amount = $session->amount_total / 100;

                    $plan = Plan::where('id', $planId)->first();
                    if (! $plan) {
                        return ApiResponse::error('Plan associated with payment not found.', 404);
                    }

                    $subscription = $this->activateUserSubscription($user->id, $plan, $amount, 'stripe', $transactionId, $promotionId);

                    return ApiResponse::success([
                        'subscription' => new SubscriptionResource($subscription),
                    ], 'Subscription activated successfully.', 200);

                case 'razorpay':
                    $paymentId = $request->payment_id;
                    $orderId = $request->order_id;
                    $signature = $request->signature;

                    $razorpayKey = GetpaymentMethod('razorpay_publickey');
                    $razorpaySecret = GetpaymentMethod('razorpay_secretkey');
                    $api = new \Razorpay\Api\Api($razorpayKey, $razorpaySecret);

                    if ($signature) {
                        $attributes = [
                            'razorpay_order_id' => $orderId,
                            'razorpay_payment_id' => $paymentId,
                            'razorpay_signature' => $signature,
                        ];
                        $api->utility->verifyPaymentSignature($attributes);
                    }

                    $payment = $api->payment->fetch($paymentId);
                    if ($payment->status !== 'captured' && $payment->status !== 'authorized') {
                        return ApiResponse::error('Razorpay payment not completed.', 400);
                    }

                    $planId = (int) $request->input('plan_id');
                    $plan = Plan::find($planId);
                    if (! $plan) {
                        return ApiResponse::error('Plan not found.', 404);
                    }

                    $subscription = $this->activateUserSubscription($user->id, $plan, $payment->amount / 100, 'razorpay', $paymentId, $request->input('promotion_id'));

                    return ApiResponse::success([
                        'subscription' => new SubscriptionResource($subscription),
                    ], 'Subscription activated successfully.', 200);

                case 'paystack':
                    $reference = $request->reference ?? $request->input('trxref');
                    if (! $reference) {
                        return ApiResponse::error('Paystack reference is required.', 422);
                    }

                    $secretKey = GetpaymentMethod('paystack_secretkey');
                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $secretKey,
                    ])->get("https://api.paystack.co/transaction/verify/{$reference}");

                    $body = $response->json();
                    if (! ($body['status'] ?? false) || ($body['data']['status'] ?? '') !== 'success') {
                        return ApiResponse::error('Paystack verification failed.', 400);
                    }

                    $planId = (int) ($body['data']['metadata']['plan_id'] ?? $request->input('plan_id'));
                    $plan = Plan::find($planId);
                    if (! $plan) {
                        return ApiResponse::error('Plan not found.', 404);
                    }

                    $amount = $body['data']['amount'] / 100;
                    $subscription = $this->activateUserSubscription($user->id, $plan, $amount, 'paystack', $reference);

                    return ApiResponse::success([
                        'subscription' => new SubscriptionResource($subscription),
                    ], 'Subscription activated successfully.', 200);

                default:
                    return ApiResponse::error("Verification for [{$gateway}] is not implemented.", 400);
            }
        } catch (Exception $e) {
            Log::error('verifySubscriptionPayment exception', ['error' => $e->getMessage()]);
            return ApiResponse::error('Payment verification failed: ' . $e->getMessage(), 500);
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
        $daysLeft = max(0, $now->diffInDays($endDate, false));

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
            'type' => 'required|string|in:movie,tvshow,video,episode,season',
            'payment_method' => 'required|string|in:stripe,razorpay,paystack,paypal,free',
            'redirect_url' => 'nullable|url',
        ]);

        $user = Auth::user();
        $content = $this->resolveContentModel($request->type, $request->movie_id);
        if (! $content) {
            return ApiResponse::error('Content not found.', 404);
        }

        $calcRequest = new Request([
            'movie_id' => $content->id,
            'type' => $request->type,
        ]);
        $pricing = $this->calculatePayPerViewPrice($calcRequest)->getData(true)['data'];
        $finalPrice = (float) $pricing['total_amount'];
        $paymentMethod = strtolower($request->payment_method);

        if ($finalPrice <= 0 || $paymentMethod === 'free') {
            $ppv = $this->activatePayPerViewAccess($user->id, $content, $request->type, 0, 'free', 'FREE-' . uniqid());
            return ApiResponse::success(['success' => true, 'ppv' => $ppv], 'Content rented successfully.', 200);
        }

        $frontendUrl = rtrim($request->input('redirect_url', env('FRONTEND_URL', env('APP_URL'))), '/');
        $currency = GetcurrentCurrency() ?: 'USD';

        if ($paymentMethod === 'stripe') {
            $stripeSecret = GetpaymentMethod('stripe_secretkey');
            $stripe = new StripeClient($stripeSecret);

            $checkoutSession = $stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => [
                            'name' => 'Pay-Per-View: ' . ($content->name ?? 'Content'),
                        ],
                        'unit_amount' => (int) round($finalPrice * 100),
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'metadata' => [
                    'movie_id' => $content->id,
                    'type' => $request->type,
                    'is_ppv' => '1',
                ],
                'success_url' => "{$frontendUrl}/payment-success?gateway=stripe&session_id={CHECKOUT_SESSION_ID}&is_ppv=1",
                'cancel_url' => "{$frontendUrl}/movies",
            ]);

            return ApiResponse::success([
                'redirect_url' => $checkoutSession->url,
                'session_id' => $checkoutSession->id,
            ], 'Stripe checkout initiated.', 200);
        }

        return ApiResponse::error("Payment method [{$paymentMethod}] is not supported for PPV web checkout.", 400);
    }

    /**
     * Verify Pay-Per-View payment
     */
    public function verifyPayPerViewPayment(Request $request)
    {
        $request->validate([
            'gateway' => 'required|string',
            'session_id' => 'required|string',
        ]);

        $user = Auth::user();
        if ($request->gateway === 'stripe') {
            $stripeSecret = GetpaymentMethod('stripe_secretkey');
            $stripe = new StripeClient($stripeSecret);
            $session = $stripe->checkout->sessions->retrieve($request->session_id);

            if ($session->payment_status !== 'paid') {
                return ApiResponse::error('Payment not completed.', 400);
            }

            $movieId = (int) ($session->metadata->movie_id ?? 0);
            $type = (string) ($session->metadata->type ?? 'movie');
            $content = $this->resolveContentModel($type, $movieId);

            if (! $content) {
                return ApiResponse::error('Content not found.', 404);
            }

            $amount = $session->amount_total / 100;
            $txId = $session->payment_intent ?: $request->session_id;

            $ppv = $this->activatePayPerViewAccess($user->id, $content, $type, $amount, 'stripe', $txId);

            return ApiResponse::success([
                'ppv' => $ppv,
            ], 'Pay-per-view rental unlocked successfully.', 200);
        }

        return ApiResponse::error('Invalid gateway for PPV verification.', 400);
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

        $currenciesWithoutCents = ['XAF', 'XOF', 'JPY', 'KRW'];
        $unitAmount = in_array(strtoupper($currency), $currenciesWithoutCents)
            ? (int) round($price)
            : (int) round($price * 100);

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
            return ApiResponse::error('Stripe error: ' . $e->getMessage(), 400);
        }
    }

    private function createRazorpaySubscriptionOrder(Plan $plan, float $price, string $currency, User $user, ?int $promotionId, string $frontendUrl)
    {
        $razorpayKey = GetpaymentMethod('razorpay_publickey');
        $razorpaySecret = GetpaymentMethod('razorpay_secretkey');
        $api = new \Razorpay\Api\Api($razorpayKey, $razorpaySecret);

        try {
            $order = $api->order->create([
                'receipt' => 'sub_' . time(),
                'amount' => (int) round($price * 100),
                'currency' => strtoupper($currency),
                'payment_capture' => 1,
            ]);

            return ApiResponse::success([
                'key' => $razorpayKey,
                'amount' => (int) round($price * 100),
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
            return ApiResponse::error('Razorpay error: ' . $e->getMessage(), 400);
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
            'amount' => (int) round($price * 100),
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

