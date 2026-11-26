<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use LogicException;
use RuntimeException;

/** Creates the optional local admin from an explicitly configured private password. */
class LocalAdminBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('LocalAdminBootstrapSeeder may only run in local or testing.');
        }

        $password = (string) config('app.demo_seed_password');
        if ($password === '') {
            return;
        }

        $user = User::withTrashed()->where('email', 'admin@swann.com')->first();
        $created = false;

        if ($user === null) {
            $user = User::query()->create([
                'first_name' => 'Local',
                'last_name' => 'Administrator',
                'email' => 'admin@swann.com',
                'password' => Hash::make($password),
                'user_type' => 'admin',
                'status' => 1,
                'is_banned' => 0,
                'email_verified_at' => now(),
            ]);
            $created = true;
        } elseif ($user->trashed()) {
            throw new RuntimeException('admin@swann.com is soft-deleted; restore it through the account recovery process.');
        } elseif ($user->user_type !== 'admin') {
            throw new RuntimeException('admin@swann.com belongs to a non-admin account; refusing to elevate it.');
        } elseif (! $user->status || $user->is_banned) {
            throw new RuntimeException('The local admin account is inactive or banned; restore it through account recovery.');
        }

        $role = Role::query()
            ->where('name', 'super-admin')
            ->where('guard_name', 'web')
            ->where('is_active', true)
            ->firstOrFail();

        if ($created) {
            $user->roles()->syncWithoutDetaching([$role->getKey()]);
        } elseif (! $user->hasRole($role)) {
            throw new RuntimeException('The existing local admin lacks the Super Admin role; grant access through the authorized RBAC workflow.');
        }
    }
}
