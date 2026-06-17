<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Course;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * ProfileOrdersHistoryTest
 *
 * Verifies that GET /api/profile/orders returns both course orders and appointment
 * orders with the correct shape in OrderResource:
 *  - Course order: has `course` key, NO `appointment` key
 *  - Appointment order: has `appointment` key (with service.title, scheduled_date,
 *    scheduled_time, deposit_amount_cents), NO `course` key
 *
 * Also verifies the course-order shape is unchanged (regression).
 */
class ProfileOrdersHistoryTest extends TestCase
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
            'title'              => 'Maquillaje Social',
        ]);
    }

    /**
     * Create an appointment with a linked order and return both.
     */
    private function makeAppointmentOrder(User $user, Service $service): array
    {
        $depositCents = (int) round((float) $service->price * $service->depositPercentage() / 100 * 100);

        $appointment = Appointment::create([
            'service_id'          => $service->id,
            'user_id'             => $user->id,
            'order_id'            => null,
            'scheduled_date'      => '2026-07-04',
            'scheduled_time'      => '10:00',
            'slot_key'            => "{$service->id}|2026-07-04|10:00",
            'whatsapp'            => '+593099912345',
            'payment_mode'        => 'gateway',
            'deposit_amount_cents' => $depositCents,
            'status'              => 'pending',
        ]);

        $order = Order::create([
            'user_id'               => $user->id,
            'course_id'             => null,
            'appointment_id'        => $appointment->id,
            'client_transaction_id' => 'ORD-' . Str::uuid(),
            'gateway'               => 'fake',
            'amount_cents'          => $depositCents,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);

        $appointment->update(['order_id' => $order->id]);

        return [$appointment->fresh(), $order->fresh()];
    }

    // -------------------------------------------------------------------------
    // Main scenario: user has both a course order and an appointment order
    // -------------------------------------------------------------------------

    public function test_profile_orders_returns_both_course_and_appointment_rows(): void
    {
        $user    = $this->makeUser();
        $service = $this->makeService();

        // Create a course order (use instructor + course factory)
        $instructor = User::factory()->instructor()->create();
        $course     = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'title'         => 'Curso de Maquillaje',
            'price'         => 99.00,
        ]);

        $courseOrder = Order::create([
            'user_id'               => $user->id,
            'course_id'             => $course->id,
            'client_transaction_id' => 'ORD-course-001',
            'gateway'               => 'fake',
            'amount_cents'          => 9900,
            'currency'              => 'USD',
            'status'                => 'paid',
        ]);

        // Create an appointment order
        [$appointment, $appointmentOrder] = $this->makeAppointmentOrder($user, $service);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/profile/orders');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(2, $data, 'Expected 2 orders (1 course + 1 appointment)');

        // Find course order and appointment order in response (order may vary)
        $courseRow      = null;
        $appointmentRow = null;

        foreach ($data as $row) {
            if ($row['id'] === $courseOrder->id) {
                $courseRow = $row;
            }
            if ($row['id'] === $appointmentOrder->id) {
                $appointmentRow = $row;
            }
        }

        $this->assertNotNull($courseRow, 'Course order should appear in response');
        $this->assertNotNull($appointmentRow, 'Appointment order should appear in response');

        // Course order: must have `course` key, must NOT have `appointment` key
        $this->assertArrayHasKey('course', $courseRow, 'Course order must have `course` key');
        $this->assertArrayNotHasKey('appointment', $courseRow, 'Course order must NOT have `appointment` key');
        $this->assertEquals($course->title, $courseRow['course']['title']);

        // Appointment order: must have `appointment` key, must NOT have `course` key
        $this->assertArrayHasKey('appointment', $appointmentRow, 'Appointment order must have `appointment` key');
        $this->assertArrayNotHasKey('course', $appointmentRow, 'Appointment order must NOT have `course` key');

        $apptData = $appointmentRow['appointment'];
        $this->assertEquals('Maquillaje Social', $apptData['service_title']);
        $this->assertEquals('2026-07-04', $apptData['scheduled_date']);
        $this->assertEquals('10:00', $apptData['scheduled_time']);
        $this->assertEquals($appointment->deposit_amount_cents, $apptData['deposit_amount_cents']);
    }

    // -------------------------------------------------------------------------
    // Regression: course-only user sees no appointment key (unchanged behavior)
    // -------------------------------------------------------------------------

    public function test_course_only_user_sees_no_appointment_key_regression(): void
    {
        $user = $this->makeUser();

        $instructor = User::factory()->instructor()->create();
        $course     = Course::factory()->create(['instructor_id' => $instructor->id]);

        Order::create([
            'user_id'               => $user->id,
            'course_id'             => $course->id,
            'client_transaction_id' => 'ORD-regression-001',
            'gateway'               => 'fake',
            'amount_cents'          => 4900,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/profile/orders');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);

        $row = $data[0];
        $this->assertArrayHasKey('course', $row, 'Course order must have `course` key');
        $this->assertArrayNotHasKey('appointment', $row, 'Course order must NOT have `appointment` key');
        $this->assertEquals($course->title, $row['course']['title']);
    }

    // -------------------------------------------------------------------------
    // Appointment order shape: correct nested fields
    // -------------------------------------------------------------------------

    public function test_appointment_order_has_correct_shape_in_profile_history(): void
    {
        $user    = $this->makeUser();
        $service = $this->makeService();

        [$appointment, $order] = $this->makeAppointmentOrder($user, $service);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/profile/orders');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);

        $row = $data[0];
        $this->assertArrayHasKey('appointment', $row);
        $this->assertArrayNotHasKey('course', $row);

        $appt = $row['appointment'];
        $this->assertArrayHasKey('service_title', $appt);
        $this->assertArrayHasKey('scheduled_date', $appt);
        $this->assertArrayHasKey('scheduled_time', $appt);
        $this->assertArrayHasKey('deposit_amount_cents', $appt);

        $this->assertEquals('Maquillaje Social', $appt['service_title']);
        $this->assertEquals('2026-07-04', $appt['scheduled_date']);
        $this->assertEquals($appointment->deposit_amount_cents, $appt['deposit_amount_cents']);
    }
}
