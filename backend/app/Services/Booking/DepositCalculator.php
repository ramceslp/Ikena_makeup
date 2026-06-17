<?php

namespace App\Services\Booking;

use App\Models\Service;

/**
 * Pure deposit calculation service.
 *
 * Formula: deposit_amount_cents = round(price * deposit_percentage / 100 * 100)
 *
 * Keeping the multiplication by 100 explicit makes the rounding intent clear:
 * convert dollar amount to cents and round to nearest cent.
 */
class DepositCalculator
{
    /**
     * Compute the deposit amount in cents for the given service.
     */
    public function cents(Service $service): int
    {
        return (int) round((float) $service->price * $service->depositPercentage() / 100 * 100);
    }
}
