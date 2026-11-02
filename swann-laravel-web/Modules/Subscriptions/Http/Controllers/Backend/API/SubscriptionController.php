<?php

namespace Modules\Subscriptions\Http\Controllers\Backend\API;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Modules\Subscriptions\Http\Requests\SubscriptionRequest;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Subscription;
use Modules\Subscriptions\Models\SubscriptionTransactions;
use Modules\Subscriptions\Trait\SubscriptionTrait;
use Modules\Subscriptions\Transformers\SubscriptionResource;
use Modules\Subscriptions\Transformers\PlanlimitationMappingResource;
use Modules\Tax\Models\Tax;
use Modules\Coupon\Models\Coupon;
use App\Models\UserMultiProfile;
use Illuminate\Support\Facades\Cache;


class SubscriptionController extends Controller
{
    use SubscriptionTrait;

    /**
     * Display a listing of the resource.
     *
     * @return Renderable
     */
    public function saveSubscriptionDetails(SubscriptionRequest $request)
    {
        return ApiResponse::error('Direct client payment recording is no longer supported.', 410);
    }

    public function getUserSubscriptionHistroy()
    {
        $user_id = auth()->id();
        $subscription = Subscription::where('user_id', $user_id)->orderBy('created_at', 'desc')->get();

        $subscription = $subscription->map(function ($item) {
            $item->start_date = !empty($item->start_date) ? formatDate($item->start_date) : null;
            $item->end_date   = !empty($item->end_date)   ? formatDate($item->end_date)   : null;
            return $item;
        });

        return $this->sendResponse(
            SubscriptionResource::collection($subscription),
            __('messages.user_subscribe_history')
        );
    }

    public function cancelSubscription(Request $request)
    {
        $user_id = $request->user_id ? $request->user_id : auth()->id();
        $subscription_plan_id = $request->id;

        $user_subscription = Subscription::where('id', $subscription_plan_id)->where('user_id', $user_id)->first();

        $user = User::where('id', $user_id)->first();

        if ($user_subscription) {
            $user_subscription->status = config('constant.SUBSCRIPTION_STATUS.CANCLE');
            $user_subscription->save();
            $user->is_subscribe = 0;
            $user->save();
        }else{
            $message = __('messages.not_subscribe_cancel');

            return ApiResponse::error($message, 404);
        }
        $response = new SubscriptionResource($user_subscription);
        $this->sendNotificationOnsubscription('cancle_subscription', $response);
        $message = __('messages.subscribe_cancel');

        return $this->sendResponse($subscription_plan_id, $message);
    }

    /**
     * Clear profile_details_v3 cache for specific user profile
     * 
     * @param int $user_id
     * @param int|null $current_profile_id
     * @param string $current_device_type
     * @return void
     */
    private function clearProfileDetailsV3Cache($user_id, $current_device_type)
    {
        // Clear cache only for the specific profile_id and device_type combination
        $cacheKey = 'profile_details_v3_' . md5(json_encode([
            'user_id' => $user_id,
            // 'device_type' => $current_device_type
        ]));          
        Cache::forget($cacheKey);
    }
}
