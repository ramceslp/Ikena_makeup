<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Course;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * OrdersMigrationRegressionTest
 *
 * Verifies that the orders migration (nullable course_id + new appointment_id FK)
 * does not break existing course orders and correctly enables appointment orders.
 *
 * All scenarios use SQLite :memory: (RefreshDatabase runs all migrations fresh).
 */
class OrdersMigrationRegressionTest extends TestCase
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

    // -------------------------------------------------------------------------
    // Schema verification (migration ran correctly)
    // -------------------------------------------------------------------------

    public function test_orders_table_has_nullable_course_id_and_appointment_id_columns(): void
    {
        // After migration, both columns must exist
        $this->assertTrue(Schema::hasColumn('orders', 'course_id'));
        $this->assertTrue(Schema::hasColumn('orders', 'appointment_id'));
    }

    // -------------------------------------------------------------------------
    // (a) Existing course orders survive — course_id intact, appointment_id null
    // -------------------------------------------------------------------------

    public function test_existing_course_order_retains_course_id_after_migration(): void
    {
        $user   = $this->makeUser();
        $course = $this->makeCourse();

        // Create a course order (legacy style — explicit course_id, no appointment_id)
        $order = Order::create([
            'user_id'               => $user->id,
            'course_id'             => $course->id,
            'client_transaction_id' => 'ORD-regression-001',
            'gateway'               => 'fake',
            'amount_cents'          => 9900,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);

        $this->assertDatabaseHas('orders', [
            'id'             => $order->id,
            'course_id'      => $course->id,
            'appointment_id' => null,
            'status'         => 'pending',
        ]);
    }

    // -------------------------------------------------------------------------
    // (b) Appointment-only order: course_id null, appointment_id set
    // -------------------------------------------------------------------------

    public function test_appointment_order_has_null_course_id_and_appointment_id_set(): void
    {
        $user        = $this->makeUser();
        $service     = $this->makeService();

        // Create appointment without order (order_id comes after)
        $appointment = Appointment::create([
            'service_id'          => $service->id,
            'user_id'             => $user->id,
            'order_id'            => null,
            'scheduled_date'      => '2026-07-04',
            'scheduled_time'      => '10:00',
            'slot_key'            => "{$service->id}|2026-07-04|10:00",
            'whatsapp'            => '+593099912345',
            'payment_mode'        => 'gateway',
            'deposit_amount_cents' => 10000,
            'status'              => 'pending',
        ]);

        $order = Order::create([
            'user_id'               => $user->id,
            'course_id'             => null,
            'appointment_id'        => $appointment->id,
            'client_transaction_id' => 'ORD-appt-001',
            'gateway'               => 'fake',
            'amount_cents'          => 10000,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);

        $this->assertDatabaseHas('orders', [
            'id'             => $order->id,
            'course_id'      => null,
            'appointment_id' => $appointment->id,
        ]);

        // Relationship works
        $order->loadMissing('appointment');
        $this->assertEquals($appointment->id, $order->appointment->id);
    }

    // -------------------------------------------------------------------------
    // (c) CheckoutController regression — course orders still have course_id set
    // -------------------------------------------------------------------------

    public function test_checkout_controller_creates_order_with_course_id_and_null_appointment_id(): void
    {
        // Uses PAYMENT_DRIVER=fake set in phpunit.xml
        $student = $this->makeUser();
        $course  = $this->makeCourse();

        \Laravel\Sanctum\Sanctum::actingAs($student);

        $response = $this->postJson("/api/courses/{$course->slug}/checkout");

        $response->assertStatus(201);

        $orderId = $response->json('data.order_id');

        $this->assertDatabaseHas('orders', [
            'id'             => $orderId,
            'course_id'      => $course->id,
            'appointment_id' => null,
        ]);
    }

    // -------------------------------------------------------------------------
    // (d) Order XOR guard — DomainException on both-null
    // -------------------------------------------------------------------------

    public function test_order_xor_guard_throws_on_both_null(): void
    {
        $this->expectException(\DomainException::class);

        $user = $this->makeUser();

        Order::create([
            'user_id'               => $user->id,
            'course_id'             => null,
            'appointment_id'        => null,
            'client_transaction_id' => 'ORD-bad-001',
            'gateway'               => 'fake',
            'amount_cents'          => 1000,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);
    }

    // -------------------------------------------------------------------------
    // (e) Order XOR guard — DomainException on both-set
    // -------------------------------------------------------------------------

    public function test_order_xor_guard_throws_on_both_set(): void
    {
        $this->expectException(\DomainException::class);

        $user        = $this->makeUser();
        $course      = $this->makeCourse();
        $service     = $this->makeService();

        $appointment = Appointment::create([
            'service_id'          => $service->id,
            'user_id'             => $user->id,
            'order_id'            => null,
            'scheduled_date'      => '2026-07-05',
            'scheduled_time'      => '11:00',
            'slot_key'            => "{$service->id}|2026-07-05|11:00",
            'whatsapp'            => '+593099912345',
            'payment_mode'        => 'gateway',
            'deposit_amount_cents' => 5000,
            'status'              => 'pending',
        ]);

        Order::create([
            'user_id'               => $user->id,
            'course_id'             => $course->id,
            'appointment_id'        => $appointment->id,
            'client_transaction_id' => 'ORD-bad-002',
            'gateway'               => 'fake',
            'amount_cents'          => 5000,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);
    }
}
