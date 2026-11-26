<?php

namespace App\Services\Payments;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Coupon\Models\Coupon;
use RuntimeException;

class CouponReservationService
{
    public function findActiveCouponByCode(string $code): ?Coupon
    {
        return Coupon::where('code', trim($code))
            ->where('status', 1)
            ->where(fn ($query) => $query->whereNull('start_date')->orWhereDate('start_date', '<=', today()))
            ->where(fn ($query) => $query->whereNull('expire_date')->orWhereDate('expire_date', '>=', today()))
            ->first();
    }

    public function isUsageAvailable(Coupon $coupon, ?int $userId, ?string $exceptReservation = null): bool
    {
        $redeemed = DB::table('user_coupon_redeem')->where('coupon_id', $coupon->id);
        $pending = DB::table('subscription_coupon_reservations')
            ->where('coupon_id', $coupon->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now());

        if ($exceptReservation) {
            $pending->where('token', '<>', $exceptReservation);
        }

        $redeemedCount = (clone $redeemed)->count();
        $pendingCount = (clone $pending)->count();
        if ($coupon->max_redemptions !== null && $redeemedCount + $pendingCount >= (int) $coupon->max_redemptions) {
            return false;
        }

        if ($userId !== null && $coupon->per_user_limit !== null) {
            $userRedeemed = (clone $redeemed)->where('user_id', $userId)->count();
            $userPending = (clone $pending)->where('user_id', $userId)->count();
            if ($userRedeemed + $userPending >= (int) $coupon->per_user_limit) {
                return false;
            }
        }

        return true;
    }

    public function reserve(int $couponId, int $userId): string
    {
        return DB::transaction(function () use ($couponId, $userId): string {
            $coupon = Coupon::query()->lockForUpdate()->find($couponId);
            if (! $coupon || ! $coupon->status
                || ($coupon->start_date && Carbon::parse($coupon->start_date)->startOfDay()->isFuture())
                || ($coupon->expire_date && Carbon::parse($coupon->expire_date)->endOfDay()->isPast())
                || ! $this->isUsageAvailable($coupon, $userId)) {
                throw new RuntimeException('This coupon is no longer available or has reached its usage limit.');
            }

            $token = (string) Str::uuid();
            DB::table('subscription_coupon_reservations')->insert([
                'token' => $token,
                'coupon_id' => $coupon->id,
                'user_id' => $userId,
                'status' => 'pending',
                'expires_at' => now()->addHours(24),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $token;
        });
    }

    public function bind(?string $token, string $provider, string $providerReference): void
    {
        if (! $token) {
            return;
        }

        DB::table('subscription_coupon_reservations')
            ->where('token', $token)
            ->where('status', 'pending')
            ->update([
                'provider' => $provider,
                'provider_reference' => $providerReference,
                'updated_at' => now(),
            ]);
    }

    public function release(?string $token): void
    {
        if (! $token) {
            return;
        }

        DB::table('subscription_coupon_reservations')
            ->where('token', $token)
            ->where('status', 'pending')
            ->update([
                'status' => 'released',
                'updated_at' => now(),
            ]);
    }

    public function consume(?string $token, int $couponId, int $userId, int $subscriptionId): void
    {
        $reservation = null;
        if ($token) {
            $reservation = DB::table('subscription_coupon_reservations')
                ->where('token', $token)
                ->lockForUpdate()
                ->first();

            if (! $reservation || (int) $reservation->user_id !== $userId
                || ($couponId > 0 && (int) $reservation->coupon_id !== $couponId)) {
                throw new RuntimeException('Coupon checkout reservation does not match the verified subscription payment.');
            }
            if ($reservation->status === 'redeemed') {
                if ((int) $reservation->subscription_id === $subscriptionId) {
                    return;
                }
                throw new RuntimeException('Coupon reservation was already used for another subscription.');
            }
            if ($reservation->status !== 'pending') {
                throw new RuntimeException('Coupon checkout reservation is no longer available.');
            }

            $couponId = (int) $reservation->coupon_id;
        }

        if ($couponId <= 0) {
            return;
        }

        $coupon = Coupon::query()->lockForUpdate()->find($couponId);
        if (! $coupon) {
            throw new RuntimeException('The coupon associated with this payment no longer exists.');
        }

        DB::table('user_coupon_redeem')->insert([
            'coupon_code' => $coupon->code,
            'discount' => $coupon->discount,
            'user_id' => $userId,
            'coupon_id' => $coupon->id,
            'booking_id' => $subscriptionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($reservation) {
            DB::table('subscription_coupon_reservations')->where('id', $reservation->id)->update([
                'status' => 'redeemed',
                'redeemed_at' => now(),
                'subscription_id' => $subscriptionId,
                'updated_at' => now(),
            ]);
        }
    }
}
