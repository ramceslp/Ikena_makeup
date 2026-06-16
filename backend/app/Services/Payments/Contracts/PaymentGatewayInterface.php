<?php

namespace App\Services\Payments\Contracts;

use App\Models\Order;
use App\Services\Payments\DTOs\CheckoutSession;
use App\Services\Payments\DTOs\PaymentResult;

interface PaymentGatewayInterface
{
    /**
     * Build the checkout payload the frontend passes to the provider widget.
     */
    public function createCheckout(Order $order): CheckoutSession;

    /**
     * Verify a transaction with the payment provider and return a normalised result.
     *
     * @param  string  $gatewayId            The `id` returned by PayPhone in the redirect URL.
     * @param  string  $clientTransactionId  Our own client_transaction_id.
     */
    public function confirm(string $gatewayId, string $clientTransactionId): PaymentResult;

    /**
     * Driver name: 'payphone' | 'fake'.
     */
    public function name(): string;
}
