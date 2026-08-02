<?php

namespace Tests\Feature\Admin;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * AppointmentMarkPaidSettlementTest
 *
 * Design D1/D2 — `markPaid` now records the SECOND write-once money channel,
 * `settled_amount_cents`/`settled_at` (money collected IN PERSON). The
 * default, when the admin does not pass an explicit amount, is:
 *
 *   settled_amount_cents = max(0, service_price_cents - deposit_collected_cents)
 *
 * THE correctness detail this suite pins: the subtrahend is
 * `deposit_collected_cents` (what the gateway actually captured), never
 * `deposit_amount_cents` (the quoted deposit, which is not itself income).
 * Subtracting the quoted-but-uncollected amount would double-count nothing
 * collected as if it were — that is the exact bug design D1 exists to
 * prevent. One formula covers both product cases: a captured deposit yields
 * the balance; a never-captured deposit (deposit_collected_cents=0) yields
 * the full price.
 */
class AppointmentMarkPaidSettlementTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeService(float $price = 100.00): Service
    {
        return Service::factory()->create([
            'availability_type' => 'by_appointment',
            'is_published' => true,
            'price' => $price,
            'deposit_percentage' => 30,
        ]);
    }

    /**
     * Create a pending appointment with the given money state, bypassing the
     * write paths (CreateBookingAction / CheckoutController) so each scenario
     * can pin an exact fixture independent of those flows' own tests.
     */
    private function makeAppointment(array $overrides = []): Appointment
    {
        $service = $this->makeService();
        $user = User::factory()->create();

        return Appointment::create(array_merge([
            'service_id' => $service->id,
            'user_id' => $user->id,
            'order_id' => null,
            'scheduled_date' => '2026-08-10',
            'scheduled_time' => '10:00',
            'slot_key' => "{$service->id}|2026-08-10|10:00",
            'whatsapp' => '+593099912345',
            'payment_mode' => 'gateway',
            'deposit_amount_cents' => 2400,
            'service_price_cents' => 8000,
            'status' => 'pending',
        ], $overrides));
    }

    /**
     * Spec scenario "Settlement on a paid-deposit appointment, no explicit
     * amount": deposit already collected via the gateway → default is the
     * remaining balance.
     */
    public function test_settlement_default_is_balance_when_deposit_already_collected(): void
    {
        $admin = $this->makeAdmin();
        $appointment = $this->makeAppointment([
            'service_price_cents' => 8000,
            'deposit_collected_cents' => 2000,
            'deposit_collected_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/appointments/{$appointment->id}/mark-paid");

        $response->assertStatus(200)
            ->assertJsonPath('data.settled_amount_cents', 6000);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'settled_amount_cents' => 6000,
        ]);
        $this->assertNotNull($appointment->fresh()->settled_at);
    }

    /**
     * Spec scenario "Settlement on a pending-deposit appointment, no explicit
     * amount": deposit never captured (deposit_collected_cents=0) → default
     * is the FULL price, not the price minus the merely-quoted deposit.
     */
    public function test_settlement_default_is_full_price_when_deposit_never_collected(): void
    {
        $admin = $this->makeAdmin();
        $appointment = $this->makeAppointment([
            'service_price_cents' => 8000,
            'deposit_collected_cents' => 0,
            'deposit_collected_at' => null,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/appointments/{$appointment->id}/mark-paid");

        $response->assertStatus(200)
            ->assertJsonPath('data.settled_amount_cents', 8000);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'settled_amount_cents' => 8000,
        ]);
    }

    /**
     * Spec scenario "Settlement amount differs from computed balance
     * (discount applied)": an explicit amount is persisted verbatim, never
     * recomputed from the default formula.
     */
    public function test_explicit_settled_amount_is_not_recomputed(): void
    {
        $admin = $this->makeAdmin();
        $appointment = $this->makeAppointment([
            'service_price_cents' => 8000,
            'deposit_collected_cents' => 2000,
            'deposit_collected_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/appointments/{$appointment->id}/mark-paid", [
            'settled_amount_cents' => 5000,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.settled_amount_cents', 5000);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'settled_amount_cents' => 5000,
        ]);
    }

    /**
     * Spec scenario "Zero-price service settlement": contributes exactly 0,
     * not null and not an error.
     */
    public function test_zero_price_service_settles_to_zero(): void
    {
        $admin = $this->makeAdmin();
        $appointment = $this->makeAppointment([
            'service_price_cents' => 0,
            'deposit_amount_cents' => 0,
            'deposit_collected_cents' => 0,
            'deposit_collected_at' => null,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/appointments/{$appointment->id}/mark-paid");

        $response->assertStatus(200)
            ->assertJsonPath('data.settled_amount_cents', 0);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'settled_amount_cents' => 0,
        ]);
        $this->assertNotNull($appointment->fresh()->settled_at, 'Zero must still be recorded as an explicit settlement, not left null.');
    }

    /**
     * Regression guard — the bug design D1 corrects: subtracting the QUOTED
     * deposit (deposit_amount_cents) instead of the COLLECTED deposit
     * (deposit_collected_cents) would produce 8000 - 2400 = 5600 here, not
     * the correct 8000 (deposit was never actually collected).
     */
    public function test_default_never_subtracts_the_quoted_deposit_amount(): void
    {
        $admin = $this->makeAdmin();
        $appointment = $this->makeAppointment([
            'service_price_cents' => 8000,
            'deposit_amount_cents' => 2400, // quoted, never collected
            'deposit_collected_cents' => 0,
            'deposit_collected_at' => null,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/appointments/{$appointment->id}/mark-paid");

        $response->assertStatus(200)
            ->assertJsonPath('data.settled_amount_cents', 8000);
    }
}
