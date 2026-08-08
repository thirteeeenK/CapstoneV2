<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserOnboarding
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && empty($user->preferences_embedding)) {
            // Skip onboarding check for onboarding routes, logout, admin routes, the
            // account-suspended page, or API calls
            if (
                !$request->routeIs('onboarding.*') &&
                !$request->routeIs('logout') &&
                !$request->routeIs('admin.*') &&
                !$request->routeIs('account.suspended') &&
                !$request->is('admin*') &&
                !$request->expectsJson()
            ) {
                return redirect()->route('onboarding.index');
            }
        }

        return $next($request);
    }
}
