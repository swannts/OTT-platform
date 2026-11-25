<?php

namespace App\Http\Controllers\Auth\API;

use App\Http\Controllers\Auth\Trait\AuthTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangeParentalLockRequest;
use App\Http\Requests\Auth\ChangePinRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Requests\Auth\VerifyPinRequest;
use App\Services\Auth\AccountManagementService;
use App\Services\Auth\DeviceLimitService;
use App\Services\Auth\ParentalControlService;
use App\Http\Resources\LoginResource;
use App\Http\Resources\RegisterResource;
use App\Http\Resources\SocialLoginResource;
use App\Models\User;
use Auth;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use App\Models\Device;
use Illuminate\Support\Facades\Mail;
use App\Mail\sendOtp;
use Illuminate\Support\Facades\RateLimiter;
use Jenssegers\Agent\Agent;
use Modules\Subscriptions\Models\Subscription;
use Modules\Subscriptions\Services\PlanEntitlementSnapshot;
use Modules\Entertainment\Models\ContinueWatch;
use Modules\Entertainment\Models\Watchlist;
use Modules\Entertainment\Models\EntertainmentDownload;
use Modules\Entertainment\Models\UserReminder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\TvLoginSession;
use Modules\NotificationTemplate\Jobs\SendBulkNotification;
use Modules\NotificationTemplate\Notifications\CommonNotification;
use Spatie\Image\Image;
use Exception;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    use AuthTrait;

    public function __construct(
        private readonly DeviceLimitService $deviceLimit,
        private readonly ParentalControlService $parentalControl,
        private readonly AccountManagementService $accountManagement,
    ) {}

    
    /**
     * Short Drama is an optional addon. Do not break root auth flows
     * when the addon module/classes are missing.
     */
    private function issueShortDramaSessionTokenIfAvailable(int $userId): ?string
    {
        $serviceClass = \Modules\ShortDrama\Services\DRMService::class;

        try {
            if (! class_exists($serviceClass)) {
                return null;
            }

            $service = app($serviceClass);
            if (! method_exists($service, 'issueSessionToken')) {
                return null;
            }

            return (string) $service->issueSessionToken($userId);
        } catch (\Throwable $e) {
            Log::warning('Short drama session token could not be issued.', ['exception' => $e::class]);

            return null;
        }
    }

    public function register(Request $request)
    {
        $user = $this->registerTrait($request);

        if ($user instanceof \Illuminate\Http\JsonResponse && $user->status() == 422) {
            $responseData = $user->original;
            // If already in correct format, return as is
            if (isset($responseData['status']) && isset($responseData['message'])) {
                return $user;
            }
            // Otherwise format it
            $message = $responseData['message'] ?? 'The email has already been taken.';
            return ApiResponse::error(
                is_array($message) ? collect($message)->flatten()->first() : $message,
                422
            );
        }

        $issuedToken = $user->createToken(setting('app_name'));
        $user->api_token = $issuedToken->plainTextToken;
        $profile = $user->userMultiProfile()->first();
        Device::create([
            'user_id' => $user->id,
            'sanctum_token_id' => $issuedToken->accessToken->getKey(),
            'device_id' => (string) \Illuminate\Support\Str::uuid(),
            'device_name' => $request->input('device_name', 'OTT device'),
            'platform' => $request->input('platform', 'unknown'),
            'active_profile' => $profile?->id,
            'session_id' => session()->getId(),
            'last_activity' => now(),
        ]);
        $success['name'] = $user->name;
        $userResource = new RegisterResource($user);

        return $this->sendResponse($userResource, __('messages.register_successfull'));
    }

    /**
     * Login api
     *
     * @return \Illuminate\Http\Response
     */
   public function login(LoginRequest $request){
    
    if (RateLimiter::tooManyAttempts($request->throttleKey(), 6)) {
        $seconds = RateLimiter::availableIn($request->throttleKey());
        return response()->json([
            'message' => 'Too many login attempts. Please try again in ' . $seconds . ' seconds.',
            'retry_after' => $seconds
        ], 429);
    }

    try {
        \DB::table('sessions')
        ->whereNull('user_id')
        ->where('last_activity', '<', now()->subMinutes(config('session.lifetime', 120))->timestamp)
        ->delete();
    } catch (\Exception $e) {
        Log::warning('Session cleanup failed during login.', ['exception' => $e::class]);
    }

    $user = User::with(['subscriptionPackage', 'userMultiProfile'])->where('email', request('email'))->first();
        if ($user == null) {
            RateLimiter::hit($request->throttleKey());
            Log::info('Login failed: user not found');
            return ApiResponse::error(__('messages.register_before_login'), 400);
        }

    // Check if demo login is disabled and user is trying to login as demo/super admin
    $demoLoginEnabled = setting('demo_login', 0);
    if ($demoLoginEnabled != 1) {
        // Block known demo/super admin seeded credentials
        if (request('email') === 'demo@swann.com') {
            RateLimiter::hit($request->throttleKey());
            return ApiResponse::error(__('messages.demo_login_disabled'), 403);
        }

        // Also check by user_type if user exists
        if ($user && $user->user_type === 'demo_admin') {
            RateLimiter::hit($request->throttleKey());
            return ApiResponse::error(__('messages.demo_login_disabled'), 403);
        }
    }

    $remember = $request->boolean('remember');
    if (Auth::attempt(['email' => request('email'), 'password' => request('password')], $remember)) {
        RateLimiter::clear($request->throttleKey());
        $user = Auth::user();
        Log::info('Customer API sign-in succeeded.');

        if ($request->has('is_ajax') && $request->is_ajax == 1) {
            $agent = new Agent();
            $device_name = $agent->browser();
            $platform = $agent->platform();
        } else {
            $device_name = $request->input('device_name', 'OTT device');
            $platform = $request->input('platform', 'unknown');
        }
        // Device identifiers supplied by clients (or inferred from IP addresses) are not
        // identity. A fresh Sanctum token ID is the server-issued device binding.
        $response = $this->checkDeviceLimit($user, null, true);
        if ($response) {
            return $response;
        }

    
        if ($user->is_banned == 1 || $user->status == 0) {
            return ApiResponse::error(__('messages.login_error'), 400);
        }

        // Save the user
        $user->save();
        $issuedToken = $user->createToken(setting('app_name'));
        $device_id = (string) \Illuminate\Support\Str::uuid();
        $user['api_token'] = $issuedToken->plainTextToken;
        $user['short_drama_session_token'] = $this->issueShortDramaSessionTokenIfAvailable((int) $user->id);

        if ($user->is_subscribe == 1) {
            $user['plan_details'] = $user->subscriptionPackage;
            if (isSmtpConfigured()) {
                // if ($user->subscriptionPackage->device_id != $request->device_id) {
                //     Mail::to($user->email)->send(new DeviceEmail($user));
                // }
            }
        }



        $profile = $user->userMultiProfile->first();
        

        Device::create([
            'user_id' => $user->id,
            'sanctum_token_id' => $issuedToken->accessToken->getKey(),
            'device_id' => $device_id,
            'device_name' => $device_name,
            'platform' => $platform,
            'active_profile' => $profile->id ?? null,
            'session_id' => session()->getId(),
            'last_activity' => now(),
        ]);

        $loginResource = new LoginResource($user);
        $message = __('messages.user_login');

        setCurrentProfileSession();

        if ($request->has('is_ajax') && $request->is_ajax == 1) {

            return $this->sendResponse($loginResource, $message);
        }

        return $this->sendResponse($loginResource, $message);
    } else {
        RateLimiter::hit($request->throttleKey());
        Log::info('Login failed: invalid credentials');
        return $this->sendError(__('messages.not_matched'), ['error' => __('messages.unauthorised')], 200);
    }
}


  public function socialLogin(Request $request)
{
    $input = $request->except('file_url');

    
    if ($input['login_type'] === 'otp') {
        $user_data = User::where('mobile', $input['mobile'])->first();
    } else {
        $user_data = User::where('email', $input['email'])->first();
    }


    if ($user_data != null) {

        $response = $this->checkDeviceLimit($user_data, null, true);
        if ($response) {
            return $response;
        }


        if (!isset($user_data->login_type) || $user_data->login_type == '') {
            if ($request->login_type === 'google') {
                $message = __('validation.unique', ['attribute' => 'email']);
            } 

            if(isset($message) && $message != ''){
                return $this->sendError($message,[], 400);
            }
        }
        $message = __('messages.login_success');
    } else {
        if ($request->login_type === 'google' || $request->login_type === 'apple') {
            $key = 'email';
            $value = $request->email;
        } else {
            $key = 'mobile';
            $value = $request->mobile;
        }

        $trashed_user_data = User::with('subscriptionPackage')->where($key, $value)->whereNotNull('login_type')->withTrashed()->first();

        if ($trashed_user_data != null && $trashed_user_data->trashed()) {
            if ($request->login_type === 'google') {
                $message = __('validation.unique', ['attribute' => 'email']);
            } else {
                $message = __('validation.unique', ['attribute' => 'username']);
            }

            return $this->sendError($message, [],400);
        }

        if ($request->login_type === 'otp' && $user_data == null) {
            $otp_response = [
                'status' => true,
                'is_user_exist' => false,
            ];

            return $this->sendError($otp_response,[],400);
        }

        if ($request->login_type === 'otp' && $user_data != null) {
            $otp_response = [
                'status' => true,
                'is_user_exist' => true,
            ];

            return $this->sendError($otp_response,[],400);
        }

        $password = !empty($input['password']) ? $input['password'] : $input['email'];

        $input['user_type'] = $request->user_type;
        $input['display_name'] = $input['first_name'] . ' ' . $input['last_name'];
        $input['password'] = Hash::make($password);
        $input['user_type'] = isset($input['user_type']) ? $input['user_type'] : 'user';

        $user = User::create($input);

        $user->assignRole($user->user_type);
        $user->save();
        $user->createOrUpdateProfileWithAvatar();
        // if(!empty($input['file_url'])){
        //     $input['file_url'] = $input['file_url'];
        // $user->update(['file_url' => $input['file_url']]);

        // }
        $user_data = User::where('id', $user->id)->first();

        $message = trans('messages.save_form', ['form' => $input['user_type']]);
    }

    $issuedToken = $user_data->createToken(setting('app_name'));
    $user_data['api_token'] = $issuedToken->plainTextToken;
    Device::create([
        'user_id' => $user_data->id,
        'sanctum_token_id' => $issuedToken->accessToken->getKey(),
        'device_id' => (string) \Illuminate\Support\Str::uuid(),
        'device_name' => $request->input('device_name', 'OTT device'),
        'platform' => $request->input('platform', 'unknown'),
        'active_profile' => $user_data->userMultiProfile()->value('id'),
        'session_id' => session()->getId(),
        'last_activity' => now(),
    ]);
    $user_data['short_drama_session_token'] = $this->issueShortDramaSessionTokenIfAvailable((int) $user_data->id);

    if ($user_data->is_subscribe == 1) {
        $user_data['plan_details'] = $user_data->subscriptionPackage;
    }

    $socialLogin = new SocialLoginResource($user_data);

    return $this->sendResponse($socialLogin, $message);
}

