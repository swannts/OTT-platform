<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Policies\AdminRolePolicy;
use App\Support\Rbac\RbacRegistry;
use Database\Seeders\LocalAdminRoleAccountsSeeder;
use Database\Seeders\RbacFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class RbacFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_defines_twelve_roles_with_explicit_permission_sets(): void
    {
        $roles = RbacRegistry::rolePermissions();

        $this->assertCount(12, $roles);
        $this->assertSame(array_keys(RbacRegistry::roles()), array_keys($roles));
        $this->assertContains('admin.roles.super_admin.assign', $roles['super-admin']);
        $this->assertNotContains('admin.roles.super_admin.assign', $roles['platform_admin']);
        $this->assertNotContains('content.approve', $roles['content_editor']);
        $this->assertNotContains('content.publish', $roles['content_reviewer']);
        $this->assertContains('finance.refunds.approve', $roles['finance_manager']);
        $this->assertContains('media.drm.configure', $roles['media_manager']);
    }

    public function test_seeder_is_idempotent_and_does_not_assign_roles_to_users(): void
    {
        $user = User::factory()->create();

        $this->seed(RbacFoundationSeeder::class);
        $this->seed(RbacFoundationSeeder::class);

        $this->assertSame(12, Role::query()->whereIn('name', array_keys(RbacRegistry::roles()))->count());
        $this->assertCount(count(RbacRegistry::permissions()), Permission::query()->whereIn('name', array_keys(RbacRegistry::permissions()))->get());
        $this->assertSame([], $user->fresh()->getRoleNames()->all());
        $this->assertEqualsCanonicalizing(
            RbacRegistry::rolePermissions()['content_editor'],
            Role::findByName('content_editor', 'web')->permissions->pluck('name')->all(),
        );
    }

    public function test_local_demo_role_accounts_create_one_login_per_canonical_role_and_are_idempotent(): void
    {
        config(['app.demo_seed_password' => 'local-demo-password']);

        $this->seed(LocalAdminRoleAccountsSeeder::class);
        $editor = User::query()->where('email', 'role.content-editor@noorcinema.test')->firstOrFail();
        $originalPasswordHash = $editor->password;
        $replacementHash = Hash::make('password-set-by-the-developer');
        $editor->forceFill(['password' => $replacementHash])->save();
        $this->seed(LocalAdminRoleAccountsSeeder::class);

        $this->assertDatabaseCount('users', 12);

        foreach (array_keys(RbacRegistry::roles()) as $roleName) {
            $email = $roleName === 'super-admin'
                ? 'admin@swann.com'
                : 'role.'.str_replace('_', '-', $roleName).'@noorcinema.test';
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertSame('admin', $user->user_type);
            $this->assertTrue($user->hasRole($roleName));
            $this->assertSame([$roleName], $user->getRoleNames()->all());
            $this->assertTrue(Hash::check('local-demo-password', $user->password) || $user->email === $editor->email);
        }
        $this->assertSame($replacementHash, $editor->fresh()->password);
        $this->assertNotSame($originalPasswordHash, $replacementHash);
    }

    public function test_local_role_account_seeder_refuses_to_assign_a_role_to_an_existing_admin(): void
    {
        config(['app.demo_seed_password' => 'local-demo-password']);
        User::factory()->create([
            'email' => 'role.content-editor@noorcinema.test',
            'user_type' => 'admin',
            'password' => Hash::make('preserve-this-password'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->seed(LocalAdminRoleAccountsSeeder::class);
    }

    public function test_local_bootstrap_does_not_elevate_an_existing_admin_without_super_admin(): void
    {
        config(['app.demo_seed_password' => 'local-demo-password']);
        $user = User::factory()->create([
            'email' => 'admin@swann.com',
            'user_type' => 'admin',
            'password' => Hash::make('preserve-this-password'),
        ]);
        $passwordHash = $user->password;

        try {
            $this->seed(LocalAdminRoleAccountsSeeder::class);
            $this->fail('An existing administrator must not be silently elevated.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('authorized RBAC workflow', $exception->getMessage());
        }

        $this->assertSame($passwordHash, $user->fresh()->password);
        $this->assertFalse($user->fresh()->hasRole('super-admin'));
    }

    public function test_role_policy_uses_explicit_permissions_and_protects_fixed_roles(): void
    {
        $permission = Permission::create(['name' => 'admin.roles.update', 'guard_name' => 'web', 'is_fixed' => true]);
        $role = Role::create(['name' => 'custom_role', 'title' => 'Custom Role', 'guard_name' => 'web', 'is_fixed' => false, 'is_admin_role' => true, 'is_active' => true]);
        $fixedRole = Role::create(['name' => 'platform_admin', 'title' => 'Platform Admin', 'guard_name' => 'web', 'is_fixed' => true]);
        $user = User::factory()->create();
        $user->givePermissionTo($permission);
        $user->refresh();
        $this->assertDatabaseHas('model_has_permissions', [
            'permission_id' => $permission->id,
            'model_id' => $user->id,
            'model_type' => User::class,
        ]);
        $policy = new AdminRolePolicy();

        $this->assertTrue($policy->update($user, $role));
        $this->assertFalse($policy->update($user, $fixedRole));
        $this->assertFalse($policy->delete($user, $role));
    }

    public function test_legacy_admin_role_does_not_bypass_new_admin_api_permission_checks(): void
    {
        $legacyAdmin = Role::create(['name' => 'admin', 'title' => 'Admin', 'guard_name' => 'web', 'is_fixed' => true]);
        $user = User::factory()->create();
        $user->assignRole($legacyAdmin);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/me')
            ->assertForbidden();

        $legacyAdmin->givePermissionTo(Permission::create([
            'name' => 'admin.access',
            'guard_name' => 'web',
            'is_fixed' => true,
        ]));
        $user->refresh();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/me')
            ->assertOk();
    }
}
