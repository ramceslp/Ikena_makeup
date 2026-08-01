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

    /**
     * A pending order to confirm against. The gateway verifies the capture
     * against this row, so it is required input, not decoration.
     */
    private function order(string $clientTxId = 'ORD-abc-123', int $amountCents = 2999): Order
    {
        return Order::factory()->create([
            'client_transaction_id' => $clientTxId,
            'amount_cents'          => $amountCents,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);
    }

    /**
     * A well-formed PayPhone confirm response for $order.
     * Shape per https://docs.payphone.app/cajita-de-pagos.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function approvedPayload(Order $order, array $overrides = []): array
    {
        return array_merge([
            'statusCode'          => 3,
            'transactionStatus'   => 'Approved',
            'amount'              => $order->amount_cents,   // INTEGER CENTS
            'currency'            => 'USD',
            'clientTransactionId' => $order->client_transaction_id,
            'transactionId'       => 23178284,
        ], $overrides);
    }

    public function test_confirm_returns_approved_true_when_the_capture_matches_the_order(): void
    {
        $order = $this->order();

        Http::fake([$this->confirmUrl => Http::response($this->approvedPayload($order), 200)]);

        $result = $this->gateway->confirm($order, '42');

        $this->assertTrue($result->approved);
        $this->assertSame('paid', $result->status);
        $this->assertSame('42', $result->gatewayId);
    }

    public function test_confirm_returns_approved_false_when_statusCode_is_not_3(): void
    {
        $order = $this->order('ORD-xyz-999');

        Http::fake([
            $this->confirmUrl => Http::response(['statusCode' => 2, 'transactionStatus' => 'Canceled'], 200),
        ]);

        $result = $this->gateway->confirm($order, '7');

        $this->assertFalse($result->approved);
        $this->assertSame('failed', $result->status);
    }

    // -------------------------------------------------------------------------
    // Capture verification — 'approved' alone is not enough
    // -------------------------------------------------------------------------

    public function test_confirm_rejects_a_capture_for_a_different_amount(): void
    {
        $order = $this->order(amountCents: 50_000);

        Http::fake([
            $this->confirmUrl => Http::response($this->approvedPayload($order, ['amount' => 1]), 200),
        ]);

        $result = $this->gateway->confirm($order, '42');

        $this->assertFalse($result->approved);
        $this->assertSame('amount_mismatch', $result->status);
    }

    public function test_confirm_rejects_a_response_with_no_amount(): void
    {
        $order   = $this->order();
        $payload = $this->approvedPayload($order);
        unset($payload['amount']);

        Http::fake([$this->confirmUrl => Http::response($payload, 200)]);

        $result = $this->gateway->confirm($order, '42');

        $this->assertFalse($result->approved);
        $this->assertSame('amount_unverifiable', $result->status);
    }

    public function test_confirm_rejects_a_capture_in_another_currency(): void
    {
        $order = $this->order();

        Http::fake([
            $this->confirmUrl => Http::response($this->approvedPayload($order, ['currency' => 'COP']), 200),
        ]);

        $result = $this->gateway->confirm($order, '42');

        $this->assertFalse($result->approved);
        $this->assertSame('currency_mismatch', $result->status);
    }

    public function test_confirm_rejects_a_capture_belonging_to_another_order(): void
    {
        $order = $this->order();

        Http::fake([
            $this->confirmUrl => Http::response(
                $this->approvedPayload($order, ['clientTransactionId' => 'ORD-someone-elses']),
                200,
            ),
        ]);

        $result = $this->gateway->confirm($order, '42');

        $this->assertFalse($result->approved);
        $this->assertSame('transaction_mismatch', $result->status);
    }

    // -------------------------------------------------------------------------
    // Request shape
    // -------------------------------------------------------------------------

    public function test_confirm_sends_request_to_correct_url_with_bearer_token(): void
    {
        $order = $this->order('ORD-token-check');

        Http::fake([$this->confirmUrl => Http::response($this->approvedPayload($order), 200)]);

        $this->gateway->confirm($order, '99');

        Http::assertSent(function ($request) {
            return $request->url() === $this->confirmUrl
                && $request->hasHeader('Authorization', 'Bearer test-bearer-token');
        });
    }

    public function test_confirm_sends_the_orders_own_transaction_id(): void
    {
        $order = $this->order('ORD-body-check');

        Http::fake([$this->confirmUrl => Http::response($this->approvedPayload($order), 200)]);

        $this->gateway->confirm($order, '55');

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['id'] === 55              // must be cast to int
                && $body['clientTxId'] === 'ORD-body-check';
        });
    }

    public function test_confirm_includes_raw_response_in_result(): void
    {
        $order       = $this->order('ORD-raw-check');
        $fakePayload = $this->approvedPayload($order, ['someField' => 'someValue']);

        Http::fake([$this->confirmUrl => Http::response($fakePayload, 200)]);

        $result = $this->gateway->confirm($order, '11');

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
