<?php

namespace App\Services\Payments\DTOs;

/**
 * Normalized result returned by PaymentGatewayInterface::confirm.
 */
readonly class PaymentResult
{
    public function __construct(
        public bool   $approved,
        public string $gatewayId,
        public string $status,
        public array  $raw,
    ) {}
}
