<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by CreateBookingAction when the venue-wide concurrency cap for the
 * requested [scheduled_time, scheduled_end_time) window has already been
 * reached (BOOK-002/BOOK-005).
 */
class BookingCapacityExceededException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This time slot has reached capacity. Please choose another time.');
    }
}
