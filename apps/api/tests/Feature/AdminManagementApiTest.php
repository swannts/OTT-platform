<?php

namespace Tests\Feature;

use App\Mail\AdminStaffInvitationMail;
use App\Models\AdminAuditLog;
use App\Models\AdminInvitation;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacFoundationSeeder::class);
        $this->admin = User::factory()->create([
            'password' => bcrypt('Correct-Horse-123!'),
            'status' => 1,
            'is_banned' => 0,
        ]);
        $this->admin->assignRole('super-admin');
        $this->actingAs($this->admin, 'sanctum');
    }

    public function test_admin_can_create_custom_role_and_assign_it_without_removing_customer_roles(): void
    {
        $this->postJson('/api/admin/roles', [
            'title' => 'Playback Observer',
            'description' => 'Read-only playback support.',
            'permissions' => ['support.playback.view'],
        ])->assertCreated()->assertJsonPath('data.name', 'playback_observer');

        $staff = User::factory()->create();
        $customerRole = Role::query()->create([
            'name' => 'subscriber', 'title' => 'Subscriber', 'guard_name' => 'web',
            'is_fixed' => true, 'is_admin_role' => false, 'is_active' => true,
        ]);
        $staff->assignRole('content_editor');
        $staff->roles()->attach($customerRole->getKey());

        $response = $this->putJson("/api/admin/staff/{$staff->id}/roles", ['role_names' => ['playback_observer']]);
        $response->assertOk();

        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $customerRole->getKey(), 'model_id' => $staff->getKey(), 'model_type' => User::class,
        ]);
        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => Role::query()->where('name', 'playback_observer')->value('id'),
            'model_id' => $staff->getKey(), 'model_type' => User::class,
        ]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'role.created']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'staff.roles.updated']);
    }

    public function test_admin_can_list_administrative_roles_with_the_view_permission(): void
    {
        $this->getJson('/api/admin/roles')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonFragment(['name' => 'super-admin']);
    }

    public function test_user_role_checks_read_the_spatie_role_relationship(): void
    {
        $admin = $this->admin->fresh();

        $this->assertSame(['super-admin'], $admin->getRoleNames()->all());
        $this->assertTrue($admin->hasRole('super-admin'));
    }

    public function test_admin_can_list_permission_catalog_with_an_empty_search_filter(): void
    {
        $this->getJson('/api/admin/permissions?search=')
            ->assertOk()
            ->assertJsonStructure(['data', 'groups'])
            ->assertJsonFragment(['name' => 'admin.permissions.view']);
    }

    public function test_admin_can_list_audit_logs_with_empty_optional_filters(): void
    {
        $this->getJson('/api/admin/audit-logs?actor_id=&action=&resource_id=&from=&to=&page=1&per_page=25')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_admin_notifications_are_persisted_scoped_and_can_be_read_archived_and_restored(): void
    {
        $this->admin->notify(new class extends BaseNotification
        {
            public function via(object $notifiable): array
            {
                return ['database'];
            }

            public function toDatabase(object $notifiable): array
            {
                return ['subject' => 'Media processing complete', 'data' => [
                    'notification_type' => 'transcode_completed',
                    'message' => 'The source asset is ready.',
                ]];
            }
        });

        $notification = $this->admin->notifications()->firstOrFail();

        $this->getJson('/api/admin/notifications?status=unread')
            ->assertOk()
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.title', 'Media processing complete')
            ->assertJsonPath('data.0.message', 'The source asset is ready.')
            ->assertJsonPath('counts.unread', 1);
        $this->getJson('/api/admin/notifications/counts')
            ->assertOk()
            ->assertJsonPath('data.unread', 1);

        $this->patchJson("/api/admin/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonStructure(['data' => ['read_at']]);
        $this->assertNotNull($notification->fresh()->read_at);

        $this->getJson('/api/admin/notifications?status=unread')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('counts.unread', 0);

        $this->postJson("/api/admin/notifications/{$notification->id}/archive")->assertOk();
        $this->getJson('/api/admin/notifications?status=archived')
            ->assertOk()
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('counts.archived', 1);

        $this->postJson("/api/admin/notifications/{$notification->id}/restore")->assertOk();
        $this->getJson('/api/admin/notifications?status=all')
            ->assertOk()
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('counts.archived', 0);

        $anotherUser = User::factory()->create();
        $anotherUser->notify(new class extends BaseNotification
        {
            public function via(object $notifiable): array
            {
                return ['database'];
            }

            public function toDatabase(object $notifiable): array
            {
                return ['subject' => 'Private notice'];
            }
        });
        $otherNotification = $anotherUser->notifications()->firstOrFail();

        $this->patchJson("/api/admin/notifications/{$otherNotification->id}/read")->assertNotFound();
    }

    public function test_role_details_cannot_expose_a_customer_role(): void
    {
        $customerRole = Role::query()->create([
            'name' => 'subscriber',
            'title' => 'Subscriber',
            'guard_name' => 'web',
            'is_fixed' => true,
            'is_admin_role' => false,
            'is_active' => true,
        ]);

        $this->getJson("/api/admin/roles/{$customerRole->getKey()}")
            ->assertForbidden();
    }

    public function test_admin_login_is_rate_limited_after_five_attempts(): void
    {
        $credentials = ['email' => 'unknown-admin@example.test', 'password' => 'not-a-valid-password'];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/admin/login', $credentials)->assertUnprocessable();
        }

        $this->postJson('/api/admin/login', $credentials)->assertTooManyRequests();
    }

    public function test_deactivating_staff_revokes_tokens_and_inactive_users_cannot_reuse_them(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('content_editor');
        $token = $staff->createToken('admin-ui', ['admin'], now()->addHours(8));

        $this->patchJson("/api/admin/staff/{$staff->getKey()}/status", ['active' => false])->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->getKey()]);
        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)->getJson('/api/admin/me')->assertUnauthorized();
    }

    public function test_valid_token_for_inactive_admin_is_denied_by_permission_middleware(): void
    {
        $staff = User::factory()->create(['status' => 0]);
        $staff->assignRole('content_editor');
        $token = $staff->createToken('admin-ui', ['admin'], now()->addHours(8));

        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)->getJson('/api/admin/me')->assertForbidden();
    }

    public function test_valid_token_for_banned_admin_is_denied_by_permission_middleware(): void
    {
        $staff = User::factory()->create(['status' => 1, 'is_banned' => 1]);
        $staff->assignRole('content_editor');
        $token = $staff->createToken('admin-ui', ['admin'], now()->addHours(8));

        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)->getJson('/api/admin/me')->assertForbidden();
    }

    public function test_invitation_is_single_use_and_audit_masks_email_address(): void
    {
        Mail::fake();
        config(['services.admin_ui.url' => 'https://admin.example.test']);

        $this->postJson('/api/admin/staff/invitations', [
            'email' => 'new.staff@example.test',
            'role_names' => ['content_editor'],
        ])->assertCreated();

        $invitation = AdminInvitation::query()->firstOrFail();
        $audit = AdminAuditLog::query()->where('action', 'staff.invitation.created')->firstOrFail();
        $this->assertSame('n***@example.test', $audit->after_values['email']);
        $this->assertNotSame('new.staff@example.test', $audit->after_values['email']);

        $token = null;
        Mail::assertSent(AdminStaffInvitationMail::class, function (AdminStaffInvitationMail $mail) use (&$token): bool {
            parse_str((string) parse_url($mail->invitationUrl, PHP_URL_QUERY), $query);
            $token = $query['token'] ?? null;

            return str_starts_with($mail->invitationUrl, 'https://admin.example.test/admin/invitations/accept');
        });
        $this->assertIsString($token);
        $this->assertNotSame($token, $invitation->token_hash);

        $this->postJson('/api/admin/invitations/accept', [
            'token' => $token,
            'first_name' => 'New',
            'last_name' => 'Staff',
            'password' => 'A-Long-Secure-Password-123!',
            'password_confirmation' => 'A-Long-Secure-Password-123!',
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'new.staff@example.test']);
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->postJson('/api/admin/invitations/accept', [
            'token' => $token,
            'first_name' => 'Again',
            'password' => 'A-Long-Secure-Password-123!',
            'password_confirmation' => 'A-Long-Secure-Password-123!',
        ])->assertUnprocessable();
    }

    public function test_console_bootstrap_grants_only_the_first_super_admin(): void
    {
        $this->admin->removeRole('super-admin');
        $this->artisan('ott:rbac:bootstrap-super-admin', [
            'email' => $this->admin->email,
            '--force' => true,
        ])->assertExitCode(0);

        $roleId = Role::query()->where('name', 'super-admin')->value('id');
        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $roleId, 'model_id' => $this->admin->id, 'model_type' => User::class,
        ]);
        $secondUser = User::factory()->create();
        $this->artisan('ott:rbac:bootstrap-super-admin', [
            'email' => $secondUser->email,
            '--force' => true,
        ])->assertExitCode(1);
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => null,
            'action' => 'staff.bootstrap.super_admin',
        ]);
    }
}
