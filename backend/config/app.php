<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Frontend URL
    |--------------------------------------------------------------------------
    |
    | Base URL of the web frontend (the Vue SPA). Used to build fully
    | qualified links back to it — e.g. the checkout-handoff resume URL
    | the mobile app opens in the system/in-app Browser (see
    | App\Http\Controllers\Api\CheckoutHandoffController).
    |
    */

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Comma-separated addresses/CIDRs whose X-Forwarded-* headers are believed
    | (applied in bootstrap/app.php). The app runs behind a TLS-terminating
    | proxy — a Cloudflare tunnel in development — which forwards plain HTTP,
    | so without this Laravel builds http:// URLs for an https:// site and the
    | browser blocks them as mixed content.
    |
    | Deliberately NOT '*'. The rate limiters in AppServiceProvider key on
    | $request->ip(); trusting X-Forwarded-For from anywhere would let a
    | client rotate that header for a fresh login-throttle budget per fake
    | address. The default trusts only a proxy on the loopback, which is where
    | cloudflared runs. Behind a proxy on another host (a load balancer, or
    | Cloudflare's edge reaching the origin directly), set TRUSTED_PROXIES to
    | those addresses or ranges — never to '*'.
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES', '127.0.0.1,::1'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions.
    |
    | This is DELIBERATELY not Laravel's "UTC" default. The business is a
    | single venue in Ecuador: every appointment, agenda block and opening
    | hour is defined, entered and read in America/Guayaquil local time, and
    | booking.timezone has always said so. Leaving the framework on UTC meant
    | every bare now() in the codebase silently disagreed with the domain by
    | five hours, and each boundary that had to bridge the gap was expected to
    | remember on its own. Two of them did not, and both shipped as real bugs
    | — see StoreBookingRequest::rules() (same-day bookings rejected every
    | evening) and DashboardController::buildSalesOverTime().
    |
    | Ecuador observes no DST, which removes the usual argument against
    | storing local wall-clock time: there is no ambiguous or skipped hour to
    | land on. ConfigTimezoneTest asserts that assumption still holds.
    |
    | MUST stay equal to config('booking.timezone'). ConfigTimezoneTest pins
    | that invariant so the two cannot drift apart again.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'America/Guayaquil'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
