<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use LogicException;
use RuntimeException;

/** Creates one local demo login for each canonical administrative role. */
class LocalAdminRoleAccountsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('LocalAdminRoleAccountsSeeder may only run in local or testing.');
        }

        $this->call(RbacFoundationSeeder::class);

        $password = (string) config('app.demo_seed_password');
        if ($password === '') {
            return;
        }

        $passwordHash = Hash::make($password);
        $this->call(LocalAdminBootstrapSeeder::class);

        foreach (RbacRegistry::roles() as $roleName => $roleTitle) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->where('is_active', true)
                ->firstOrFail();

            // Reuse the established local Super Admin login; keep the remaining
            // role accounts distinct so each role can be tested independently.
            $email = $roleName === 'super-admin'
                ? 'admin@swann.com'
                : 'role.'.str_replace('_', '-', $roleName).'@noorcinema.test';

            $user = User::withTrashed()->where('email', $email)->first();
            if ($user?->trashed()) {
                throw new RuntimeException("The demo role account {$email} is soft-deleted; restore or remove it manually before seeding.");
            }
            if ($user && $user->user_type !== 'admin') {
                throw new RuntimeException("The demo role email {$email} belongs to a non-admin account; refusing to elevate it.");
            }
            if ($user && (! $user->status || $user->is_banned)) {
                throw new RuntimeException("The demo role account {$email} is inactive or banned; restore it through account recovery.");
            }

            if ($user !== null) {
                if (! $user->hasRole($role)) {
                    throw new RuntimeException("The existing demo account {$email} is not assigned its expected role; refusing to change access.");
                }

                continue;
            }

            $user = User::query()->create([
                'first_name' => $roleName === 'super-admin' ? 'Local' : 'Role Demo',
                'last_name' => $roleName === 'super-admin' ? 'Administrator' : $roleTitle,
                'email' => $email,
                'password' => $passwordHash,
                'user_type' => 'admin',
                'status' => 1,
                'is_banned' => 0,
                'email_verified_at' => now(),
            ]);

            $user->syncRoles([$role->name]);
        }
    }
}
