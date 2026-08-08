<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class EnforceGuestChatLimits
{
    protected int $dailyMax = 15;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            return $next($request);
        }

        $ip = $request->ip();
        $burstKey = "chat-guest:{$ip}";
        $dailyKey = "chat-guest-daily:{$ip}";

        if (RateLimiter::tooManyAttempts($burstKey, 5)) {
            return $this->limitReachedResponse(30);
        }

        $dailyCount = RateLimiter::attempts($dailyKey);
        if ($dailyCount >= $this->dailyMax) {
            return $this->limitReachedResponse(now()->endOfDay()->diffInSeconds());
        }

        RateLimiter::hit($burstKey, 60);
        RateLimiter::hit($dailyKey, now()->endOfDay()->diffInSeconds());

        return $next($request);
    }

    protected function limitReachedResponse(int $retryAfter): Response
    {
        return response()->json([
            'status' => 'guest_limit_reached',
            'reply' => 'You have reached the daily chat limit for guest users. Sign up for a free account to get unlimited access to SunnyTrips AI assistant!',
            'login_url' => route('login'),
            'register_url' => route('register'),
            'retry_after_seconds' => $retryAfter,
        ], 429);
    }
}
