<?php

namespace App\Services\Payments;

use App\Models\User;
use App\Models\VerifiedPayment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
use Modules\Video\Models\Video;

final class FulfillVerifiedPayment
{
    use SubscriptionTrait;

    public function execute(
        string $gateway,
        string $providerReference,
        int $userId,
        string $purchaseType,
        int $purchaseId,
        int $amountMinor,
        string $currency,
        ?int $promotionId = null,
        ?string $contentType = null,
        ?string $couponReservationToken = null
    ) {
        return DB::transaction(function () use ($gateway, $providerReference, $userId, $purchaseType, $purchaseId, $amountMinor, $currency, $promotionId, $contentType, $couponReservationToken) {
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

            $amount = $amountMinor / $this->currencyMultiplier($currency, $gateway);
            if ($purchaseType === 'subscription') {
                $plan = Plan::where('id', $purchaseId)->where('status', 1)->firstOrFail();
                $fulfillment = $this->activateUserSubscription($userId, $plan, $amount, $gateway, $providerReference, $promotionId, strtoupper($currency));
                if ($promotionId || $couponReservationToken) {
                    $this->consumeCouponReservation($couponReservationToken, (int) $promotionId, $userId, (int) $fulfillment->id);
                }
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

    public function activateUserSubscription(int $userId, Plan $plan, float $amount, string $paymentType, string $transactionId, ?int $promotionId = null, string $currency = 'USD'): Subscription
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
            'currency_code' => strtoupper($currency),
            'payment_type' => $paymentType,
            'payment_status' => 'paid',
            'transaction_id' => $transactionId,
        ]);

        User::where('id', $userId)->update(['is_subscribe' => 1]);
        Cache::flush();

        return $subscription;
    }

    public function activatePayPerViewAccess(int $userId, $content, string $type, float $amount, string $paymentType, string $transactionId): PayPerView
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

    public function consumeCouponReservation(?string $token, int $couponId, int $userId, int $subscriptionId): void
    {
        $reservation = null;
        if ($token) {
            $reservation = DB::table('subscription_coupon_reservations')->where('token', $token)->lockForUpdate()->first();
            if (!$reservation || (int) $reservation->user_id !== $userId
                || ($couponId > 0 && (int) $reservation->coupon_id !== $couponId)) {
                throw new \RuntimeException('Coupon checkout reservation does not match the verified subscription payment.');
            }
            if ($reservation->status === 'redeemed') {
                if ((int) $reservation->subscription_id === $subscriptionId) return;
                throw new \RuntimeException('Coupon reservation was already used for another subscription.');
            }
            if ($reservation->status !== 'pending') {
                throw new \RuntimeException('Coupon checkout reservation is no longer available.');
            }
            $couponId = (int) $reservation->coupon_id;
        }

        if ($couponId <= 0) return;
        $coupon = Coupon::query()->lockForUpdate()->find($couponId);
        if (!$coupon) throw new \RuntimeException('The coupon associated with this payment no longer exists.');

        DB::table('user_coupon_redeem')->insert([
            'coupon_code' => $coupon->code, 'discount' => $coupon->discount,
            'user_id' => $userId, 'coupon_id' => $coupon->id, 'booking_id' => $subscriptionId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        if ($reservation) {
            DB::table('subscription_coupon_reservations')->where('id', $reservation->id)->update([
                'status' => 'redeemed', 'redeemed_at' => now(), 'subscription_id' => $subscriptionId, 'updated_at' => now(),
            ]);
        }
    }

    public function currencyMultiplier(string $currency, string $gateway = 'stripe'): int
    {
        // Paystack requires a 100x subunit amount for every currently supported currency,
        // including XOF, which otherwise has no ISO subunit.
        if (strtolower($gateway) === 'paystack') return 100;

        // Stripe's charge minor-unit rules. UGX stays at 100x for backward compatibility.
        // Razorpay follows the ISO zero-decimal set, including UGX. Its 3-decimal currencies are not enabled here.
        $zeroDecimalCurrencies = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];
        if (strtolower($gateway) === 'razorpay') $zeroDecimalCurrencies[] = 'UGX';

        return in_array(strtoupper($currency), $zeroDecimalCurrencies, true) ? 1 : 100;
    }

    public function resolveContentModel(string $type, int $id)
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
}
