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
     * Takes the Order rather than a bare client_transaction_id because a
     * gateway must answer "was THIS order paid?", not the weaker "did some
     * transaction succeed?". The provider widget is client-side, so the amount
     * it charges passed through the browser; the Order row is the only
     * authoritative record of what should have been captured, and an
     * implementation is REQUIRED to check the captured amount and currency
     * against it. Anything less makes every priced item free.
     *
     * Implementations must fail closed: if the provider's response does not
     * carry the fields needed to verify the capture, the result is not approved.
     *
     * @param  Order   $order      The order being paid — the source of truth for amount/currency.
     * @param  string  $gatewayId  The `id` returned by the provider in the redirect URL.
     */
    public function confirm(Order $order, string $gatewayId): PaymentResult;

    /**
     * Driver name: 'payphone' | 'fake'.
     */
    public function name(): string;
}
