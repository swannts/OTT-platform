<?php

namespace App\Support\Rbac;

use App\Models\Permission;
use App\Models\User;

/** Checks Spatie grants directly, without consulting Laravel Gate overrides. */
final class ExplicitPermission
{
    public static function allows(User $user, string $name): bool
    {
        $accountIsEligible = $user->newQuery()
            ->whereKey($user->getKey())
            ->where('status', 1)
            ->where('is_banned', 0)
            ->exists();

        if (! $accountIsEligible) {
            return false;
        }

        $permission = Permission::query()
            ->where('name', $name)
            ->where('guard_name', 'web')
            ->first();

        if ($permission === null) {
            return false;
        }

        return $user->permissions()->whereKey($permission->getKey())->exists()
            || $user->roles()->where('roles.is_active', true)->whereHas('permissions', static function ($query) use ($permission): void {
                $query->whereKey($permission->getKey());
            })->exists();
    }
}
