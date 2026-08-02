<?php

namespace Tests\Feature\Checkout;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * AppointmentDepositCollectedTest
 *
 * Design D1 — the FIRST of the two write-once money channels on
 * `appointments`: `deposit_collected_cents`/`deposit_collected_at` record
 * money the GATEWAY actually captured, distinct from `deposit_amount_cents`
 * (the QUOTED deposit, never itself income). `CheckoutController::confirm()`'s
 * appointment branch (~line 174) writes this pair the moment the gateway
 * confirms — it is the only write site for this column pair (the admin
 * `markPaid` flow writes the OTHER channel, `settled_*`, for the balance
 * collected in person).
 */
class AppointmentDepositCollectedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The approved-payment path transitions the appointment to paid, which
     * dispatches BookingConfirmed over FCM. Faking the notification keeps this
     * suite independent of Firebase credentials — CI copies .env.example, which
     * has none, so a real send fails with "Unable to determine the Firebase
     * Project ID" even though the money assertions below never touch FCM.
     * Same convention as BookingConfirmedNotificationTest.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function makeService(float $price = 100.00, int $depositPct = 30): Service
    {
        return Service::factory()->create([
            'availability_type' => 'by_appointment',
            'is_published' => true,
            'price' => $price,
            'deposit_percentage' => $depositPct,
            'duration_hours' => 1,
        ]);
    }

    /**
     * Create a pending appointment + linked pending order, exactly like
     * CreateBookingAction does, so this test exercises the same shape the
     * real gateway-confirm flow sees.
     */
    private function makeAppointmentOrder(User $user, Service $service, array $orderOverrides = []): Order
    {
        $depositCents = (int) round((float) $service->price * $service->depositPercentage() / 100 * 100);

        $appointment = Appointment::create([
            'service_id' => $service->id,
            'user_id' => $user->id,
            'order_id' => null,
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00',
            'scheduled_end_time' => '11:00',
            'slot_key' => null,
            'whatsapp' => '+593999999999',
            'payment_mode' => 'gateway',
            'deposit_amount_cents' => $depositCents,
            'service_price_cents' => (int) round((float) $service->price * 100),
            'status' => 'pending',
        ]);

        $order = Order::create(array_merge([
            'user_id' => $user->id,
            'appointment_id' => $appointment->id,
            'client_transaction_id' => 'ORD-appt-'.$appointment->id,
            'gateway' => 'fake',
            'amount_cents' => $depositCents,
            'currency' => 'USD',
            'status' => 'pending',
        ], $orderOverrides));

        $appointment->update(['order_id' => $order->id]);

        return $order->fresh();
    }

    public function test_confirm_approved_writes_deposit_collected_cents_and_at(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $service = $this->makeService(100.00, 30); // deposit = 3000 cents

        $order = $this->makeAppointmentOrder($user, $service);

        $this->postJson('/api/payments/confirm', [
            'id' => 1,
            'clientTransactionId' => $order->client_transaction_id,
        ])->assertStatus(200)->assertJsonPath('data.status', 'paid');

        $appointment = $order->appointment()->first();

        $this->assertSame(3000, $appointment->deposit_collected_cents);
        $this->assertNotNull($appointment->deposit_collected_at);
    }

    /**
     * The GATEWAY-captured amount must come from the order actually charged
     * (`amount_cents`), never the quote-only `deposit_amount_cents` — even
     * though they happen to be equal on the happy path, this pins the source
     * column so a future refactor cannot silently swap them.
     */
    public function test_deposit_collected_cents_equals_the_orders_captured_amount(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $service = $this->makeService(200.00, 25); // deposit = 5000 cents

        $order = $this->makeAppointmentOrder($user, $service);

        $this->postJson('/api/payments/confirm', [
            'id' => 1,
            'clientTransactionId' => $order->client_transaction_id,
        ])->assertStatus(200);

        $appointment = $order->appointment()->first();

        $this->assertSame($order->amount_cents, $appointment->deposit_collected_cents);
    }

    public function test_confirm_declined_does_not_write_deposit_collected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $service = $this->makeService();

        $order = $this->makeAppointmentOrder($user, $service, [
            'client_transaction_id' => 'decline-appt-deposit-test',
        ]);

        $this->postJson('/api/payments/confirm', [
            'id' => 1,
            'clientTransactionId' => $order->client_transaction_id,
        ])->assertStatus(200)->assertJsonPath('data.status', 'failed');

        $appointment = $order->appointment()->first();

        $this->assertSame(0, $appointment->deposit_collected_cents);
        $this->assertNull($appointment->deposit_collected_at);
    }

    /**
     * Idempotency: confirming an already-paid order a second time must not
     * attempt to re-record the deposit — the write-once guard
     * (Appointment::booted()) would throw DomainException if it tried, so
     * this also proves CheckoutController's early-return short-circuits
     * before reaching the write.
     */
    public function test_confirming_an_already_paid_order_twice_does_not_throw(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $service = $this->makeService();

        $order = $this->makeAppointmentOrder($user, $service);

        $payload = ['id' => 1, 'clientTransactionId' => $order->client_transaction_id];

        $this->postJson('/api/payments/confirm', $payload)->assertStatus(200);
        $this->postJson('/api/payments/confirm', $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'paid');
    }
}
