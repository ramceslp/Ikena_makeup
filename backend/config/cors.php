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

    'allowed_methods' => ['*'],

    // Static, always-on origins — allowed in EVERY environment (including
    // production). The Capacitor origins are required because a shipped
    // native app's WebView origin is always portless localhost-scheme,
    // regardless of APP_ENV: http://localhost (Android), https://localhost /
    // capacitor://localhost / ionic://localhost (iOS). See design Decision 6
    // (sdd/mobile-capacitor-setup/design.md).
    'allowed_origins' => array_filter([
        env('FRONTEND_URL', 'http://localhost:5173'),
        'http://localhost', 'https://localhost',
        'capacitor://localhost', 'ionic://localhost',
    ]),

    // Allow any localhost port in local development (Vite may shift ports,
    // e.g. 5173 -> 5174 when one is busy). Only active when APP_ENV=local —
    // this convenience pattern stays dev-only.
    'allowed_origins_patterns' => env('APP_ENV') === 'local'
        ? ['/^http:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/']
        : [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
