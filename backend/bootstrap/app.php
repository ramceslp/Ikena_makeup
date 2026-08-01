<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsInstructor;
use App\Http\Middleware\OptionalSanctum;
use App\Http\Middleware\RejectScopedCheckoutToken;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // NOTE: trusted proxies are configured in AppServiceProvider::boot(),
        // not here. This closure runs before the config repository exists, so
        // config('app.trusted_proxies') would fatal, and env() is unavailable
        // once the config is cached. TrustProxies is already in the default
        // global middleware stack; the provider only supplies its values.

        // Prepended so the headers land on error responses too (401/404/500),
        // not only on responses that reach a controller.
        $middleware->api(prepend: [
            HandleCors::class,
            SecurityHeaders::class,
        ]);

        // Laravel 11 does NOT throttle the api group by default (Laravel 10
        // did). Without this the whole API is unthrottled — see the 'api'
        // limiter in AppServiceProvider::configureRateLimiting().
        $middleware->throttleApi('api');

        $middleware->alias([
            'instructor'    => EnsureUserIsInstructor::class,
            'admin'         => EnsureUserIsAdmin::class,
            'auth.optional' => OptionalSanctum::class,
            'reject-scoped-checkout-token' => RejectScopedCheckoutToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
