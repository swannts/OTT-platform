<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Support\Rbac\AdminAuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BootstrapSuperAdmin extends Command
{
    protected $signature = 'ott:rbac:bootstrap-super-admin {email} {--force : Skip the interactive confirmation}';

    protected $description = 'Grant the first active Super Admin role to an existing account';

    public function handle(AdminAuditLogger $audit): int
    {
        $email = mb_strtolower((string) $this->argument('email'));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])
            ->where('status', 1)->where('is_banned', 0)->first();
        if (!$user) {
            $this->error('No active, non-banned user account was found for that email.');
            return self::FAILURE;
        }

        $role = Role::query()->where('name', 'super-admin')->where('guard_name', 'web')
            ->where('is_admin_role', true)->where('is_active', true)->first();
        if (!$role) {
            $this->error('The Super Admin role is not available. Run the RBAC foundation seeder first.');
            return self::FAILURE;
        }

        if (!$this->option('force') && !$this->confirm("Grant Super Admin access to {$user->email}?")) {
            $this->info('No roles were changed.');
            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($user, $role, $audit): void {
                $lockedRole = Role::query()->whereKey($role->getKey())->lockForUpdate()->firstOrFail();
                $activeSuperAdminExists = User::query()->where('status', 1)->where('is_banned', 0)
                    ->whereHas('roles', fn ($roles) => $roles->where('roles.name', 'super-admin')->where('roles.is_active', true))
                    ->lockForUpdate()->exists();
                if ($activeSuperAdminExists) {
                    throw new \RuntimeException('An active Super Admin already exists; use the staff access screen to grant additional access.');
                }

                $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
                $lockedUser->assignRole($lockedRole);
                $audit->record(null, 'staff.bootstrap.super_admin', User::class, $lockedUser->getKey(), null, [
                    'user_id' => $lockedUser->getKey(),
                    'email' => $this->maskedEmail($lockedUser->email),
                    'role_names' => ['super-admin'],
                ]);
            });
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->info('The first Super Admin was granted access.');
        return self::SUCCESS;
    }

    private function maskedEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        return ($local === '' ? '' : mb_substr($local, 0, 1).'***').'@'.$domain;
    }
}
