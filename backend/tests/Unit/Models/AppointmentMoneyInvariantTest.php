<?php

namespace Tests\Unit\Models;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AppointmentMoneyInvariantTest
 *
 * Design D2/D1 — these are the double-count REGRESSION tests. The bug this
 * design corrects is: reading `orders.status='paid'` (deposit already
 * counted) and separately adding a settlement default computed from the
 * QUOTED deposit (`deposit_amount_cents`) instead of the COLLECTED deposit,
 * which subtracts money that never arrived and inflates income.
 *
 * `Appointment::booted()`'s `saving` hook makes the wrong version impossible
 * to record by enforcing two structural invariants on the two write-once
 * money channels (`deposit_collected_*`, `settled_*`):
 *  1. Pairing   — timestamp and amount are set together, never one without
 *     the other.
 *  2. Write-once — once a money event is recorded, it is immutable. There is
 *     no code path that can overwrite or re-record it, so it can never be
 *     double-counted by a second write.
 */
class AppointmentMoneyInvariantTest extends TestCase
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

    /**
     * @return array{0: Appointment, 1: Service, 2: User}
     */
    private function makeAppointment(array $overrides = []): array
    {
        $user    = $this->makeUser();
        $service = $this->makeService();

        $appointment = Appointment::create(array_merge([
            'service_id'           => $service->id,
            'user_id'              => $user->id,
            'order_id'             => null,
            'scheduled_date'       => '2026-08-10',
            'scheduled_time'       => '10:00',
            'slot_key'             => "{$service->id}|2026-08-10|10:00",
            'whatsapp'             => '+593099912345',
            'payment_mode'         => 'gateway',
            'deposit_amount_cents' => 3000,
            // PR1b: service_price_cents is now required at creation (no
            // bridging default — see the "required snapshot" guard below).
            // Every helper in this file that does NOT test that guard itself
            // must supply it so the pairing/write-once tests stay isolated
            // to the invariant they actually exercise.
            'service_price_cents'  => 10000,
            'status'               => 'pending',
        ], $overrides));

        return [$appointment, $service, $user];
    }

    // -------------------------------------------------------------------------
    // Pairing — deposit
    // -------------------------------------------------------------------------

    public function test_deposit_collected_at_without_amount_throws_domain_exception(): void
    {
        $this->expectException(DomainException::class);

        $this->makeAppointment([
            'deposit_collected_at'    => now(),
            'deposit_collected_cents' => 0,
        ]);
    }

    public function test_deposit_collected_cents_without_timestamp_throws_domain_exception(): void
    {
        $this->expectException(DomainException::class);

        $this->makeAppointment([
            'deposit_collected_at'    => null,
            'deposit_collected_cents' => 2000,
        ]);
    }

    public function test_deposit_collected_at_and_cents_together_is_valid(): void
    {
        [$appointment] = $this->makeAppointment([
            'deposit_collected_at'    => now(),
            'deposit_collected_cents' => 2000,
        ]);

        $this->assertDatabaseHas('appointments', [
            'id'                      => $appointment->id,
            'deposit_collected_cents' => 2000,
        ]);
    }

    // -------------------------------------------------------------------------
    // Pairing — settlement
    // -------------------------------------------------------------------------

    public function test_settled_at_without_amount_throws_domain_exception(): void
    {
        $this->expectException(DomainException::class);

        $this->makeAppointment([
            'settled_at'           => now(),
            'settled_amount_cents' => null,
        ]);
    }

    public function test_settled_amount_without_timestamp_throws_domain_exception(): void
    {
        $this->expectException(DomainException::class);

        $this->makeAppointment([
            'settled_at'           => null,
            'settled_amount_cents' => 6000,
        ]);
    }

    public function test_settled_at_and_amount_together_is_valid(): void
    {
        [$appointment] = $this->makeAppointment([
            'settled_at'           => now(),
            'settled_amount_cents' => 6000,
        ]);

        $this->assertDatabaseHas('appointments', [
            'id'                    => $appointment->id,
            'settled_amount_cents'  => 6000,
        ]);
    }

    // -------------------------------------------------------------------------
    // Write-once — deposit
    // -------------------------------------------------------------------------

    public function test_mutating_a_recorded_deposit_throws_domain_exception(): void
    {
        [$appointment] = $this->makeAppointment([
            'deposit_collected_at'    => now(),
            'deposit_collected_cents' => 2000,
        ]);

        $this->expectException(DomainException::class);

        $appointment->update(['deposit_collected_cents' => 3000]);
    }

    public function test_updating_unrelated_field_after_deposit_recorded_does_not_throw(): void
    {
        [$appointment] = $this->makeAppointment([
            'deposit_collected_at'    => now(),
            'deposit_collected_cents' => 2000,
        ]);

        // Sanity check: the write-once guard is scoped to the money pair,
        // not the whole row — unrelated fields remain freely editable.
        $appointment->update(['whatsapp' => '+593099999999']);

        $this->assertDatabaseHas('appointments', [
            'id'       => $appointment->id,
            'whatsapp' => '+593099999999',
        ]);
    }

    // -------------------------------------------------------------------------
    // Write-once — settlement (re-settling an already-settled row)
    // -------------------------------------------------------------------------

    public function test_re_settling_an_already_settled_appointment_throws_domain_exception(): void
    {
        [$appointment] = $this->makeAppointment([
            'settled_at'           => now(),
            'settled_amount_cents' => 6000,
        ]);

        $this->expectException(DomainException::class);

        // Attempting to record a second, different settlement amount on the
        // same appointment is exactly the double-count shape this guard
        // exists to make impossible.
        $appointment->update(['settled_amount_cents' => 8000, 'settled_at' => now()]);
    }

    // -------------------------------------------------------------------------
    // Required snapshot — the third invariant from design D2, deferred out of
    // PR1a (see architecture/admin-reports-pr-budget carried-debt item 2) and
    // closed here now that CreateBookingAction always supplies the value.
    // -------------------------------------------------------------------------

    public function test_creating_an_appointment_without_service_price_cents_throws_domain_exception(): void
    {
        $this->expectException(DomainException::class);

        $user    = $this->makeUser();
        $service = $this->makeService();

        Appointment::create([
            'service_id'           => $service->id,
            'user_id'              => $user->id,
            'order_id'             => null,
            'scheduled_date'       => '2026-08-10',
            'scheduled_time'       => '10:00',
            'slot_key'             => "{$service->id}|2026-08-10|10:00",
            'whatsapp'             => '+593099912345',
            'payment_mode'         => 'gateway',
            'deposit_amount_cents' => 3000,
            'status'               => 'pending',
            // service_price_cents intentionally omitted.
        ]);
    }

    public function test_updating_an_existing_appointment_without_service_price_cents_set_does_not_throw(): void
    {
        // The guard only fires on CREATE (design D2's "required on create").
        // An existing row already carries a value — an unrelated update must
        // not be blocked by this guard even though the update payload itself
        // does not repeat service_price_cents.
        [$appointment] = $this->makeAppointment();

        $appointment->update(['whatsapp' => '+593099998888']);

        $this->assertDatabaseHas('appointments', [
            'id'       => $appointment->id,
            'whatsapp' => '+593099998888',
        ]);
    }
}
