<?php

namespace Modules\User\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class DeviceSessionController extends Controller
{
    public function revoke(Request $request): JsonResponse
    {
        $data = $request->validate(['device_id' => ['required', 'integer', 'min:1']]);
        $user = $request->user();
        $currentToken = $user?->currentAccessToken();
        abort_unless($currentToken instanceof PersonalAccessToken, 401);

        DB::transaction(function () use ($data, $user, $currentToken): void {
            $device = Device::query()->where('user_id', $user->getKey())
                ->whereKey($data['device_id'])->lockForUpdate()->firstOrFail();
            abort_if((int) $device->sanctum_token_id === (int) $currentToken->getKey(), 422, 'This temporary device-management session cannot revoke itself.');

            $tokenId = $device->sanctum_token_id;
            if ($tokenId) {
                DB::table('playback_sessions')->where('user_id', $user->getKey())
                    ->where('device_token_id', $tokenId)->whereNull('ended_at')
                    ->update(['ended_at' => now(), 'updated_at' => now()]);
                PersonalAccessToken::query()->where('tokenable_id', $user->getKey())->whereKey($tokenId)->delete();
            }
            $device->delete();
            // This narrowly scoped login challenge is single-use, even when the
            // revoked device was not the challenge's own device.
            $currentToken->delete();
        });

        return ApiResponse::success(null, 'Device session revoked.');
    }

    public function revokeAll(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        DB::transaction(function () use ($user): void {
            $tokenIds = Device::query()->where('user_id', $user->getKey())->pluck('sanctum_token_id')->filter()->values();
            if ($tokenIds->isNotEmpty()) {
                DB::table('playback_sessions')->where('user_id', $user->getKey())
                    ->whereIn('device_token_id', $tokenIds)->whereNull('ended_at')
                    ->update(['ended_at' => now(), 'updated_at' => now()]);
            }
            DB::table('personal_access_tokens')->where('tokenable_type', $user->getMorphClass())
                ->where('tokenable_id', $user->getKey())->delete();
            Device::query()->where('user_id', $user->getKey())->delete();
        });

        return ApiResponse::success(null, 'All device sessions revoked. Sign in again to continue.');
    }
}
