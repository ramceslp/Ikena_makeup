<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a guarded stock UPDATE affects 0 rows (oversell attempt).
 * Caught in CartCheckoutController to return 409.
 */
class OutOfStockException extends RuntimeException
{
    public function __construct(public readonly int $productId)
    {
        parent::__construct("Product {$productId} is out of stock or has insufficient quantity.");
    }
}
