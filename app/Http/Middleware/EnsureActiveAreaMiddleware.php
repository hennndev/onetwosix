<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAreaMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user) {
            if (! $user->hasMultiAreaAccess() && $user->getAssignedArea()) {
                session(['active_area_id' => $user->getAssignedArea()->id]);
            } elseif (! session()->has('active_area_id')) {
                $activeArea = $user->resolveActiveArea();
                if ($activeArea) {
                    session(['active_area_id' => $activeArea->id]);
                }
            }
        }

        return $next($request);
    }
}
