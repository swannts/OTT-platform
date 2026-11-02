<?php

namespace Modules\Subscriptions\database\seeders;

use Illuminate\Database\Seeder;

class PlanTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        \DB::table('plan')->delete();

        \DB::table('plan')->insert(array (
            0 =>
            array (
                'id' => 1,
                'name' => 'Monthly VIP (মাসিক ভিআইপি)',
                'identifier' => 'monthly_vip',
                'android_identifier' => NULL,
                'apple_identifier' => NULL,
                'price' => 199.0,
                'discount' => 0,
                'discount_percentage' => NULL,
                'total_price' => 199.0,
                'level' => 1,
                'duration' => 'month',
                'duration_value' => 1,
                'status' => 1,
                'description' => 'Unlimited 4K Ultra HD Streaming, ad-free sacred cinema, TV series, and live Haramain broadcasts.',
                'created_by' => 2,
                'updated_by' => 2,
                'deleted_by' => NULL,
                'deleted_at' => NULL,
                'created_at' => now(),
                'updated_at' => now(),
            ),
            1 =>
            array (
                'id' => 2,
                'name' => 'Annual VIP (বাৎসরিক ভিআইপি - সাশ্রয়ী)',
                'identifier' => 'annual_vip',
                'android_identifier' => NULL,
                'apple_identifier' => NULL,
                'price' => 1999.0,
                'discount' => 389.0,
                'discount_percentage' => 16,
                'total_price' => 1999.0,
                'level' => 2,
                'duration' => 'year',
                'duration_value' => 1,
                'status' => 1,
                'description' => 'Complete 12-month access to entire sacred library, exclusive documentaries, waz & live channels with 2 months free.',
                'created_by' => 2,
                'updated_by' => 2,
                'deleted_by' => NULL,
                'deleted_at' => NULL,
                'created_at' => now(),
                'updated_at' => now(),
            ),
            2 =>
            array (
                'id' => 3,
                'name' => 'Family VIP (ফ্যামিলি ভিআইপি)',
                'identifier' => 'family_vip',
                'android_identifier' => NULL,
                'apple_identifier' => NULL,
                'price' => 299.0,
                'discount' => 0,
                'discount_percentage' => NULL,
                'total_price' => 299.0,
                'level' => 3,
                'duration' => 'month',
                'duration_value' => 1,
                'status' => 1,
                'description' => 'Multi-screen streaming up to 4 devices simultaneously with parental lock and safe kids profiles.',
                'created_by' => 2,
                'updated_by' => 2,
                'deleted_by' => NULL,
                'deleted_at' => NULL,
                'created_at' => now(),
                'updated_at' => now(),
            ),
        ));


    }
}
