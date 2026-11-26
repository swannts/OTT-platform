<?php

namespace Modules\Entertainment\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Support\Playback\PlaybackDiagnosticRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Entertainment\Http\Requests\API\PlaybackDetailsRequest;
use Modules\Entertainment\Models\Entertainment;
use Modules\Entertainment\Services\PlaybackAuthorizationAction;
use Modules\Entertainment\Services\PlaybackEntitlementService;
use Modules\Entertainment\Services\PlaybackMediaUrlService;
use Modules\Entertainment\Services\PlaybackSessionService;
use Modules\Episode\Models\Episode;
use Modules\Video\Models\Video;

class PlaybackSessionController extends Controller
{
    public function playbackDetails(PlaybackDetailsRequest $request, PlaybackAuthorizationAction $action)
    {
        $result = $action->execute($request, $request->validated());
        if (! $result['ok']) {
            return ApiResponse::error($result['message'], $result['status']);
        }

        return ApiResponse::success($result['data'], $result['message'], 200);
    }

    public function heartbeatPlayback(Request $request, string $sessionId, PlaybackSessionService $sessions, PlaybackMediaUrlService $mediaUrls, PlaybackEntitlementService $entitlements)
    {
        $token = $request->user()->currentAccessToken();
        if (!$token instanceof PersonalAccessToken) {
            return ApiResponse::error('A registered playback session is required.', 401);
        }
        $session = DB::table('playback_sessions')
            ->where('session_id', $sessionId)
            ->where('user_id', $request->user()->id)
            ->where('device_token_id', $token->getKey())
            ->whereNull('ended_at')
            ->where('expires_at', '>', now())
            ->first();
        if (!$session) {
            return ApiResponse::error('Playback session has expired or was revoked.', 403);
        }
        try {
            $entitlements->authorize(
                $request->user(),
                (string) $session->content_id,
                (string) $session->content_type,
                (int) $session->profile_id,
                $request
            );
            $expiresAt = $sessions->heartbeat($request->user(), $token, $sessionId);
        } catch (\Illuminate\Auth\Access\AuthorizationException $exception) {
            return ApiResponse::error($exception->getMessage(), 403);
        }
        $response = ['expires_at' => $expiresAt];
        $model = match ($session->content_type) {
            'movie', 'tvshow' => Entertainment::class,
            'episode' => Episode::class,
            'video' => Video::class,
            default => null,
        };
        $assetId = $model ? $model::query()->whereKey($session->content_id)->value('media_asset_id') : null;
        if ($assetId) {
            $response['media_url'] = $mediaUrls->sign((int) $assetId, 'master.m3u8', $sessionId);
        }
        return ApiResponse::success($response, 'Playback session refreshed.', 200);
    }

    public function endPlayback(Request $request, string $sessionId, PlaybackSessionService $sessions, PlaybackDiagnosticRecorder $diagnostics)
    {
        $token = $request->user()->currentAccessToken();
        if (!$token instanceof PersonalAccessToken) {
            return ApiResponse::error('A registered playback session is required.', 401);
        }
        try {
            DB::transaction(function () use ($request, $token, $sessionId, $sessions, $diagnostics) {
                $ended = $sessions->end($request->user(), $token, $sessionId);
                if ($ended !== null) {
                    $diagnostics->record($request->user(), 'playback.session_ended', $ended['content_type'], $ended['content_id'], $sessionId);
                }

                return $ended;
            }, 3);
        } catch (\Illuminate\Auth\Access\AuthorizationException $exception) {
            return ApiResponse::error($exception->getMessage(), 403);
        }
        return ApiResponse::success(null, 'Playback session ended.', 200);
    }
}
