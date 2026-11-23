<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Support\Rbac\ExplicitPermission;

/** Authorization rules for administrative role management. */
class AdminRolePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'admin.roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->hasPermission($user, 'admin.roles.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'admin.roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $this->hasPermission($user, 'admin.roles.update') && !$role->is_fixed;
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->hasPermission($user, 'admin.roles.delete') && !$role->is_fixed;
    }

    /** The target role must not be accepted from a role name alone. */
    public function assign(User $user, Role $role): bool
    {
        $permission = $role->name === 'super-admin'
            ? 'admin.roles.super_admin.assign'
            : 'admin.roles.assign';

        return $this->hasPermission($user, $permission);
    }

    private function hasPermission(User $user, string $permission): bool
    {
        return ExplicitPermission::allows($user, $permission);
    }
}
