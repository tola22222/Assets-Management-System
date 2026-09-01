<?php

namespace App\Http\Middleware;

use App\Services\PermissionRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Route guard for the Role & Permission system: permission:module,ability
 *
 *   Route::get('/roles', ...)->middleware('permission:roles,view');
 *
 * Sits alongside the older `role:` middleware rather than replacing it. Where
 * both are present a request must satisfy both, which is what keeps the
 * existing guards authoritative while permissions are layered on.
 *
 * `hide` is rejected outright as a route guard — it is a presentation flag, and
 * accepting it here would let a UI-only setting look like an access control.
 */
class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $module, string $ability = 'view')
    {
        if (! Auth::check()) {
            abort(401, 'Unauthenticated.');
        }

        if ($ability === 'hide' || ! PermissionRegistry::isModule($module) || ! PermissionRegistry::isAbility($ability)) {
            abort(500, "Route declares an unknown permission: {$module}.{$ability}");
        }

        if (! Auth::user()->hasPermission($module, $ability)) {
            $label = PermissionRegistry::MODULES[$module][0] ?? $module;

            abort(403, "You do not have permission to {$ability} in {$label}.");
        }

        return $next($request);
    }
}
