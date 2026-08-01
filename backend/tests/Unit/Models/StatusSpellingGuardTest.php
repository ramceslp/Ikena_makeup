<?php

namespace Tests\Unit\Models;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * StatusSpellingGuardTest
 *
 * Design D5 — status spelling drift prevention. `orders.status` has exactly
 * one documented spelling (`canceled`, single L); `appointments.status` has
 * its own documented spelling (`cancelled`, two Ls) and the two are NOT the
 * same enum. Both guards are validated inside their model's EXISTING `saving`
 * hook (`Order::booted()` / `Appointment::booted()`), and the two guards
 * sitting side by side in this one test file is the documentation of why the
 * columns legitimately disagree: they are two different domain concepts that
 * happen to share the word "cancelled/canceled" in English.
 */
class StatusSpellingGuardTest extends TestCase
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

    // -------------------------------------------------------------------------
    // Order::STATUSES — 'cancelled' (two Ls) is rejected
    // -------------------------------------------------------------------------

    public function test_order_status_cancelled_two_ls_throws_domain_exception(): void
    {
        $this->expectException(DomainException::class);

        $user = $this->makeUser();

        Order::create([
            'user_id'               => $user->id,
            'type'                  => 'product_cart',
            'client_transaction_id' => 'ORD-status-guard-001',
            'gateway'               => 'fake',
            'amount_cents'          => 5000,
            'currency'              => 'USD',
            'status'                => 'cancelled', // two Ls — not in Order::STATUSES
        ]);
    }

    public function test_order_status_canceled_single_l_is_accepted(): void
    {
        $user = $this->makeUser();

        $order = Order::create([
            'user_id'               => $user->id,
            'type'                  => 'product_cart',
            'client_transaction_id' => 'ORD-status-guard-002',
            'gateway'               => 'fake',
            'amount_cents'          => 5000,
            'currency'              => 'USD',
            'status'                => 'canceled', // single L — the documented spelling
        ]);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'canceled']);
    }

    public function test_order_status_update_to_cancelled_two_ls_throws_domain_exception(): void
    {
        // The guard must fire on UPDATE too (it lives in `saving`, not `creating`),
        // otherwise a two-step create-then-update could still smuggle the bad
        // spelling in.
        $user  = $this->makeUser();
        $order = Order::create([
            'user_id'               => $user->id,
            'type'                  => 'product_cart',
            'client_transaction_id' => 'ORD-status-guard-003',
            'gateway'               => 'fake',
            'amount_cents'          => 5000,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);

        $this->expectException(DomainException::class);

        $order->update(['status' => 'cancelled']);
    }

    // -------------------------------------------------------------------------
    // Appointment::STATUSES — 'cancelled' (two Ls) is the CORRECT spelling here
    // -------------------------------------------------------------------------

    public function test_appointment_status_cancelled_two_ls_is_accepted(): void
    {
        $user        = $this->makeUser();
        $service     = $this->makeService();
        $appointment = Appointment::create([
            'service_id'             => $service->id,
            'user_id'                => $user->id,
            'order_id'               => null,
            'scheduled_date'         => '2026-08-10',
            'scheduled_time'         => '10:00',
            'slot_key'               => "{$service->id}|2026-08-10|10:00",
            'whatsapp'               => '+593099912345',
            'payment_mode'           => 'gateway',
            'deposit_amount_cents'   => 3000,
            'status'                 => 'cancelled', // two Ls — correct here per Appointment::STATUSES
        ]);

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'cancelled']);
    }

    public function test_appointment_status_unknown_value_throws_domain_exception(): void
    {
        $this->expectException(DomainException::class);

        $user    = $this->makeUser();
        $service = $this->makeService();

        Appointment::create([
            'service_id'             => $service->id,
            'user_id'                => $user->id,
            'order_id'               => null,
            'scheduled_date'         => '2026-08-11',
            'scheduled_time'         => '11:00',
            'slot_key'               => "{$service->id}|2026-08-11|11:00",
            'whatsapp'               => '+593099912345',
            'payment_mode'           => 'gateway',
            'deposit_amount_cents'   => 3000,
            'status'                 => 'not_a_real_status',
        ]);
    }
}
