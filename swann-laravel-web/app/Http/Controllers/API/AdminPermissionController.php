<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Rbac\ExplicitPermission;
use App\Support\Rbac\RbacRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'admin.permissions.view'), 403);

        $request->validate(['search' => ['sometimes', 'string', 'max:125'], 'module' => ['sometimes', 'string', 'max:40']]);

        $registered = RbacRegistry::permissions();
        $query = Permission::query()->where('guard_name', 'web')->whereIn('name', array_keys($registered));
        if ($request->filled('search')) {
            $term = strtolower((string) $request->string('search'));
            $query->where('name', 'like', '%'.addcslashes($term, '%_\\').'%');
        }
        if ($request->filled('module')) {
            $module = (string) $request->string('module');
            $query->where('name', 'like', $module.'.%');
        }

        $permissions = $query->orderBy('name')->get()->map(function (Permission $permission) use ($registered): array {
            return [
                'name' => $permission->name,
                'description' => $registered[$permission->name],
                'module' => explode('.', $permission->name, 2)[0],
                'roles' => Role::query()->where('guard_name', 'web')
                    ->where('is_admin_role', true)
                    ->whereHas('permissions', fn ($query) => $query->whereKey($permission->getKey()))
                    ->orderBy('title')->get(['id', 'name', 'title']),
            ];
        });

        $groups = $permissions->groupBy('module')->map(fn ($items) => $items->values())->all();

        return response()->json(['data' => $permissions->values(), 'groups' => $groups]);
    }
}
