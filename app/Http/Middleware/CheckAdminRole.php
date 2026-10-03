<?php

namespace App\Http\Middleware;

use App\Models\GeneralSetting;
use App\Support\FeatureAccess;
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

        // Feature yang diblokir lewat .env (BLOCKED_FEATURES) ditolak untuk
        // SEMUA role, termasuk Administrator — konsisten dengan sidebar yang
        // menyembunyikan item feature tersebut.
        if (FeatureAccess::isRouteBlocked($currentRoute)) {
            abort(403, 'Fitur ini sedang dinonaktifkan.');
        }

        if (str_starts_with($currentRoute, 'admin.settings.daily-auth-code.')) {
            // Endpoint workflow POS (dipakai tombol "Request Auth Code" &
            // validasi kode saat checkout): tidak mengekspos kode apa pun —
            // verify hanya membandingkan, send-email mengirim ke tujuan yang
            // dikonfigurasi. Bebas untuk semua user area admin; kalau tidak,
            // kasir selalu 403 "Akses ditolak" saat minta/validasi kode.
            if (in_array($currentRoute, [
                'admin.settings.daily-auth-code.verify',
                'admin.settings.daily-auth-code.send-email',
            ], true)) {
                return $next($request);
            }

            // Endpoint manajemen kode (lihat kode, regenerate, override):
            // tetap terbatas Administrator + email whitelist.
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
