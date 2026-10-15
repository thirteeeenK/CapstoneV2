<?php

namespace App\Services;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class RegistrationAbuseService
{
    public function reserve(string $ip): void
    {
        $key = 'registration:create:'.hash('sha256', $ip);
        try {
            Cache::lock($key.':lock', 10)->block(3, function () use ($key): void {
                if (RateLimiter::tooManyAttempts($key, 20)) {
                    throw new TooManyRequestsHttpException(RateLimiter::availableIn($key), 'Too many account creation attempts. Please try again later.');
                }
                RateLimiter::hit($key, 3600);
            });
        } catch (LockTimeoutException) {
            throw new TooManyRequestsHttpException(3, 'Account creation is busy. Please try again in a few seconds.');
        }
    }
}
