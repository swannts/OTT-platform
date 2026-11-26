<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;
use Modules\Coupon\Models\Coupon;
use Modules\Entertainment\Models\Entertainment;
use Modules\Episode\Models\Episode;
use Modules\Season\Models\Season;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Services\PlanPricingService;
use Modules\Tax\Models\Tax;
use Modules\Video\Models\Video;

class CheckoutPricingService
{
    public function __construct(
        private readonly PlanPricingService $planPricingService,
        private readonly CouponReservationService $couponReservationService,
    ) {}

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, message: string, status: int}
     */
    public function calculateSubscription(Plan $plan, Request $request, ?int $userId = null): array
    {
        $resolvedPrice = $this->planPricingService->resolve($plan, $request);
        $basePrice = $resolvedPrice['amount'];
        $planDiscountAmount = 0;
        $decimalPlaces = $resolvedPrice['decimal_places'];

        if ($plan->discount_percentage > 0) {
            $planDiscountAmount = round(($basePrice * $plan->discount_percentage) / 100, $decimalPlaces);
            $basePrice = round($basePrice - $planDiscountAmount, $decimalPlaces);
        }

        $couponDiscountAmount = 0;
        $couponData = null;

        $couponQuery = Coupon::where('status', 1)
            ->where(fn ($query) => $query->whereNull('start_date')->orWhereDate('start_date', '<=', today()))
            ->where(fn ($query) => $query->whereNull('expire_date')->orWhereDate('expire_date', '>=', today()))
            ->whereHas('CouponPlanMappings', function ($q) use ($plan) {
                $q->where('subscription_plan_id', $plan->id);
            });

        if ($request->filled('coupon_code')) {
            $couponQuery->where('code', $request->coupon_code);
        } elseif ($request->filled('coupon_id')) {
            $couponQuery->where('id', $request->coupon_id);
        } else {
            $couponQuery->whereRaw('1 = 0');
        }

        $coupon = $couponQuery->first();
        if ($coupon) {
            if ($coupon->discount_type === 'fixed' && $resolvedPrice['regional']
                && $resolvedPrice['currency'] !== strtoupper((string) (GetcurrentCurrency() ?: 'USD'))) {
                return [
                    'ok' => false,
                    'message' => 'Fixed-amount coupons are not configured for this regional currency.',
                    'status' => 422,
                ];
            }

            if (! $this->couponReservationService->isUsageAvailable($coupon, $userId)) {
                return [
                    'ok' => false,
                    'message' => 'This coupon has reached its redemption limit.',
                    'status' => 422,
                ];
            }

            if ($coupon->discount_type === 'fixed') {
                $couponDiscountAmount = round(min($basePrice, (float) $coupon->discount), $decimalPlaces);
            } else {
                $couponDiscountAmount = round(($basePrice * (float) $coupon->discount) / 100, $decimalPlaces);
            }

            $couponData = [
                'id' => $coupon->id,
                'coupon_code' => $coupon->code,
                'discount_type' => $coupon->discount_type,
                'discount' => $coupon->discount,
                'discount_amount' => round($couponDiscountAmount, $decimalPlaces),
            ];
        } elseif ($request->filled('coupon_code')) {
            return [
                'ok' => false,
                'message' => 'The provided coupon code is invalid or has expired.',
                'status' => 422,
            ];
        }

        $subtotalAfterDiscounts = max(0, round($basePrice - $couponDiscountAmount, $decimalPlaces));

        $taxes = Tax::active()->get();
        if ($resolvedPrice['regional']
            && $resolvedPrice['currency'] !== strtoupper((string) (GetcurrentCurrency() ?: 'USD'))
            && $taxes->contains(static fn ($tax): bool => strtolower((string) $tax->type) === 'fixed')) {
            return [
                'ok' => false,
                'message' => 'Fixed taxes are not configured for this regional currency.',
                'status' => 422,
            ];
        }

        [$totalTax, $taxBreakdown] = $this->calculateTaxes($taxes, $subtotalAfterDiscounts, $decimalPlaces);
        $finalTotal = round($subtotalAfterDiscounts + $totalTax, $decimalPlaces);

        return [
            'ok' => true,
            'data' => [
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'original_price' => (float) $resolvedPrice['amount'],
                'pricing_country' => $resolvedPrice['country_code'],
                'plan_discount_percentage' => (float) $plan->discount_percentage,
                'plan_discount_amount' => round($planDiscountAmount, $decimalPlaces),
                'price_after_plan_discount' => round($basePrice, $decimalPlaces),
                'coupon' => $couponData,
                'coupon_discount_amount' => round($couponDiscountAmount, $decimalPlaces),
                'subtotal' => round($subtotalAfterDiscounts, $decimalPlaces),
                'total_tax' => round($totalTax, $decimalPlaces),
                'taxes' => $taxBreakdown,
                'total_amount' => $finalTotal,
                'currency' => $resolvedPrice['currency'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function calculatePayPerView(object $content, string $type): array
    {
        $basePrice = (float) ($content->price ?? 0);
        $discountAmount = 0;

        if (! empty($content->discount_percentage) && $content->discount_percentage > 0) {
            $discountAmount = ($basePrice * (float) $content->discount_percentage) / 100;
        }

        $subtotal = max(0, $basePrice - $discountAmount);
        $taxes = Tax::active()->get();
        [$totalTax, $taxBreakdown] = $this->calculateTaxes($taxes, $subtotal, 2);
        $finalTotal = round($subtotal + $totalTax, 2);

        return [
            'content_id' => $content->id,
            'content_title' => $content->name ?? $content->title ?? 'Video',
            'content_type' => $type,
            'available_for_days' => (int) ($content->available_for ?? 2),
            'access_duration_days' => (int) ($content->access_duration ?? 2),
            'original_price' => $basePrice,
            'discount_amount' => round($discountAmount, 2),
            'subtotal' => round($subtotal, 2),
            'total_tax' => round($totalTax, 2),
            'taxes' => $taxBreakdown,
            'total_amount' => $finalTotal,
            'currency' => GetcurrentCurrency() ?: 'USD',
        ];
    }

    public function resolveContentModel(string $type, int $id): ?object
    {
        return match ($type) {
            'movie', 'tvshow' => Entertainment::find($id),
            'video' => Video::find($id),
            'episode' => Episode::find($id),
            'season' => Season::find($id),
            default => null,
        };
    }

    public function currencyMultiplier(string $currency, string $gateway = 'stripe'): int
    {
        if (strtolower($gateway) === 'paystack') {
            return 100;
        }

        $zeroDecimalCurrencies = [
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW',
            'MGA', 'PYG', 'RWF', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
        ];
        if (strtolower($gateway) === 'razorpay') {
            $zeroDecimalCurrencies[] = 'UGX';
        }

        return in_array(strtoupper($currency), $zeroDecimalCurrencies, true) ? 1 : 100;
    }

    public function toMinorUnits(float $amount, string $currency, string $gateway = 'stripe'): int
    {
        return (int) round($amount * $this->currencyMultiplier($currency, $gateway));
    }

    /**
     * @param  iterable<int, Tax>  $taxes
     * @return array{0: float, 1: array<int, array<string, mixed>>}
     */
    private function calculateTaxes(iterable $taxes, float $subtotal, int $decimalPlaces): array
    {
        $totalTax = 0.0;
        $taxBreakdown = [];

        foreach ($taxes as $tax) {
            $taxAmount = strtolower((string) $tax->type) === 'fixed'
                ? round((float) $tax->value, $decimalPlaces)
                : round(($subtotal * (float) $tax->value) / 100, $decimalPlaces);

            $totalTax += $taxAmount;
            $taxBreakdown[] = [
                'title' => $tax->title,
                'type' => $tax->type,
                'value' => $tax->value,
                'tax_amount' => round($taxAmount, $decimalPlaces),
            ];
        }

        return [$totalTax, $taxBreakdown];
    }
}
