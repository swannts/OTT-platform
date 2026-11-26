<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Database\Seeders\RbacFoundationSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupportOperationsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacFoundationSeeder::class);
    }

    public function test_support_agent_can_open_ticket_add_internal_note_escalate_and_resolve_with_audit(): void
    {
        $support = User::factory()->create();
        $support->assignRole('customer_support');
        $customer = User::factory()->create();
        $ticket = $this->actingAs($support, 'sanctum')->postJson('/api/admin/support/tickets', [
            'customer_id' => $customer->id, 'subject' => 'Cannot watch a title', 'description' => 'Playback stopped.',
            'category' => 'playback', 'priority' => 'high', 'assigned_to' => $support->id,
        ])->assertCreated()->assertJsonPath('data.status', 'open')->json('data');

        $this->postJson('/api/admin/support/tickets/'.$ticket['id'].'/notes', ['note' => 'Verified the device and asked for a retry.'])
            ->assertCreated()->assertJsonPath('data.is_internal', 1);
        $this->postJson('/api/admin/support/tickets/'.$ticket['id'].'/escalate', ['target' => 'technical_operations', 'reason' => 'Repeated manifest failures.'])
            ->assertOk()->assertJsonPath('data.status', 'escalated');
        $this->patchJson('/api/admin/support/tickets/'.$ticket['id'], ['status' => 'resolved'])
            ->assertOk()->assertJsonPath('data.status', 'resolved');

        $this->assertDatabaseHas('support_ticket_notes', ['ticket_id' => $ticket['id'], 'is_internal' => true]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'support.ticket.created']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'support.ticket.note_added']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'support.ticket.escalated']);
        $this->assertDatabaseHas('support_tickets', ['id' => $ticket['id'], 'status' => 'resolved', 'escalation_target' => 'technical_operations']);
    }

    public function test_support_customer_views_are_explicit_and_never_return_sensitive_credentials(): void
    {
        $support = User::factory()->create();
        $support->assignRole('customer_support');
        $customer = User::factory()->create(['pin' => '1234']);
        $this->actingAs($support, 'sanctum')->getJson('/api/admin/support/customers?search='.$customer->email)
            ->assertOk()->assertJsonPath('data.0.email', $customer->email)->assertJsonMissing(['password' => $customer->password])->assertJsonMissing(['pin' => '1234']);
        $this->getJson('/api/admin/support/customers/'.$customer->id)->assertOk()
            ->assertJsonPath('data.customer.id', $customer->id)->assertJsonMissing(['password'])->assertJsonMissing(['pin']);

        $staff = User::factory()->create(['email' => 'support-colleague@example.test']);
        $staff->assignRole('finance_manager');
        $this->getJson('/api/admin/support/customers?search=support-colleague@example.test')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/admin/support/customers/'.$staff->id)->assertNotFound();

        $support->removeRole('customer_support');
        $support->givePermissionTo(['admin.access', 'support.accounts.view']);
        $this->getJson('/api/admin/support/customers/'.$customer->id.'/playback')->assertForbidden();
    }

    public function test_support_payment_view_masks_provider_reference_and_cannot_modify_financial_records(): void
    {
        $support = User::factory()->create();
        $support->assignRole('customer_support');
        $customer = User::factory()->create();
        $reference = 'secret-provider-reference-9876';
        $paymentId = DB::table('verified_payments')->insertGetId([
            'gateway' => 'stripe', 'provider_reference' => $reference, 'user_id' => $customer->id,
            'purchase_type' => 'subscription', 'purchase_id' => 82, 'amount_minor' => 1299,
            'currency' => 'USD', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $transactionId = DB::table('subscriptions_transactions')->insertGetId([
            'user_id' => $customer->id, 'subscriptions_id' => 82, 'amount' => 12.99, 'payment_type' => 'stripe',
            'transaction_id' => 'failed-provider-reference-4567', 'payment_status' => 'failed', 'currency_code' => 'USD',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($support, 'sanctum')->getJson('/api/admin/support/customers/'.$customer->id.'/payments')->assertOk()
            ->assertJsonFragment(['id' => $paymentId, 'reference' => 'se************76'])
            ->assertJsonFragment(['id' => $transactionId, 'status' => 'failed'])
            ->assertJsonMissing(['reference' => $reference])->assertJsonMissing(['reference' => 'failed-provider-reference-4567']);
        $this->postJson('/api/admin/finance/refunds', [])->assertForbidden();
        $this->assertDatabaseHas('verified_payments', ['id' => $paymentId, 'provider_reference' => $reference]);
    }

    public function test_authorized_device_revocation_ends_playback_and_deletes_token_without_returning_it(): void
    {
        $support = User::factory()->create();
        $support->assignRole('customer_support');
        $customer = User::factory()->create();
        $token = $customer->createToken('customer-device');
        $tokenId = $token->accessToken->id;
        $device = Device::query()->create(['user_id' => $customer->id, 'sanctum_token_id' => $tokenId, 'device_id' => 'device-local-id', 'device_name' => 'Living room', 'platform' => 'tv', 'session_id' => 'session-local', 'last_activity' => now()]);
        $sessionId = (string) Str::uuid();
        DB::table('playback_sessions')->insert([
            'session_id' => $sessionId, 'user_id' => $customer->id, 'device_token_id' => $tokenId,
            'profile_id' => 1, 'content_id' => '44', 'content_type' => 'movie', 'last_heartbeat_at' => now(),
            'expires_at' => now()->addMinute(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($support, 'sanctum')->postJson('/api/admin/support/customers/'.$customer->id.'/devices/'.$device->id.'/revoke')
            ->assertOk()->assertJsonPath('data.revoked', true)->assertJsonMissing(['token' => $token->plainTextToken]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
        $this->assertNotNull(DB::table('playback_sessions')->where('session_id', $sessionId)->value('ended_at'));
        $this->assertDatabaseHas('devices', ['id' => $device->id, 'sanctum_token_id' => null, 'session_id' => null]);
    }

    public function test_support_agent_can_revoke_one_customer_playback_session_with_audit(): void
    {
        $support = User::factory()->create();
        $support->assignRole('customer_support');
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $makeSession = function (User $owner): string {
            $id = (string) Str::uuid();
            DB::table('playback_sessions')->insert([
                'session_id' => $id, 'user_id' => $owner->id, 'device_token_id' => $owner->createToken('playback')->accessToken->id,
                'profile_id' => 1, 'content_id' => '44', 'content_type' => 'movie', 'last_heartbeat_at' => now(),
                'expires_at' => now()->addMinute(), 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $id;
        };
        $targetId = $makeSession($customer);
        $preservedId = $makeSession($customer);
        $foreignId = $makeSession($otherCustomer);
        $targetRowId = (int) DB::table('playback_sessions')->where('session_id', $targetId)->value('id');

        $this->actingAs($support, 'sanctum')->getJson('/api/admin/support/customers/'.$customer->id.'/playback')
            ->assertOk()->assertJsonFragment(['id' => $targetRowId, 'session_reference' => substr($targetId, 0, 8)]);
        $this->postJson('/api/admin/support/customers/'.$customer->id.'/playback-sessions/'.$targetRowId.'/revoke')
            ->assertOk()->assertJsonPath('data.revoked', true);

        $this->assertNotNull(DB::table('playback_sessions')->where('session_id', $targetId)->value('ended_at'));
        $this->assertNull(DB::table('playback_sessions')->where('session_id', $preservedId)->value('ended_at'));
        $this->assertNull(DB::table('playback_sessions')->where('session_id', $foreignId)->value('ended_at'));
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'support.playback_session.revoked', 'resource_id' => (string) $targetRowId]);
    }

    public function test_support_playback_session_revocation_requires_explicit_permission(): void
    {
        $support = User::factory()->create();
        $support->assignRole('customer_support');
        $customer = User::factory()->create();
        $sessionId = (string) Str::uuid();
        $token = $customer->createToken('playback')->accessToken;
        $sessionRowId = DB::table('playback_sessions')->insertGetId([
            'session_id' => $sessionId, 'user_id' => $customer->id, 'device_token_id' => $token->id,
            'profile_id' => 1, 'content_id' => '44', 'content_type' => 'movie', 'last_heartbeat_at' => now(),
            'expires_at' => now()->addMinute(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $support->removeRole('customer_support');
        $support->givePermissionTo(['admin.access', 'support.playback.view']);

        $this->actingAs($support, 'sanctum')->postJson('/api/admin/support/customers/'.$customer->id.'/playback-sessions/'.$sessionRowId.'/revoke')->assertForbidden();
        $this->assertNull(DB::table('playback_sessions')->where('id', $sessionRowId)->value('ended_at'));
    }

    public function test_non_support_user_cannot_open_support_ticket_or_customer_search(): void
    {
        $user = User::factory()->create();
        $user->assignRole('content_editor');
        $this->actingAs($user, 'sanctum')->getJson('/api/admin/support/tickets')->assertForbidden();
        $this->getJson('/api/admin/support/customers?search=person@example.com')->assertForbidden();
    }

    public function test_customer_playback_error_reports_are_session_bound_and_store_only_allowlisted_codes(): void
    {
        $customer = User::factory()->create();
        $token = $customer->createToken('playback-device');
        $sessionId = (string) Str::uuid();
        DB::table('playback_sessions')->insert([
            'session_id' => $sessionId, 'user_id' => $customer->id, 'device_token_id' => $token->accessToken->id,
            'profile_id' => 1, 'content_id' => 'content-77', 'content_type' => 'movie', 'last_heartbeat_at' => now(),
            'expires_at' => now()->addMinute(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->withToken($token->plainTextToken)->postJson('/api/v3/playback/diagnostics', [
            'session_id' => $sessionId, 'error_code' => 'manifest_error', 'message' => 'contains sensitive playback url',
        ])->assertAccepted()->assertJsonPath('data.recorded', true);
        $this->assertDatabaseHas('playback_diagnostic_events', ['user_id' => $customer->id, 'event_code' => 'playback.client_error.manifest_error', 'content_id' => 'content-77']);
        $this->assertDatabaseMissing('playback_diagnostic_events', ['event_code' => 'contains sensitive playback url']);
        $this->withToken($token->plainTextToken)->postJson('/api/v3/playback/diagnostics', [
            'session_id' => $sessionId, 'event_type' => 'started', 'duration_ms' => 1450, 'quality' => '1080p',
        ])->assertAccepted();
        $this->assertDatabaseHas('playback_diagnostic_events', ['session_id' => $sessionId, 'event_code' => 'playback.started', 'duration_ms' => 1450, 'quality' => '1080p']);

        $otherCustomer = User::factory()->create();
        $otherToken = $otherCustomer->createToken('other-device');
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken->plainTextToken)->postJson('/api/v3/playback/diagnostics', ['session_id' => $sessionId, 'error_code' => 'manifest_error'])->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)->postJson('/api/v3/playback/diagnostics', ['session_id' => $sessionId, 'error_code' => 'https://example.test/private'])->assertUnprocessable();
    }
}
