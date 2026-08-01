<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * SanctumConfigTest — access tokens must expire.
 *
 * The SPA and the Capacitor app both persist the plaintext token in
 * localStorage (frontend/src/stores/auth.js, App/src/stores/auth.js), where it
 * has no HttpOnly protection. Login/register/google mint tokens with the '*'
 * ability, so an unbounded lifetime turns any single leak — a shared device, a
 * proxy log, a backup, a future XSS — into permanent full account access.
 */
class SanctumConfigTest extends TestCase
{
    public function test_tokens_have_a_finite_expiration(): void
    {
        $expiration = config('sanctum.expiration');

        $this->assertNotNull(
            $expiration,
            'sanctum.expiration must not be null — tokens would never expire.',
        );
        $this->assertIsInt($expiration);
        $this->assertGreaterThan(0, $expiration);
    }

    public function test_expiration_is_not_absurdly_long(): void
    {
        // Anything beyond ~30 days is indistinguishable from "never expires"
        // for the leak scenarios this guards against.
        $this->assertLessThanOrEqual(60 * 24 * 30, config('sanctum.expiration'));
    }

    public function test_expired_tokens_are_pruned_on_a_schedule(): void
    {
        // Expiry alone only stops the token from authenticating; the rows stay
        // in personal_access_tokens forever without a prune job.
        $commands = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->map(fn ($event) => $event->command ?? '')
            ->implode("\n");

        $this->assertStringContainsString('sanctum:prune-expired', $commands);
    }
}
