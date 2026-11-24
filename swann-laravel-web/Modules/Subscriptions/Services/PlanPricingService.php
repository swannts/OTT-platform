<?php

namespace Modules\Subscriptions\Services;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Subscriptions\Models\Plan;
use Modules\Currency\Models\Currency;
use App\Support\Playback\TrustedCountryResolver;

final class PlanPricingService
{
    /** @return array{amount: float, discounted_amount: float, currency: string, decimal_places: int, country_code: ?string, regional: bool} */
    public function resolve(Plan $plan, Request $request): array
    {
        $regionalPrices = $plan->relationLoaded('regionalPrices')
            ? $plan->regionalPrices
            : $plan->regionalPrices()->get();
        if ($regionalPrices->isEmpty()) {
            $currencyCode = strtoupper((string) (GetcurrentCurrency() ?: 'USD'));
            $currency = Currency::query()->whereRaw('UPPER(currency_code) = ?', [$currencyCode])->first();
            $decimalPlaces = $currency ? (int) $currency->no_of_decimal : 2;
            if (! in_array($decimalPlaces, [0, 2], true)) {
                throw ValidationException::withMessages([
                    'pricing' => 'The configured currency precision is not supported by the payment providers.',
                ]);
            }
            $amount = (float) $plan->price;
            if ($decimalPlaces === 0 && floor($amount) !== $amount) {
                throw ValidationException::withMessages([
                    'pricing' => 'The plan price is invalid for the configured zero-decimal currency.',
                ]);
            }
            return [
                'amount' => $amount,
                'discounted_amount' => $this->discountedAmount($plan, $amount, $decimalPlaces),
                'currency' => $currencyCode,
                'decimal_places' => $decimalPlaces,
                'country_code' => null,
                'regional' => false,
            ];
        }

        $countryCode = app(TrustedCountryResolver::class)->resolve($request);
        if ($countryCode === null) {
            throw ValidationException::withMessages([
                'pricing' => 'Regional pricing requires a trusted country signal from the configured edge.',
            ]);
        }

        $regionalPrice = $regionalPrices->firstWhere('country_code', $countryCode);
        if ($regionalPrice === null) {
            throw ValidationException::withMessages([
                'pricing' => 'A price is not configured for this territory.',
            ]);
        }

        $currency = Currency::query()->whereRaw('UPPER(currency_code) = ?', [strtoupper($regionalPrice->currency_code)])->first();
        if (! $currency || ! in_array((int) $currency->no_of_decimal, [0, 2], true)) {
            throw ValidationException::withMessages([
                'pricing' => 'The configured regional currency precision is not supported by the payment providers.',
            ]);
        }

        $amount = (float) $regionalPrice->amount;
        $decimalPlaces = (int) $currency->no_of_decimal;
        if ($decimalPlaces === 0 && floor($amount) !== $amount) {
            throw ValidationException::withMessages([
                'pricing' => 'The configured regional price is invalid for its zero-decimal currency.',
            ]);
        }
        return [
            'amount' => $amount,
            'discounted_amount' => $this->discountedAmount($plan, $amount, $decimalPlaces),
            'currency' => strtoupper($regionalPrice->currency_code),
            'decimal_places' => $decimalPlaces,
            'country_code' => $countryCode,
            'regional' => true,
        ];
    }

    private function discountedAmount(Plan $plan, float $amount, int $decimalPlaces): float
    {
        $discount = max(0, min(100, (float) $plan->discount_percentage));
        return round($amount - ($amount * $discount / 100), $decimalPlaces);
    }
}
