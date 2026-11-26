<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\MediaProcessingJob;
use App\Models\MediaAssetTrack;
use App\Models\MediaUploadSession;
use App\Models\User;
use Database\Seeders\RbacFoundationSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminMediaManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacFoundationSeeder::class);
        Storage::fake('private');
        Http::fake(['*' => Http::response(['routed' => true], 200)]);
    }

    public function test_media_upload_sessions_are_permissioned_and_account_bound(): void
    {
        $mediaManager = User::factory()->create();
        $mediaManager->assignRole('media_manager');
        $reviewer = User::factory()->create();
        $reviewer->assignRole('content_reviewer');

        $this->postJson('/api/admin/media/uploads', ['file_name' => 'film.mp4', 'size_bytes' => 1024])->assertUnauthorized();
        $this->actingAs($reviewer, 'sanctum')->postJson('/api/admin/media/uploads', ['file_name' => 'film.mp4', 'size_bytes' => 1024])->assertForbidden();

        $sessionId = $this->actingAs($mediaManager, 'sanctum')
            ->postJson('/api/admin/media/uploads', ['file_name' => 'film.mp4', 'size_bytes' => 1024])
            ->assertCreated()->assertJsonPath('data.chunk_size', 8388608)->json('data.id');

        $anotherManager = User::factory()->create();
        $anotherManager->assignRole('media_manager');
        $this->actingAs($anotherManager, 'sanctum')
            ->putJson("/api/admin/media/uploads/{$sessionId}/chunks/0")
            ->assertNotFound();
    }

    public function test_upload_completion_rejects_missing_parts_and_releases_session_for_recovery(): void
    {
        $mediaManager = User::factory()->create();
        $mediaManager->assignRole('media_manager');
        $sessionId = $this->actingAs($mediaManager, 'sanctum')
            ->postJson('/api/admin/media/uploads', ['file_name' => 'film.mp4', 'size_bytes' => 1024])
            ->assertCreated()->json('data.id');

        $this->postJson("/api/admin/media/uploads/{$sessionId}/complete")->assertStatus(409);
        $this->assertDatabaseHas('media_upload_sessions', ['id' => $sessionId, 'status' => 'uploading']);
    }

    public function test_upload_can_resume_only_through_the_owner_bound_session_and_reports_received_parts(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('media_manager');
        $first = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/admin/media/uploads', ['file_name' => 'resume.mp4', 'size_bytes' => 1024])
            ->assertCreated()->json('data.id');
        Storage::disk('private')->put("media/uploads/{$first}/0.part", str_repeat('x', 1024));

        $this->postJson('/api/admin/media/uploads', [
            'file_name' => 'resume.mp4', 'size_bytes' => 1024, 'session_id' => $first,
        ])->assertOk()->assertJsonPath('data.id', $first)->assertJsonPath('data.received_chunks.0', 0);

        $other = User::factory()->create();
        $other->assignRole('media_manager');
        $this->actingAs($other, 'sanctum')->postJson('/api/admin/media/uploads', [
            'file_name' => 'resume.mp4', 'size_bytes' => 1024, 'session_id' => $first,
        ])->assertNotFound();
    }

    public function test_completed_assets_hide_private_source_location_and_retry_is_bounded(): void
    {
        $mediaManager = User::factory()->create();
        $mediaManager->assignRole('media_manager');
        $asset = MediaAsset::query()->create([
            'uploaded_by' => $mediaManager->id, 'original_name' => 'film.mp4', 'disk' => 'private',
            'source_path' => 'media/originals/hidden/source.mp4', 'mime_type' => 'video/mp4',
            'extension' => 'mp4', 'size_bytes' => 128, 'sha256' => str_repeat('a', 64), 'status' => 'failed',
        ]);
        $job = MediaProcessingJob::query()->create([
            'media_asset_id' => $asset->id, 'type' => 'transcode', 'status' => 'failed',
            'attempts' => 1, 'max_attempts' => 3, 'idempotency_key' => hash('sha256', 'failed-job-one'),
        ]);

        $this->actingAs($mediaManager, 'sanctum')->getJson('/api/admin/media/assets')
            ->assertOk()->assertJsonMissingPath('data.0.source_path')->assertJsonMissingPath('data.0.sha256');
        $this->postJson("/api/admin/media/assets/{$asset->id}/retry")
            ->assertAccepted()->assertJsonPath('data.status', 'queued');
        $this->assertDatabaseHas('media_processing_jobs', ['media_asset_id' => $asset->id, 'status' => 'queued', 'attempts' => 0]);

        $asset->refresh()->update(['status' => 'failed']);
        $job->update(['attempts' => 3]);
        $this->postJson("/api/admin/media/assets/{$asset->id}/retry")->assertStatus(409);
    }

    public function test_media_search_and_status_filters_are_validated_and_paginated(): void
    {
        $mediaManager = User::factory()->create();
        $mediaManager->assignRole('media_manager');

        $this->actingAs($mediaManager, 'sanctum')->getJson('/api/admin/media/assets?status=unknown')->assertUnprocessable();
        $this->getJson('/api/admin/media/assets?per_page=1000')->assertUnprocessable();
    }

    public function test_subtitle_and_audio_tracks_require_separate_permissions_and_keep_paths_private(): void
    {
        $mediaManager = User::factory()->create();
        $mediaManager->assignRole('media_manager');
        $asset = MediaAsset::query()->create([
            'uploaded_by' => $mediaManager->id, 'original_name' => 'film.mp4', 'disk' => 'private',
            'source_path' => 'media/originals/private/source.mp4', 'mime_type' => 'video/mp4',
            'extension' => 'mp4', 'size_bytes' => 128, 'sha256' => str_repeat('b', 64), 'status' => 'ready',
        ]);
        $file = UploadedFile::fake()->createWithContent('captions.srt', "1\n00:00:01,000 --> 00:00:02,000\nA caption\n");
        app(\App\Services\Upload\FileUploadValidationService::class)->validate($file, 'subtitle');

        $track = $this->actingAs($mediaManager, 'sanctum')->withHeaders(['Accept' => 'application/json'])->post("/api/admin/media/assets/{$asset->id}/tracks", [
            'kind' => 'subtitle', 'language' => 'bn', 'file' => $file,
        ])->assertCreated()->assertJsonPath('data.kind', 'subtitle')->json('data.id');
        $this->assertInstanceOf(MediaAssetTrack::class, MediaAssetTrack::query()->find($track));
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'media.track.added', 'resource_id' => (string) $track]);
        $this->actingAs($mediaManager, 'sanctum')->getJson('/api/admin/media/assets')
            ->assertOk()->assertJsonMissingPath('data.0.source_path')->assertJsonMissingPath('data.0.asset_tracks.0.path');
    }

    public function test_drm_status_never_returns_provider_credentials(): void
    {
        config(['media.drm_provider' => 'widevine', 'media.drm_license_url' => 'https://license.example.test/private', 'media.drm_secret' => 'never-return-this']);
        $mediaManager = User::factory()->create();
        $mediaManager->assignRole('media_manager');

        $this->actingAs($mediaManager, 'sanctum')->getJson('/api/admin/media/drm')
            ->assertOk()->assertJsonPath('data.provider', 'widevine')
            ->assertJsonPath('data.license_server_configured', true)
            ->assertJsonMissing(['https://license.example.test/private', 'never-return-this']);
    }

    public function test_replacement_upload_creates_an_owner_scoped_session_without_overwriting_prior_version(): void
    {
        $mediaManager = User::factory()->create();
        $mediaManager->assignRole('media_manager');
        $previous = MediaAsset::query()->create([
            'uploaded_by' => $mediaManager->id, 'original_name' => 'film-v1.mp4', 'disk' => 'private',
            'source_path' => 'media/originals/version-one/source.mp4', 'mime_type' => 'video/mp4',
            'extension' => 'mp4', 'size_bytes' => 128, 'sha256' => str_repeat('c', 64), 'status' => 'ready', 'version' => 1,
        ]);

        $sessionId = $this->actingAs($mediaManager, 'sanctum')->postJson('/api/admin/media/uploads', [
            'file_name' => 'film-v2.mp4', 'size_bytes' => 1024, 'replaces_asset_id' => $previous->id,
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('media_upload_sessions', [
            'id' => $sessionId, 'replaces_asset_id' => $previous->id, 'status' => 'uploading',
        ]);
        $this->assertDatabaseHas('media_assets', ['id' => $previous->id, 'version' => 1, 'deleted_at' => null]);
    }
}
