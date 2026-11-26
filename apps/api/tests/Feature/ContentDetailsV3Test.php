<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Modules\Entertainment\Models\Entertainment;
use Tests\TestCase;

class ContentDetailsV3Test extends TestCase
{
    use DatabaseTransactions;

    public function test_content_details_v3_returns_movie_metadata_and_media_sections(): void
    {
        $movie = Entertainment::query()->create([
            'name' => 'Test Documentary '.Str::random(6),
            'slug' => 'test-doc-'.Str::lower(Str::random(6)),
            'type' => 'movie',
            'status' => 1,
            'movie_access' => 'free',
            'trailer_url_type' => 'URL',
            'trailer_url' => 'https://cdn.example.com/trailer.mp4',
            'video_upload_type' => 'URL',
            'video_url_input' => 'https://cdn.example.com/movie.mp4',
            'download_status' => 1,
            'download_type' => 'URL',
            'download_url' => 'https://cdn.example.com/download.mp4',
        ]);

        $this->getJson("/api/v3/content-details?id={$movie->id}&type=movie")
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.id', $movie->id)
            ->assertJsonPath('data.trailer_data.0.url', 'https://cdn.example.com/trailer.mp4')
            ->assertJsonPath('data.download_data.download_enable', 0);
    }

    public function test_content_details_v3_returns_not_found_payload_for_missing_movie(): void
    {
        $this->getJson('/api/v3/content-details?id=99999999&type=movie')
            ->assertOk()
            ->assertJsonPath('data.status', false)
            ->assertJsonPath('data.message', 'Content not found.');
    }
}
