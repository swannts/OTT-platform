<?php

namespace Modules\Coupon\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Coupon\Models\Coupon;
use Carbon\Carbon;

class CouponDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $coupons = [
            [
                'code' => 'WELCOME20',
                'description' => 'Welcome offer - Get 20% off your subscription',
                'discount_type' => 'percentage',
                'discount' => 20.00,
                'start_date' => Carbon::now()->toDateString(),
                'expire_date' => Carbon::now()->addMonths(6)->toDateString(),
                'status' => true,
            ],
            [
                'code' => 'RAMADAN50',
                'description' => 'Mubarak Ramadan Special - 50% off on Annual VIP',
                'discount_type' => 'percentage',
                'discount' => 50.00,
                'start_date' => Carbon::now()->toDateString(),
                'expire_date' => Carbon::now()->addMonths(6)->toDateString(),
                'status' => true,
            ],
            [
                'code' => 'NOOR25',
                'description' => 'Sacred Cinema special - 25% discount across all packages',
                'discount_type' => 'percentage',
                'discount' => 25.00,
                'start_date' => Carbon::now()->toDateString(),
                'expire_date' => Carbon::now()->addMonths(3)->toDateString(),
                'status' => true,
            ],
            [
                'code' => 'EIDMUBARAK',
                'description' => 'Eid Celebration - 30% off any plan',
                'discount_type' => 'percentage',
                'discount' => 30.00,
                'start_date' => Carbon::now()->toDateString(),
                'expire_date' => Carbon::now()->addMonths(3)->toDateString(),
                'status' => true,
            ],
        ];

        foreach ($coupons as $couponData) {
            $existingCoupon = Coupon::where('code', $couponData['code'])->first();
            
            if (!$existingCoupon) {
                $coupon = Coupon::create($couponData);
                
                switch ($couponData['code']) {
                    case 'WELCOME20':
                        $coupon->subscriptionPlans()->attach([1]);
                        break;
                    case 'RAMADAN50':
                        $coupon->subscriptionPlans()->attach([2]);
                        break;
                    case 'NOOR25':
                    case 'EIDMUBARAK':
                        $coupon->subscriptionPlans()->attach([1, 2, 3]);
                        break;
                }
            }
        }
    }
}
