<?php

namespace Tests\Unit\Models;

use App\Models\Appointment;
use App\Models\Course;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * OrderEvolutionTest
 *
 * Verifies the type-discriminated Order invariant, creating hook, items() relationship,
 * scopeExpiredProductCarts(), and money field casts.
 *
 * Phase 6.1 (RED) — tests must fail until the migration + model changes are applied.
 */
class OrderEvolutionTest extends TestCase
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

        return Course::factory()->create(['instructor_id' => $instructor->id, 'price' => 100.00]);
    }

    private function makeService(): Service
    {
        return Service::factory()->create([
            'availability_type' => 'by_appointment',
            'is_published'      => true,
            'price'             => 100.00,
            'deposit_percentage'=> 30,
        ]);
    }

    private function makeAppointment(Service $service, User $user): Appointment
    {
        return Appointment::create([
            'service_id'          => $service->id,
            'user_id'             => $user->id,
            'order_id'            => null,
            'scheduled_date'      => '2026-07-10',
            'scheduled_time'      => '10:00',
            'slot_key'            => "{$service->id}|2026-07-10|10:00",
            'whatsapp'            => '+593099912345',
            'payment_mode'        => 'gateway',
            'deposit_amount_cents'=> 3000,
            'status'              => 'pending',
        ]);
    }

    private function makeProduct(): Product
    {
        return Product::factory()->create([
            'price'     => 50.00,
            'stock_qty' => 10,
        ]);
    }

    // -------------------------------------------------------------------------
    // Type-discriminated invariant — valid shapes
    // -------------------------------------------------------------------------

    public function test_course_order_valid_shape(): void
    {
        $user   = $this->makeUser();
        $course = $this->makeCourse();

        $order = Order::create([
            'user_id'               => $user->id,
            'course_id'             => $course->id,
            'type'                  => 'course',
            'client_transaction_id' => 'ORD-course-001',
            'gateway'               => 'fake',
            'amount_cents'          => 10000,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'type' => 'course']);
    }

    public function test_appointment_order_valid_shape(): void
    {
        $user        = $this->makeUser();
        $service     = $this->makeService();
        $appointment = $this->makeAppointment($service, $user);

        $order = Order::create([
            'user_id'               => $user->id,
            'appointment_id'        => $appointment->id,
            'type'                  => 'appointment',
            'client_transaction_id' => 'ORD-appt-001',
            'gateway'               => 'fake',
            'amount_cents'          => 3000,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'type' => 'appointment']);
    }

    public function test_product_cart_order_valid_shape(): void
    {
        $user = $this->makeUser();

        $order = Order::create([
            'user_id'               => $user->id,
            'type'                  => 'product_cart',
            'client_transaction_id' => 'ORD-cart-001',
            'gateway'               => 'fake',
            'amount_cents'          => 5000,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'type' => 'product_cart']);
    }

    // -------------------------------------------------------------------------
    // Type-discriminated invariant — invalid combos → DomainException
    // -------------------------------------------------------------------------

    public function test_domain_exception_on_product_cart_with_course_id(): void
    {
        $this->expectException(DomainException::class);

        $user   = $this->makeUser();
        $course = $this->makeCourse();

        Order::create([
            'user_id'               => $user->id,
            'course_id'             => $course->id,
            'type'                  => 'product_cart',
            'client_transaction_id' => 'ORD-bad-001',
            'gateway'               => 'fake',
            'amount_cents'          => 5000,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);
    }

    public function test_domain_exception_on_course_type_with_appointment_id(): void
    {
        $this->expectException(DomainException::class);

        $user        = $this->makeUser();
        $service     = $this->makeService();
        $appointment = $this->makeAppointment($service, $user);

        Order::create([
            'user_id'               => $user->id,
            'appointment_id'        => $appointment->id,
            'type'                  => 'course',
            'client_transaction_id' => 'ORD-bad-002',
            'gateway'               => 'fake',
            'amount_cents'          => 3000,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);
    }

    public function test_domain_exception_on_unknown_type(): void
    {
        $this->expectException(DomainException::class);

        $user = $this->makeUser();

        $order = new Order([
            'user_id'               => $user->id,
            'type'                  => 'unknown_type',
            'client_transaction_id' => 'ORD-bad-003',
            'gateway'               => 'fake',
            'amount_cents'          => 1000,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);
        $order->save();
    }

    // -------------------------------------------------------------------------
    // creating hook — infers type when omitted
    // -------------------------------------------------------------------------

    public function test_creating_hook_infers_course_type_when_omitted(): void
    {
        $user   = $this->makeUser();
        $course = $this->makeCourse();

        $order = Order::create([
            'user_id'               => $user->id,
            'course_id'             => $course->id,
            // type intentionally omitted
            'client_transaction_id' => 'ORD-infer-course',
            'gateway'               => 'fake',
            'amount_cents'          => 10000,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);

        $this->assertSame('course', $order->fresh()->type);
    }

    public function test_creating_hook_infers_appointment_type_when_omitted(): void
    {
        $user        = $this->makeUser();
        $service     = $this->makeService();
        $appointment = $this->makeAppointment($service, $user);

        $order = Order::create([
            'user_id'               => $user->id,
            'course_id'             => null,
            'appointment_id'        => $appointment->id,
            // type intentionally omitted
            'client_transaction_id' => 'ORD-infer-appt',
            'gateway'               => 'fake',
            'amount_cents'          => 3000,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);

        $this->assertSame('appointment', $order->fresh()->type);
    }

    // -------------------------------------------------------------------------
    // items() hasMany relationship
    // -------------------------------------------------------------------------

    public function test_order_has_many_items(): void
    {
        $user    = $this->makeUser();
        $product = $this->makeProduct();

        $order = Order::create([
            'user_id'               => $user->id,
            'type'                  => 'product_cart',
            'client_transaction_id' => 'ORD-items-001',
            'gateway'               => 'fake',
            'amount_cents'          => 5000,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);

        OrderItem::create([
            'order_id'         => $order->id,
            'product_id'       => $product->id,
            'product_title'    => $product->title,
            'quantity'         => 2,
            'unit_price_cents' => 2500,
            'line_total_cents' => 5000,
        ]);

        $this->assertCount(1, $order->items);
        $this->assertEquals(2, $order->items->first()->quantity);
    }

    // -------------------------------------------------------------------------
    // scopeExpiredProductCarts
    // -------------------------------------------------------------------------

    public function test_scope_expired_product_carts_returns_only_expired_pending_carts(): void
    {
        $user = $this->makeUser();

        // Expired pending product_cart — should be in scope
        $expired = Order::create([
            'user_id'               => $user->id,
            'type'                  => 'product_cart',
            'client_transaction_id' => 'ORD-expired',
            'gateway'               => 'fake',
            'amount_cents'          => 5000,
            'currency'              => 'USD',
            'status'                => 'pending',
            'reserved_until'        => now()->subMinutes(5),
        ]);

        // Not-yet-expired pending product_cart — should NOT be in scope
        $pending = Order::create([
            'user_id'               => $user->id,
            'type'                  => 'product_cart',
            'client_transaction_id' => 'ORD-not-expired',
            'gateway'               => 'fake',
            'amount_cents'          => 5000,
            'currency'              => 'USD',
            'status'                => 'pending',
            'reserved_until'        => now()->addMinutes(10),
        ]);

        // Course order — should NOT be in scope
        $course  = $this->makeCourse();
        $courseOrder = Order::create([
            'user_id'               => $user->id,
            'course_id'             => $course->id,
            'client_transaction_id' => 'ORD-course-scope',
            'gateway'               => 'fake',
            'amount_cents'          => 10000,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);

        $scoped = Order::expiredProductCarts()->pluck('id')->toArray();

        $this->assertContains($expired->id, $scoped);
        $this->assertNotContains($pending->id, $scoped);
        $this->assertNotContains($courseOrder->id, $scoped);
    }

    // -------------------------------------------------------------------------
    // Money field casts (integer)
    // -------------------------------------------------------------------------

    public function test_money_fields_are_cast_to_integer(): void
    {
        $user = $this->makeUser();

        $order = Order::create([
            'user_id'               => $user->id,
            'type'                  => 'product_cart',
            'client_transaction_id' => 'ORD-money-001',
            'gateway'               => 'fake',
            'amount_cents'          => 24610,
            'subtotal_cents'        => 21400,
            'tax_cents'             => 3210,
            'total_cents'           => 24610,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);

        $fresh = $order->fresh();
        $this->assertIsInt($fresh->subtotal_cents);
        $this->assertIsInt($fresh->tax_cents);
        $this->assertIsInt($fresh->total_cents);
        $this->assertSame(21400, $fresh->subtotal_cents);
        $this->assertSame(3210, $fresh->tax_cents);
        $this->assertSame(24610, $fresh->total_cents);
    }
}
