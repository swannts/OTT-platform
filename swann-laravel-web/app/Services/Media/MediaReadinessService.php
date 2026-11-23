<?php

namespace App\Services\Media;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;

class MediaReadinessService
{
    /** @return array{ready: bool, checks: array<string, bool>} */
    public function inspect(MediaAsset $asset): array
    {
        $disk = $asset->disk === 'private' ? Storage::disk('private') : null;
        $base = trim((string) $asset->rendition_path, '/');
        $metadata = $asset->metadata ?? [];
        $streams = is_array($metadata['Streams'] ?? null) ? $metadata['Streams'] : [];
        $hasVideo = collect($streams)->contains(fn ($stream): bool => ($stream['codec_type'] ?? null) === 'video'
            && (int) ($stream['width'] ?? 0) > 0 && (int) ($stream['height'] ?? 0) > 0);
        $hasDuration = is_numeric($metadata['Format']['duration'] ?? null) && (float) $metadata['Format']['duration'] > 0;
        $tracksAvailable = $disk !== null && $asset->assetTracks()->get()->every(
            fn ($track): bool => $track->disk === 'private' && $disk->exists($track->path),
        );
        $checks = [
            'private_source' => $disk !== null && $disk->exists($asset->source_path),
            'valid_video_metadata' => $hasVideo && $hasDuration,
            'hls_master' => $disk !== null && $base !== '' && $disk->exists($base.'/master.m3u8'),
            'dash_manifest' => $disk !== null && $base !== '' && $disk->exists($base.'/manifest.mpd'),
            'poster' => $disk !== null && $base !== '' && $disk->exists($base.'/poster.jpg'),
            'tracks' => $tracksAvailable,
            'processing_complete' => $asset->status === 'ready',
        ];
        return ['ready' => !in_array(false, $checks, true), 'checks' => $checks];
    }
}
