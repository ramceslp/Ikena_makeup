<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user  = User::create($request->validated());
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user'  => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        /** @var User $user */
        $user  = Auth::user();
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user'  => new UserResource($user),
            'token' => $token,
        ]);
    }

    /**
     * POST /api/auth/google
     *
     * Sign in with a Google ID token (JWT). Only a token audienced to THIS
     * application is ever accepted.
     *
     * Socialite's GoogleProvider validates `iss` and `aud` ONLY inside its JWT
     * branch (getUserFromJwtToken). Which branch runs is decided by
     * isJwtToken(), a heuristic: `substr_count($token,'.') === 2 &&
     * strlen($token) > 100`. Anything failing that heuristic — notably an
     * ordinary opaque OAuth access token — is instead sent to Google's
     * userinfo endpoint as a bearer token, and userinfo performs NO audience
     * check whatsoever. It returns the profile of whoever authorised that
     * token, for ANY OAuth client.
     *
     * That made this endpoint a confused deputy: an attacker registers their
     * own Google app, gets a victim to authorise it (scope `email profile`
     * looks harmless), posts the resulting access token here, and receives a
     * full-access ('*') Sanctum token for the victim's account.
     *
     * Two layers, because trusting a heuristic inside a dependency is how this
     * regresses:
     *   1. Shape + length are validated below, so isJwtToken() MUST return
     *      true and the verifying branch MUST run.
     *   2. `iss`, `aud` and `email_verified` are re-asserted here from the
     *      returned claims. A userinfo response carries no `iss`/`aud` at all,
     *      so if that branch is ever reached anyway, this fails closed.
     *
     * Locked by tests/Feature/GoogleSignInTest.php.
     */
    public function google(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => [
                'required',
                'string',
                // Both conditions of Socialite's isJwtToken(): three
                // base64url segments AND longer than 100 characters. Shape
                // alone is not enough — 'a.b.c' would still be routed to the
                // non-verifying userinfo endpoint.
                'min:101',
                'regex:/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/',
            ],
        ], [
            'id_token.regex' => 'The id_token must be a Google ID token (JWT).',
            'id_token.min'   => 'The id_token must be a Google ID token (JWT).',
        ]);

        try {
            $googleUser = Socialite::driver('google')->userFromToken($validated['id_token']);
        } catch (\Throwable $e) {
            // Covers a bad signature, a wrong issuer/audience and an expired
            // token — Socialite throws for all of them in the JWT branch.
            Log::info('Google sign-in rejected by Socialite: '.$e->getMessage());

            return $this->invalidGoogleToken();
        }

        $claims = (array) ($googleUser->user ?? []);

        if (! $this->claimsAreTrustworthy($claims)) {
            return $this->invalidGoogleToken();
        }

        $subject = trim((string) $googleUser->getId());
        $email   = trim((string) $googleUser->getEmail());

        if ($subject === '' || $email === '') {
            Log::warning('Google sign-in rejected: token carried no subject or email.');

            return $this->invalidGoogleToken();
        }

        // Match on the stable subject first. Falling back to email is only for
        // linking a pre-existing local account, and only when that account is
        // not already bound to a different Google identity.
        $user = User::where('google_id', $subject)->first();

        if (! $user) {
            $user = User::where('email', $email)->first();

            if ($user && $user->google_id !== null && ! hash_equals($user->google_id, $subject)) {
                // Same email address, different Google account. Silently
                // rebinding here would be an account takeover.
                Log::warning('Google sign-in rejected: email already bound to another Google subject.', [
                    'user_id' => $user->id,
                ]);

                return $this->invalidGoogleToken();
            }

            if ($user) {
                $user->update([
                    'google_id' => $subject,
                    'avatar'    => $googleUser->getAvatar(),
                ]);
            } else {
                $user = User::create([
                    'name'      => $googleUser->getName(),
                    'email'     => $email,
                    'google_id' => $subject,
                    'avatar'    => $googleUser->getAvatar(),
                    // Never taken from the request — a client must not be able
                    // to self-assign a privileged role (mirrors the
                    // 'in:student' guard in RegisterRequest).
                    'role'      => 'student',
                ]);
            }
        }

        return response()->json([
            'user'  => new UserResource($user),
            'token' => $user->createToken('api')->plainTextToken,
        ]);
    }

    /**
     * Re-assert the security-critical claims of a Google ID token.
     *
     * @param  array<string, mixed>  $claims
     */
    private function claimsAreTrustworthy(array $claims): bool
    {
        $clientId = trim((string) config('services.google.client_id'));

        if ($clientId === '') {
            // An unset GOOGLE_CLIENT_ID makes the audience check vacuous.
            // Treat it as a misconfiguration, not as an open door.
            Log::error('Google sign-in refused: services.google.client_id is not configured.');

            return false;
        }

        // The JWT spec allows `aud` to be an array; Google sends a string.
        $audiences = is_array($claims['aud'] ?? null) ? $claims['aud'] : [$claims['aud'] ?? null];

        $audienceMatches = false;

        foreach ($audiences as $audience) {
            if (is_string($audience) && hash_equals($clientId, $audience)) {
                $audienceMatches = true;
                break;
            }
        }

        if (! $audienceMatches) {
            Log::warning('Google sign-in rejected: token audience does not match this application.');

            return false;
        }

        // Google documents both spellings for the issuer.
        if (! in_array($claims['iss'] ?? null, ['https://accounts.google.com', 'accounts.google.com'], true)) {
            Log::warning('Google sign-in rejected: unexpected token issuer.');

            return false;
        }

        // Linking an account on an UNVERIFIED email is a takeover primitive for
        // anyone able to mint a Google identity on a domain they control.
        // Google sends a boolean; the string form is tolerated because some
        // Google endpoints have historically serialised it that way.
        if (! in_array($claims['email_verified'] ?? null, [true, 'true'], true)) {
            Log::warning('Google sign-in rejected: email is not verified.');

            return false;
        }

        return true;
    }

    private function invalidGoogleToken(): JsonResponse
    {
        // Deliberately uniform: the caller learns only that the token was not
        // accepted, never which check failed or whether the account exists.
        return response()->json(['message' => 'Invalid Google token.'], 422);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}
