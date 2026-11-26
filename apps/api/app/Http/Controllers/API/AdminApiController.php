<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Rbac\ExplicitPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminApiController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $authenticated = DB::transaction(function () use ($credentials): ?array {
            $user = User::query()->where('email', $credentials['email'])->lockForUpdate()->first();

            if (! $user || ! Hash::check($credentials['password'], $user->password) || ! ExplicitPermission::allows($user, 'admin.access') || (int) $user->status !== 1 || (int) $user->is_banned === 1) {
                return null;
            }

            $token = $user->createToken('ott-admin-ui', ['admin'], now()->addHours(8));

            return ['user' => $user, 'token' => $token->plainTextToken];
        });

        if (! $authenticated) {
            return response()->json(['message' => 'The provided credentials are invalid.'], 422);
        }

        return response()->json([
            'token' => $authenticated['token'],
            'user' => $this->userPayload($authenticated['user']),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    public function dashboard(): JsonResponse
    {
        $media = DB::table('entertainments')->where('status', 1)->whereNull('deleted_at');

        return response()->json([
            'viewers' => DB::table('users')->where('user_type', 'user')->whereNull('deleted_at')->count(),
            'subscribers' => DB::table('users')->where('user_type', 'user')->where('is_subscribe', 1)->whereNull('deleted_at')->count(),
            'movies' => (clone $media)->where('type', 'movie')->count(),
            'series' => (clone $media)->where('type', 'tvshow')->count(),
            'videos' => DB::table('videos')->where('status', 1)->whereNull('deleted_at')->count(),
            'liveChannels' => DB::table('live_tv_channel')->where('status', 1)->whereNull('deleted_at')->count(),
        ]);
    }

    private function userPayload(User $user): array
    {
        $roles = $user->roles()->where('roles.is_active', true)->where('roles.is_admin_role', true)->get();
        $permissions = $user->getDirectPermissions()->pluck('name')
            ->merge($roles->load('permissions')->flatMap(fn ($role) => $role->permissions->pluck('name')))
            ->unique()->sort()->values();

        return [
            'id' => $user->id,
            'name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->email,
            'email' => $user->email,
            'role' => $roles->first()?->name,
            'roles' => $roles->pluck('name')->values(),
            'permissions' => $permissions,
        ];
    }
}
