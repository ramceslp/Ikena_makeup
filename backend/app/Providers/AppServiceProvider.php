<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Rate limiters for the API.
     *
     * Laravel 11 does NOT throttle the api middleware group by default — that
     * changed from Laravel 10, and it is opt-in via $middleware->throttleApi()
     * in bootstrap/app.php, which applies the 'api' limiter below to every
     * /api route. The named limiters are attached to specific routes in
     * routes/api.php.
     *
     * Locked by tests/Feature/RateLimitingTest.php.
     */
    private function configureRateLimiting(): void
    {
        // Baseline for every /api route. Keyed on the authenticated user when
        // there is one, so users sharing an IP (school, office, carrier NAT)
        // do not consume each other's budget.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        // Credential endpoints (login, register, google). Two independent
        // limits: per-IP stops password spraying across many accounts from one
        // host, per-email stops a distributed guess against one account. An
        // attacker must defeat both, and rotating one does not reset the other.
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(5)->by('auth-ip:'.$request->ip()),
            Limit::perMinute(5)->by('auth-email:'.strtolower((string) $request->input('email'))),
        ]);

        // Endpoints where an opaque secret is the only thing standing between
        // the caller and someone else's money: the 40-char single-use handoff
        // token and the payment-confirmation transaction ids.
        RateLimiter::for('opaque-token', fn (Request $request) => Limit::perMinute(10)
            ->by($request->user()?->id ?: $request->ip()));

        // Public certificate verification. Enumerating 'IKENA-' + 10 chars
        // leaks a student name and course title per hit, so this is a PII
        // scraping limit, not an abuse limit.
        RateLimiter::for('verify', fn (Request $request) => Limit::perMinute(15)
            ->by($request->ip()));

        // Multi-megabyte image uploads — a disk-exhaustion guard.
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(10)
            ->by($request->user()?->id ?: $request->ip()));

        // Signed media reads. One screen legitimately issues dozens of these
        // (the instructor review list renders 15 submissions x 2 photos), so
        // the 60/min baseline would lock it on the second page load. Keyed on
        // IP because these requests carry no bearer token — an <img> tag
        // cannot send one.
        RateLimiter::for('media', fn (Request $request) => Limit::perMinute(300)
            ->by($request->ip()));
    }
}
