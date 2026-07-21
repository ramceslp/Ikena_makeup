<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by CartCheckoutAction when one or more requested products no
 * longer exist or are unpublished at the moment the action runs.
 *
 * The HTTP boundary (StoreCartCheckoutRequest) already enforces existence +
 * published status for the direct /api/cart/checkout path, so this is
 * defense-in-depth there. It matters more for callers without that request
 * validation layer — e.g. the checkout-handoff redeem endpoint (PR2), which
 * re-runs this action against a cart snapshot that may have gone stale
 * (product deleted/unpublished) since the handoff token was issued.
 */
class ProductUnavailableException extends RuntimeException
{
    public function __construct(public readonly int $productId)
    {
        parent::__construct("Product {$productId} is unavailable or unpublished.");
    }
}
