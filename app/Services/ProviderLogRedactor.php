<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Keeps provider credentials out of logs, responses and persisted chat state.
 *
 * Outbound AI/provider calls carry secrets in query strings and headers, and
 * Guzzle puts the full effective URL into its exception messages. Logging a
 * raw exception message or response body therefore writes a live credential to
 * disk. Every provider log site funnels through this class instead, which
 * emits bounded structured fields and scrubs known secret shapes.
 */
class ProviderLogRedactor
{
    /**
     * Maximum characters kept from any provider-supplied string.
     */
    public const SNIPPET_LIMIT = 500;

    public const PLACEHOLDER = '[redacted]';

    /**
     * Query parameters and header names whose values are credentials.
     *
     * @var array<int, string>
     */
    protected const SENSITIVE_PARAMETERS = [
        'key',
        'api_key',
        'appid',
        'apikey',
        'access_token',
        'refresh_token',
        'token',
        'client_secret',
        'password',
        'signature',
        'sig',
    ];

    /**
     * Config paths holding credentials that must never be logged verbatim.
     *
     * @var array<int, string>
     */
    protected const SECRET_CONFIG_PATHS = [
        'services.gemini.api_key',
        'services.groq.key',
        'services.stripe.secret',
        'services.payment.stripe.secret',
        'services.resend.key',
        'services.openweather.api_key',
        'services.turnstile.secret',
        'app.key',
    ];

    /**
     * Removes credential-shaped substrings and bounds the result.
     */
    public static function scrub(?string $text, int $limit = self::SNIPPET_LIMIT): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $scrubbed = $text;

        foreach (static::configuredSecrets() as $secret) {
            $scrubbed = str_replace($secret, self::PLACEHOLDER, $scrubbed);
        }

        $parameters = implode('|', array_map('preg_quote', self::SENSITIVE_PARAMETERS));

        // key=AIza..., ?api_key=..., &token=... in URLs and query fragments.
        $scrubbed = preg_replace(
            '/\b('.$parameters.')=([^&\s"\']+)/i',
            '$1='.self::PLACEHOLDER,
            $scrubbed
        ) ?? $scrubbed;

        // "api_key": "...", 'token' => '...' in bodies and dumps.
        $scrubbed = preg_replace(
            '/(["\']?(?:'.$parameters.')["\']?\s*(?::|=>)\s*["\'])([^"\']+)(["\'])/i',
            '$1'.self::PLACEHOLDER.'$3',
            $scrubbed
        ) ?? $scrubbed;

        // Authorization: Bearer <token> / Basic <token>.
        $scrubbed = preg_replace(
            '/\b(Bearer|Basic|Token)\s+[A-Za-z0-9._\-=+\/]{8,}/i',
            '$1 '.self::PLACEHOLDER,
            $scrubbed
        ) ?? $scrubbed;

        // Provider key prefixes that are recognisable on their own.
        $scrubbed = preg_replace(
            '/\b(AIza[0-9A-Za-z_\-]{10,}|gsk_[0-9A-Za-z]{10,}|sk_(?:live|test)_[0-9A-Za-z]{10,}|re_[0-9A-Za-z]{10,})\b/',
            self::PLACEHOLDER,
            $scrubbed
        ) ?? $scrubbed;

        return mb_strimwidth($scrubbed, 0, max(1, $limit), '…');
    }

    /**
     * Bounded structured context for a failed provider call.
     *
     * @return array{operation: string, exception: string, status: int|null, trace_id: string, detail: string}
     */
    public static function exceptionContext(string $operation, Throwable $e, ?int $status = null): array
    {
        return [
            'operation' => $operation,
            'exception' => class_basename($e),
            'status' => $status,
            'trace_id' => static::traceId(),
            'detail' => static::scrub($e->getMessage(), 200),
        ];
    }

    /**
     * Bounded structured context for an unsuccessful provider response.
     *
     * @return array{operation: string, status: int|null, trace_id: string, body: string}
     */
    public static function responseContext(string $operation, ?Response $response): array
    {
        return [
            'operation' => $operation,
            'status' => $response?->status(),
            'trace_id' => static::traceId(),
            'body' => $response ? static::scrub($response->body()) : 'no response',
        ];
    }

    /**
     * Short correlation id so a user-facing failure can be matched to a log
     * line without exposing request contents.
     */
    public static function traceId(): string
    {
        return bin2hex(random_bytes(4));
    }

    /**
     * @return array<int, string>
     */
    protected static function configuredSecrets(): array
    {
        $secrets = [];

        foreach (self::SECRET_CONFIG_PATHS as $path) {
            $value = config($path);
            if (is_string($value) && mb_strlen($value) >= 8) {
                $secrets[] = $value;
            }
        }

        return $secrets;
    }
}
