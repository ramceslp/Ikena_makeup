<?php

namespace App\Services\Payments\Gateways;

use App\Models\Order;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\DTOs\CheckoutSession;
use App\Services\Payments\DTOs\PaymentResult;

/**
 * Deterministic fake gateway for tests and local sandbox.
 *
 * Approval rule (no real network call):
 *   APPROVED  — clientTransactionId does NOT contain the substring "decline"
 *   DECLINED  — clientTransactionId CONTAINS the substring "decline"
 *
 * This means any normal UUID-style clientTransactionId will approve, and a
 * test can force a decline by including "decline" anywhere in the string,
 * e.g. "decline-test-123".
 */
class FakeGateway implements PaymentGatewayInterface
{
    public function createCheckout(Order $order): CheckoutSession
    {
        return new CheckoutSession(
            provider: $this->name(),
            config: [
                'clientTransactionId' => $order->client_transaction_id,
                'amount'              => $order->amount_cents,
                'currency'            => 'USD',
            ],
        );
    }

    public function confirm(Order $order, string $gatewayId): PaymentResult
    {
        $approved = ! str_contains($order->client_transaction_id, 'decline');

        return new PaymentResult(
            approved: $approved,
            gatewayId: $gatewayId,
            status: $approved ? 'paid' : 'failed',
            raw: [
                'statusCode' => $approved ? 3 : 2,
                // Mirror the real gateway's verifiable fields so the fake
                // cannot pass a scenario the real one would reject.
                'amount'              => $order->amount_cents,
                'currency'            => $order->currency,
                'clientTransactionId' => $order->client_transaction_id,
                'fake'                => true,
            ],
        );
    }

    public function name(): string
    {
        return 'fake';
    }
}
