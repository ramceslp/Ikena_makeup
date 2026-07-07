<?php

namespace Tests\Unit;

use Tests\TestCase;

class BookingConfigTest extends TestCase
{
    public function test_booking_timezone_is_guayaquil(): void
    {
        $this->assertSame('America/Guayaquil', config('booking.timezone'));
    }

    public function test_booking_deposit_default_percentage_is_50(): void
    {
        $this->assertSame(50, config('booking.deposit.default_percentage'));
    }

    // -------------------------------------------------------------------------
    // DM-003 — venue.* configuration keys
    // -------------------------------------------------------------------------

    public function test_venue_default_concurrency_limit_is_1(): void
    {
        $this->assertSame(1, config('booking.venue.default_concurrency_limit'));
    }

    public function test_venue_default_soft_threshold_is_null(): void
    {
        $this->assertNull(config('booking.venue.default_soft_threshold'));
    }

    public function test_venue_warning_message_is_set(): void
    {
        $this->assertSame(
            'Alta demanda — quedan pocos horarios',
            config('booking.venue.warning_message')
        );
    }

    public function test_venue_candidate_granularity_minutes_is_30(): void
    {
        $this->assertSame(30, config('booking.venue.candidate_granularity_minutes'));
    }

    public function test_venue_look_ahead_days_is_60(): void
    {
        $this->assertSame(60, config('booking.venue.look_ahead_days'));
    }
}
