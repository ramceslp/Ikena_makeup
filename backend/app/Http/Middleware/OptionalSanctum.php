<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional authentication for public routes that personalize their response
 * for logged-in users (e.g. is_enrolled, my_review, completed flags).
 *
 * The default guard is 'web' (session-based), but the SPA authenticates with
 * Sanctum bearer tokens. On a public route those tokens are never resolved, so
 * $request->user() returns null and personalization breaks. This middleware
 * resolves the bearer token via the 'sanctum' guard when present and sets it as
 * the authenticated user — without rejecting guests (unlike 'auth:sanctum').
 */
class OptionalSanctum
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() && ($user = Auth::guard('sanctum')->user())) {
            Auth::setUser($user);
        }

        return $next($request);
    }
}
