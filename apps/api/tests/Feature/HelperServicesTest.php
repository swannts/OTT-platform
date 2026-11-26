<?php

namespace Tests\Feature;

use App\Services\Media\BunnyStreamService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HelperServicesTest extends TestCase
{
    public function test_bunny_extract_guid_and_delete_by_file_guards(): void
    {
        $service = new BunnyStreamService;

        $guid = $service->extractGuid('https://cdn.example.com/123e4567-e89b-12d3-a456-426614174000/playlist.m3u8');
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $guid);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', extractBunnyGuid('https://cdn.example.com/123e4567-e89b-12d3-a456-426614174000/playlist.m3u8'));
        $this->assertNull($service->extractGuid('https://cdn.example.com/not-a-guid/playlist.m3u8'));

        Config::set('filesystems.active', 'local');
        $this->assertFalse(deleteBunnyStreamVideoByFile('movie.mp4'));

        Config::set('filesystems.active', 'bunny');
        $this->assertFalse(deleteBunnyStreamVideoByFile('poster.jpg'));
    }

    public function test_bunny_ingest_returns_existing_m3u8_when_configured(): void
    {
        Http::fake([
            'https://video.bunnycdn.com/library/*/videos' => Http::response([
                'items' => [
                    ['title' => 'Sample Video', 'guid' => '123e4567-e89b-12d3-a456-426614174000'],
                ],
            ], 200),
        ]);

        putenv('BUNNY_STREAM_API_KEY=test-key');
        putenv('BUNNY_VIDEO_KEY=12345');
        putenv('BUNNY_CDN_HOSTNAME=cdn.example.com');
        $_ENV['BUNNY_STREAM_API_KEY'] = $_SERVER['BUNNY_STREAM_API_KEY'] = 'test-key';
        $_ENV['BUNNY_VIDEO_KEY'] = $_SERVER['BUNNY_VIDEO_KEY'] = '12345';
        $_ENV['BUNNY_CDN_HOSTNAME'] = $_SERVER['BUNNY_CDN_HOSTNAME'] = 'cdn.example.com';

        $url = bunnyIngestAndGetM3u8('https://source.example.com/video.mp4', 'Sample Video');
        $this->assertSame('https://cdn.example.com/123e4567-e89b-12d3-a456-426614174000/playlist.m3u8', $url);

        putenv('BUNNY_STREAM_API_KEY');
        putenv('BUNNY_VIDEO_KEY');
        putenv('BUNNY_CDN_HOSTNAME');
        unset($_ENV['BUNNY_STREAM_API_KEY'], $_SERVER['BUNNY_STREAM_API_KEY'], $_ENV['BUNNY_VIDEO_KEY'], $_SERVER['BUNNY_VIDEO_KEY'], $_ENV['BUNNY_CDN_HOSTNAME'], $_SERVER['BUNNY_CDN_HOSTNAME']);
    }
}
