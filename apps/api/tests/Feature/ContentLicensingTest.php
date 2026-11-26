<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RbacFoundationSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContentLicensingTest extends TestCase
{
    use DatabaseTransactions;

    private User $contentManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacFoundationSeeder::class);
        $this->contentManager = User::factory()->create(['status' => 1, 'is_banned' => 0]);
        $this->contentManager->assignRole('content_manager');
        $this->actingAs($this->contentManager, 'sanctum');
    }

    public function test_content_manager_can_save_an_audited_utc_license_window(): void
    {
        $contentId = $this->createMovie();

        $this->putJson("/api/admin/content/entertainment/{$contentId}/license-window", [
            'starts_at' => '2026-12-01T00:00:00Z',
            'ends_at' => '2027-01-01T00:00:00Z',
        ])->assertOk()
            ->assertJsonPath('data.starts_at', '2026-12-01T00:00:00+00:00')
            ->assertJsonPath('data.ends_at', '2027-01-01T00:00:00+00:00');

        $this->assertDatabaseHas('content_license_windows', [
            'content_type' => 'entertainment',
            'content_id' => $contentId,
            'created_by' => $this->contentManager->getKey(),
            'updated_by' => $this->contentManager->getKey(),
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $this->contentManager->getKey(),
            'action' => 'content.license_window.updated',
            'resource_id' => $contentId,
        ]);
    }

    public function test_license_window_rejects_an_end_not_after_its_start(): void
    {
        $contentId = $this->createMovie();

        $this->putJson("/api/admin/content/entertainment/{$contentId}/license-window", [
            'starts_at' => '2026-12-02T00:00:00Z',
            'ends_at' => '2026-12-01T00:00:00Z',
        ])->assertUnprocessable()->assertJsonValidationErrors('ends_at');

        $this->assertDatabaseMissing('content_license_windows', [
            'content_type' => 'entertainment',
            'content_id' => $contentId,
        ]);
    }

    public function test_role_without_content_schedule_permission_cannot_change_a_window(): void
    {
        $reviewer = User::factory()->create(['status' => 1, 'is_banned' => 0]);
        $reviewer->assignRole('content_reviewer');
        $this->actingAs($reviewer, 'sanctum');
        $contentId = $this->createMovie();

        $this->putJson("/api/admin/content/entertainment/{$contentId}/license-window", [
            'ends_at' => '2027-01-01T00:00:00Z',
        ])->assertForbidden();
    }

    public function test_licensing_endpoints_reject_oversized_content_ids(): void
    {
        $this->getJson('/api/admin/content/entertainment/'.str_repeat('x', 65).'/license-window')->assertNotFound();
    }

    private function createMovie(): string
    {
        $id = (string) Str::uuid();
        DB::table('entertainments')->insert([
            'id' => $id,
            'name' => 'Licensing test title',
            'type' => 'movie',
            'movie_access' => 'free',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
