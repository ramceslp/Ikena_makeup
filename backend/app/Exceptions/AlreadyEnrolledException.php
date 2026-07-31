<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by CourseCheckoutAction when the user already holds an Enrollment
 * for the course they are trying to buy.
 *
 * Kept separate from CourseUnavailableException because it maps to a
 * different status: 409 (a conflict with state that already exists and is
 * good news for the user) rather than 422 (a request that can never succeed).
 * This mirrors the OutOfStockException / ProductUnavailableException split
 * already used on the product_cart path.
 *
 * Genuinely reachable from the checkout-handoff redeem endpoint even when the
 * app checked `is_enrolled` before creating the handoff: the user may have
 * completed a purchase of the same course on the web during the token's
 * 10-minute window.
 */
class AlreadyEnrolledException extends RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
