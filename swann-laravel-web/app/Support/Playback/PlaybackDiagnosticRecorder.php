<?php

namespace App\Support\Playback;

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

final class PlaybackDiagnosticRecorder
{
    public function record(User $user, string $eventCode, ?string $contentType = null, ?string $contentId = null, ?string $sessionId = null, ?int $durationMs = null, ?string $quality = null): void
    {
        $token = $user->currentAccessToken();
        $deviceId = $token instanceof PersonalAccessToken
            ? Device::query()->where('user_id', $user->id)->where('sanctum_token_id', $token->getKey())->value('id')
            : null;
        DB::table('playback_diagnostic_events')->insert([
            'user_id' => $user->id, 'device_id' => $deviceId, 'session_id' => $sessionId,
            'event_code' => $eventCode, 'content_type' => $contentType, 'content_id' => $contentId,
            'duration_ms' => $durationMs, 'quality' => $quality,
            'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
