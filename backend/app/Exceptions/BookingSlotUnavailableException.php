<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by CreateBookingAction when no AgendaBlock covers the requested
 * scheduled_date/scheduled_time.
 *
 * StoreBookingRequest's withValidator already rejects this at the HTTP
 * boundary for the direct /api/bookings path, making it unreachable there
 * under normal conditions. It is genuinely reachable, however, via the
 * checkout-handoff redeem endpoint (mobile-capacitor-setup PR2), which
 * invokes CreateBookingAction directly against a snapshot that may be up to
 * 10 minutes stale — the covering AgendaBlock may have been removed or
 * blocked since the handoff was created. This is no longer a purely
 * defensive/theoretical guard.
 */
class BookingSlotUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This slot is no longer available. Please choose another time.');
    }
}
