<?php

namespace Modules\Entertainment\Services;

use App\Services\Media\MediaReadinessService;
use App\Services\Playback\PlaybackDiagnosticRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class PlaybackAuthorizationAction
{
    public function __construct(
        private readonly PlaybackEntitlementService $entitlements,
        private readonly PlaybackSessionService $sessions,
        private readonly PlaybackMediaUrlService $mediaUrls,
        private readonly MediaReadinessService $mediaReadiness,
        private readonly PlaybackDiagnosticRecorder $diagnostics,
        private readonly ContentMediaMetadataService $mediaMetadata,
    ) {}

    /**
     * @param  array{id: string, type: string, profile_id: int|string}  $validated
     * @return array{ok: true, data: array<string, mixed>, message: string}|array{ok: false, message: string, status: int}
     */
    public function execute(Request $request, array $validated): array
    {
        $user = $request->user();
        $type = $validated['type'];
        $profileId = (int) $validated['profile_id'];

        try {
            $result = $this->entitlements->authorize($user, $validated['id'], $type, $profileId, $request);
        } catch (AuthorizationException $exception) {
            $this->diagnostics->record($user, 'playback.authorization_denied', $type, $validated['id']);
            Log::notice('Customer playback authorization denied.', [
                'user_id' => $user->id,
                'content_id' => $validated['id'],
                'content_type' => $type,
                'profile_id' => $profileId,
                'reason' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'message' => $exception->getMessage(), 'status' => 403];
        }

        $content = $result['content'];
        $assetState = $this->mediaMetadata->resolveReadyMediaAsset($content, $this->mediaReadiness);
        if ($content->media_asset_id && ! $assetState['ready']) {
            $this->diagnostics->record($user, 'playback.media_not_ready', $type, (string) $content->id);

            return ['ok' => false, 'message' => 'The linked media asset is not ready for protected playback.', 'status' => 503];
        }
        if (! $content->media_asset_id && ! in_array(strtolower((string) ($content->movie_access ?? $content->access ?? 'free')), ['', 'free'], true)) {
            return ['ok' => false, 'message' => 'Protected playback requires a private, signed media asset.', 'status' => 503];
        }

        $mediaAsset = $assetState['asset'];
        $qualityRows = $this->mediaMetadata->streamMappingsFor($type, $content->id);
        $qualities = $this->mediaMetadata->formatPlaybackQualities($content, $type, $qualityRows);

        if (! $mediaAsset && empty($qualities[0]['url'])) {
            return ['ok' => false, 'message' => 'Playback is not configured for this title.', 'status' => 404];
        }

        $token = $user->currentAccessToken();
        if (! $token instanceof PersonalAccessToken) {
            return ['ok' => false, 'message' => 'A registered playback session is required.', 'status' => 401];
        }

        try {
            $session = $this->sessions->start(
                $user,
                $token,
                (int) $result['profile']->id,
                (string) $content->id,
                $type
            );
        } catch (AuthorizationException $exception) {
            return ['ok' => false, 'message' => $exception->getMessage(), 'status' => 403];
        }

        $this->diagnostics->record($user, 'playback.session_started', $type, (string) $content->id, $session['session_id']);

        if ($mediaAsset) {
            $manifestUrl = $this->mediaUrls->sign((int) $mediaAsset->id, 'master.m3u8', $session['session_id']);
            $qualities = [[
                'quality' => 'Auto',
                'url_type' => 'HLS',
                'url' => $manifestUrl,
            ]];
            $session['media_url'] = $manifestUrl;
        }

        Log::info('Customer playback session started.', [
            'user_id' => $user->id,
            'playback_session_id' => $session['session_id'],
            'content_id' => (string) $content->id,
            'content_type' => $type,
            'profile_id' => (int) $result['profile']->id,
        ]);

        return [
            'ok' => true,
            'data' => [
                'content_id' => $content->id,
                'content_type' => $type,
                'profile_id' => $result['profile']->id,
                'playback_session' => $session,
                'video_qualities' => $qualities,
            ],
            'message' => 'Playback authorized.',
        ];
    }
}
