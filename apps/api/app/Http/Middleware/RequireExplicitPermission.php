<?php

namespace App\Http\Middleware;

use App\Support\Rbac\ExplicitPermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Permission middleware that bypasses Gate::before role shortcuts. */
class RequireExplicitPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!ExplicitPermission::allows($user, $permission)) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        return $next($request);
    }
}
