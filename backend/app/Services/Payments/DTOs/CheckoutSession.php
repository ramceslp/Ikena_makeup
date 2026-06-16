<?php

namespace App\Services\Payments\DTOs;

/**
 * Returned by PaymentGatewayInterface::createCheckout.
 * `config` is the exact payload the frontend passes to the provider widget.
 */
readonly class CheckoutSession
{
    public function __construct(
        public string $provider,
        public array  $config,
    ) {}
}
