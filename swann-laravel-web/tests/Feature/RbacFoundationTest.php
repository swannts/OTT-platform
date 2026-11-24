<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Policies\AdminRolePolicy;
use App\Support\Rbac\RbacRegistry;
use Database\Seeders\RbacFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
