<?php

namespace App\Services\Auth;

use App\Http\Responses\ApiResponse;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Subscriptions\Services\PlanEntitlementSnapshot;

class DeviceLimitService
{
    public function check(User $user, ?string $deviceId = null, bool $isLoginFlow = true): ?JsonResponse
    {
        if ($isLoginFlow) {
            Device::where('user_id', $user->id)
                ->whereIn('platform', ['Windows', 'Linux', 'Mac', 'web'])
                ->where('updated_at', '<', now()->subDays(2))
                ->delete();

            if ($deviceId) {
                $existingDevice = Device::where('user_id', $user->id)
                    ->where('device_id', $deviceId)
                    ->first();

                if ($existingDevice) {
                    return null;
                }
            }
        }

        $count = Device::where('user_id', $user->id)->count();

        $devicesQuery = Device::where('user_id', $user->id);
        if (! $isLoginFlow && $deviceId) {
            $devicesQuery->where('device_id', '!=', $deviceId);
        }

        $otherDevices = $devicesQuery->get()->map(function ($device) use ($isLoginFlow): array {
            $formatted = [
                'id' => $device->id,
                'user_id' => $device->user_id,
                'device_id' => $device->device_id,
                'device_name' => $device->device_name,
                'active_profile' => $device->active_profile,
                'platform' => $device->platform,
                'created_at' => formatDateTimeWithTimezone($device->created_at),
                'updated_at' => formatDateTimeWithTimezone($device->updated_at),
                'deleted_at' => $device->deleted_at,
            ];

            if ($isLoginFlow) {
                $formatted['session_id'] = session()->getId();
                $formatted['last_activity'] = now();
            }

            return $formatted;
        });

        $subscription = $user->subscriptionPackage;
        $limit = PlanEntitlementSnapshot::integerLimit($subscription?->plan_type, 'device-limit')
            ?? max(1, (int) config('constant.DEVICE_LIMIT', 1));

        $shouldBlock = $count >= $limit && ($isLoginFlow || $otherDevices->isNotEmpty());
        if (! $shouldBlock) {
            return null;
        }

        Auth::logout();

        if ($isLoginFlow) {
            $deviceManagementToken = $user->createToken(
                'device-management',
                ['device-sessions:manage'],
                now()->addMinutes(5)
            )->plainTextToken;

            return response()->json([
                'error' => __('messages.device_limit_reached'),
                'other_device' => $otherDevices,
                'device_management_token' => $deviceManagementToken,
            ], 406);
        }

        return ApiResponse::error(__('messages.device_limit_reached'), 406, null, ['other_device' => $otherDevices]);
    }
}
