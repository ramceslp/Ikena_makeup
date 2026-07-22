<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RejectScopedCheckoutToken — deny-list guard for the short-lived,
 * ability-scoped 'checkout-confirm' Sanctum token minted by
 * CheckoutHandoffController::redeem() (mobile-capacitor-setup PR2).
 *
 * That token intentionally authenticates as the handoff's bound user (see
 * redeem()'s docblock) so the anonymous web browser can complete
 * POST /api/payments/confirm without a full login. Sanctum's ability check
 * (tokenCan()/can()) is opt-in per-route, so nothing on its own stops that
 * same token from being used against every other auth:sanctum route
 * (/api/me, /api/profile, /api/cart/checkout, ...) exactly like a normal,
 * full-access login token.
 *
 * This middleware is applied to the shared auth:sanctum route group
 * (excluding POST /api/payments/confirm, which explicitly opts out via
 * withoutMiddleware() in routes/api.php) and rejects any authenticated
 * request whose current access token has the 'checkout-confirm' ability but
 * NOT the '*' ability — i.e. a scoped checkout-confirm token being used
 * outside the one action it was minted for. Normal tokens (abilities
 * ['*'], see AuthController::login/register/google) are unaffected.
 */
class RejectScopedCheckoutToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token && $token->can('checkout-confirm') && ! $token->can('*')) {
            return response()->json([
                'message' => 'This token cannot be used for this action.',
            ], 403);
        }

        return $next($request);
    }
}
