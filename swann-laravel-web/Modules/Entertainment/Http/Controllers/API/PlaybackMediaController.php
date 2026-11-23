<?php

namespace Modules\Entertainment\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Entertainment\Models\Entertainment;
use Modules\Entertainment\Services\PlaybackEntitlementService;
use Modules\Entertainment\Services\PlaybackMediaUrlService;
use Modules\Episode\Models\Episode;
use Modules\Video\Models\Video;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PlaybackMediaController extends Controller
{
    public function show(
        Request $request,
        int $assetId,
        string $file,
        PlaybackMediaUrlService $urls,
        PlaybackEntitlementService $entitlements
    ): BinaryFileResponse|\Illuminate\Http\Response {
        $session = DB::table('playback_sessions')
            ->where('session_id', $request->query('sessionId'))
            ->whereNull('ended_at')
            ->where('expires_at', '>', now())
            ->first();
        abort_unless($session, 403, 'Playback session expired or revoked.');

        $token = PersonalAccessToken::query()
            ->whereKey($session->device_token_id)
            ->where('tokenable_id', $session->user_id)
            ->first();
        abort_unless($token instanceof PersonalAccessToken, 403, 'Playback device was revoked.');
        abort_unless(Device::query()
            ->where('user_id', $session->user_id)
            ->where('sanctum_token_id', $token->getKey())
            ->where('active_profile', $session->profile_id)
            ->exists(), 403, 'Playback device was revoked.');

        $user = User::query()->findOrFail($session->user_id);
        $user->withAccessToken($token);
        $contentModel = match ($session->content_type) {
            'movie', 'tvshow' => Entertainment::class,
            'episode' => Episode::class,
            'video' => Video::class,
            default => null,
        };
        abort_unless($contentModel, 403, 'Unsupported playback content.');
        $content = $contentModel::query()->whereKey($session->content_id)->first();
        abort_unless($content && (int) $content->media_asset_id === $assetId, 403, 'Media is no longer assigned to this title.');

        $entitlementRequest = Request::create('/api/v3/playback/media', 'GET');
        try {
            $entitlements->authorize($user, (string) $content->id, $session->content_type, (int) $session->profile_id, $entitlementRequest);
        } catch (AuthorizationException) {
            abort(403, 'Playback entitlement expired or was revoked.');
        }

        $asset = MediaAsset::query()->whereKey($assetId)->where('status', 'ready')->firstOrFail();
        abort_unless($asset->disk === 'private' && filled($asset->rendition_path), 404);
        $relativeFile = $this->normalizeRelativePath($file);
        $root = trim((string) $asset->rendition_path, '/');
        $disk = Storage::disk('private');
        $basePath = realpath($disk->path($root));
        $fullPath = realpath($disk->path($root.'/'.$relativeFile));
        abort_unless($basePath && $fullPath && str_starts_with($fullPath, $basePath.DIRECTORY_SEPARATOR) && is_file($fullPath), 404);

        if (strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)) === 'm3u8') {
            $manifest = file_get_contents($fullPath);
            abort_unless(is_string($manifest), 404);
            $manifestBase = trim(dirname($relativeFile), '.');
            $manifest = preg_replace_callback('/URI="([^"]+)"/', function (array $match) use ($manifestBase, $assetId, $session, $urls): string {
                return 'URI="'.$urls->sign($assetId, $this->joinRelative($manifestBase, $match[1]), $session->session_id).'"';
            }, $manifest);
            $lines = preg_split('/\r\n|\n|\r/', (string) $manifest);
            foreach ($lines as &$line) {
                $trimmed = trim($line);
                if ($trimmed !== '' && !str_starts_with($trimmed, '#')) {
                    $line = $urls->sign($assetId, $this->joinRelative($manifestBase, $trimmed), $session->session_id);
                }
            }
            unset($line);
            return response(implode("\n", $lines), 200, [
                'Content-Type' => 'application/vnd.apple.mpegurl',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return response()->file($fullPath, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function normalizeRelativePath(string $path): string
    {
        abort_if($path === '' || str_contains($path, "\0") || str_contains($path, '\\') || str_starts_with($path, '/'), 404);
        $segments = explode('/', $path);
        abort_if(in_array('.', $segments, true) || in_array('..', $segments, true), 404);
        abort_unless(in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['m3u8', 'ts', 'm4s', 'mp4', 'm4a', 'aac', 'vtt', 'webvtt'], true), 404);
        return $path;
    }

    private function joinRelative(string $base, string $file): string
    {
        $parsed = parse_url($file);
        abort_if(isset($parsed['scheme']) || isset($parsed['host']) || isset($parsed['query']), 404);
        $filePath = ltrim((string) ($parsed['path'] ?? ''), '/');
        $joined = ($base !== '' ? $base.'/' : '').$filePath;
        return $this->normalizeRelativePath($joined);
    }
}
