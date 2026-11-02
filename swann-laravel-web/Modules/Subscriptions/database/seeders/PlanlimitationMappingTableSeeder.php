<?php

namespace Modules\Subscriptions\database\seeders;

use Illuminate\Database\Seeder;

class PlanlimitationMappingTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        \DB::table('planlimitation_mapping')->delete();

        \DB::table('planlimitation_mapping')->insert([
            // Plan 1: Monthly VIP
            [
                'id' => 1,
                'plan_id' => 1,
                'planlimitation_id' => 1,
                'limitation_slug' => 'video-cast',
                'limitation_value' => 1,
                'limit' => null,
            ],
            [
                'id' => 2,
                'plan_id' => 1,
                'planlimitation_id' => 2,
                'limitation_slug' => 'ads',
                'limitation_value' => 0,
                'limit' => null,
            ],
            [
                'id' => 3,
                'plan_id' => 1,
                'planlimitation_id' => 3,
                'limitation_slug' => 'device-limit',
                'limitation_value' => 1,
                'limit' => '2',
            ],
            [
                'id' => 4,
                'plan_id' => 1,
                'planlimitation_id' => 4,
                'limitation_slug' => 'download-status',
                'limitation_value' => 1,
                'limit' => '{"480p":1,"720p":1,"1080p":1,"1440p":0,"2K":0,"4K":0,"8K":0}',
            ],
            [
                'id' => 5,
                'plan_id' => 1,
                'planlimitation_id' => 5,
                'limitation_slug' => 'supported-device-type',
                'limitation_value' => 1,
                'limit' => '{"tablet":"1","laptop":"1","mobile":"1","tv":"1"}',
            ],
            [
                'id' => 6,
                'plan_id' => 1,
                'planlimitation_id' => 6,
                'limitation_slug' => 'profile-limit',
                'limitation_value' => 1,
                'limit' => 2,
            ],

            // Plan 2: Annual VIP
            [
                'id' => 7,
                'plan_id' => 2,
                'planlimitation_id' => 1,
                'limitation_slug' => 'video-cast',
                'limitation_value' => 1,
                'limit' => null,
            ],
            [
                'id' => 8,
                'plan_id' => 2,
                'planlimitation_id' => 2,
                'limitation_slug' => 'ads',
                'limitation_value' => 0,
                'limit' => null,
            ],
            [
                'id' => 9,
                'plan_id' => 2,
                'planlimitation_id' => 3,
                'limitation_slug' => 'device-limit',
                'limitation_value' => 1,
                'limit' => '4',
            ],
            [
                'id' => 10,
                'plan_id' => 2,
                'planlimitation_id' => 4,
                'limitation_slug' => 'download-status',
                'limitation_value' => 1,
                'limit' => '{"480p":1,"720p":1,"1080p":1,"1440p":1,"2K":1,"4K":1,"8K":0}',
            ],
            [
                'id' => 11,
                'plan_id' => 2,
                'planlimitation_id' => 5,
                'limitation_slug' => 'supported-device-type',
                'limitation_value' => 1,
                'limit' => '{"tablet":"1","laptop":"1","mobile":"1","tv":"1"}',
            ],
            [
                'id' => 12,
                'plan_id' => 2,
                'planlimitation_id' => 6,
                'limitation_slug' => 'profile-limit',
                'limitation_value' => 1,
                'limit' => 4,
            ],

            // Plan 3: Family VIP
            [
                'id' => 13,
                'plan_id' => 3,
                'planlimitation_id' => 1,
                'limitation_slug' => 'video-cast',
                'limitation_value' => 1,
                'limit' => null,
            ],
            [
                'id' => 14,
                'plan_id' => 3,
                'planlimitation_id' => 2,
                'limitation_slug' => 'ads',
                'limitation_value' => 0,
                'limit' => null,
            ],
            [
                'id' => 15,
                'plan_id' => 3,
                'planlimitation_id' => 3,
                'limitation_slug' => 'device-limit',
                'limitation_value' => 1,
                'limit' => '5',
            ],
            [
                'id' => 16,
                'plan_id' => 3,
                'planlimitation_id' => 4,
                'limitation_slug' => 'download-status',
                'limitation_value' => 1,
                'limit' => '{"480p":1,"720p":1,"1080p":1,"1440p":1,"2K":1,"4K":1,"8K":1}',
            ],
            [
                'id' => 17,
                'plan_id' => 3,
                'planlimitation_id' => 5,
                'limitation_slug' => 'supported-device-type',
                'limitation_value' => 1,
                'limit' => '{"tablet":"1","laptop":"1","mobile":"1","tv":"1"}',
            ],
            [
                'id' => 18,
                'plan_id' => 3,
                'planlimitation_id' => 6,
                'limitation_slug' => 'profile-limit',
                'limitation_value' => 1,
                'limit' => 5,
            ],
        ]);
    }
}
