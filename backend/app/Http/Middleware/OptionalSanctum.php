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
            $token = $user->currentAccessToken();

            // A short-lived, ability-scoped 'checkout-confirm' token (see
            // RejectScopedCheckoutToken) must not authenticate the requester
            // on these optional-auth routes — it is only allowed to perform
            // POST /api/payments/confirm. Unlike auth:sanctum routes, there is
            // no deny-list middleware here to reject it, and anonymous access
            // is itself a valid, intended outcome on optional-auth routes, so
            // the scoped token is simply ignored (treated as if no token was
            // presented) rather than rejected with an error.
            if (! $token || ! $token->can('checkout-confirm') || $token->can('*')) {
                Auth::setUser($user);
            }
        }

        return $next($request);
    }
}
