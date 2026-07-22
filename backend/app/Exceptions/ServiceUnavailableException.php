<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by CreateBookingAction when the service targeted by a booking
 * request fails one of the business-rule checks that StoreBookingRequest's
 * withValidator() already enforces at the direct /api/bookings HTTP
 * boundary:
 *   - the service is not published;
 *   - the service's availability_type is not 'by_appointment';
 *   - BOOK-004 — the requested time + service.duration_hours overflows the
 *     covering AgendaBlock's close_time.
 *
 * StoreBookingRequest's validation only protects /api/bookings. The
 * checkout-handoff redeem endpoint (mobile-capacitor-setup PR2) invokes
 * CreateBookingAction directly against a snapshot that may be up to 10
 * minutes stale (the service could have been unpublished, its
 * availability_type changed, or its duration extended since the handoff was
 * created) — so the Action re-runs these same checks itself instead of
 * trusting the snapshot verbatim.
 */
class ServiceUnavailableException extends RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
