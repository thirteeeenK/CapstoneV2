<?php

namespace App\Http\Middleware;

use App\Services\SystemSettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class EnforceGuestChatLimits
{
    public function __construct(private readonly SystemSettingsService $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            return $next($request);
        }

        $ip = $request->ip();
        $burstKey = "chat-guest:{$ip}";
        $dailyKey = "chat-guest-daily:{$ip}";
        $limits = $this->settings->section('chatbot');
        $untilMidnight = max(1, now('Asia/Manila')->addDay()->startOfDay()->timestamp - now()->timestamp);

        if (RateLimiter::tooManyAttempts($burstKey, $limits['minute_limit'])) {
            return $this->limitReachedResponse(max(1, RateLimiter::availableIn($burstKey)), 'minute');
        }

        $dailyCount = RateLimiter::attempts($dailyKey);
        if ($dailyCount >= $limits['daily_limit']) {
            return $this->limitReachedResponse(max(1, RateLimiter::availableIn($dailyKey)), 'day');
        }

        RateLimiter::hit($burstKey, 60);
        RateLimiter::hit($dailyKey, $untilMidnight);

        return $next($request);
    }

    protected function limitReachedResponse(int $retryAfter, string $period): Response
    {
        return response()->json([
            'status' => 'guest_limit_reached',
            'reply' => $period === 'minute'
                ? 'You have reached the guest chat limit for this minute. Please wait a moment and try again.'
                : 'You have reached the daily guest chat limit. Sign in to continue or return tomorrow.',
            'limit_type' => $period,
            'login_url' => route('login'),
            'register_url' => route('register'),
            'retry_after_seconds' => $retryAfter,
        ], 429)->header('Retry-After', (string) $retryAfter);
    }
}
