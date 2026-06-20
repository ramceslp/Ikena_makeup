<?php

namespace Tests\Feature\Regression;

use App\Models\Appointment;
use App\Models\Course;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * OrderCourseRegressionTest
 *
 * Ensures that the Order model evolution (type discriminator + creating hook)
 * does NOT break existing course and appointment order creation paths.
 *
 * All call sites that existed BEFORE PR2b must continue to work without
 * modification. This is the linchpin regression guard.
 *
 * Phase 6.2 (RED) — must pass after migration + model changes in Phase 6.
 */
class OrderCourseRegressionTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeCourse(): Course
    {
        $instructor = User::factory()->instructor()->create();

        return Course::factory()->create([
            'instructor_id' => $instructor->id,
            'price'         => 99.00,
            'is_published'  => true,
        ]);
    }

    private function makeService(): Service
    {
        return Service::factory()->create([
            'availability_type'  => 'by_appointment',
            'is_published'       => true,
            'price'              => 200.00,
            'deposit_percentage' => 50,
        ]);
    }

    private function makeAppointment(Service $service, User $user): Appointment
    {
        return Appointment::create([
            'service_id'          => $service->id,
            'user_id'             => $user->id,
            'order_id'            => null,
            'scheduled_date'      => '2026-07-15',
            'scheduled_time'      => '11:00',
            'slot_key'            => "{$service->id}|2026-07-15|11:00",
            'whatsapp'            => '+593099912345',
            'payment_mode'        => 'gateway',
            'deposit_amount_cents'=> 10000,
            'status'              => 'pending',
        ]);
    }

    // -------------------------------------------------------------------------
    // Regression: CheckoutController pattern — course order (no type provided)
    // -------------------------------------------------------------------------

    public function test_checkout_controller_style_course_order_still_works(): void
    {
        $user   = $this->makeUser();
        $course = $this->makeCourse();

        // Mirrors exactly what CheckoutController::checkout() does.
        $order = Order::create([
            'user_id'               => $user->id,
            'course_id'             => $course->id,
            'client_transaction_id' => 'ORD-regression-cc-001',
            'gateway'               => 'fake',
            'amount_cents'          => (int) round((float) $course->price * 100),
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);

        $this->assertDatabaseHas('orders', [
            'id'             => $order->id,
            'course_id'      => $course->id,
            'appointment_id' => null,
            'type'           => 'course',
        ]);
    }

    // -------------------------------------------------------------------------
    // Regression: BookingController pattern — appointment order (no type provided)
    // -------------------------------------------------------------------------

    public function test_booking_controller_style_appointment_order_still_works(): void
    {
        $user        = $this->makeUser();
        $service     = $this->makeService();
        $appointment = $this->makeAppointment($service, $user);

        // Mirrors exactly what BookingController::store() does.
        $order = Order::create([
            'user_id'               => $user->id,
            'course_id'             => null,
            'appointment_id'        => $appointment->id,
            'client_transaction_id' => 'ORD-regression-bc-001',
            'gateway'               => 'fake',
            'amount_cents'          => 10000,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);

        $this->assertDatabaseHas('orders', [
            'id'             => $order->id,
            'course_id'      => null,
            'appointment_id' => $appointment->id,
            'type'           => 'appointment',
        ]);
    }

    // -------------------------------------------------------------------------
    // Regression: OrderFactory style — course_id via factory
    // -------------------------------------------------------------------------

    public function test_order_factory_default_still_creates_course_order(): void
    {
        $order = Order::factory()->create();

        // Factory sets course_id → creating hook infers type='course'
        $this->assertSame('course', $order->fresh()->type);
        $this->assertNotNull($order->course_id);
        $this->assertNull($order->appointment_id);
    }

    public function test_order_factory_paid_state_still_works(): void
    {
        $order = Order::factory()->paid()->create();

        $this->assertSame('paid', $order->status);
        $this->assertSame('course', $order->fresh()->type);
    }

    // -------------------------------------------------------------------------
    // Regression: AppointmentAdminTest style — explicit Order::create with appointment_id
    // -------------------------------------------------------------------------

    public function test_appointment_admin_test_style_order_create_works(): void
    {
        $user        = $this->makeUser();
        $service     = $this->makeService();
        $appointment = $this->makeAppointment($service, $user);

        // Mirrors AppointmentAdminTest::makeAppointmentWithOrder()
        $order = Order::create([
            'user_id'               => $user->id,
            'course_id'             => null,
            'appointment_id'        => $appointment->id,
            'client_transaction_id' => 'ORD-' . uniqid(),
            'gateway'               => 'fake',
            'amount_cents'          => 10000,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);

        $this->assertSame('appointment', $order->fresh()->type);
    }

    // -------------------------------------------------------------------------
    // Regression: PaymentCheckoutTest style — course order, explicit course_id
    // -------------------------------------------------------------------------

    public function test_payment_checkout_test_style_course_order_works(): void
    {
        $user   = $this->makeUser();
        $course = $this->makeCourse();

        // Mirrors PaymentCheckoutTest — explicit course_id, no type
        $order = Order::create([
            'user_id'               => $user->id,
            'course_id'             => $course->id,
            'client_transaction_id' => 'decline-test-id-regression',
            'gateway'               => 'fake',
            'amount_cents'          => 4999,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);

        $this->assertSame('course', $order->fresh()->type);
        $this->assertSame($course->id, $order->course_id);
    }

    // -------------------------------------------------------------------------
    // Regression: DomainException still fires on both-null (old XOR guard shape)
    // -------------------------------------------------------------------------

    public function test_both_null_fks_with_no_type_throws_domain_exception(): void
    {
        // When both FKs are null and type is not provided, the creating hook
        // leaves type at its column default 'course', then the saving invariant
        // fires: course requires course_id NOT NULL → throws.
        $this->expectException(DomainException::class);

        $user = $this->makeUser();

        Order::create([
            'user_id'               => $user->id,
            'course_id'             => null,
            'appointment_id'        => null,
            'client_transaction_id' => 'ORD-regression-bothnull',
            'gateway'               => 'fake',
            'amount_cents'          => 1000,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);
    }

    // -------------------------------------------------------------------------
    // Regression: Course checkout endpoint still works end-to-end
    // -------------------------------------------------------------------------

    public function test_course_checkout_endpoint_creates_correct_order(): void
    {
        $student = $this->makeUser();
        $course  = $this->makeCourse();

        Sanctum::actingAs($student);

        $response = $this->postJson("/api/courses/{$course->slug}/checkout");

        $response->assertStatus(201);

        $orderId = $response->json('data.order_id');

        $this->assertDatabaseHas('orders', [
            'id'             => $orderId,
            'course_id'      => $course->id,
            'appointment_id' => null,
            'type'           => 'course',
            'status'         => 'pending',
        ]);
    }
}
