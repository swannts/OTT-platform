<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Policies\AdminRolePolicy;
use App\Support\Rbac\AdminAuditLogger;
use App\Support\Rbac\ExplicitPermission;
use App\Support\Rbac\RbacRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminStaffController extends Controller
{
    public function __construct(private readonly AdminAuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'admin.staff.view'), 403);

        $query = User::query()->whereHas('roles', fn ($roles) => $roles->where('roles.is_admin_role', true));
        if ($request->filled('search')) {
            $term = '%'.addcslashes((string) $request->string('search'), '%_\\').'%';
            $query->where(fn ($users) => $users->where('email', 'like', $term)
                ->orWhere('first_name', 'like', $term)->orWhere('last_name', 'like', $term));
        }
        if ($request->has('status')) {
            $query->where('status', $request->boolean('status') ? 1 : 0);
        }

        $staff = $query->with(['roles' => fn ($roles) => $roles->where('roles.is_admin_role', true)->orderBy('title')])
            ->orderBy('first_name')->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return response()->json([
            'data' => collect($staff->items())->map(fn (User $user) => $this->serializeStaff($user))->values(),
            'meta' => ['current_page' => $staff->currentPage(), 'last_page' => $staff->lastPage(), 'per_page' => $staff->perPage(), 'total' => $staff->total()],
        ]);
    }

    public function updateRoles(Request $request, User $user): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'admin.staff.roles.update'), 403);
        abort_unless($this->isStaff($user), 404);

        $data = $request->validate([
            'role_names' => ['required', 'array', 'max:20'],
            'role_names.*' => ['required', 'string', 'distinct'],
            'current_password' => ['nullable', 'string', 'max:255'],
        ]);
        $desiredRoles = Role::query()->where('guard_name', 'web')->where('is_admin_role', true)
            ->where('is_active', true)->whereIn('name', $data['role_names'])->get();
        abort_if($desiredRoles->count() !== count($data['role_names']), 422, 'One or more selected roles are unavailable.');
        foreach ($desiredRoles as $role) {
            abort_unless(app(AdminRolePolicy::class)->assign($request->user(), $role), 403);
        }

        $currentNames = $this->activeAdminRoles($user)->pluck('name')->all();
        $desiredNames = $desiredRoles->pluck('name')->all();
        $superAdminChange = in_array('super-admin', array_unique([...$currentNames, ...$desiredNames]), true)
            && in_array('super-admin', $currentNames, true) !== in_array('super-admin', $desiredNames, true);
        if ($superAdminChange) {
            $superAdminRole = Role::query()
                ->where('guard_name', 'web')
                ->where('name', 'super-admin')
                ->where('is_admin_role', true)
                ->where('is_active', true)
                ->first();
            abort_unless(
                $superAdminRole && app(AdminRolePolicy::class)->assign($request->user(), $superAdminRole),
                403,
            );
            abort_unless(isset($data['current_password']) && Hash::check($data['current_password'], $request->user()->password), 403, 'Re-enter your password to change Super Admin access.');
            abort_if($request->user()->is($user), 422, 'A Super Admin cannot change their own Super Admin access.');
        }

        $before = $currentNames;
        DB::transaction(function () use ($request, $user, $desiredRoles, $before, $desiredNames): void {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $removesSuperAdmin = in_array('super-admin', $before, true) && ! in_array('super-admin', $desiredNames, true);
            if ($removesSuperAdmin) {
                abort_if($this->activeSuperAdminCount() <= 1, 409, 'The final active Super Admin cannot be removed.');
            }

            $nonAdminRoles = $lockedUser->roles()->where('roles.is_admin_role', false)->get();
            $lockedUser->syncRoles($nonAdminRoles->merge($desiredRoles)->pluck('name')->all());
            $this->audit->record($request->user(), 'staff.roles.updated', User::class, $lockedUser->getKey(), ['role_names' => $before], ['role_names' => $desiredNames]);
        });

        return response()->json(['data' => $this->serializeStaff($user->fresh()->load('roles'))]);
    }

    public function updateStatus(Request $request, User $user): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'admin.staff.status.update'), 403);
        $data = $request->validate(['active' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $user, $data): void {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($this->isStaff($lockedUser), 404);
            abort_if($request->user()->is($lockedUser), 422, 'You cannot deactivate your own staff account.');

            $before = ['active' => (bool) $lockedUser->status];
            $deactivatesSuperAdmin = $before['active'] && ! $data['active']
                && $this->activeAdminRoles($lockedUser)->contains(static fn (Role $role): bool => $role->name === 'super-admin');

            if ($deactivatesSuperAdmin) {
                abort_if($this->activeSuperAdminCount() <= 1, 409, 'The final active Super Admin cannot be deactivated.');
            }
            $lockedUser->forceFill(['status' => $data['active'] ? 1 : 0])->save();
            if (! $data['active']) {
                $lockedUser->tokens()->delete();
            }
            $this->audit->record($request->user(), 'staff.status.updated', User::class, $lockedUser->getKey(), $before, ['active' => (bool) $lockedUser->status]);
        });

        return response()->json(['data' => $this->serializeStaff($user->fresh()->load('roles'))]);
    }

    public function revokeSessions(Request $request, User $user): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'admin.staff.sessions.revoke'), 403);
        abort_unless($this->isStaff($user), 404);

        $tokenCount = $user->tokens()->count();
        DB::transaction(function () use ($request, $user, $tokenCount): void {
            $user->tokens()->delete();
            $this->audit->record($request->user(), 'staff.sessions.revoked', User::class, $user->getKey(), ['active_token_count' => $tokenCount], ['active_token_count' => 0]);
        });

        return response()->json(['message' => 'Staff sessions revoked.']);
    }

    public function effectivePermissions(Request $request, User $user): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'admin.staff.view'), 403);
        abort_unless($this->isStaff($user), 404);

        $permissionIds = $user->getDirectPermissions()->pluck('id')
            ->merge($this->activeAdminRoles($user)->load('permissions')->flatMap(fn (Role $role) => $role->permissions->pluck('id')))
            ->unique()->values();
        $descriptions = RbacRegistry::permissions();
        $permissions = Permission::query()->whereIn('id', $permissionIds)->where('guard_name', 'web')->orderBy('name')->get()
            ->map(fn (Permission $permission) => [
                'name' => $permission->name,
                'description' => $descriptions[$permission->name] ?? 'Legacy permission',
                'direct' => $user->getDirectPermissions()->contains(fn (Permission $direct) => $direct->is($permission)),
            ]);

        return response()->json(['data' => $permissions]);
    }

    private function activeAdminRoles(User $user)
    {
        return $user->roles()->where('roles.is_admin_role', true)->where('roles.is_active', true)->get();
    }

    private function isStaff(User $user): bool
    {
        return $user->roles()->where('roles.is_admin_role', true)->exists();
    }

    private function activeSuperAdminCount(): int
    {
        return User::query()->where('status', 1)->where('is_banned', 0)
            ->whereHas('roles', fn ($roles) => $roles->where('roles.name', 'super-admin')->where('roles.is_active', true))
            ->lockForUpdate()->get(['users.id'])->count();
    }

    private function serializeStaff(User $user): array
    {
        $roles = $user->relationLoaded('roles') ? $user->roles : $user->roles()->where('roles.is_admin_role', true)->get();

        return [
            'id' => $user->getKey(),
            'name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->email,
            'email' => $user->email,
            'active' => (bool) $user->status,
            'roles' => $roles->where('is_admin_role', true)->map(fn (Role $role) => ['name' => $role->name, 'title' => $role->title, 'active' => (bool) $role->is_active])->values(),
        ];
    }
}
