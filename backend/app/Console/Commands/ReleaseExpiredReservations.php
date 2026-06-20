<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Commerce\StockReservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * stock:release-expired — Release stock held by expired pending product_cart orders.
 *
 * Algorithm (idempotent, concurrency-safe vs confirm()):
 *
 *  foreach (expired product_cart orders via cursor) {
 *    claimed = DB::update WHERE id=? AND status='pending' SET status='canceled'
 *    if claimed === 0: skip (confirm() already won — do NOT restore stock)
 *    else: restore stock via StockReservation::release($order)
 *  }
 *
 * Properties:
 *  - Idempotent: re-running finds canceled/paid orders excluded by the scope.
 *  - Concurrency-safe: the conditional UPDATE is the single arbitration point.
 *    If confirm() transitions pending→paid first, our UPDATE affects 0 rows → skip.
 *    Stock is only restored when this command successfully claims the transition.
 *  - Uses cursor() to avoid loading all expired orders into memory at once.
 */
class ReleaseExpiredReservations extends Command
{
    protected $signature = 'stock:release-expired';
    protected $description = 'Cancel expired pending product_cart reservations and restore their stock';

    public function __construct(private readonly StockReservation $stockReservation)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = 0;

        foreach (Order::expiredProductCarts()->cursor() as $order) {
            // Attempt to atomically claim the transition pending → canceled.
            // If confirm() already transitioned this order (pending → paid),
            // affected rows = 0 → we skip stock restore entirely.
            $claimed = DB::update(
                "UPDATE orders SET status = 'canceled' WHERE id = ? AND status = 'pending'",
                [$order->id]
            );

            if ($claimed === 0) {
                // confirm() won the race — order was already paid or otherwise handled.
                continue;
            }

            // We claimed the cancellation — restore the reserved stock.
            $this->stockReservation->release($order);

            $count++;
        }

        $this->info("Released {$count} expired reservation(s).");

        return self::SUCCESS;
    }
}
