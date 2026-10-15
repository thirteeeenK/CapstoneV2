<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TurnstileService
{
    public function verify(mixed $token): void
    {
        $failure = 'Please complete the security check again and retry.';
        if (! is_string($token) || $token === '' || strlen($token) > 2048) {
            throw ValidationException::withMessages(['cf-turnstile-response' => $failure]);
        }

        $secret = config('services.turnstile.secret_key');
        $hostname = config('services.turnstile.hostname');
        if (! filled($secret) || ! filled($hostname) || ! filled(config('services.turnstile.site_key'))) {
            Log::error('auth.turnstile.configuration_missing');
            throw ValidationException::withMessages(['cf-turnstile-response' => 'Registration is temporarily unavailable. Please try again later.']);
        }

        try {
            $response = Http::asForm()
                ->connectTimeout(3)->timeout(8)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secret, 'response' => $token,
                ]);
            $result = $response->json();
            if ($response->successful() && is_array($result)
                && ($result['success'] ?? false) === true
                && ($result['hostname'] ?? null) === $hostname
                && ($result['action'] ?? null) === 'register') {
                return;
            }
            Log::notice('auth.turnstile.rejected');
        } catch (ConnectionException) {
            Log::warning('auth.turnstile.unavailable');
        }

        throw ValidationException::withMessages(['cf-turnstile-response' => $failure]);
    }
}
