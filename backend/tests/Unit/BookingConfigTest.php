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
}
