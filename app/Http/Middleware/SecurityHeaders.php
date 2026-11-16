<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Third-party origins the browser is allowed to load, keyed by CSP directive.
     *
     * Images stay on `https:` because admins can attach external image URLs to listings.
     * `'unsafe-eval'` / `'unsafe-inline'` are an accepted risk: Alpine.js 3 evaluates its
     * directive expressions at runtime and the views use inline handlers.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_SOURCES = [
        'script-src' => [
            'https://challenges.cloudflare.com',
            'https://unpkg.com',
            'https://cdn.jsdelivr.net',
        ],
        'style-src' => [
            'https://fonts.googleapis.com',
            'https://fonts.bunny.net',
            'https://unpkg.com',
            'https://cdn.jsdelivr.net',
        ],
        'font-src' => [
            'https://fonts.gstatic.com',
            'https://fonts.bunny.net',
        ],
        'connect-src' => [
            'https://challenges.cloudflare.com',
        ],
        'frame-src' => [
            'https://challenges.cloudflare.com',
        ],
        'form-action' => [
            'https://accounts.google.com',
            'https://checkout.stripe.com',
            'https://*.paymongo.com',
        ],
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        $response->headers->remove('X-Powered-By');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());

        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        if ($response instanceof RedirectResponse) {
            $response->setContent('');
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $isProduction = app()->isProduction();

        // Local dev loads assets from the Vite dev server (http + ws on a random host/port).
        $devSources = $isProduction ? '' : ' http: https: ws: wss:';

        $sources = fn (string $directive): string => implode(' ', self::ALLOWED_SOURCES[$directive]).$devSources;

        return implode(' ', [
            "default-src 'self';",
            "base-uri 'self';",
            "object-src 'none';",
            "frame-ancestors 'none';",
            "form-action 'self' ".implode(' ', self::ALLOWED_SOURCES['form-action']).';',
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' {$sources('script-src')};",
            "style-src 'self' 'unsafe-inline' {$sources('style-src')};",
            "font-src 'self' data: {$sources('font-src')};",
            "img-src 'self' data: blob: https:;",
            "connect-src 'self' {$sources('connect-src')};",
            "frame-src 'self' {$sources('frame-src')};",
            "media-src 'self' data: blob:;",
            "worker-src 'self' blob:;",
            "manifest-src 'self';",
        ]);
    }
}
