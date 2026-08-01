<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * RateLimitingTest — the API must be throttled.
 *
 * Laravel 11 does NOT apply throttle:api to the api middleware group by
 * default (unlike Laravel 10), and $middleware->throttleApi() was never
 * called, so every endpoint was unthrottled: unlimited password spraying on
 * /login, unlimited brute force against the 40-char opaque handoff token,
 * and unlimited enumeration of certificate codes — which leak a student name
 * and course title per hit.
 *
 * Each test method boots a fresh application (and therefore a fresh array
 * cache store, per phpunit.xml CACHE_STORE=array), so limiter counters do not
 * leak between tests.
 */
class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Credential endpoints — 5/min
    // =========================================================================

    public function test_login_is_throttled_after_five_attempts(): void
    {
        $payload = ['email' => 'victim@example.com', 'password' => 'wrong-password'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', $payload)->assertStatus(401);
        }

        $this->postJson('/api/login', $payload)->assertStatus(429);
    }

    public function test_login_throttle_is_not_bypassed_by_rotating_the_email(): void
    {
        // Password spraying: one attempt each against many accounts. The
        // per-IP limit is what stops this, so rotating the email must not
        // hand the attacker a fresh budget.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email'    => "victim{$i}@example.com",
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $this->postJson('/api/login', [
            'email'    => 'victim99@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_a_successful_login_still_counts_towards_the_limit(): void
    {
        User::factory()->create([
            'email'    => 'real@example.com',
            'password' => 'password123',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email'    => 'real@example.com',
                'password' => 'password123',
            ])->assertStatus(200);
        }

        $this->postJson('/api/login', [
            'email'    => 'real@example.com',
            'password' => 'password123',
        ])->assertStatus(429);
    }

    public function test_register_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/register', [
                'name'                  => 'Spam',
                'email'                 => "spam{$i}@example.com",
                'password'              => 'password123',
                'password_confirmation' => 'password123',
            ])->assertStatus(201);
        }

        $this->postJson('/api/register', [
            'name'                  => 'Spam',
            'email'                 => 'spam99@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(429);
    }

    public function test_google_sign_in_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            // 422 — an obviously invalid token; the throttle runs before the
            // controller, so the status here only needs to not be 429.
            $this->postJson('/api/auth/google', ['id_token' => 'not-a-real-token'])
                ->assertStatus(422);
        }

        $this->postJson('/api/auth/google', ['id_token' => 'not-a-real-token'])
            ->assertStatus(429);
    }

    public function test_throttled_response_carries_a_retry_after_header(): void
    {
        $payload = ['email' => 'victim@example.com', 'password' => 'wrong-password'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', $payload);
        }

        $response = $this->postJson('/api/login', $payload)->assertStatus(429);

        $this->assertNotNull($response->headers->get('Retry-After'));
    }

    // =========================================================================
    // Opaque-token endpoints — 10/min
    // =========================================================================

    public function test_checkout_handoff_redeem_is_throttled(): void
    {
        // Brute-forcing the 40-char single-use token. 404 = unknown token.
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/checkout/handoff/redeem', ['token' => "guess-{$i}"])
                ->assertStatus(404);
        }

        $this->postJson('/api/checkout/handoff/redeem', ['token' => 'guess-final'])
            ->assertStatus(429);
    }

    // =========================================================================
    // Public certificate verification — 15/min
    // =========================================================================

    public function test_certificate_verify_is_throttled(): void
    {
        // Enumeration here leaks a student name + course title per hit.
        for ($i = 0; $i < 15; $i++) {
            $this->getJson("/api/certificates/verify/IKENA-GUESS{$i}")
                ->assertStatus(404);
        }

        $this->getJson('/api/certificates/verify/IKENA-GUESSXX')
            ->assertStatus(429);
    }

    // =========================================================================
    // Baseline api limiter
    // =========================================================================

    public function test_public_endpoints_advertise_a_rate_limit(): void
    {
        $response = $this->getJson('/api/categories')->assertStatus(200);

        $this->assertSame('60', $response->headers->get('X-RateLimit-Limit'));
    }

    public function test_authenticated_requests_are_keyed_per_user_not_per_ip(): void
    {
        // Two users behind the same IP (a school, an office, a mobile carrier
        // NAT) must not consume each other's budget.
        $first  = User::factory()->create();
        $second = User::factory()->create();

        Sanctum::actingAs($first);
        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/me')->assertStatus(200);
        }

        Sanctum::actingAs($second);
        $response = $this->getJson('/api/me')->assertStatus(200);

        // A fresh budget, not 30 already spent.
        $this->assertSame('59', $response->headers->get('X-RateLimit-Remaining'));
    }

    public function test_baseline_limit_returns_429_when_exhausted(): void
    {
        Sanctum::actingAs(User::factory()->create());

        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/me')->assertStatus(200);
        }

        $this->getJson('/api/me')->assertStatus(429);
    }
}
