<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RbacFoundationSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminContentWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacFoundationSeeder::class);
    }

    public function test_submission_requires_review_and_separates_approval_from_publication(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('content_editor');
        $reviewer = User::factory()->create();
        $reviewer->assignRole('content_reviewer');
        $manager = User::factory()->create();
        $manager->assignRole('content_manager');
        $contentId = $this->createContent();

        $submitted = $this->actingAs($editor, 'sanctum')
            ->postJson("/api/admin/content/{$contentId}/submit")
            ->assertCreated()->assertJsonPath('data.status', 'in_review');
        $submissionId = $submitted->json('data.id');

        $this->actingAs($editor, 'sanctum')->getJson('/api/admin/content/review-queue')->assertForbidden();
        $this->actingAs($reviewer, 'sanctum')->getJson('/api/admin/content/review-queue')
            ->assertOk()->assertJsonPath('data.0.content_id', (string) $contentId);
        $this->actingAs($reviewer, 'sanctum')->postJson("/api/admin/content/reviews/{$submissionId}/approve", [])
            ->assertOk()->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('entertainments', ['id' => $contentId, 'status' => 0]);
        $this->actingAs($reviewer, 'sanctum')->postJson("/api/admin/content/reviews/{$submissionId}/publish")
            ->assertForbidden();
        $this->actingAs($manager, 'sanctum')->postJson("/api/admin/content/reviews/{$submissionId}/publish")
            ->assertOk()->assertJsonPath('data.status', 'published');
        $this->assertDatabaseHas('entertainments', ['id' => $contentId, 'status' => 1]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'content.submitted']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'content.approved']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'content.published']);
    }

    public function test_reviewer_cannot_approve_own_submission_or_approve_changed_content(): void
    {
        $reviewer = User::factory()->create();
        $reviewer->assignRole('content_reviewer');
        $reviewer->assignRole('content_editor');
        $selfReviewContentId = $this->createContent();

        $submissionId = $this->actingAs($reviewer, 'sanctum')
            ->postJson("/api/admin/content/{$selfReviewContentId}/submit")
            ->assertCreated()->json('data.id');
        $this->postJson("/api/admin/content/reviews/{$submissionId}/approve", [])->assertForbidden();

        $editor = User::factory()->create();
        $editor->assignRole('content_editor');
        $changedContentId = $this->createContent();
        $pendingId = $this->actingAs($editor, 'sanctum')->postJson("/api/admin/content/{$changedContentId}/submit")->assertCreated()->json('data.id');
        DB::table('entertainments')->where('id', $changedContentId)->update(['name' => 'Changed after submission']);
        $this->actingAs($reviewer, 'sanctum')->postJson("/api/admin/content/reviews/{$pendingId}/approve", [])->assertStatus(409);
    }

    public function test_operational_api_requires_the_domain_permission(): void
    {
        $reviewer = User::factory()->create();
        $reviewer->assignRole('content_reviewer');
        $mediaManager = User::factory()->create();
        $mediaManager->assignRole('media_manager');

        $this->actingAs($reviewer, 'sanctum')->getJson('/api/admin/media/overview')->assertForbidden();
        $this->actingAs($mediaManager, 'sanctum')->getJson('/api/admin/media/overview')->assertOk()->assertJsonStructure(['data' => ['published_videos', 'published_catalog_items', 'pending_reviews']]);
    }

    public function test_each_operational_summary_requires_its_own_role_permission(): void
    {
        $domainRoutes = [
            'media_manager' => '/api/admin/media/overview',
            'subscription_manager' => '/api/admin/subscriptions/overview',
            'finance_manager' => '/api/admin/finance/overview',
            'marketing_manager' => '/api/admin/marketing/overview',
            'analytics_manager' => '/api/admin/analytics/overview',
            'technical_operations_admin' => '/api/admin/operations/overview',
        ];

        foreach ($domainRoutes as $role => $path) {
            $staff = User::factory()->create();
            $staff->assignRole($role);
            $this->actingAs($staff, 'sanctum')->getJson($path)->assertOk();
        }

        $reviewer = User::factory()->create();
        $reviewer->assignRole('content_reviewer');
        foreach ($domainRoutes as $path) {
            $this->actingAs($reviewer, 'sanctum')->getJson($path)->assertForbidden();
        }
    }

    public function test_attached_media_must_be_fully_packaged_before_content_publication(): void
    {
        Storage::fake('private');
        $editor = User::factory()->create();
        $editor->assignRole('content_editor');
        $reviewer = User::factory()->create();
        $reviewer->assignRole('content_reviewer');
        $publisher = User::factory()->create();
        $publisher->assignRole('content_manager');
        $contentId = $this->createContent();
        $asset = \App\Models\MediaAsset::query()->create([
            'uploaded_by' => $publisher->id, 'original_name' => 'film.mp4', 'disk' => 'private',
            'source_path' => 'media/originals/unready/source.mp4', 'mime_type' => 'video/mp4',
            'extension' => 'mp4', 'size_bytes' => 1024, 'sha256' => str_repeat('d', 64), 'status' => 'queued',
        ]);

        $this->actingAs($publisher, 'sanctum')->putJson("/api/admin/media/assets/{$asset->id}/content", ['content_id' => $contentId])->assertOk();
        $submissionId = $this->actingAs($editor, 'sanctum')->postJson("/api/admin/content/{$contentId}/submit")->assertCreated()->json('data.id');
        $this->actingAs($reviewer, 'sanctum')->postJson("/api/admin/content/reviews/{$submissionId}/approve", [])->assertOk();
        $this->actingAs($publisher, 'sanctum')->postJson("/api/admin/content/reviews/{$submissionId}/publish")->assertStatus(409);

        $asset->update([
            'status' => 'ready', 'rendition_path' => 'media/renditions/'.$asset->id.'/1',
            'metadata' => ['Format' => ['duration' => '10'], 'Streams' => [['codec_type' => 'video', 'width' => 640, 'height' => 360]]],
        ]);
        Storage::disk('private')->put($asset->source_path, 'source');
        foreach (['master.m3u8', 'manifest.mpd', 'poster.jpg'] as $file) {
            Storage::disk('private')->put($asset->rendition_path.'/'.$file, 'ready');
        }
        $this->postJson("/api/admin/content/reviews/{$submissionId}/publish")->assertOk();
    }

    private function createContent(): string
    {
        $id = (string) Str::uuid();
        DB::table('entertainments')->insert([
            'id' => $id,
            'name' => 'Reviewable film', 'type' => 'movie', 'poster_url' => 'https://example.test/poster.jpg',
            'status' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return $id;
    }
}
