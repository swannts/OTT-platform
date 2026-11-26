<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Policies\AdminRolePolicy;
use App\Support\Rbac\AdminAuditLogger;
use App\Support\Rbac\ExplicitPermission;
use App\Support\Rbac\RbacRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminRoleManagementController extends Controller
{
    public function __construct(private readonly AdminAuditLogger $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless(app(AdminRolePolicy::class)->viewAny($request->user()), 403);

        $query = Role::query()->where('guard_name', 'web')->where('is_admin_role', true)->withCount('users');
        if ($request->filled('search')) {
            $search = '%'.addcslashes((string) $request->string('search'), '%_\\').'%';
            $query->where(fn ($roles) => $roles->where('title', 'like', $search)->orWhere('name', 'like', $search));
        }
        if ($request->has('scope') && in_array($request->input('scope'), ['system', 'custom'], true)) {
            $query->where('is_fixed', $request->input('scope') === 'system');
        }
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $roles = $query->orderBy('title')->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return response()->json([
            'data' => collect($roles->items())->map(fn (Role $role) => $this->serializeRole($role))->values(),
            'meta' => ['current_page' => $roles->currentPage(), 'last_page' => $roles->lastPage(), 'per_page' => $roles->perPage(), 'total' => $roles->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(app(AdminRolePolicy::class)->create($request->user()), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:191'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array', 'max:100'],
            'permissions.*' => ['required', 'string', 'distinct'],
        ]);
        $permissionNames = $data['permissions'] ?? [];
        $this->validatePermissionChanges($request, $permissionNames);

        $name = Str::slug($data['title'], '_');
        abort_if($name === '' || strlen($name) > 125, 422, 'The role name is invalid.');
        abort_if(Role::query()->where('name', $name)->where('guard_name', 'web')->exists(), 422, 'A role with this name already exists.');
        abort_if(in_array($name, array_keys(RbacRegistry::roles()), true), 422, 'System role names are reserved.');

        $role = DB::transaction(function () use ($data, $name, $permissionNames, $request): Role {
            $role = Role::query()->create([
                'name' => $name,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'guard_name' => 'web',
                'is_fixed' => false,
                'is_admin_role' => true,
                'is_active' => $data['is_active'] ?? true,
            ]);
            if ($permissionNames !== []) {
                $role->syncPermissions($this->permissionsByName($permissionNames));
            }
            $this->audit->record($request->user(), 'role.created', Role::class, $role->getKey(), null, $this->auditRole($role));

            return $role->load('permissions');
        });

        return response()->json(['data' => $this->serializeRole($role)], 201);
    }

    public function show(Request $request, Role $role): JsonResponse
    {
        abort_unless(app(AdminRolePolicy::class)->view($request->user(), $role), 403);

        $role->load('permissions')->loadCount('users');
        $staff = $role->users()->where('users.deleted_at', null)
            ->select('users.id', 'users.first_name', 'users.last_name', 'users.email', 'users.status')
            ->limit(100)->get();
        $history = AdminAuditLog::query()->where('resource_type', Role::class)
            ->where('resource_id', (string) $role->getKey())->latest('created_at')->limit(25)->get();

        return response()->json([
            'data' => $this->serializeRole($role),
            'staff' => $staff,
            'history' => $history,
        ]);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        abort_unless(app(AdminRolePolicy::class)->update($request->user(), $role), 403);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'min:2', 'max:191'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array', 'max:100'],
            'permissions.*' => ['required', 'string', 'distinct'],
        ]);
        $permissionNames = $data['permissions'] ?? null;
        if ($permissionNames !== null) {
            $this->validatePermissionChanges($request, $permissionNames);
        }

        $before = $this->auditRole($role->load('permissions'));
        $updated = DB::transaction(function () use ($data, $permissionNames, $role, $request, $before): Role {
            $role->fill(collect($data)->except('permissions')->all());
            if (isset($data['title'])) {
                $name = Str::slug($data['title'], '_');
                abort_if($name === '' || Role::query()->where('name', $name)->where('guard_name', 'web')->where('id', '!=', $role->getKey())->exists(), 422, 'A role with this name already exists or is invalid.');
                abort_if(in_array($name, array_keys(RbacRegistry::roles()), true), 422, 'System role names are reserved.');
                $role->name = $name;
            }
            $role->save();
            if ($permissionNames !== null) {
                $role->syncPermissions($this->permissionsByName($permissionNames));
            }
            $role->load('permissions');
            $this->audit->record($request->user(), 'role.updated', Role::class, $role->getKey(), $before, $this->auditRole($role));

            return $role;
        });

        return response()->json(['data' => $this->serializeRole($updated)], 200);
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        abort_unless(app(AdminRolePolicy::class)->delete($request->user(), $role), 403);
        abort_if($role->users()->exists(), 409, 'Remove this role from assigned staff before deleting it.');

        DB::transaction(function () use ($role, $request): void {
            $snapshot = $this->auditRole($role->load('permissions'));
            $this->audit->record($request->user(), 'role.deleted', Role::class, $role->getKey(), $snapshot, null);
            $role->delete();
        });

        return response()->json(['message' => 'Role deleted.']);
    }

    private function validatePermissionChanges(Request $request, array $names): void
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'admin.permissions.manage'), 403);
        $unknown = array_diff($names, array_keys(RbacRegistry::permissions()));
        abort_if($unknown !== [], 422, 'The permission list contains unrecognized permissions.');
    }

    private function permissionsByName(array $names)
    {
        return Permission::query()->where('guard_name', 'web')->whereIn('name', $names)->get();
    }

    private function auditRole(Role $role): array
    {
        return [
            'name' => $role->name,
            'title' => $role->title,
            'description' => $role->description,
            'is_fixed' => (bool) $role->is_fixed,
            'is_active' => (bool) $role->is_active,
            'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
        ];
    }

    private function serializeRole(Role $role): array
    {
        $permissionNames = $role->relationLoaded('permissions')
            ? $role->permissions->pluck('name')
            : $role->permissions()->pluck('permissions.name');
        $permissions = RbacRegistry::permissions();

        return [
            'id' => $role->getKey(),
            'name' => $role->name,
            'title' => $role->title,
            'description' => $role->description,
            'is_system' => (bool) $role->is_fixed,
            'is_active' => (bool) $role->is_active,
            'staff_count' => $role->users_count ?? $role->users()->count(),
            'permissions' => $permissionNames->values()->all(),
            'permission_descriptions' => $permissionNames->mapWithKeys(fn (string $name) => [$name => $permissions[$name] ?? 'Legacy permission'])->all(),
        ];
    }
}
