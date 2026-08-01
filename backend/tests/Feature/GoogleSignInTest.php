<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

/**
 * GoogleSignInTest — POST /api/auth/google must only ever trust a Google ID
 * token audienced to THIS application.
 *
 * The vulnerability this locks: Socialite's GoogleProvider validates `iss` and
 * `aud` ONLY in its JWT branch (getUserFromJwtToken). Which branch runs is
 * decided by isJwtToken(), a heuristic — `substr_count($token,'.') === 2 &&
 * strlen($token) > 100`. Anything else falls through to Google's userinfo
 * endpoint with an `Authorization: Bearer` header and NO audience check at all.
 *
 * That is a confused-deputy: an attacker registers their own Google OAuth app,
 * gets a victim to authorise it (scope `email profile` looks harmless), and
 * posts the resulting access token here. userinfo happily returns the victim's
 * profile, and the endpoint mints a full-access ('*') Sanctum token for the
 * victim's account.
 *
 * Defence is two-layer, because relying on a heuristic in a dependency is how
 * this reappears:
 *   1. The request only accepts a JWT-shaped token long enough that
 *      isJwtToken() MUST return true.
 *   2. The controller re-asserts `iss`, `aud` and `email_verified` from the
 *      returned claims. A userinfo response carries no `iss`/`aud` at all, so
 *      if that branch is ever reached anyway, this fails closed.
 */
class GoogleSignInTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = '1234567890-abcdef.apps.googleusercontent.com';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.client_id' => self::CLIENT_ID]);
    }

    /**
     * A token that satisfies Socialite's isJwtToken(): three dot-separated
     * base64url segments, comfortably over 100 characters.
     */
    private function jwtShapedToken(): string
    {
        return str_repeat('a', 40).'.'.str_repeat('b', 60).'.'.str_repeat('c', 43);
    }

    /**
     * Claims as Google's ID token delivers them.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function claims(array $overrides = []): array
    {
        return array_merge([
            'iss'            => 'https://accounts.google.com',
            'aud'            => self::CLIENT_ID,
            'sub'            => '110000000000000000001',
            'email'          => 'student@gmail.com',
            'email_verified' => true,
            'name'           => 'Ana Estudiante',
            'picture'        => 'https://lh3.googleusercontent.com/a/photo',
        ], $overrides);
    }

    /**
     * Stub Socialite so it "succeeds" and returns the given claims, whatever
     * token was presented. This is the attacker's best case: the tests assert
     * our boundary refuses regardless.
     *
     * @param  array<string, mixed>  $claims
     */
    private function fakeGoogleReturns(array $claims): object
    {
        $socialiteUser = (new SocialiteUser)->setRaw($claims)->map([
            'id'     => $claims['sub'] ?? null,
            'name'   => $claims['name'] ?? null,
            'email'  => $claims['email'] ?? null,
            'avatar' => $claims['picture'] ?? null,
        ]);

        $provider = new class($socialiteUser)
        {
            public bool $wasCalled = false;

            public function __construct(private SocialiteUser $user) {}

            public function userFromToken(string $token): SocialiteUser
            {
                $this->wasCalled = true;

                return $this->user;
            }
        };

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        return $provider;
    }

    // =========================================================================
    // The vulnerability: opaque access tokens
    // =========================================================================

    public function test_opaque_access_token_is_rejected_even_when_google_resolves_it(): void
    {
        $victim = User::factory()->create([
            'email'     => 'victim@gmail.com',
            'google_id' => '110000000000000000009',
        ]);

        // Google's userinfo endpoint would return the victim's profile for an
        // access token minted by ANY OAuth app the victim ever authorised.
        $provider = $this->fakeGoogleReturns($this->claims([
            'sub'   => $victim->google_id,
            'email' => $victim->email,
        ]));

        // A realistic Google access token: opaque, no dots.
        $this->postJson('/api/auth/google', [
            'id_token' => 'ya29.'.str_repeat('A1b2C3d4', 25),
        ])->assertStatus(422)->assertJsonValidationErrors('id_token');

        $this->assertFalse(
            $provider->wasCalled,
            'A non-JWT token must be refused at the request boundary, before Socialite is consulted at all.',
        );
    }

    public function test_short_three_segment_token_is_rejected(): void
    {
        // Socialite's isJwtToken() also requires strlen > 100, so a short
        // three-segment string would still be sent to the userinfo endpoint —
        // shape alone is not enough to force the validating branch.
        $provider = $this->fakeGoogleReturns($this->claims());

        $this->postJson('/api/auth/google', ['id_token' => 'a.b.c'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_token');

        $this->assertFalse($provider->wasCalled);
    }

    public function test_rejected_opaque_token_creates_no_user_and_issues_no_token(): void
    {
        $this->fakeGoogleReturns($this->claims(['email' => 'newvictim@gmail.com']));

        $response = $this->postJson('/api/auth/google', [
            'id_token' => str_repeat('opaque', 30),
        ])->assertStatus(422);

        $this->assertNull($response->json('token'));
        $this->assertDatabaseMissing('users', ['email' => 'newvictim@gmail.com']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // =========================================================================
    // Defence in depth: the claims are re-checked
    // =========================================================================

    public function test_payload_without_an_aud_claim_is_rejected(): void
    {
        // This is the shape a userinfo response has — no iss, no aud. If that
        // branch is ever reached despite the shape guard, we must fail closed.
        $claims = $this->claims();
        unset($claims['aud'], $claims['iss']);

        $this->fakeGoogleReturns($claims);

        $this->postJson('/api/auth/google', ['id_token' => $this->jwtShapedToken()])
            ->assertStatus(422);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_token_audienced_to_another_application_is_rejected(): void
    {
        $this->fakeGoogleReturns($this->claims([
            'aud' => 'attacker-app-99999.apps.googleusercontent.com',
        ]));

        $this->postJson('/api/auth/google', ['id_token' => $this->jwtShapedToken()])
            ->assertStatus(422);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_token_from_an_unexpected_issuer_is_rejected(): void
    {
        $this->fakeGoogleReturns($this->claims(['iss' => 'https://evil.example']));

        $this->postJson('/api/auth/google', ['id_token' => $this->jwtShapedToken()])
            ->assertStatus(422);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_sign_in_is_refused_when_the_client_id_is_not_configured(): void
    {
        // A blank GOOGLE_CLIENT_ID makes the audience check vacuous, so it must
        // be treated as a misconfiguration rather than an open door.
        config(['services.google.client_id' => null]);

        $this->fakeGoogleReturns($this->claims(['aud' => null]));

        $this->postJson('/api/auth/google', ['id_token' => $this->jwtShapedToken()])
            ->assertStatus(422);

        $this->assertDatabaseCount('users', 0);
    }

    // =========================================================================
    // Unverified emails must never link an account
    // =========================================================================

    public function test_unverified_google_email_is_rejected(): void
    {
        $this->fakeGoogleReturns($this->claims(['email_verified' => false]));

        $this->postJson('/api/auth/google', ['id_token' => $this->jwtShapedToken()])
            ->assertStatus(422);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_missing_email_verified_claim_is_rejected(): void
    {
        $claims = $this->claims();
        unset($claims['email_verified']);

        $this->fakeGoogleReturns($claims);

        $this->postJson('/api/auth/google', ['id_token' => $this->jwtShapedToken()])
            ->assertStatus(422);
    }

    public function test_blank_email_is_rejected(): void
    {
        $this->fakeGoogleReturns($this->claims(['email' => null]));

        $this->postJson('/api/auth/google', ['id_token' => $this->jwtShapedToken()])
            ->assertStatus(422);
    }

    // =========================================================================
    // The happy paths still work
    // =========================================================================

    public function test_valid_id_token_creates_a_student_and_returns_a_token(): void
    {
        $this->fakeGoogleReturns($this->claims());

        $response = $this->postJson('/api/auth/google', ['id_token' => $this->jwtShapedToken()])
            ->assertStatus(200);

        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseHas('users', [
            'email'     => 'student@gmail.com',
            'google_id' => '110000000000000000001',
            'role'      => 'student',
        ]);
    }

    public function test_new_google_user_cannot_choose_a_privileged_role(): void
    {
        $this->fakeGoogleReturns($this->claims());

        $this->postJson('/api/auth/google', [
            'id_token' => $this->jwtShapedToken(),
            'role'     => 'admin',
        ])->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'email' => 'student@gmail.com',
            'role'  => 'student',
        ]);
    }

    public function test_links_google_id_to_an_existing_account_with_the_same_verified_email(): void
    {
        $existing = User::factory()->create([
            'email'     => 'student@gmail.com',
            'google_id' => null,
        ]);

        $this->fakeGoogleReturns($this->claims());

        $this->postJson('/api/auth/google', ['id_token' => $this->jwtShapedToken()])
            ->assertStatus(200);

        $this->assertSame('110000000000000000001', $existing->fresh()->google_id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_matches_on_google_id_even_when_the_google_email_changed(): void
    {
        $existing = User::factory()->create([
            'email'     => 'old-address@gmail.com',
            'google_id' => '110000000000000000001',
        ]);

        $this->fakeGoogleReturns($this->claims(['email' => 'new-address@gmail.com']));

        $this->postJson('/api/auth/google', ['id_token' => $this->jwtShapedToken()])
            ->assertStatus(200);

        // The stable subject wins — no duplicate account is created.
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($existing->id, User::firstWhere('google_id', '110000000000000000001')->id);
    }

    public function test_refuses_an_email_already_linked_to_a_different_google_subject(): void
    {
        User::factory()->create([
            'email'     => 'student@gmail.com',
            'google_id' => '110000000000000000009',
        ]);

        // Same email, different Google account — never silently take it over.
        $this->fakeGoogleReturns($this->claims(['sub' => '110000000000000000001']));

        $this->postJson('/api/auth/google', ['id_token' => $this->jwtShapedToken()])
            ->assertStatus(422);

        $this->assertSame(
            '110000000000000000009',
            User::firstWhere('email', 'student@gmail.com')->google_id,
        );
    }
}
