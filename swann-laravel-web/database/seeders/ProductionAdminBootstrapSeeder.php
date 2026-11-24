<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/** Creates the first production Super Admin only from explicit deployment secrets. */
class ProductionAdminBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment(['local', 'testing'])) {
            throw new LogicException('ProductionAdminBootstrapSeeder cannot run in local or testing environments.');
        }

        $email = trim((string) config('app.admin_bootstrap.email'));
        $password = (string) config('app.admin_bootstrap.password');
        $name = trim((string) config('app.admin_bootstrap.name', 'Platform Administrator'));

        if ($email === '' && $password === '') {
            $this->call(RbacFoundationSeeder::class);

            return;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 16) {
            throw new InvalidArgumentException('Set a valid ADMIN_BOOTSTRAP_EMAIL and an ADMIN_BOOTSTRAP_PASSWORD of at least 16 characters.');
        }

        $this->call(RbacFoundationSeeder::class);

        DB::transaction(function () use ($email, $password, $name): void {
            $user = User::query()->where('email', $email)->lockForUpdate()->first();

            if ($user === null) {
                $parts = preg_split('/\s+/', $name, 2) ?: ['Platform Administrator'];
                $user = User::query()->create([
                    'first_name' => $parts[0],
                    'last_name' => $parts[1] ?? '',
                    'email' => $email,
                    'password' => Hash::make($password),
                    'user_type' => 'admin',
                    'status' => 1,
                    'is_banned' => 0,
                    'email_verified_at' => now(),
                ]);
            } elseif ($user->user_type !== 'admin') {
                throw new RuntimeException('The configured bootstrap email belongs to a non-admin account; refusing to elevate it.');
            } elseif (! $user->status || $user->is_banned) {
                throw new RuntimeException('The configured bootstrap account is inactive or banned; restore it through the account recovery process.');
            }

            $superAdminRole = Role::query()->where('name', 'super-admin')->where('guard_name', 'web')->firstOrFail();
            $user->roles()->syncWithoutDetaching([$superAdminRole->getKey()]);
        });
    }
}
