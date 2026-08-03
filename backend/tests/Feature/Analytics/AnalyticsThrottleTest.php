<?php

namespace Tests\Feature\Analytics;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AnalyticsThrottleTest — POST /api/analytics/events must be governed by
 * the 'throttle:analytics' limiter, NOT the group's baseline 'throttle:api'
 * (60/min) (visitor-analytics PR1b, design D4: sdd/visitor-analytics/design).
 *
 * A route-level throttle does NOT replace the group one — both apply and
 * the tighter one still binds (see the documented trap at
 * routes/api.php:98-100, above the 'submissions/{submission}/{variant}'
 * route). Without an explicit withoutMiddleware('throttle:api') override, a
 * pageview per navigation would contend with the visitor's real API calls
 * for the same 60/min budget and could starve the app well before any
 * abuse threshold is reached.
 */
class AnalyticsThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_more_than_sixty_requests_per_minute_still_succeed(): void
    {
        // Proves throttle:api (60/min) does NOT bind on this route — if it
        // did, request 61 would already be 429.
        for ($i = 0; $i < 61; $i++) {
            $this->postJson('/api/analytics/events', [
                'event_type' => 'page_view',
                'path' => '/',
            ])->assertStatus(204);
        }
    }

    public function test_the_analytics_limiter_still_eventually_throttles(): void
    {
        // throttle:analytics is sized higher than throttle:api (120/min per
        // design D4) but is not unlimited — abuse protection still exists.
        for ($i = 0; $i < 120; $i++) {
            $this->postJson('/api/analytics/events', [
                'event_type' => 'page_view',
                'path' => '/',
            ])->assertStatus(204);
        }

        $this->postJson('/api/analytics/events', [
            'event_type' => 'page_view',
            'path' => '/',
        ])->assertStatus(429);
    }
}
