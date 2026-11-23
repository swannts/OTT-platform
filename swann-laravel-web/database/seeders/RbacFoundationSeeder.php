<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Rbac\RbacRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/** Safe, rerunnable RBAC foundation seeder. Does not assign roles to users. */
class RbacFoundationSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function (): void {
            $permissions = [];
            foreach (RbacRegistry::permissions() as $name => $description) {
                $permissions[$name] = Permission::firstOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    ['is_fixed' => true],
                );
                $permissions[$name]->forceFill(['is_fixed' => true])->save();
            }

            foreach (RbacRegistry::roles() as $name => $title) {
                $role = Role::firstOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    ['title' => $title, 'is_fixed' => true],
                );
                $role->forceFill(['title' => $title, 'is_fixed' => true])->save();
                // The canonical mappings are authoritative. Existing user-to-role
                // assignments remain untouched; only this role's permission set is reconciled.
                $role->syncPermissions(array_map(
                    static fn (string $permission): Permission => $permissions[$permission],
                    RbacRegistry::rolePermissions()[$name],
                ));
            }

            // Explicit compatibility grants keep known legacy staff role names able to
            // enter the current admin UI. This does not add any user-role assignments.
            foreach (['admin', 'demo_admin', 'channel_manager', 'video_editor'] as $legacyRoleName) {
                $legacyRole = Role::query()->where('name', $legacyRoleName)->where('guard_name', 'web')->first();
                if ($legacyRole) {
                    $legacyRole->givePermissionTo($permissions['admin.access'], $permissions['admin.dashboard.view']);
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
