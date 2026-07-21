<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CorsTest
 *
 * Verifies backend/config/cors.php allows the portless Capacitor origins
 * (http://localhost, https://localhost, capacitor://localhost,
 * ionic://localhost) in EVERY environment — not just APP_ENV=local — per
 * design Decision 6 (sdd/mobile-capacitor-setup/design.md).
 *
 * phpunit.xml sets APP_ENV=testing for this suite, so a passing assertion
 * here proves the origins are allowed outside 'local', matching the posture
 * the shipped (production) Capacitor app needs.
 */
class CorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_env_under_test_is_not_local(): void
    {
        // Sanity guard: if this ever runs under APP_ENV=local, the assertions
        // below would pass for the wrong reason (the old gated pattern would
        // also allow these origins). Fail loudly instead of giving a false green.
        $this->assertNotSame('local', config('app.env'));
    }

    public function test_portless_http_localhost_origin_is_allowed_outside_local_env(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost',
        ])->getJson('/api/categories');

        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost');
    }

    public function test_portless_https_localhost_origin_is_allowed_outside_local_env(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://localhost',
        ])->getJson('/api/categories');

        $response->assertHeader('Access-Control-Allow-Origin', 'https://localhost');
    }

    public function test_capacitor_scheme_origin_is_allowed_outside_local_env(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'capacitor://localhost',
        ])->getJson('/api/categories');

        $response->assertHeader('Access-Control-Allow-Origin', 'capacitor://localhost');
    }

    public function test_ionic_scheme_origin_is_allowed_outside_local_env(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'ionic://localhost',
        ])->getJson('/api/categories');

        $response->assertHeader('Access-Control-Allow-Origin', 'ionic://localhost');
    }
}
