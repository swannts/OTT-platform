<?php

namespace Database\Seeders\Auth;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Class PermissionRoleTableSeeder.
 */
class PermissionRoleTableSeeder extends Seeder
{
    /**
     * Run the database seed.
     *
     * @return void
     */
    public function run()
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('PermissionRoleTableSeeder is a legacy development seeder and may only run in local or testing environments.');
        }

        // Keep canonical RBAC permissions and mappings intact. This legacy
        // fixture adds its compatibility permissions without resetting them.
        DB::transaction(function (): void {
            $admin = Role::firstOrCreate(['name' => 'admin', 'title' => 'Admin', 'is_fixed' => true]);
            $demo_admin = Role::firstOrCreate(['name' => 'demo_admin', 'title' => 'Demo Admin', 'is_fixed' => true]);
            Role::firstOrCreate(['name' => 'user', 'title' => 'user', 'is_fixed' => true]);

            Permission::firstOrCreate(['name' => 'edit_settings', 'is_fixed' => true]);
            Permission::firstOrCreate(['name' => 'view_logs', 'is_fixed' => true]);

            $modules = config('constant.MODULES', []);

            foreach ($modules as $module) {
                $legacyPermissions = ['view', 'add', 'edit', 'delete', 'restore', 'force_delete'];
                $module_name = strtolower(str_replace(' ', '_', $module['module_name']));
                foreach ($legacyPermissions as $value) {
                    $permission_name = $value.'_'.$module_name;
                    Permission::firstOrCreate(['name' => $permission_name, 'is_fixed' => true]);
                }
                if (isset($module['more_permission']) && is_array($module['more_permission'])) {
                    foreach ($module['more_permission'] as $value) {
                        $permission_name = $module_name.'_'.$value;
                        Permission::firstOrCreate(['name' => $permission_name, 'is_fixed' => true]);
                    }
                }

                if ($module['module_name'] === 'Clinic Categories') {
                    Permission::firstOrCreate(['name' => 'view_'.$module_name, 'is_fixed' => true]);
                }
            }

            // Keep legacy admin compatibility grants additive; never clear or
            // replace permissions owned by the canonical RBAC registry.
            $admin->givePermissionTo(Permission::all());
            $demo_admin->givePermissionTo(Permission::all());
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
