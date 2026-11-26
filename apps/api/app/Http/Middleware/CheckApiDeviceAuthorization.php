<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Device;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Log;

class CheckApiDeviceAuthorization
{
    /**
     * Handle an incoming request.
     * Check if user's device exists - if logoutAll was called, device should not exist
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        
        // Check if user is authenticated via Sanctum (Bearer token)
        if ($request->user()) {

            $user = $request->user();
            
            // Skip check for admin users
            if ($user && ($user->hasRole(['admin', 'super-admin', 'demo_admin']))) {
                return $next($request);
            }
            
            $token = $request->user()->currentAccessToken();
            // Bind device authorization to the server-issued Sanctum token row. Never
            // accept a client device_id or token name as an authorization identity.
            if (!$token instanceof PersonalAccessToken) {
                return $next($request);
            }
            $deviceId = (string) $token->getKey();
            $deviceExists = Device::where('user_id', $user->id)
                ->where('sanctum_token_id', $deviceId)
                ->exists();
           
            // If device doesn't exist, it means logoutAll was called OR device was deleted
            // For authenticated users with valid tokens, the device MUST exist
            // Return 401 to force mobile app to logout
            if (!$deviceExists) {
                \Log::info("Device token not found ");
               
                // Revoke token and return 401
                // Only delete PersonalAccessToken (not TransientToken)
                if ($token && $token instanceof \Laravel\Sanctum\PersonalAccessToken) {
                    $token->delete();
                }
                return response()->json([
                    'status' => false,
                    'message' => __('messages.logout_all_text'),
                    'code' => 401
                ], 401);
            }
        }
    
        return $next($request);
    }
}
