<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SecurityHeadersTest — every API response carries the baseline hardening
 * headers (App\Http\Middleware\SecurityHeaders).
 *
 * Auth tokens live in browser localStorage (frontend/src/stores/auth.js), so
 * they have no HttpOnly protection: a CSP that forbids script execution is the
 * remaining line of defence if an XSS sink is ever introduced. The API only
 * ever returns JSON and streamed files, so `default-src 'none'` costs nothing.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_response_sets_nosniff(): void
    {
        // Uploads are served from public/storage; sniffing an image as HTML
        // is a script-execution primitive.
        $this->getJson('/api/categories')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_response_denies_framing(): void
    {
        $this->getJson('/api/categories')
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_response_sets_referrer_policy(): void
    {
        $this->getJson('/api/categories')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_response_sets_a_locked_down_csp(): void
    {
        $csp = $this->getJson('/api/categories')->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp, 'API responses must carry a Content-Security-Policy.');
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("base-uri 'none'", $csp);
    }

    public function test_response_sets_permissions_policy(): void
    {
        $policy = $this->getJson('/api/categories')->headers->get('Permissions-Policy');

        $this->assertNotNull($policy);
        $this->assertStringContainsString('camera=()', $policy);
    }

    // =========================================================================
    // HSTS is conditional on the request actually being secure
    // =========================================================================

    public function test_hsts_is_sent_over_https(): void
    {
        $hsts = $this->getJson('https://localhost/api/categories')
            ->headers->get('Strict-Transport-Security');

        $this->assertNotNull($hsts, 'HTTPS responses must carry HSTS.');
        $this->assertStringContainsString('max-age=31536000', $hsts);
    }

    public function test_hsts_is_not_sent_over_plain_http(): void
    {
        // Sending HSTS over http:// is meaningless and pins nothing; more
        // importantly it must not appear on the local dev server.
        $this->getJson('http://localhost/api/categories')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    // =========================================================================
    // Headers must survive error responses too
    // =========================================================================

    public function test_headers_are_present_on_a_401_response(): void
    {
        $this->getJson('/api/me')
            ->assertStatus(401)
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_headers_are_present_on_a_404_response(): void
    {
        $this->getJson('/api/posts/no-such-post-slug')
            ->assertStatus(404)
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
