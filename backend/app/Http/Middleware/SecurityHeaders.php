<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SecurityHeaders — baseline hardening headers on every API response.
 *
 * Applied to the api group in bootstrap/app.php, prepended alongside
 * HandleCors so the headers are present on error responses too (401/404/500),
 * not just successful ones.
 *
 * Why the CSP is this strict: the API only ever returns JSON or a streamed
 * file, so it never legitimately loads a script, style, frame or image of its
 * own — `default-src 'none'` costs nothing here. It matters because auth tokens
 * are persisted in browser localStorage (frontend/src/stores/auth.js), which
 * has no HttpOnly protection: if an XSS sink is ever introduced, a CSP that
 * forbids script execution is the only remaining line of defence. The SPA's own
 * CSP is a separate concern and belongs on the server serving the Vue build.
 *
 * Locked by tests/Feature/SecurityHeadersTest.php.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Uploads are served from public/storage; sniffing an image as HTML is
        // a script-execution primitive.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Clickjacking — nothing in this API should ever be framed.
        // frame-ancestors in the CSP below is the modern equivalent; this stays
        // for older browsers.
        $response->headers->set('X-Frame-Options', 'DENY');

        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'",
        );

        // Only meaningful over TLS — and must not appear on the local dev
        // server, where it would pin http://localhost to HTTPS in the
        // developer's browser.
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
            );
        }

        return $response;
    }
}
