<?php

namespace Modules\Frontend\Trait;
use Jenssegers\Agent\Agent;
use App\Models\Device;
use App\Models\UserMultiProfile;
use Modules\Subscriptions\Services\PlanEntitlementSnapshot;
use DB;


trait LoginTrait
{

    public function CheckDeviceLimit($user, $current_device)
    {
        $count = Device::where('user_id', $user->id)->count();

        $deviceLimit = PlanEntitlementSnapshot::integerLimit($user->subscriptionPackage?->plan_type, 'device-limit')
            ?? max(1, (int) config('constant.DEVICE_LIMIT', 1));
        if ($count >= $deviceLimit) {
            return [
                'error' => __('messages.device_limit_reached'),
                'status' => 406,
            ];
        }

        return [
            'success' => 'Your device limit is available.',
            'status' => 200
        ];
    }


    public function CheckDevice($user)
    {
         if($user->subscriptionPackage==null){

            $agent = new Agent();

            if ($agent->isMobile()) {
                $deviceType = 'Mobile';
            } elseif ($agent->isTablet()) {
                $deviceType = 'Tablet';
            } elseif ($agent->isDesktop()) {
                $deviceType = 'Desktop';
            } else {
                $deviceType = 'Unknown';
            }

            $userAgent = $agent->getUserAgent();

            return response()->json(['status' => false, 'message' => __('messages.user_not_logged_in')]);


         }else{


         }

}

public function setDevice($user, $request)
{

    $agent = new Agent();
    $device_id = $request->getClientIp();
    $device_name =  $agent->browser();
    $platform = $agent->platform();

    $profile=UserMultiProfile::where('user_id',$user->id)->first();

    if (!empty($device_id) && !empty($device_name)) {
        $device = Device::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_id' => $device_id
            ],
            [
                'device_name' => $device_name,
                'platform' => $platform,
                'active_profile'=> $profile->id ?? null,
                'session_id' => session()->getId(),
                'last_activity' => now(),
            ]
        );

        return $device;
    }

    return null;
}



public function removeDevice($user, $request){

    $device_id = $request->getClientIp();

    Device::where('device_id', $device_id)->where('user_id', $user->id)->delete();

}

}
