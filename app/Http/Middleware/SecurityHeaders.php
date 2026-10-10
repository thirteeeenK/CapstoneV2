<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
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

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $httpSources = app()->isProduction() ? 'https:' : 'http: https:';
        $webSocketSources = app()->isProduction() ? 'wss:' : 'ws: wss:';

        return implode(' ', [
            "default-src 'self';",
            "base-uri 'self';",
            "object-src 'none';",
            "frame-ancestors 'none';",
            "form-action 'self' https:;",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' {$httpSources};",
            "style-src 'self' 'unsafe-inline' {$httpSources};",
            "font-src 'self' data: {$httpSources};",
            "img-src 'self' data: blob: {$httpSources};",
            "connect-src 'self' {$httpSources} {$webSocketSources};",
            "frame-src 'self' {$httpSources};",
            "media-src 'self' data: blob: {$httpSources};",
            "worker-src 'self' blob:;",
            "manifest-src 'self';",
        ]);
    }
}
