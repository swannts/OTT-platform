<?php

namespace Modules\User\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\UserMultiProfile;
use App\Models\Device;
use Modules\User\Transformers\UserMultiProfileResource;
use Illuminate\Http\Request;
use Jenssegers\Agent\Agent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserMultiProfileController extends Controller
{
    public function profileList(Request $request)
    {
        $perPage = min(max((int) $request->input('per_page', 10), 1), 50);
        $profiles = UserMultiProfile::query()
            ->where('user_id', $request->user()->id)
            ->paginate($perPage);

        $responseData = UserMultiProfileResource::collection($profiles);

        return ApiResponse::success($responseData, __('movie.profile_list'), 200);
    }

    public function saveProfile(Request $request)
    {
        $user = auth()->user();
        $data = $request->validate([
            'id' => ['sometimes', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:12'],
            'is_child_profile' => ['sometimes', 'boolean'],
            'avatar' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'file_url' => ['sometimes', 'file', 'image', 'max:5120'],
        ]);

        if (isset($data['name']) && strlen(trim($data['name'])) > 12) {
            return ApiResponse::error(__('messages.name_max_12_characters'), 422);
        }

        $existingProfile = null;
        if (!empty($request->id)) {
            $existingProfile = UserMultiProfile::where('user_id', $user->id)
                                               ->where('id', $request->id)
                                               ->first();
        }

        $isUpdate = $existingProfile !== null;

        if ($isUpdate) {
            $profilesCheck = UserMultiProfile::where('user_id', $user->id);

            $isChild = isset($data['is_child_profile']) ? (int)$data['is_child_profile'] : 0;

            if ($profilesCheck->count('id') <= 1 && $isChild === 1) {
                return ApiResponse::error(__('messages.if_you_only_have_one_parent_profile_you_can_t_convert_it_to_a_child_profile'), 406);
            }

            $proCheck = UserMultiProfile::where([
                'is_child_profile' => 0,
                'user_id' => $user->id
            ])->where('id','!=',$request->id)->count('id');

            if ($proCheck < 1 && $isChild === 1) {
                return ApiResponse::error(__('messages.atleast_one_parent_profile_is_required'), 406);
            }
        }

        $avatar = $data['avatar'] ?? asset('storage/avatars/image/icon2.png');
        
        if (isset($data['avatar']) && filter_var($data['avatar'], FILTER_VALIDATE_URL)) {
            $parsedUrl = parse_url($data['avatar']);
            $appUrl = parse_url(config('app.url'));
            
            if (!isset($parsedUrl['host']) || $parsedUrl['host'] !== $appUrl['host']) {
                return ApiResponse::error(__('messages.external_urls_not_allowed') ?? 'External image URLs are not allowed. Please upload an image or select from available avatars.', 422);
            }
        }

        $profile_data = [
            'user_id' => $user->id,
            'name'    => $data['name'],
            'avatar'  => $avatar,
            'is_child_profile' => isset($data['is_child_profile']) ? $data['is_child_profile'] : 0,
        ];

        $profile_count = UserMultiProfile::where('user_id', $user->id)->count();

        if (!$isUpdate) {
            $max_profiles = $user->is_subscribe ? $this->getSubscriptionProfileLimit($user) : 1;

            if ($profile_count >= $max_profiles) {
                return ApiResponse::error(__('messages.profile_limit_reached_for_plan'), 406);
            }
        }

        if ($isUpdate) {
            $existingProfile->update($profile_data);
            $user_profile = $existingProfile->fresh();
        } else {
            $user_profile = UserMultiProfile::create($profile_data);
        }

        // Avatar upload
        if ($request->hasFile('file_url')) {
            $file = $request->file('file_url');
            $filename = $file->getClientOriginalName();
            $filePath = $file->storeAs('avatars', $filename, 'public');
            $avatar = setavatarBaseUrl('/storage/' . $filePath);
            $user_profile->update(['avatar' => $avatar]);
        }

        $profiles = UserMultiProfile::where('user_id', $user->id)->get();
        $responseData = UserMultiProfileResource::collection($profiles);
        $message = $isUpdate ? __('messages.profile_update') : __('messages.profile_add');

        cache::flush(['profile_list'], 'user');

        return ApiResponse::success($responseData, $message, 200, ['user_profile' => $user_profile]);
    }


private function getSubscriptionProfileLimit($user) 
{
    if (!$user->subscriptionPackage) {
        return 0;
    }

    $subscription = $user->subscriptionPackage;

    if (isset($subscription->plan_type) && !empty($subscription->plan_type)) {
        $planLimitations = json_decode($subscription->plan_type, true);

        if (is_array($planLimitations)) {
            foreach ($planLimitations as $limitation) {
                if (isset($limitation['slug']) && $limitation['slug'] === 'profile-limit') {
                    $limitData = $limitation['limit'] ?? null;
                    
                    if (is_array($limitData) && isset($limitData['value'])) {
                        return (int)$limitData['value'];
                    } elseif (is_string($limitData) || is_numeric($limitData)) {
                        return (int)$limitData;
                    }
                    
                    return 0;   
                }
            }
        }
    }

    return 0;
}

    public function getprofile(Request $request, int $id)    
    {
        
        $profile = UserMultiProfile::where('user_id', $request->user()->id)->where('id', $id)->first();

        if (!$profile) {
            return ApiResponse::error(__('messages.profile_not_found') ?? 'Profile not found.', 404);
        }

        $responseData = New UserMultiProfileResource($profile);

        return ApiResponse::success($responseData, __('messages.profile_update'), 200);
    }

    public function SelectProfile(Request $request, int $id)    
{
    // The authenticated account is the sole authority for profile ownership.
    $user = $request->user();
    $user_id = $user->id;
    $request->validate(['pin' => ['sometimes', 'array', 'max:8'], 'pin.*' => ['digits:1']]);

    $currentProfile = getCurrentProfileSession();
    $is_child_profile = $currentProfile->is_child_profile ?? 0;

    // Look up the target profile to check if it's a parent profile
    $targetProfile = UserMultiProfile::where('user_id', $user_id)->where('id', $id)->first();
    $isTargetParent = $targetProfile && $targetProfile->is_child_profile == 0;

    // PIN is only required when switching FROM a child profile TO a parent profile
    // (matches the frontend pin-verification.js isPinRequired logic)
    if (!$targetProfile) {
        return ApiResponse::error(__('messages.profile_not_found') ?? 'Profile not found.', 404);
    }

    if ($isTargetParent && $user->is_parental_lock_enable == 1) {
        $pin = $request->pin ?? null;
        if (!$pin) {
            return ApiResponse::error(__('messages.parental_lock'), 406);
        }
        $pinString = implode('', $pin);
        $storedPin = (string) ($user->pin_hash ?: $user->pin);
        $pinMatches = str_starts_with($storedPin, '$2y$') || str_starts_with($storedPin, '$argon2')
            ? Hash::check($pinString, $storedPin)
            : hash_equals($storedPin, $pinString);
        if (!$pinMatches) {
            return ApiResponse::error(__('messages.lbl_invalid_pin'), 406);
        }

        if (!$user->pin_hash) {
            $user->forceFill(['pin_hash' => Hash::make($pinString), 'pin' => null])->save();
        }

    }
    $token = $request->user()?->currentAccessToken();
    if (!$token instanceof \Laravel\Sanctum\PersonalAccessToken) {
        return ApiResponse::error('A registered device session is required.', 401);
    }
    $device = Device::where('user_id', $user_id)
                    ->where('sanctum_token_id', $token->getKey())
                    ->orderBy('id','DESC')
                    ->first();

    if ($device) {

        Device::where('user_id', $user_id)
                ->where('sanctum_token_id', $token->getKey())
                ->where('id','!=',$device->id)
                ->delete();

        $device->update(['active_profile' => $id]);
    } else {
        return ApiResponse::error('Register this device before selecting a profile.', 409);

    }

    $profiles = UserMultiProfile::where('user_id', $user_id)->get();

    $responseData = UserMultiProfileResource::collection($profiles);

    setCurrentProfileSession(1,$id);

    return ApiResponse::success($responseData, __('movie.profile_selected'), 200);
}



    public function deleteProfile(Request $request)
    {
        $user = auth()->user();

        $profile = UserMultiProfile::where('user_id', $user->id)->where('id', $request->profile_id)->first();

        if ($profile == null) {

            $message = __('movie.profile');

            return ApiResponse::error($message, 404);
        }
  
        if ($profile->is_child_profile == 0) {
            $remainingParentProfiles = UserMultiProfile::where('user_id', $user->id)
                ->where('is_child_profile', 0)
                ->where('id', '!=', $profile->id)
                ->count();
            
            if ($remainingParentProfiles < 1) {
                return ApiResponse::error(__('messages.atleast_one_parent_profile_is_required'), 406);
            }
        }

        $profile->delete();
        $message = __('movie.profile_delete');

        clearRelatedCache(['profile_list'], 'user');
        return ApiResponse::success(null, $message, 200);
    }

}
