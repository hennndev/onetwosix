<?php

namespace App\Http\Middleware;

use App\Models\GeneralSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        $currentRoute = $request->route()?->getName();

        if (! $currentRoute) {
            return $next($request);
        }

        if (str_starts_with($currentRoute, 'admin.settings.daily-auth-code.')) {
            if ($user->hasRole('Administrator') && GeneralSetting::instance()->allowsDailyAuthCodeAccess($user->email)) {
                return $next($request);
            }

            abort(403, 'Akses ditolak.');
        }

        // Administrators have unrestricted access outside the daily auth code settings page
        if ($user->hasRole('Administrator')) {
            return $next($request);
        }

        // Check if any of the user's permissions (via their roles) match the current route
        $permissions = $user->getAllPermissions()->pluck('name');

        foreach ($permissions as $permission) {
            if (fnmatch($permission, $currentRoute)) {
                return $next($request);
            }
        }

        abort(403, 'Akses ditolak.');
    }
}
