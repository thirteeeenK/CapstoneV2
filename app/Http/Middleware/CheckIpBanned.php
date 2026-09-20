<?php

namespace App\Http\Middleware;

use App\Models\IpBan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckIpBanned
{
    /**
     * Block requests from IPs with an active temporary/permanent ban.
     * Warnings are notices only and do not block.
     * Admin login page is exempt so an admin can self-unban via another IP.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('admin.login') && $request->isMethod('get')) {
            return $next($request);
        }

        if ($request->routeIs('account.suspended')) {
            return $next($request);
        }

        $ip = $request->ip();

        // ponytail: simple DB check, cache if this becomes hot
        if ($ip && IpBan::isBanned($ip)) {
            $ban = IpBan::active()->where('ip_address', $ip)->latest()->first();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your IP has been banned.'.($ban?->reason ? ' Reason: '.$ban->reason : ''),
                ], 403);
            }

            return redirect()->route('account.suspended')
                ->with('suspended', [
                    'level' => $ban?->ban_level ?? 'temporary',
                    'reason' => $ban?->reason ?? 'Terms of service violation.',
                    'banned_at' => $ban?->banned_at?->format('M d, Y'),
                    'expires_at' => $ban?->expires_at?->format('M d, Y g:i A'),
                    'message' => $ban?->reason
                        ? 'Your IP has been banned. Reason: '.$ban->reason
                        : 'Your IP has been banned.',
                ]);
        }

        return $next($request);
    }
}
