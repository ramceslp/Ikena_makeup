<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * AppointmentAdminTest
 *
 * Tests for the admin appointment management endpoints:
 *  - GET  /api/admin/appointments         (list + filter)
 *  - PATCH /api/admin/appointments/{id}/mark-paid
 *  - PATCH /api/admin/appointments/{id}/cancel
 *
 * All use SQLite :memory: and PAYMENT_DRIVER=fake.
 */
class AppointmentAdminTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

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
     * Create a pending appointment with an associated pending order.
     */
    private function makeAppointmentWithOrder(
        Service $service,
        User $user,
        string $date = '2026-07-04',
        string $time = '10:00',
        string $status = 'pending'
    ): array {
        $depositCents = (int) round((float) $service->price * $service->depositPercentage() / 100 * 100);
        $slotKey = "{$service->id}|{$date}|{$time}";

        $appointment = Appointment::create([
            'service_id'          => $service->id,
            'user_id'             => $user->id,
            'order_id'            => null,
            'scheduled_date'      => $date,
            'scheduled_time'      => $time,
            'slot_key'            => ($status === 'cancelled') ? null : $slotKey,
            'whatsapp'            => '+593099912345',
            'payment_mode'        => 'gateway',
            'deposit_amount_cents' => $depositCents,
            'status'              => $status,
        ]);

        $order = Order::create([
            'user_id'               => $user->id,
            'course_id'             => null,
            'appointment_id'        => $appointment->id,
            'client_transaction_id' => 'ORD-' . uniqid(),
            'gateway'               => 'fake',
            'amount_cents'          => $depositCents,
            'currency'              => 'USD',
            'status'                => ($status === 'paid') ? 'paid' : 'pending',
        ]);

        $appointment->update(['order_id' => $order->id]);

        return [$appointment->fresh(), $order->fresh()];
    }

    // -------------------------------------------------------------------------
    // GET /api/admin/appointments — list
    // -------------------------------------------------------------------------

    public function test_admin_can_list_all_appointments(): void
    {
        $admin   = $this->makeAdmin();
        $service = $this->makeService();
        $user    = $this->makeUser();

        [$a1] = $this->makeAppointmentWithOrder($service, $user, '2026-07-01', '09:00');
        [$a2] = $this->makeAppointmentWithOrder($service, $user, '2026-07-02', '10:00');
        [$a3] = $this->makeAppointmentWithOrder($service, $user, '2026-07-03', '11:00');

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/appointments');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data'  => [['id', 'service', 'user', 'scheduled_date', 'scheduled_time', 'status', 'deposit_amount_cents']],
                     'links' => [],
                     'meta'  => [],
                 ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_admin_can_filter_appointments_by_status(): void
    {
        $admin   = $this->makeAdmin();
        $service = $this->makeService();
        $user    = $this->makeUser();

        $this->makeAppointmentWithOrder($service, $user, '2026-07-01', '09:00', 'pending');
        $this->makeAppointmentWithOrder($service, $user, '2026-07-02', '10:00', 'pending');
        $this->makeAppointmentWithOrder($service, $user, '2026-07-03', '11:00', 'pending');
        $this->makeAppointmentWithOrder($service, $user, '2026-07-04', '12:00', 'paid');
        $this->makeAppointmentWithOrder($service, $user, '2026-07-05', '13:00', 'paid');

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/appointments?status=pending');
        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));

        $paidResponse = $this->getJson('/api/admin/appointments?status=paid');
        $paidResponse->assertStatus(200);
        $this->assertCount(2, $paidResponse->json('data'));
    }

    public function test_non_admin_cannot_list_appointments(): void
    {
        $student = $this->makeUser();
        Sanctum::actingAs($student);

        $this->getJson('/api/admin/appointments')
             ->assertStatus(403);
    }

    public function test_unauthenticated_cannot_list_appointments(): void
    {
        $this->getJson('/api/admin/appointments')
             ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // PATCH /api/admin/appointments/{id}/mark-paid
    // -------------------------------------------------------------------------

    public function test_admin_can_mark_pending_appointment_as_paid(): void
    {
        $admin   = $this->makeAdmin();
        $service = $this->makeService();
        $user    = $this->makeUser();

        [$appointment, $order] = $this->makeAppointmentWithOrder($service, $user);

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/appointments/{$appointment->id}/mark-paid");

        $response->assertStatus(200)
                 ->assertJsonPath('data.status', 'paid')
                 ->assertJsonPath('data.payment_mode', 'manual');

        $this->assertDatabaseHas('appointments', [
            'id'           => $appointment->id,
            'status'       => 'paid',
            'payment_mode' => 'manual',
        ]);

        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => 'paid',
        ]);

        // paid_at must be set on order
        $updatedOrder = $order->fresh();
        $this->assertNotNull($updatedOrder->paid_at);
    }

    public function test_mark_paid_returns_422_when_appointment_already_paid(): void
    {
        $admin   = $this->makeAdmin();
        $service = $this->makeService();
        $user    = $this->makeUser();

        [$appointment] = $this->makeAppointmentWithOrder($service, $user, '2026-07-04', '10:00', 'paid');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/appointments/{$appointment->id}/mark-paid")
             ->assertStatus(422);
    }

    public function test_mark_paid_returns_403_for_non_admin(): void
    {
        $student = $this->makeUser();
        $service = $this->makeService();
        $owner   = $this->makeUser();

        [$appointment] = $this->makeAppointmentWithOrder($service, $owner);

        Sanctum::actingAs($student);

        $this->patchJson("/api/admin/appointments/{$appointment->id}/mark-paid")
             ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // PATCH /api/admin/appointments/{id}/cancel
    // -------------------------------------------------------------------------

    public function test_admin_can_cancel_pending_appointment(): void
    {
        $admin   = $this->makeAdmin();
        $service = $this->makeService();
        $user    = $this->makeUser();

        [$appointment] = $this->makeAppointmentWithOrder($service, $user);

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/appointments/{$appointment->id}/cancel");

        $response->assertStatus(200)
                 ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('appointments', [
            'id'               => $appointment->id,
            'status'           => 'cancelled',
            'slot_key'         => null,
            'cancelled_by_id'  => $admin->id,
        ]);

        // cancelled_at must be set
        $updated = $appointment->fresh();
        $this->assertNotNull($updated->cancelled_at);
    }

    public function test_cancel_frees_slot_so_same_slot_can_be_rebooked(): void
    {
        $admin   = $this->makeAdmin();
        $service = $this->makeService();
        $user1   = $this->makeUser();
        $user2   = $this->makeUser();

        // Create a recurring slot for today's day
        ServiceSlot::factory()->create([
            'service_id'  => $service->id,
            'day_of_week' => Carbon::today()->dayOfWeek,
            'start_time'  => '10:00',
            'is_blocked'  => false,
        ]);

        // User1 books via API
        Sanctum::actingAs($user1);
        $slotsRes = $this->getJson("/api/services/{$service->id}/available-slots");
        $slot     = $slotsRes->json('data.0');

        $firstBooking = $this->postJson('/api/bookings', [
            'service_id'     => $service->id,
            'scheduled_date' => $slot['date_label'],
            'scheduled_time' => $slot['start_time'],
            'whatsapp'       => '+593099900001',
        ]);
        $firstBooking->assertStatus(201);

        $appointment = Appointment::where('user_id', $user1->id)->first();

        // Admin cancels user1's appointment
        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/appointments/{$appointment->id}/cancel")
             ->assertStatus(200);

        // Slot must be freed — slot_key is now null
        $this->assertNull($appointment->fresh()->slot_key);

        // User2 can now book the same slot
        Sanctum::actingAs($user2);
        $secondBooking = $this->postJson('/api/bookings', [
            'service_id'     => $service->id,
            'scheduled_date' => $slot['date_label'],
            'scheduled_time' => $slot['start_time'],
            'whatsapp'       => '+593099900002',
        ]);
        $secondBooking->assertStatus(201);
    }

    public function test_cancel_returns_422_when_appointment_already_cancelled(): void
    {
        $admin   = $this->makeAdmin();
        $service = $this->makeService();
        $user    = $this->makeUser();

        [$appointment] = $this->makeAppointmentWithOrder($service, $user, '2026-07-04', '10:00', 'cancelled');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/appointments/{$appointment->id}/cancel")
             ->assertStatus(422);
    }

    public function test_cancel_returns_403_for_non_admin(): void
    {
        $student = $this->makeUser();
        $service = $this->makeService();
        $owner   = $this->makeUser();

        [$appointment] = $this->makeAppointmentWithOrder($service, $owner);

        Sanctum::actingAs($student);

        $this->patchJson("/api/admin/appointments/{$appointment->id}/cancel")
             ->assertStatus(403);
    }
}
