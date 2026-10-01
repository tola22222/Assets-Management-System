<?php

namespace App\Http\Middleware;

use App\Services\PermissionRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * role:operations_hr_manager,finance_manager — the base-role route guard.
 *
 * A listed base role passes, exactly as before. Anyone else passes only when
 * one of their ACTIVE custom roles (Roles & Permissions) grants the ability
 * this route needs — read off the route by PermissionRegistry::abilityForRoute()
 * — so a custom role adds access on top of the base role and never takes any
 * away. The base role's own baseline is deliberately not consulted here: it is
 * a transcription of these guards, not an extra way through them.
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (! Auth::check()) {
            abort(401, 'Unauthenticated.');
        }

        $user = Auth::user();

        if (in_array($user->role, $roles)) {
            return $next($request);
        }

        $route = $request->route();
        $needed = $route ? PermissionRegistry::abilityForRoute($request->method(), $route->uri(), $route->parameters()) : null;

        if ($needed && $user->hasCustomPermission(...$needed)) {
            return $next($request);
        }

        abort(403, 'Unauthorized action.');
    }
}
