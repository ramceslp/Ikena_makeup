<?php

namespace Tests\Feature\Gateway;

use App\Models\Order;
use App\Models\User;
use App\Services\Payments\Gateways\PayPhoneGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PayPhoneProductCartTest
 *
 * Verifies that PayPhoneGateway::createCheckout() works correctly for
 * product_cart orders (no null-deref on course relation) and that course
 * behavior is unchanged (regression).
 *
 * Phase 8.6 (RED) → GREEN after PayPhoneGateway fix.
 */
class PayPhoneProductCartTest extends TestCase
{
    use RefreshDatabase;

    private PayPhoneGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.payments.payphone.token'       => 'test-bearer-token',
            'services.payments.payphone.store_id'    => 'store-001',
            'services.payments.payphone.confirm_url' => 'https://paymentbox.payphonetodoesposible.com/api/confirm',
        ]);

        $this->gateway = new PayPhoneGateway();
    }

    // -------------------------------------------------------------------------
    // product_cart — no null-deref, reference present
    // -------------------------------------------------------------------------

    public function test_create_checkout_for_product_cart_returns_session_without_null_deref(): void
    {
        $user = User::factory()->create();

        // product_cart order — no course or appointment relation
        $order = Order::factory()->make([
            'user_id'               => $user->id,
            'course_id'             => null,
            'type'                  => 'product_cart',
            'client_transaction_id' => 'ORD-cart-gateway-test',
            'amount_cents'          => 24610,
            'currency'              => 'USD',
        ]);
        // Intentionally do NOT set course or appointment relation

        $session = $this->gateway->createCheckout($order);

        $this->assertSame('payphone', $session->provider);

        $config = $session->config;
        $this->assertNotNull($config['reference'], 'Reference should not be null for product_cart');
        $this->assertStringContainsString('Pedido', $config['reference'], 'Reference should contain "Pedido" for product_cart');
        $this->assertSame(24610, $config['amount']);
    }

    // -------------------------------------------------------------------------
    // course — unchanged behavior (regression)
    // -------------------------------------------------------------------------

    public function test_create_checkout_for_course_still_uses_course_title(): void
    {
        $user   = User::factory()->create();
        $course = \App\Models\Course::factory()->create(['title' => 'PHP Advanced', 'price' => 99.99]);

        $order = Order::factory()->make([
            'user_id'               => $user->id,
            'course_id'             => $course->id,
            'type'                  => 'course',
            'client_transaction_id' => 'ORD-course-gateway-reg',
            'amount_cents'          => 9999,
        ]);
        $order->setRelation('course', $course);

        $session = $this->gateway->createCheckout($order);

        $this->assertSame('Curso: PHP Advanced', $session->config['reference']);
    }

    // -------------------------------------------------------------------------
    // appointment — no null-deref (appointment reference)
    // -------------------------------------------------------------------------

    public function test_create_checkout_for_appointment_returns_session_without_null_deref(): void
    {
        $user    = User::factory()->create();
        $service = \App\Models\Service::factory()->create(['title' => 'Maquillaje Social']);

        $appointment = \App\Models\Appointment::create([
            'service_id'          => $service->id,
            'user_id'             => $user->id,
            'order_id'            => null,
            'scheduled_date'      => '2026-07-20',
            'scheduled_time'      => '14:00',
            'slot_key'            => "{$service->id}|2026-07-20|14:00",
            'whatsapp'            => '+593099912345',
            'payment_mode'        => 'gateway',
            'deposit_amount_cents'=> 3000,
            'status'              => 'pending',
        ]);

        $order = Order::factory()->make([
            'user_id'               => $user->id,
            'course_id'             => null,
            'appointment_id'        => $appointment->id,
            'type'                  => 'appointment',
            'client_transaction_id' => 'ORD-appt-gateway-test',
            'amount_cents'          => 3000,
        ]);
        $order->setRelation('appointment', $appointment);
        $appointment->setRelation('service', $service);

        $session = $this->gateway->createCheckout($order);

        $this->assertSame('payphone', $session->provider);
        $this->assertNotNull($session->config['reference']);
    }
}
