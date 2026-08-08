<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserBan
{
    /**
     * Log out actively-banned frontend users on any web request and send them to
     * the account-suspended page. Only the "web" guard is checked so admin requests
     * are never affected.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('account.suspended')) {
            return $next($request);
        }

        $user = Auth::guard('web')->user();

        if ($user && $user->isBanned()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('account.suspended')
                ->with('suspended', $user->banNotice());
        }

        return $next($request);
    }
}