protected function checkDeviceLimit(User $user, ?string $deviceId = null, bool $isLoginFlow = true): ?JsonResponse
{
    return $this->deviceLimit->check($user, $deviceId, $isLoginFlow);
}

    /**
     * Get fresh CSRF token for AJAX requests
     * This endpoint works for stateful requests (same-origin) where sessions are available
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getCsrfToken(Request $request)
    {
        try {
            if ($request->hasSession()) {
                return response()->json([
                    'csrf_token' => csrf_token()
                ]);
            } else {
                try {
                    $request->session()->start();
                    return response()->json([
                        'csrf_token' => csrf_token()
                    ]);
                } catch (\Exception $e) {
                    // Session not available (e.g., from mobile app)
                    // Mobile apps don't need CSRF tokens, they use Sanctum tokens
                    return response()->json([
                        'csrf_token' => null,
                        'message' => 'CSRF token not available for this request type'
                    ], 200);
                }
            }
        } catch (\Exception $e) {
            return response()->json([
                'csrf_token' => null,
                'message' => 'Could not generate CSRF token'
            ], 200);
        }
    }

    public function logout(Request $request)
    {
        // Check if the user is authenticated
        if (!Auth::guard('sanctum')->check()) {
            return ApiResponse::error(__('messages.user_not_logged_in'), 400);
        }

        $user = Auth::guard('sanctum')->user();
        $currentToken = $user->currentAccessToken();
        $currentDeviceId = $currentToken && method_exists($currentToken, 'getKey')
            ? $currentToken->getKey()
            : null;
        $tvsession = TvLoginSession::where('user_id', $user->id)->first();
        if($tvsession){
            $tvsession->delete();
        }

        if ($currentDeviceId) {
            \DB::table('playback_sessions')->where('user_id', $user->id)
                ->where('device_token_id', $currentDeviceId)->whereNull('ended_at')
                ->update(['ended_at' => now(), 'updated_at' => now()]);
            Device::where('user_id', $user->id)->where('sanctum_token_id', $currentDeviceId)->delete();
        }
        if ($currentToken && method_exists($currentToken, 'delete')) {
            $currentToken->delete();
        }

        if ($request->is('api*')) {
            return ApiResponse::success(null, __('messages.user_logout'), 200);
        }
    }


    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();
   
        if (!$user) {
            return ApiResponse::error(
                __('messages.this_email_is_not_registered_please_check_your_email_address'),
                404
            );
        }
        
        if ($user->login_type == 'otp' || $user->login_type =='google') {
           
            return ApiResponse::error(
                __('messages.user_does_not_have_permission_to_change_password'),
                404
            );
        }


        // try {
            $response = Password::sendResetLink($request->only('email'));

            return $response === Password::RESET_LINK_SENT
                ? ApiResponse::success(null, __($response), 200)
                : ApiResponse::error(__($response), 400);
        // } catch (Exception $e) {

        //     return response()->json([
        //         'message' => 'There was an issue sending the email. Please check your SMTP configuration.',
        //         'status' => false
        //     ], 500);
        // }
    }



    // public function forgotPassword(Request $request)
    // {
    //     $request->validate([
    //         'email' => 'required|email|exists:users,email',
    //     ]);

    //     try {
    //         $response = Password::sendResetLink($request->only('email'));

    //         if ($response === Password::RESET_LINK_SENT) {
    //             return response()->json(['message' => 'Password reset link sent successfully.', 'status' => true], 200);
    //         }

    //         return response()->json(['message' => 'Failed to send password reset link.', 'status' => false], 400);
    //     } catch (Exception $e) {
    //         Log::error('SMTP Error: ' . $e->getMessage());

    //         return response()->json([
    //             'message' => 'There was an issue sending the email. Please check your SMTP configuration.',
    //             'status' => false
    //         ], 500);
    //     }
    // }

    public function changePassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required',
            'new_password' => 'required|min:8',
        ]);

        $authenticatedUser = \Auth::user();
        if (! $authenticatedUser) {
            return ApiResponse::error(__('messages.user_notfound'), 400);
        }

        $result = $this->accountManagement->changePassword($authenticatedUser, $request);
        if (! $result['ok']) {
            return ApiResponse::error($result['message'], $result['status']);
        }

        return ApiResponse::success($result['data'], $result['message'], 200);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        if ($user == null) {
            return ApiResponse::error(__('messages.no_record'), 400);
        }

        try {
            $request->validate([
                'email' => 'required|email|unique:users,email,'.$user->id,
                'mobile' => 'required|unique:users,mobile,'.$user->id,
                'date_of_birth' => 'required|date|before_or_equal:today',
            ]);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $firstError = collect($errors)->flatten()->first();

            return ApiResponse::error(
                $firstError ?? __('messages.validation_error'),
                422,
                $errors
            );
        }

        $data = $this->accountManagement->updateProfile($user, $request);

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => __('messages.profile_update'),
        ], 200);
    }

    public function userDetails(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return ApiResponse::error(__('messages.user_not_logged_in'), 401);
        }
        $user['about_self'] = $user->profile->about_self ?? null;
        $user['expert'] = $user->profile->expert ?? null;
        $user['facebook_link'] = $user->profile->facebook_link ?? null;
        $user['instagram_link'] = $user->profile->instagram_link ?? null;
        $user['twitter_link'] = $user->profile->twitter_link ?? null;
        $user['dribbble_link'] = $user->profile->dribbble_link ?? null;

        return ApiResponse::success($user, __('messages.user_details_successfull'), 200);
    }

    public function deleteAccount(Request $request)
    {
        if (! $this->accountManagement->deleteAccount((int) \Auth::user()->id)) {
            return ApiResponse::error(__('messages.user_not_found'), 200);
        }

        return ApiResponse::success(null, __('messages.delete_account'), 200);
    }


    // public function changePin(Request $request)
    // {
    //     (isset($request->pin) && is_array($request->pin)) &&
    //     $request->merge([
    //         'pin' => isset($request->pin) ? implode("",$request->pin): NULL,
    //         'confirm_pin' => isset($request->confirm_pin) ? implode("",$request->confirm_pin) : NULL,
    //     ]);

    //     $request->validate([
    //         'pin' => 'required|min:4|max:4',
    //         'confirm_pin' => 'required_with:pin|same:pin|min:4|max:4'
    //     ]);

    //     $userId = isset($request->user_id) ? $request->user_id : auth()->user()->id;

    //     if(empty($userId)){
    //         return response()->json(['status' => false, 'message' => __('frontend.something_went_wrong')]);
    //     }
    //     $user = User::find($userId);

    //     if (!empty($user->pin) && $user->pin === $request->pin) {
    //         return response()->json(['status' => false, 'message' => __('frontend.new_pin_must_be_different')]);
    //     }

    //     $message = (!empty($user->otp)) ? __('messages.change_pin_successfull') : __('messages.set_pin_successfull');

    //     $user->update([
    //         'pin' => $request->pin,
    //     ]);

    //     return response()->json([
    //         'status' => true,
    //         'message' => $message,
    //     ], 200);
    // }
    public function changePin(ChangePinRequest $request)
    {
        $user = User::find($request->user()->id);
        if (! $user) {
            return ApiResponse::error(__('frontend.something_went_wrong'), 400);
        }

        $result = $this->parentalControl->changePin($user, (string) $request->pin);
        if (! $result['ok']) {
            return ApiResponse::error($result['message'], $result['status']);
        }

        return ApiResponse::success(null, $result['message'], 200, $result['extra']);
    }

    /**
     * otp resend/send on email
     * @param $request
     * @return \Illuminate\Http\Response
     */
    public function sendOtp(Request $request)
    {
        $user = User::find($request->user()->id);
        if (! $user) {
            return ApiResponse::error('Something went wrong please try again.!', 422);
        }

        $this->parentalControl->sendOtp($user);

        return ApiResponse::success(null, 'OTP sent successfully', 200);
    }

    /**
     * verify email otp
     * @param $request
     * @return \Illuminate\Http\Response
     */
    public function verifyOtp(VerifyOtpRequest $request)
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::error(__('messages.something_went_wrong'), 422);
        }

        if ($this->parentalControl->verifyOtp($user, (string) $request->otp)) {
            return ApiResponse::success(null, __('messages.otp_verified_successfully'), 200);
        }

        return ApiResponse::error(__('messages.invalid_otp'), 200);
    }

    /**
     * verify pin
     * @param $request
     * @return \Illuminate\Http\Response
     */
    public function verifyPin(VerifyPinRequest $request)
    {
        $user = User::find($request->user()->id);
        if (! $user) {
            return ApiResponse::error('Something went wrong please try again.!', 422);
        }

        if ($this->parentalControl->verifyPin($user, (string) $request->pin)) {
            return ApiResponse::success(null, 'Pin verified successfully', 200);
        }

        return ApiResponse::error('Invalid Pin', 400);
    }

    /**
     * change parental pin flag
     * @param $request
     * @return \Illuminate\Http\Response
     */
    public function changeParentalLock(ChangeParentalLockRequest $request)
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::error('Something went wrong please try again.!', 422);
        }

        if ($this->parentalControl->setParentalLock($user, (int) $request->is_parental_lock_enable)) {
            $message = (int) $request->is_parental_lock_enable === 1
                ? __('messages.parental_lock_active_successfully')
                : __('messages.parental_lock_inactive_successfully');

            return ApiResponse::success(null, $message, 200);
        }

        return ApiResponse::error('Something went wrong please try again.!', 422);
    }
}
