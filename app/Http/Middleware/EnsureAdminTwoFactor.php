<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminTwoFactor
{
    /**
     * Force every authenticated admin without confirmed TOTP into enrollment.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if ($admin && ! $admin->hasEnabledTwoFactor()) {
            if (
                ! $request->routeIs('admin.two-factor.*') &&
                ! $request->routeIs('admin.logout')
            ) {
                return redirect()->route('admin.two-factor.setup');
            }
        }

        return $next($request);
    }
}
