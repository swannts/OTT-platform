<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BunnyStreamService
{
    private const BASE_URL = 'https://video.bunnycdn.com/library';

    public function ingestAndGetM3u8(string $sourceUrl, string $title): ?string
    {
        $apiKey = env('BUNNY_STREAM_API_KEY');
        $libraryId = env('BUNNY_VIDEO_KEY');
        $cdnHost = env('BUNNY_CDN_HOSTNAME');
        if (! $apiKey || ! $libraryId || ! $cdnHost) {
            return null;
        }

        $list = Http::withHeaders([
            'AccessKey' => $apiKey,
        ])->get(self::BASE_URL."/{$libraryId}/videos");

        if ($list->successful()) {
            $videos = $list->json()['items'] ?? [];
            foreach ($videos as $video) {
                if (isset($video['title']) && $video['title'] == $title) {
                    $guid = $video['guid'];

                    return "https://{$cdnHost}/{$guid}/playlist.m3u8";
                }
            }
        }

        $create = Http::withHeaders([
            'AccessKey' => $apiKey,
            'Content-Type' => 'application/json',
        ])->post(self::BASE_URL."/{$libraryId}/videos", ['title' => $title]);

        if (! $create->successful()) {
            return null;
        }
        $guid = data_get($create->json(), 'guid');
        if (! $guid) {
            return null;
        }

        $fetch = Http::withHeaders([
            'AccessKey' => $apiKey,
            'Content-Type' => 'application/json',
        ])->post(self::BASE_URL."/{$libraryId}/videos/{$guid}/fetch", [
            'url' => $sourceUrl,
        ]);
        if (! $fetch->successful()) {
            return null;
        }

        return "https://{$cdnHost}/{$guid}/playlist.m3u8";
    }

    public function deleteVideo(string $identifier, bool $isGuid = false): bool
    {
        $apiKey = env('BUNNY_STREAM_API_KEY');
        $libraryId = env('BUNNY_VIDEO_KEY');

        if (! $apiKey || ! $libraryId) {
            Log::warning('Bunny Stream credentials not configured');

            return false;
        }

        $videoGuid = null;

        if (! $isGuid) {
            $list = Http::withHeaders(['AccessKey' => $apiKey])
                ->get(self::BASE_URL."/{$libraryId}/videos");

            if ($list->successful()) {
                $videos = $list->json()['items'] ?? [];
                foreach ($videos as $video) {
                    if (isset($video['title']) && $video['title'] == $identifier) {
                        $videoGuid = $video['guid'];
                        break;
                    }
                }
            }

            if (! $videoGuid) {
                Log::info('Bunny Stream video was not found.');

                return false;
            }
        } else {
            $videoGuid = $identifier;
        }

        $response = Http::withHeaders(['AccessKey' => $apiKey])
            ->delete(self::BASE_URL."/{$libraryId}/videos/{$videoGuid}");

        if ($response->successful()) {
            Log::info('Bunny Stream video deletion succeeded.');

            return true;
        }

        Log::error('Bunny Stream video deletion failed.', ['http_status' => $response->status()]);

        return false;
    }

    public function extractGuid(string $m3u8Url): ?string
    {
        if (preg_match('/\/([a-f0-9\-]{36})\/playlist\.m3u8/i', $m3u8Url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function deleteVideoByFile(string $fileName): bool
    {
        if (config('filesystems.active') != 'bunny') {
            return false;
        }

        $videoExtensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', '3gp', 'm4v', 'mpg', 'mpeg'];
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (! in_array($extension, $videoExtensions)) {
            return false;
        }

        Log::info('Deleting video from Bunny Stream.');

        return $this->deleteVideo($fileName, false);
    }
}
