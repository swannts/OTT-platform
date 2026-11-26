<?php

namespace App\Services\Frontend;

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Coupon\Models\Coupon;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Subscription;
use Modules\Subscriptions\Trait\SubscriptionTrait;
use Modules\Tax\Models\Tax;
use Throwable;

class FrontendAccountService
{
    use SubscriptionTrait;

    public function deviceLogout(int|string $userId, ?string $deviceId = null, mixed $id = null): bool
    {
        $deviceQuery = Device::where('user_id', $userId);

        if ($deviceId !== null || $id !== null) {
            $deviceQuery->where(function ($query) use ($deviceId, $id) {
                if ($deviceId !== null) {
                    $query->where('device_id', $deviceId);
                }
                if ($id !== null) {
                    $deviceId !== null
                        ? $query->orWhere('id', $id)
                        : $query->where('id', $id);
                }
            });
        }

        $device = $deviceQuery->first();
        if (! $device) {
            return false;
        }

        $deviceIdToLogout = $device->device_id;

        try {
            $user = User::find($userId);
            if ($user && class_exists('Laravel\\Sanctum\\PersonalAccessToken')) {
                PersonalAccessToken::where('tokenable_id', $userId)
                    ->where('name', $deviceIdToLogout)
                    ->delete();
            }
        } catch (Throwable $e) {
            // best-effort; ignore errors revoking tokens
        }

        $device->delete();

        try {
            DB::table('sessions')
                ->where('user_id', $userId)
                ->where('ip_address', $deviceIdToLogout)
                ->delete();
        } catch (Throwable $e) {
            // ignore if sessions table not present
        }

        return true;
    }

    /**
     * @return array{status: int, payload: array<string, mixed>}
     */
    public function cancelSubscription(int|string $userId, mixed $subscriptionId): array
    {
        $subscription = Subscription::where('user_id', $userId)
            ->where('id', $subscriptionId)
            ->where('status', 'active')
            ->first();

        if (! $subscription) {
            return [
                'status' => 404,
                'payload' => ['success' => false, 'message' => 'Subscription not found'],
            ];
        }

        DB::transaction(function () use ($subscription, $userId) {
            $subscription->update(['status' => 'cancel']);

            $otherSubscription = Subscription::where('user_id', $userId)
                ->where('status', 'active')
                ->exists();

            if (! $otherSubscription) {
                User::where('id', $userId)->update(['is_subscribe' => 0]);
            }
        });

        $this->sendNotificationOnsubscription('cancle_subscription', $subscription);

        return [
            'status' => 200,
            'payload' => ['success' => true],
        ];
    }

    /**
     * @return array{status: int, payload: array<string, mixed>}
     */
    public function getPaymentDetails(mixed $planId, mixed $promotionId = null): array
    {
        $plan = Plan::find($planId);
        if (! $plan) {
            return [
                'status' => 404,
                'payload' => ['error' => 'Plan not found.'],
            ];
        }

        $discountPercentage = $plan->discount_percentage;
        $discountAmount = ($discountPercentage * $plan->price) / 100;

        $taxes = Tax::where('status', 1)->get();
        $baseAmount = $plan->total_price;
        $totalTaxAmount = 0;
        $taxesArray = [];

        $subtotalBeforePromotion = $baseAmount;
        $promotionDiscountAmount = 0;

        if ($promotionId) {
            $promotion = Coupon::where('id', $promotionId)
                ->where('status', 1)
                ->whereHas('subscriptionPlans', function ($query) use ($planId) {
                    $query->where('subscription_plan_id', $planId);
                })
                ->first();

            if ($promotion) {
                if ($promotion->discount_type === 'percentage') {
                    $promotionDiscountAmount = ($promotion->discount * $baseAmount) / 100;
                } elseif ($promotion->discount_type === 'fixed') {
                    $promotionDiscountAmount = $promotion->discount;
                }
            } else {
                return [
                    'status' => 400,
                    'payload' => ['error' => 'Invalid or expired promotion.'],
                ];
            }
        }

        $subtotalAfterPromotion = max(0, $subtotalBeforePromotion - $promotionDiscountAmount);

        foreach ($taxes as $tax) {
            $taxAmount = 0;

            if (strtolower($tax->type) == 'fixed') {
                $taxAmount = $tax->value;
            } elseif (strtolower($tax->type) == 'percentage') {
                $taxAmount = ($subtotalAfterPromotion * $tax->value) / 100;
            }

            $taxesArray[] = [
                'name' => $tax->title,
                'type' => $tax->type,
                'value' => $tax->value,
                'tax_amount' => $taxAmount,
            ];

            $totalTaxAmount += $taxAmount;
        }

        $totalAfterPromotion = $subtotalAfterPromotion + $totalTaxAmount;

        return [
            'status' => 200,
            'payload' => [
                'price' => $plan->price,
                'total_price' => $plan->total_price,
                'subtotal' => $subtotalAfterPromotion,
                'discount_percentage' => $discountPercentage,
                'plan_discount_amount' => $discountAmount,
                'tax' => $totalTaxAmount,
                'tax_array' => $taxesArray,
                'promotion_id' => $promotionId,
                'promotion_discount_amount' => $promotionDiscountAmount,
                'total' => $totalAfterPromotion,
            ],
        ];
    }

    public function checkSubscription(int|string $userId, mixed $planId): bool
    {
        $currentSubscription = Subscription::where('user_id', $userId)
            ->where(function ($query) {
                $query->where('status', 'active')
                    ->orWhere('end_date', '>', now());
            })
            ->get();

        $planData = Plan::find($planId);
        if (! $planData) {
            return false;
        }

        $level = $planData->level;

        foreach ($currentSubscription as $plan) {
            $currentLevel = $plan->level;

            if (is_null($currentLevel)) {
                $p = Plan::find($plan->plan_id);
                if ($p) {
                    $currentLevel = $p->level;
                }
            }

            if ($currentLevel >= $level) {
                return true;
            }
        }

        return false;
    }
}
