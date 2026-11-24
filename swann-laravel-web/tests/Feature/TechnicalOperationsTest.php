<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RbacFoundationSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class TechnicalOperationsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacFoundationSeeder::class);
    }

    public function test_health_endpoint_reports_real_dependencies_and_requires_permission(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('technical_operations_admin');
        Redis::shouldReceive('connection')->andThrow(new \RuntimeException('unavailable'));
        Http::fake([
            'http://worker:8081/health' => Http::response(['status' => 'ok', 'checks' => ['rabbitmq' => 'ok']], 200),
            '*rabbitmq:15672*' => Http::response([['name' => 'ott.dead-letter', 'messages' => 2, 'messages_ready' => 2, 'messages_unacknowledged' => 0, 'consumers' => 0]], 200),
        ]);

        $this->actingAs($operator, 'sanctum')->getJson('/api/admin/operations/health')
            ->assertOk()->assertJsonPath('data.services.application.status', 'ok')
            ->assertJsonPath('data.services.go_worker.status', 'ok')
            ->assertJsonPath('data.rabbitmq_queues.0.name', 'ott.dead-letter')
            ->assertJsonStructure(['data' => ['checked_at', 'services' => ['database', 'redis', 'rabbitmq', 'rabbitmq_management', 'go_worker'], 'queue']]);

        $operator->removeRole('technical_operations_admin');
        $operator->assignRole('customer_support');
        $this->getJson('/api/admin/operations/health')->assertForbidden();
    }

    public function test_incident_lifecycle_is_permission_checked_and_audited(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('technical_operations_admin');
        $incident = $this->actingAs($operator, 'sanctum')->postJson('/api/admin/operations/incidents', [
            'title' => 'Queue processing delay', 'summary' => 'The worker backlog is above the normal range.',
            'severity' => 'high', 'affected_services' => ['rabbitmq', 'go_worker'],
        ])->assertCreated()->assertJsonPath('data.status', 'open')->json('data');

        $customer = User::factory()->create();
        $this->patchJson('/api/admin/operations/incidents/'.$incident['id'], ['assigned_to' => $customer->id])->assertStatus(422);
        $this->patchJson('/api/admin/operations/incidents/'.$incident['id'], ['status' => 'acknowledged'])
            ->assertOk()->assertJsonPath('data.status', 'acknowledged');
        $this->patchJson('/api/admin/operations/incidents/'.$incident['id'], ['status' => 'resolved'])
            ->assertOk()->assertJsonPath('data.status', 'resolved')->assertJsonPath('data.resolved_by', $operator->id);
        $this->patchJson('/api/admin/operations/incidents/'.$incident['id'], ['status' => 'acknowledged'])->assertStatus(409);
        $this->getJson('/api/admin/operations/incidents?status=resolved')->assertOk()->assertJsonCount(1, 'data');

        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'operations.incident.created', 'resource_id' => (string) $incident['id']]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'operations.incident.updated', 'resource_id' => (string) $incident['id']]);
    }

    public function test_failed_job_listing_is_sanitized_and_retry_is_allowlisted(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('technical_operations_admin');
        $uuid = (string) Str::uuid();
        $this->actingAs($operator, 'sanctum');
        \Illuminate\Support\Facades\DB::table('failed_jobs')->insert([
            'uuid' => $uuid, 'connection' => 'database', 'queue' => 'notifications',
            'payload' => json_encode(['data' => ['commandName' => \App\Jobs\DispatchMarketingCampaign::class]]),
            'exception' => 'private exception trace', 'failed_at' => now(),
        ]);
        $this->getJson('/api/admin/operations/jobs')->assertOk()
            ->assertJsonPath('data.0.retry_allowed', true)->assertJsonMissing(['private exception trace']);
        $this->postJson('/api/admin/operations/jobs/'.$uuid.'/retry')->assertOk()->assertJsonPath('data.status', 'queued');
        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
        $this->assertDatabaseHas('jobs', ['queue' => 'notifications']);

        $unsupported = (string) Str::uuid();
        \Illuminate\Support\Facades\DB::table('failed_jobs')->insert([
            'uuid' => $unsupported, 'connection' => 'database', 'queue' => 'default',
            'payload' => json_encode(['data' => ['commandName' => 'App\\Jobs\\ImportUsersJob']]),
            'exception' => 'private exception trace', 'failed_at' => now(),
        ]);
        $this->getJson('/api/admin/operations/jobs')->assertJsonPath('data.0.retry_allowed', false);
        $this->postJson('/api/admin/operations/jobs/'.$unsupported.'/retry')->assertStatus(409);
    }
}
