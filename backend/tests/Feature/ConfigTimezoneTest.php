<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Tests\TestCase;

/**
 * Pins the application timezone to the venue's timezone.
 *
 * The whole domain — agenda blocks, opening hours, appointment slots, the
 * booking window — is expressed in America/Guayaquil, and booking.timezone
 * has always declared that. app.timezone was left on Laravel's UTC default,
 * so every bare now() in the codebase disagreed with the domain by five
 * hours and each boundary was expected to bridge the gap on its own.
 *
 * Two boundaries forgot, and both shipped as user-visible bugs:
 *
 *  - StoreBookingRequest::rules() used after_or_equal:today, which Laravel
 *    resolves through app.timezone, so same-day bookings were rejected as
 *    being in the past between 19:00 Guayaquil and midnight UTC.
 *  - DashboardController::buildSalesOverTime() built its window from bare
 *    Carbon::now() and, combined with a month-overflow bug, dropped a month
 *    of revenue.
 *
 * These assertions exist so that class of bug cannot come back by way of a
 * config edit. If someone needs the app on UTC again, that has to be a
 * deliberate change here, with the domain boundaries revisited.
 */
class ConfigTimezoneTest extends TestCase
{
    public function test_app_timezone_matches_the_venue_timezone(): void
    {
        $this->assertSame(
            config('booking.timezone'),
            config('app.timezone'),
            'app.timezone and booking.timezone must not drift apart: a bare now() has to mean the same instant as a venue-local one.'
        );
    }

    public function test_venue_timezone_is_america_guayaquil(): void
    {
        $this->assertSame('America/Guayaquil', config('booking.timezone'));
    }

    /**
     * The behavioural consequence, not just the config value: a bare now()
     * and an explicitly venue-scoped one must agree on the calendar date.
     * This is the exact assertion that would have failed before the change
     * during the five-hour window where UTC had already rolled over.
     */
    public function test_bare_now_and_venue_now_agree_on_the_date(): void
    {
        $this->assertSame(
            Carbon::now(config('booking.timezone'))->toDateString(),
            Carbon::now()->toDateString(),
        );
    }

    /**
     * Guards the no-DST assumption the decision rests on. If Ecuador ever
     * adopts daylight saving, storing local wall-clock time stops being safe
     * and this decision needs revisiting rather than silently degrading.
     */
    public function test_venue_timezone_has_no_dst_transitions(): void
    {
        $tz = new \DateTimeZone(config('booking.timezone'));

        $transitions = $tz->getTransitions(
            (new \DateTime('now'))->getTimestamp(),
            (new \DateTime('+5 years'))->getTimestamp(),
        );

        // getTransitions() always returns the period in effect at the start
        // timestamp, so a zone with no upcoming changes yields exactly one.
        $this->assertCount(
            1,
            $transitions,
            'America/Guayaquil is expected to have no DST transitions; storing venue-local time assumes there is no ambiguous or skipped hour.'
        );
    }
}
