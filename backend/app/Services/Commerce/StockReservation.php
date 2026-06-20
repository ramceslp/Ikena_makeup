<?php

namespace App\Services\Commerce;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * StockReservation — domain action for atomic stock management.
 *
 * Two responsibilities:
 *  1. reserveLine(productId, qty): atomically decrement stock via a guarded UPDATE
 *     (WHERE stock_qty >= qty). Returns the number of affected rows (1 = success,
 *     0 = oversell rejected). Works identically on SQLite and MySQL.
 *
 *  2. release(Order): restore stock from order_items, skipping items whose
 *     product_id has been nulled (deleted product).
 *
 * This action is called inside a DB::transaction in CartCheckoutController and
 * in ReleaseExpiredReservations, so it does NOT open its own transaction.
 */
class StockReservation
{
    /**
     * Atomically reserve `$qty` units of product `$productId`.
     *
     * Executes a guarded UPDATE:
     *   UPDATE products SET stock_qty = stock_qty - ? WHERE id = ? AND stock_qty >= ?
     *
     * Returns:
     *   1  — reservation succeeded (stock was decremented)
     *   0  — oversell rejected (stock < qty; no change made, no negative stock possible)
     */
    public function reserveLine(int $productId, int $qty): int
    {
        return DB::update(
            'UPDATE products SET stock_qty = stock_qty - ? WHERE id = ? AND stock_qty >= ?',
            [$qty, $productId, $qty]
        );
    }

    /**
     * Restore stock for all order items in the given Order.
     *
     * Iterates order_items and increments each product's stock_qty by the
     * reserved quantity. Items with a null product_id (product was deleted)
     * are silently skipped.
     */
    public function release(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            if ($item->product_id === null) {
                continue; // Product was deleted — skip, nothing to restore
            }

            DB::update(
                'UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?',
                [$item->quantity, $item->product_id]
            );
        }
    }
}
