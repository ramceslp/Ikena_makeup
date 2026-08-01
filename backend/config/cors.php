<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // Enumerated, not '*' — the API only ever needs these verbs.
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // NOTE on the environment gate below: `app()->environment()` is NOT usable
    // inside a config file. LoadConfiguration evaluates these files BEFORE it
    // calls $app->detectEnvironment(), so the container has no 'env' binding
    // yet and app()->environment() throws "Target class [env] does not exist".
    // config('app.env') is the correct accessor — config/app.php is loaded
    // before this file (alphabetical order) and its value is baked into
    // `config:cache`, so the gate stays correct in production. The env()
    // fallbacks cover the load-order edge case without depending on it.
    // array_unique because in local, FRONTEND_URL and the dev-only entry below
    // are both http://localhost:5173.
    'allowed_origins' => array_values(array_unique(array_filter(array_merge(
        // Guarded because config/app.php defaults frontend_url to the Vite dev
        // server: a production deploy that forgot to set FRONTEND_URL must not
        // silently inherit http://localhost:5173 as a trusted origin. Locked by
        // CorsConfigTest::test_production_does_not_allow_a_localhost_frontend_url_fallback.
        (function (): array {
            $appEnv      = config('app.env') ?? env('APP_ENV', 'production');
            $frontendUrl = trim((string) (config('app.frontend_url') ?? env('FRONTEND_URL', '')));

            if ($frontendUrl === '') {
                return [];
            }

            $isLoopback = (bool) preg_match(
                '#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#i',
                rtrim($frontendUrl, '/'),
            );

            $isDevEnv = in_array($appEnv, ['local', 'testing'], true);

            return ($isLoopback && ! $isDevEnv) ? [] : [$frontendUrl];
        })(),

        // Capacitor WebView origins — allowed in EVERY environment (including
        // production). A shipped native app's origin is always portless
        // localhost-scheme, regardless of APP_ENV: http://localhost (Android),
        // https://localhost / capacitor://localhost / ionic://localhost (iOS).
        // See design Decision 6 (sdd/mobile-capacitor-setup/design.md).
        ['http://localhost', 'https://localhost', 'capacitor://localhost', 'ionic://localhost'],

        // The Vite dev server — LOCAL/TESTING ONLY. A dev origin must never
        // ship to production.
        in_array(config('app.env') ?? env('APP_ENV', 'production'), ['local', 'testing'], true)
            ? ['http://localhost:5173']
            : [],
    )))),

    // Allow any localhost port in local development (Vite may shift ports,
    // e.g. 5173 -> 5174 when one is busy). This convenience pattern stays
    // dev-only — see the NOTE above on why the gate reads config('app.env').
    'allowed_origins_patterns' => (config('app.env') ?? env('APP_ENV', 'production')) === 'local'
        ? ['/^http:\/\/(localhost|127\.0\.0\.1):\d+$/']
        : [],

    // Enumerated, not '*'. Authorization is required because the SPA and the
    // Capacitor app authenticate with Sanctum bearer tokens.
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,

];
