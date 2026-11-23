<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminApiController extends Controller
{
    private const ADMIN_ROLES = ['admin', 'super-admin', 'demo_admin'];

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password) || !$this->isAdmin($user) || (int) $user->status !== 1 || (int) $user->is_banned === 1) {
            return response()->json(['message' => 'The provided credentials are invalid.'], 422);
        }

        $token = $user->createToken('ott-admin-ui', ['admin'], now()->addHours(8));

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $this->userPayload($user),
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

    private function isAdmin(User $user): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->email,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first(fn ($role) => in_array($role, self::ADMIN_ROLES, true)),
        ];
    }
}
