<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\Playback\PlaybackDiagnosticRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class PlaybackDiagnosticsController extends Controller
{
    public function store(Request $request, PlaybackDiagnosticRecorder $diagnostics): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'uuid'],
            'error_code' => ['required', 'in:network_error,decoder_error,manifest_error,license_error,unknown_error'],
        ]);
        $token = $request->user()->currentAccessToken();
        abort_unless($token instanceof PersonalAccessToken, 401);
        $session = DB::table('playback_sessions')->where('session_id', $data['session_id'])
            ->where('user_id', $request->user()->id)->where('device_token_id', $token->getKey())
            ->whereNull('ended_at')->where('expires_at', '>', now())->first();
        abort_if(!$session, 404);
        $diagnostics->record($request->user(), 'playback.client_error.'.$data['error_code'], $session->content_type, $session->content_id, $session->session_id);
        return response()->json(['data' => ['recorded' => true]], 202);
    }
}
