<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Transport security + browser hardening headers on every response.
 *
 * - With FORCE_HTTPS=true in .env (set only AFTER AutoSSL has issued the
 *   certificate), every plain-http request is 301-redirected to https and
 *   browsers are told to stay on https (HSTS).
 * - The other headers stop MIME sniffing, clickjacking, and referrer leaks
 *   regardless of scheme.
 */
class SecureRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.force_https') && ! $request->secure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->secure()) {
            // 180 days; add includeSubDomains only if every subdomain has TLS.
            $response->headers->set('Strict-Transport-Security', 'max-age=15552000');
        }

        return $response;
    }
}
