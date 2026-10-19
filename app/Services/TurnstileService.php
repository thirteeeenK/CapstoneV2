<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TurnstileService
{
    /**
     * Challenge actions. Cloudflare echoes the action back in the siteverify
     * response, so binding each form to its own action stops a token minted on
     * one widget from being replayed against another.
     */
    public const ACTION_REGISTER = 'register';

    public const ACTION_GOOGLE = 'google-signin';

    private const ERROR_CODES = [
        'missing-input-secret', 'invalid-input-secret', 'missing-input-response',
        'invalid-input-response', 'bad-request', 'timeout-or-duplicate', 'internal-error',
    ];

    /**
     * Verify a challenge token, rejecting the submission when it does not hold up.
     *
     * @throws ValidationException
     */
    public function verify(mixed $token, string $action = self::ACTION_REGISTER): void
    {
        $failure = $this->failureReason($token, $action);

        if ($failure !== null) {
            throw ValidationException::withMessages(['cf-turnstile-response' => $failure]);
        }
    }

    /**
     * Verify a challenge token and report the outcome without throwing, for
     * callers that present their own failure message.
     */
    public function passes(mixed $token, string $action): bool
    {
        return $this->failureReason($token, $action) === null;
    }

    /**
     * Whether a challenge can be both rendered and verified.
     */
    public function configured(): bool
    {
        return filled(config('services.turnstile.site_key'))
            && filled(config('services.turnstile.secret_key'))
            && filled(config('services.turnstile.hostname'));
    }

    /**
     * The user-facing reason the token was refused, or null once verified.
     */
    private function failureReason(mixed $token, string $action): ?string
    {
        $failure = 'Please complete the security check again and retry.';
        if (! is_string($token) || $token === '' || strlen($token) > 2048) {
            return $failure;
        }

        if (! $this->configured()) {
            Log::error('auth.turnstile.configuration_missing');

            return 'Security checks are temporarily unavailable. Please try again later.';
        }

        $hostname = config('services.turnstile.hostname');

        try {
            $response = Http::asForm()
                ->connectTimeout(3)->timeout(8)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => config('services.turnstile.secret_key'), 'response' => $token,
                ]);
            $result = $response->json();
            if ($response->successful() && is_array($result)
                && ($result['success'] ?? false) === true
                && ($result['hostname'] ?? null) === $hostname
                && ($result['action'] ?? null) === $action) {
                Log::info('auth.turnstile.verified', ['hostname' => $hostname, 'action' => $action]);

                return null;
            }
            Log::notice('auth.turnstile.rejected', [
                'http_status' => $response->status(),
                'provider_success' => is_array($result) && ($result['success'] ?? false) === true,
                'hostname_matches' => is_array($result) && ($result['hostname'] ?? null) === $hostname,
                'action_matches' => is_array($result) && ($result['action'] ?? null) === $action,
                'error_codes' => $this->safeErrorCodes($result),
            ]);
        } catch (ConnectionException) {
            Log::warning('auth.turnstile.unavailable');
        }

        return $failure;
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
