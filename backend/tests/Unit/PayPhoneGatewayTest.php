<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\Gateways\PayPhoneGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit tests for PayPhoneGateway::confirm().
 *
 * Uses Http::fake to intercept outbound HTTP calls — no real PayPhone traffic.
 */
class PayPhoneGatewayTest extends TestCase
{
    use RefreshDatabase;

    private PayPhoneGateway $gateway;
    private string $confirmUrl;

    protected function setUp(): void
    {
        parent::setUp();

        // Set known config values for assertions.
        config([
            'services.payments.payphone.token'       => 'test-bearer-token',
            'services.payments.payphone.store_id'    => 'store-001',
            'services.payments.payphone.confirm_url' => 'https://paymentbox.payphonetodoesposible.com/api/confirm',
        ]);

        $this->gateway    = new PayPhoneGateway();
        $this->confirmUrl = config('services.payments.payphone.confirm_url');
    }

    public function test_confirm_returns_approved_true_when_statusCode_is_3(): void
    {
        Http::fake([
            $this->confirmUrl => Http::response(['statusCode' => 3, 'transactionStatus' => 'Approved'], 200),
        ]);

        $result = $this->gateway->confirm('42', 'ORD-abc-123');

        $this->assertTrue($result->approved);
        $this->assertSame('paid', $result->status);
        $this->assertSame('42', $result->gatewayId);
    }

    public function test_confirm_returns_approved_false_when_statusCode_is_not_3(): void
    {
        Http::fake([
            $this->confirmUrl => Http::response(['statusCode' => 2, 'transactionStatus' => 'Declined'], 200),
        ]);

        $result = $this->gateway->confirm('7', 'ORD-xyz-999');

        $this->assertFalse($result->approved);
        $this->assertSame('failed', $result->status);
    }

    public function test_confirm_sends_request_to_correct_url_with_bearer_token(): void
    {
        Http::fake([
            $this->confirmUrl => Http::response(['statusCode' => 3], 200),
        ]);

        $this->gateway->confirm('99', 'ORD-token-check');

        Http::assertSent(function ($request) {
            return $request->url() === $this->confirmUrl
                && $request->hasHeader('Authorization', 'Bearer test-bearer-token');
        });
    }

    public function test_confirm_sends_correct_body_fields(): void
    {
        Http::fake([
            $this->confirmUrl => Http::response(['statusCode' => 3], 200),
        ]);

        $this->gateway->confirm('55', 'ORD-body-check');

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['id'] === 55              // must be cast to int
                && $body['clientTxId'] === 'ORD-body-check';
        });
    }

    public function test_confirm_includes_raw_response_in_result(): void
    {
        $fakePayload = ['statusCode' => 3, 'someField' => 'someValue'];

        Http::fake([
            $this->confirmUrl => Http::response($fakePayload, 200),
        ]);

        $result = $this->gateway->confirm('11', 'ORD-raw-check');

        $this->assertEquals($fakePayload, $result->raw);
    }

    public function test_createCheckout_builds_exact_payphone_config(): void
    {
        $user     = User::factory()->create();
        $course   = Course::factory()->create(['title' => 'PHP Basics', 'price' => 29.99]);
        $order    = Order::factory()->make([
            'user_id'               => $user->id,
            'course_id'             => $course->id,
            'client_transaction_id' => 'ORD-checkout-test',
            'amount_cents'          => 2999,
        ]);
        $order->setRelation('course', $course);

        $session = $this->gateway->createCheckout($order);

        $this->assertSame('payphone', $session->provider);

        $config = $session->config;
        $this->assertSame('test-bearer-token', $config['token']);
        $this->assertSame('store-001', $config['storeId']);
        $this->assertSame('ORD-checkout-test', $config['clientTransactionId']);
        $this->assertSame(2999, $config['amount']);
        $this->assertSame(2999, $config['amountWithoutTax']);
        $this->assertSame(0, $config['amountWithTax']);
        $this->assertSame(0, $config['tax']);
        $this->assertSame(0, $config['service']);
        $this->assertSame(0, $config['tip']);
        $this->assertSame('USD', $config['currency']);
        $this->assertSame('Curso: PHP Basics', $config['reference']);
        $this->assertSame('es', $config['lang']);
    }
}
