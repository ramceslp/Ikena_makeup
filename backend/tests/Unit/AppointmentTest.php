<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AppointmentTest
 *
 * Covers Appointment::makeSlotKey(), the audit-field key builder still used by
 * BookingController::store() (slot_key UNIQUE constraint was dropped in
 * Slice 1/DM-002, but the column and its key format remain in active use as
 * an audit field — confirmed live during the Slice 5 cleanup audit).
 *
 * These two tests were relocated (not newly written) from the now-deleted
 * ServiceSlotTest.php and SlotAvailabilityResolverTest.php as part of the
 * venue-agenda-concurrency Slice 5 cleanup, since makeSlotKey() itself is
 * unrelated to the legacy ServiceSlot/SlotAvailabilityResolver code being
 * removed in that pass.
 */
class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_make_slot_key_returns_expected_format(): void
    {
        $service = Service::factory()->create();
        $key     = Appointment::makeSlotKey($service->id, '2026-07-04', '10:00');

        $this->assertSame("{$service->id}|2026-07-04|10:00", $key);
    }

    public function test_make_slot_key_normalizes_hhmmss_to_hhmm(): void
    {
        // MySQL TIME columns return '10:00:00'; SQLite returns '10:00'.
        // makeSlotKey must produce the same key regardless of which form arrives.
        $keyFromShort = Appointment::makeSlotKey(1, '2026-07-01', '10:00');
        $keyFromLong  = Appointment::makeSlotKey(1, '2026-07-01', '10:00:00');

        $this->assertSame($keyFromShort, $keyFromLong,
            'makeSlotKey must normalize HH:MM:SS and HH:MM to the same key (MySQL/SQLite parity).'
        );
    }
}
