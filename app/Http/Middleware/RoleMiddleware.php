<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Route names (beyond any `*.index` / `*.show`) that are pure read-only
     * views and therefore open to the view-only SUPERVISOR role.
     */
    private const SUPERVISOR_VIEW_ROUTES = [
        'dashboard',
        'pm-schedules.pdf',
        // Oil Audit pages are viewable; their forms are rendered disabled.
        'oil-audits.scan',
        'oil-audits.entry',
        'oil-audits.report',
        'oil-audits.history',
    ];

    public function handle(
        Request $request,
        Closure $next,
        ...$roles
    ): Response {
        if (! Auth::check()) {
            abort(403, 'Unauthorized');
        }

        $user = Auth::user();

        if (! in_array($user->role, $roles, true)) {
            abort(403, 'Unauthorized');
        }

        // SUPERVISOR is view-only: even on a route group that lists the
        // role, only GET/HEAD requests to read-only pages get through, so
        // create/edit forms and every write endpoint stay denied.
        if ($user->role === User::ROLE_SUPERVISOR && ! $this->isViewRequest($request)) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }

    private function isViewRequest(Request $request): bool
    {
        if (! $request->isMethodSafe()) {
            return false;
        }

        $name = $request->route()?->getName();

        if ($name === null) {
            return false;
        }

        return str_starts_with($name, 'reports.')
            || str_ends_with($name, '.index')
            || str_ends_with($name, '.show')
            || in_array($name, self::SUPERVISOR_VIEW_ROUTES, true);
    }
}
