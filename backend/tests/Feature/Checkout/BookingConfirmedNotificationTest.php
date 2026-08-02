<?php

namespace Tests\Feature\Checkout;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Notifications\BookingConfirmed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * BookingConfirmedNotificationTest — mobile-capacitor-setup PR3, tasks 3.7-3.8.
 *
 * Verifies that CheckoutController::confirm()'s appointment -> paid success
 * branch dispatches App\Notifications\BookingConfirmed (design Decision 2's
 * "Booking confirmed (primary)" v1 trigger:
 * sdd/mobile-capacitor-setup/design.md).
 *
 * Notification::fake() is used throughout — no real FCM send happens here.
 */
class BookingConfirmedNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(): Service
    {
        return Service::factory()->create([
            'availability_type' => 'by_appointment',
            'is_published' => true,
            'price' => 100.00,
            'deposit_percentage' => 30,
            'duration_hours' => 1,
        ]);
    }

    private function makeAppointmentOrder(User $user, Service $service, array $overrides = []): Order
    {
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
            'deposit_amount_cents' => 3000,
            'service_price_cents' => (int) round((float) $service->price * 100),
            'status' => 'pending',
        ]);

        $order = Order::create(array_merge([
            'user_id' => $user->id,
            'appointment_id' => $appointment->id,
            'client_transaction_id' => 'ORD-appt-'.$appointment->id,
            'gateway' => 'fake',
            'amount_cents' => 3000,
            'currency' => 'USD',
            'status' => 'pending',
        ], $overrides));

        $appointment->update(['order_id' => $order->id]);

        return $order->fresh();
    }

    public function test_confirm_approved_sends_booking_confirmed_notification_to_the_user(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $service = $this->makeService();

        $order = $this->makeAppointmentOrder($user, $service);

        $this->postJson('/api/payments/confirm', [
            'id' => 1,
            'clientTransactionId' => $order->client_transaction_id,
        ])->assertStatus(200)->assertJsonPath('data.status', 'paid');

        Notification::assertSentTo(
            $user,
            BookingConfirmed::class,
            function (BookingConfirmed $notification) use ($order) {
                return $notification->appointment->id === $order->appointment_id;
            }
        );
    }

    public function test_confirm_declined_does_not_send_booking_confirmed_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $service = $this->makeService();

        $order = $this->makeAppointmentOrder($user, $service, [
            'client_transaction_id' => 'decline-appt-test',
        ]);

        $this->postJson('/api/payments/confirm', [
            'id' => 1,
            'clientTransactionId' => $order->client_transaction_id,
        ])->assertStatus(200)->assertJsonPath('data.status', 'failed');

        Notification::assertNotSentTo($user, BookingConfirmed::class);
    }

    public function test_confirm_already_paid_does_not_resend_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $service = $this->makeService();

        $order = $this->makeAppointmentOrder($user, $service);

        // First confirm — dispatches the notification.
        $this->postJson('/api/payments/confirm', [
            'id' => 1,
            'clientTransactionId' => $order->client_transaction_id,
        ])->assertStatus(200);

        Notification::assertSentToTimes($user, BookingConfirmed::class, 1);

        // Second (idempotent) confirm — must NOT dispatch again.
        $this->postJson('/api/payments/confirm', [
            'id' => 1,
            'clientTransactionId' => $order->client_transaction_id,
        ])->assertStatus(200);

        Notification::assertSentToTimes($user, BookingConfirmed::class, 1);
    }
}
