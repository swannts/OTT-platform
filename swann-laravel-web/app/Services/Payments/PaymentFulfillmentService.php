<?php

namespace App\Services\Payments;

use App\Models\User;
use App\Models\VerifiedPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Frontend\Models\PayPerView;
use Modules\Frontend\Models\PayperviewTransaction;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Subscription;
use Modules\Subscriptions\Models\SubscriptionTransactions;
use Modules\Subscriptions\Transformers\PlanResource;
use RuntimeException;

class PaymentFulfillmentService
{
    public function __construct(
        private readonly CheckoutPricingService $checkoutPricing,
        private readonly CouponReservationService $couponReservations,
    ) {}

    public function fulfillVerifiedPayment(
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
    ): Subscription|PayPerView {
        return DB::transaction(function () use (
            $gateway,
            $providerReference,
            $userId,
            $purchaseType,
            $purchaseId,
            $amountMinor,
            $currency,
            $promotionId,
            $contentType,
            $couponReservationToken
        ): Subscription|PayPerView {
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
                || strtoupper((string) $receipt->currency) !== strtoupper($currency)) {
                throw new RuntimeException('Provider transaction was already associated with a different purchase.');
            }

            if ($receipt->fulfilled_at) {
                return $receipt->fulfillment_type === 'subscription'
                    ? Subscription::findOrFail($receipt->fulfillment_id)
                    : PayPerView::findOrFail($receipt->fulfillment_id);
            }

            $amount = $amountMinor / $this->checkoutPricing->currencyMultiplier($currency, $gateway);
            if ($purchaseType === 'subscription') {
                $plan = Plan::where('id', $purchaseId)->where('status', 1)->firstOrFail();
                $fulfillment = $this->activateUserSubscription(
                    $userId,
                    $plan,
                    $amount,
                    $gateway,
                    $providerReference,
                    $promotionId,
                    strtoupper($currency)
                );
                if ($promotionId || $couponReservationToken) {
                    $this->couponReservations->consume($couponReservationToken, (int) $promotionId, $userId, (int) $fulfillment->id);
                }
                $receipt->fulfillment_type = 'subscription';
            } elseif ($purchaseType === 'pay_per_view') {
                $content = $this->checkoutPricing->resolveContentModel((string) $contentType, $purchaseId);
                if (! $content) {
                    throw new RuntimeException('The content associated with the verified payment no longer exists.');
                }
                $fulfillment = $this->activatePayPerViewAccess($userId, $content, (string) $contentType, $amount, $gateway, $providerReference);
                $receipt->fulfillment_type = 'pay_per_view';
            } else {
                throw new RuntimeException('Unsupported verified payment purchase type.');
            }

            $receipt->fulfillment_id = $fulfillment->id;
            $receipt->fulfilled_at = now();
            $receipt->save();

            return $fulfillment;
        });
    }

    public function activateUserSubscription(
        int $userId,
        Plan $plan,
        float $amount,
        string $paymentType,
        string $transactionId,
        ?int $promotionId = null,
        string $currency = 'USD'
    ): Subscription {
        Subscription::where('user_id', $userId)
            ->where('status', 'active')
            ->update(['status' => 'inactive']);

        $startDate = now()->toDateTimeString();
        $endDate = $this->calculatePlanExpirationDate($startDate, $plan->duration, (int) ($plan->duration_value ?? 1));
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

    public function startEligibleTrial(User $user, Plan $plan): ?Subscription
    {
        return DB::transaction(function () use ($user, $plan): ?Subscription {
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            $existingTrial = Subscription::query()->where('user_id', $user->id)
                ->where('plan_id', $plan->id)->where('is_trial', true)->where('status', 'active')->first();
            if ($existingTrial) {
                return $existingTrial;
            }

            if (Subscription::withTrashed()->where('user_id', $user->id)->exists()) {
                return null;
            }

            $start = now();
            $snapshot = PlanResource::collection([$plan]);
            $subscription = Subscription::query()->create([
                'plan_id' => $plan->id,
                'user_id' => $user->id,
                'start_date' => $start,
                'end_date' => $start->copy()->addDays((int) $plan->trial_days),
                'status' => 'active',
                'is_trial' => true,
                'amount' => $plan->price,
                'total_amount' => 0,
                'name' => $plan->name,
                'identifier' => $plan->identifier,
                'type' => 'trial',
                'duration' => (int) $plan->trial_days,
                'level' => $plan->level,
                'plan_type' => json_encode($snapshot),
            ]);
            User::query()->whereKey($user->id)->update(['is_subscribe' => 1]);
            Cache::flush();

            return $subscription;
        }, 3);
    }

    public function activatePayPerViewAccess(
        int $userId,
        object $content,
        string $type,
        float $amount,
        string $paymentType,
        string $transactionId
    ): PayPerView {
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

    private function calculatePlanExpirationDate(string $startDate, ?string $duration, int $durationValue): string
    {
        $startAt = Carbon::parse($startDate);
        $endAt = match ($duration) {
            'month' => $startAt->addMonths($durationValue),
            'year' => $startAt->addYears($durationValue),
            'week' => $startAt->addWeeks($durationValue),
            default => $startAt->addDays($durationValue),
        };

        return $endAt->format('Y-m-d H:i:s');
    }
}
