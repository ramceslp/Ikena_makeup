<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CorsConfigTest — locks the production CORS posture.
 *
 * config/cors.php branches on the environment, so asserting against the
 * already-booted config() would only ever prove the *testing* posture. These
 * tests re-evaluate the config file with a forced environment instead, which
 * is the only way to assert what a production deploy actually allows.
 *
 * The rule being locked: the Vite dev-server origin must NEVER be an allowed
 * origin outside local/testing, while the four portless Capacitor origins must
 * survive in EVERY environment (design Decision 6,
 * sdd/mobile-capacitor-setup/design.md — see also CorsTest).
 */
class CorsConfigTest extends TestCase
{
    /**
     * Re-evaluate config/cors.php as if booted in $env.
     *
     * Overrides config('app.env') rather than the container's 'env' binding:
     * that is the value cors.php actually reads, because app()->environment()
     * is unavailable while config files are being loaded (LoadConfiguration
     * runs detectEnvironment() only after evaluating them).
     *
     * `require` (not require_once) so the file is re-executed per call.
     *
     * @return array<string, mixed>
     */
    private function corsConfigFor(string $env): array
    {
        $original = config('app.env');
        config(['app.env' => $env]);

        try {
            return require config_path('cors.php');
        } finally {
            config(['app.env' => $original]);
        }
    }

    // =========================================================================
    // Dev origins must not leak into production
    // =========================================================================

    public function test_production_does_not_allow_the_vite_dev_server_origin(): void
    {
        $origins = $this->corsConfigFor('production')['allowed_origins'];

        $this->assertNotContains(
            'http://localhost:5173',
            $origins,
            'The Vite dev-server origin must never be allowed in production.',
        );
    }

    public function test_production_does_not_allow_a_localhost_frontend_url_fallback(): void
    {
        // config/app.php defaults frontend_url to http://localhost:5173. A
        // production deploy that forgot to set FRONTEND_URL must NOT silently
        // inherit that dev origin as a trusted CORS origin.
        config(['app.frontend_url' => 'http://localhost:5173']);

        $origins = $this->corsConfigFor('production')['allowed_origins'];

        $this->assertNotContains('http://localhost:5173', $origins);
    }

    public function test_production_has_no_localhost_origin_patterns(): void
    {
        $patterns = $this->corsConfigFor('production')['allowed_origins_patterns'];

        $this->assertSame([], $patterns, 'The any-port localhost pattern is dev-only.');
    }

    public function test_local_does_allow_the_vite_dev_server_origin(): void
    {
        $origins = $this->corsConfigFor('local')['allowed_origins'];

        $this->assertContains('http://localhost:5173', $origins);
    }

    public function test_local_keeps_the_any_port_localhost_pattern(): void
    {
        $patterns = $this->corsConfigFor('local')['allowed_origins_patterns'];

        $this->assertNotSame([], $patterns);
    }

    // =========================================================================
    // A real production frontend origin is still honoured
    // =========================================================================

    public function test_production_allows_a_real_https_frontend_url(): void
    {
        config(['app.frontend_url' => 'https://ikena.ramceslp.click']);

        $origins = $this->corsConfigFor('production')['allowed_origins'];

        $this->assertContains('https://ikena.ramceslp.click', $origins);
    }

    // =========================================================================
    // FRONTEND_URL normalisation
    //
    // A browser's Origin header never carries a trailing slash, so an origin
    // stored with one can never match and CORS fails silently in production —
    // no Laravel-side error, every frontend call blocked in the browser. The
    // env value must not have to be character-perfect.
    // =========================================================================

    public function test_a_trailing_slash_in_frontend_url_is_stripped(): void
    {
        config(['app.frontend_url' => 'https://ikena.ramceslp.click/']);

        $origins = $this->corsConfigFor('production')['allowed_origins'];

        $this->assertContains('https://ikena.ramceslp.click', $origins);
        $this->assertNotContains('https://ikena.ramceslp.click/', $origins);
    }

    public function test_repeated_trailing_slashes_are_stripped(): void
    {
        config(['app.frontend_url' => 'https://ikena.ramceslp.click//']);

        $this->assertContains(
            'https://ikena.ramceslp.click',
            $this->corsConfigFor('production')['allowed_origins'],
        );
    }

    public function test_surrounding_whitespace_in_frontend_url_is_ignored(): void
    {
        config(['app.frontend_url' => '  https://ikena.ramceslp.click  ']);

        $this->assertContains(
            'https://ikena.ramceslp.click',
            $this->corsConfigFor('production')['allowed_origins'],
        );
    }

    public function test_a_trailing_slash_does_not_defeat_the_loopback_guard(): void
    {
        // The dev-origin guard must not be bypassable by a stray slash.
        config(['app.frontend_url' => 'http://localhost:5173/']);

        $origins = $this->corsConfigFor('production')['allowed_origins'];

        $this->assertNotContains('http://localhost:5173', $origins);
        $this->assertNotContains('http://localhost:5173/', $origins);
    }

    public function test_a_frontend_url_of_only_a_slash_yields_no_origin(): void
    {
        config(['app.frontend_url' => '/']);

        // Normalises to empty — fail closed rather than allowing ''.
        $origins = $this->corsConfigFor('production')['allowed_origins'];

        $this->assertNotContains('', $origins);
        $this->assertNotContains('/', $origins);
    }

    public function test_production_does_not_hardcode_the_deploy_hostname(): void
    {
        // The production origin belongs in FRONTEND_URL, not in the config file.
        config(['app.frontend_url' => 'https://someone-elses-host.example']);

        $origins = $this->corsConfigFor('production')['allowed_origins'];

        $this->assertNotContains('https://ikena.ramceslp.click', $origins);
    }

    // =========================================================================
    // Capacitor origins survive everywhere (design Decision 6)
    // =========================================================================

    /**
     * @return array<string, array{0: string}>
     */
    public static function capacitorOriginProvider(): array
    {
        return [
            'android portless http' => ['http://localhost'],
            'ios portless https'    => ['https://localhost'],
            'capacitor scheme'      => ['capacitor://localhost'],
            'ionic scheme'          => ['ionic://localhost'],
        ];
    }

    #[DataProvider('capacitorOriginProvider')]
    public function test_production_still_allows_capacitor_origin(string $origin): void
    {
        $origins = $this->corsConfigFor('production')['allowed_origins'];

        $this->assertContains(
            $origin,
            $origins,
            "A shipped native app's WebView origin must be allowed in production.",
        );
    }

    // =========================================================================
    // No wildcards
    // =========================================================================

    public function test_allowed_methods_is_not_a_wildcard(): void
    {
        $methods = $this->corsConfigFor('production')['allowed_methods'];

        $this->assertNotContains('*', $methods);
        $this->assertContains('GET', $methods);
        $this->assertContains('POST', $methods);
        $this->assertContains('DELETE', $methods);
    }

    public function test_allowed_headers_is_not_a_wildcard(): void
    {
        $headers = $this->corsConfigFor('production')['allowed_headers'];

        $this->assertNotContains('*', $headers);
        $this->assertContains('Authorization', $headers);
        $this->assertContains('Content-Type', $headers);
    }

    public function test_credentials_are_not_supported(): void
    {
        $this->assertFalse($this->corsConfigFor('production')['supports_credentials']);
    }
}
