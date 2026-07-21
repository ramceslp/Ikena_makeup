<?php

namespace App\Actions;

use App\Exceptions\OutOfStockException;
use App\Exceptions\ProductUnavailableException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Commerce\StockReservation;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\DTOs\CheckoutSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CartCheckoutAction — atomically reserve stock, price, and create a pending
 * product_cart Order + gateway checkout session for a set of cart line items.
 *
 * Extracted from CartCheckoutController::store() so the same authoritative
 * money/stock rules can be reused by the checkout-handoff redeem endpoint
 * (mobile-capacitor-setup PR2) without duplicating pricing/stock logic. A
 * caller like redeem re-validates product availability and re-prices here
 * instead of trusting a possibly-stale cart snapshot — exactly like the
 * original controller flow.
 *
 * Transaction boundary: the entire flow (reserveLine per item + Order create
 * + OrderItems insert + createCheckout) runs inside a single DB::transaction.
 * If any reserveLine returns 0 (oversell), OutOfStockException is thrown
 * inside the transaction, which auto-rolls back ALL decrements — no orphan
 * mutations.
 *
 * This mirrors the BOOK-equivalent CreateBookingAction blueprint.
 */
class CartCheckoutAction
{
    public function __construct(
        private readonly StockReservation $stockReservation,
        private readonly PaymentGatewayInterface $gateway,
    ) {}

    /**
     * @param  array<int, array{product_id:int, quantity:int}>  $items
     * @return array{order: Order, session: CheckoutSession}
     *
     * @throws ProductUnavailableException one or more products are missing/unpublished
     * @throws OutOfStockException oversell — insufficient stock for a line item
     */
    public function __invoke(User $user, array $items): array
    {
        // Load all referenced products in one query, must be published
        $productIds = array_column($items, 'product_id');
        $products = Product::whereIn('id', $productIds)
            ->where('is_published', true)
            ->get()
            ->keyBy('id');

        // One or more products missing or unpublished. Re-validated here even
        // though the HTTP boundary (StoreCartCheckoutRequest) already enforces
        // this for the direct /api/cart/checkout path, because a caller such
        // as redeem may run this against a snapshot taken earlier, when
        // product state could have changed since.
        foreach ($productIds as $pid) {
            if (! $products->has($pid)) {
                throw new ProductUnavailableException($pid);
            }
        }

        // Build lines
        $lines = [];
        foreach ($items as $item) {
            $product = $products[$item['product_id']];
            $unitPriceCents = (int) round((float) $product->price * 100);
            $lineTotalCents = $unitPriceCents * (int) $item['quantity'];

            $lines[] = [
                'product_id' => $product->id,
                'product_title' => $product->title,
                'quantity' => (int) $item['quantity'],
                'unit_price_cents' => $unitPriceCents,
                'line_total_cents' => $lineTotalCents,
            ];
        }

        // Compute money fields — round-on-subtotal, half-up, integer cents
        $subtotalCents = array_sum(array_column($lines, 'line_total_cents'));
        $ivaRate = (float) config('commerce.tax.iva_rate', 0.15);
        $taxCents = (int) round($subtotalCents * $ivaRate, 0, PHP_ROUND_HALF_UP);
        $totalCents = $subtotalCents + $taxCents;

        $windowMinutes = (int) config('commerce.reservation.window_minutes', 15);

        return DB::transaction(function () use (
            $user, $lines, $subtotalCents, $taxCents, $totalCents, $windowMinutes
        ) {
            // Atomically reserve stock for each line
            foreach ($lines as $line) {
                $affected = $this->stockReservation->reserveLine(
                    $line['product_id'],
                    $line['quantity']
                );

                if ($affected === 0) {
                    throw new OutOfStockException($line['product_id']);
                }
            }

            // Create the pending Order
            $order = Order::create([
                'user_id' => $user->id,
                'type' => 'product_cart',
                'client_transaction_id' => 'ORD-'.Str::uuid(),
                'gateway' => $this->gateway->name(),
                'amount_cents' => $totalCents,      // amount_cents = total_cents per design §6
                'subtotal_cents' => $subtotalCents,
                'tax_cents' => $taxCents,
                'total_cents' => $totalCents,
                'currency' => 'USD',
                'status' => 'pending',
                'reserved_until' => now()->addMinutes($windowMinutes),
            ]);

            // Insert OrderItems
            foreach ($lines as $line) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $line['product_id'],
                    'product_title' => $line['product_title'],
                    'quantity' => $line['quantity'],
                    'unit_price_cents' => $line['unit_price_cents'],
                    'line_total_cents' => $line['line_total_cents'],
                ]);
            }

            // Create gateway checkout session
            $session = $this->gateway->createCheckout($order);

            return ['order' => $order, 'session' => $session];
        });
    }
}
