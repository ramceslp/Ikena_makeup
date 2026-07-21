<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by CreateBookingAction when no AgendaBlock covers the requested
 * scheduled_date/scheduled_time.
 *
 * Defensive — StoreBookingRequest's withValidator already rejects this at
 * the HTTP boundary, so this should be unreachable via /api/bookings. It
 * exists so a caller without that same request-time validation (e.g. the
 * checkout-handoff redeem endpoint re-running a possibly-stale booking
 * snapshot) still gets a safe rejection instead of an unhandled null block.
 */
class BookingSlotUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This slot is no longer available. Please choose another time.');
    }
}
