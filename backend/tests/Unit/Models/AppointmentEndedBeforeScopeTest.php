<?php

namespace Tests\Unit\Models;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * AppointmentEndedBeforeScopeTest
 *
 * Design D6 — the portable, driver-agnostic "ended before $moment" predicate
 * used by the receivable-bucket queries (PR4a):
 *
 *   scheduled_date < M.date
 *   OR (scheduled_date = M.date AND scheduled_end_time <= M.time)
 *
 * `status != 'cancelled'` is folded into the scope itself (rather than left
 * to every caller to repeat) because every current and planned caller of
 * this scope (buckets B/C) always pairs it with that condition — see the
 * design D6 bucket table.
 *
 * Adjustment #4 (architecture/admin-reports-design-adjustments) pins the
 * NULL-`scheduled_end_time` behaviour explicitly: `scheduled_end_time` is a
 * nullable `time` column (2026_06_27_100001_add_scheduled_end_time_to_appointments.php:13).
 * SQL's `NULL <= X` evaluates to NULL (falsy in a WHERE clause), so the
 * second predicate branch never fires for a same-day row with a NULL end
 * time — it is only caught the FOLLOWING day, once the first branch
 * (`scheduled_date < M.date`) no longer depends on the time column at all.
 */
class AppointmentEndedBeforeScopeTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeService(): Service
    {
        return Service::factory()->create([
            'availability_type'  => 'by_appointment',
            'is_published'       => true,
            'price'              => 100.00,
            'deposit_percentage' => 30,
        ]);
    }

    private function makeAppointment(array $overrides = []): Appointment
    {
        $user    = $this->makeUser();
        $service = $this->makeService();

        return Appointment::create(array_merge([
            'service_id'           => $service->id,
            'user_id'              => $user->id,
            'order_id'             => null,
            'scheduled_date'       => '2026-08-10',
            'scheduled_time'       => '10:00',
            'scheduled_end_time'   => '11:00',
            'slot_key'             => null,
            'whatsapp'             => '+593099912345',
            'payment_mode'         => 'gateway',
            'deposit_amount_cents' => 3000,
            'service_price_cents'  => 10000,
            'status'               => 'pending',
        ], $overrides));
    }

    public function test_boundary_at_exactly_moment_is_included(): void
    {
        $appointment = $this->makeAppointment([
            'scheduled_date'     => '2026-08-10',
            'scheduled_end_time' => '14:00:00',
        ]);

        $moment = Carbon::parse('2026-08-10 14:00:00');

        $this->assertTrue(
            Appointment::endedBefore($moment)->whereKey($appointment->id)->exists(),
            'A row whose end time exactly equals the moment must be included (<=, inclusive boundary).'
        );
    }

    public function test_one_second_before_boundary_is_excluded(): void
    {
        $appointment = $this->makeAppointment([
            'scheduled_date'     => '2026-08-10',
            'scheduled_end_time' => '14:00:01',
        ]);

        $moment = Carbon::parse('2026-08-10 14:00:00');

        $this->assertFalse(
            Appointment::endedBefore($moment)->whereKey($appointment->id)->exists(),
            'A row ending 1 second after the moment must not be included yet.'
        );
    }

    public function test_day_crossing_row_is_included_regardless_of_end_time(): void
    {
        // scheduled yesterday, end time is technically "late" (23:00) — but
        // since scheduled_date < moment date, branch 1 fires and the row is
        // included without even consulting scheduled_end_time.
        $appointment = $this->makeAppointment([
            'scheduled_date'     => '2026-08-09',
            'scheduled_end_time' => '23:00:00',
        ]);

        $moment = Carbon::parse('2026-08-10 08:00:00');

        $this->assertTrue(
            Appointment::endedBefore($moment)->whereKey($appointment->id)->exists()
        );
    }

    public function test_cancelled_appointment_is_excluded_even_when_time_matches(): void
    {
        $appointment = $this->makeAppointment([
            'scheduled_date'     => '2026-08-01',
            'scheduled_end_time' => '10:00:00',
            'status'             => 'cancelled',
        ]);

        $moment = Carbon::parse('2026-08-10 08:00:00');

        $this->assertFalse(
            Appointment::endedBefore($moment)->whereKey($appointment->id)->exists(),
            'A cancelled appointment must never appear in endedBefore(), regardless of how long ago it ended.'
        );
    }

    // -------------------------------------------------------------------------
    // Adjustment #4 — NULL scheduled_end_time
    // -------------------------------------------------------------------------

    public function test_null_end_time_on_a_same_day_row_is_not_matched(): void
    {
        $appointment = $this->makeAppointment([
            'scheduled_date'     => '2026-08-10',
            'scheduled_end_time' => null,
        ]);

        // Same calendar day as the row — branch 1 (date <) cannot fire, and
        // branch 2 (date = AND end_time <=) cannot fire either because
        // NULL <= anything is NULL (falsy), never true.
        $moment = Carbon::parse('2026-08-10 23:59:59');

        $this->assertFalse(
            Appointment::endedBefore($moment)->whereKey($appointment->id)->exists(),
            'A same-day row with NULL scheduled_end_time must not be matched by the time branch.'
        );
    }

    public function test_null_end_time_row_is_caught_the_following_day(): void
    {
        $appointment = $this->makeAppointment([
            'scheduled_date'     => '2026-08-10',
            'scheduled_end_time' => null,
        ]);

        // The following day: branch 1 (scheduled_date < moment date) fires
        // purely on the date comparison — scheduled_end_time is never
        // consulted, so a permanently-NULL end time no longer blocks the match.
        $moment = Carbon::parse('2026-08-11 00:00:01');

        $this->assertTrue(
            Appointment::endedBefore($moment)->whereKey($appointment->id)->exists()
        );
    }

    public function test_not_yet_ended_row_is_excluded(): void
    {
        $appointment = $this->makeAppointment([
            'scheduled_date'     => '2026-08-15',
            'scheduled_end_time' => '10:00:00',
        ]);

        $moment = Carbon::parse('2026-08-10 08:00:00');

        $this->assertFalse(
            Appointment::endedBefore($moment)->whereKey($appointment->id)->exists()
        );
    }
}
