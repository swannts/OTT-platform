<?php

namespace Modules\Entertainment\Services;

use Illuminate\Support\Facades\URL;

class PlaybackMediaUrlService
{
    public function sign(int $assetId, string $file, string $sessionId): string
    {
        return URL::temporarySignedRoute(
            'playback.media',
            now()->addSeconds(110),
            ['assetId' => $assetId, 'file' => ltrim($file, '/'), 'sessionId' => $sessionId]
        );
    }
}
