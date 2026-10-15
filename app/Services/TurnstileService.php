<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TurnstileService
{
    private const ERROR_CODES = [
        'missing-input-secret', 'invalid-input-secret', 'missing-input-response',
        'invalid-input-response', 'bad-request', 'timeout-or-duplicate', 'internal-error',
    ];

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
                Log::info('auth.turnstile.verified', ['hostname' => $hostname, 'action' => 'register']);

                return;
            }
            Log::notice('auth.turnstile.rejected', [
                'http_status' => $response->status(),
                'provider_success' => is_array($result) && ($result['success'] ?? false) === true,
                'hostname_matches' => is_array($result) && ($result['hostname'] ?? null) === $hostname,
                'action_matches' => is_array($result) && ($result['action'] ?? null) === 'register',
                'error_codes' => $this->safeErrorCodes($result),
            ]);
        } catch (ConnectionException) {
            Log::warning('auth.turnstile.unavailable');
        }

        throw ValidationException::withMessages(['cf-turnstile-response' => $failure]);
    }

    /** @return list<string> */
    private function safeErrorCodes(mixed $result): array
    {
        if (! is_array($result) || ! is_array($result['error-codes'] ?? null)) {
            return [];
        }

        return array_values(array_filter($result['error-codes'],
            fn (mixed $code): bool => is_string($code) && in_array($code, self::ERROR_CODES, true)));
    }
}
