<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OutOfStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCartCheckoutRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Commerce\StockReservation;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * CartCheckoutController
 *
 * POST /api/cart/checkout — atomically reserve stock and create a pending
 * product_cart Order, then return a gateway checkout session.
 *
 * Transaction boundary: the entire flow (reserveLine per item + Order create +
 * OrderItems insert + createCheckout) runs inside a single DB::transaction.
 * If any reserveLine returns 0 (oversell), we throw OutOfStockException inside
 * the transaction, which auto-rolls back ALL decrements — no orphan mutations.
 *
 * This mirrors the BookingController::store() blueprint.
 */
class CartCheckoutController extends Controller
{
    public function __construct(
        private readonly StockReservation       $stockReservation,
        private readonly PaymentGatewayInterface $gateway,
    ) {}

    /**
     * POST /api/cart/checkout
     *
     * Flow:
     *  1. Validate request (StoreCartCheckoutRequest).
     *  2. Load published products for each line item → 422 if any missing.
     *  3. Build lines: unit_price_cents, line_total_cents.
     *  4. Compute subtotal_cents, tax_cents (from config), total_cents.
     *  5. Open DB::transaction:
     *     a. reserveLine(productId, qty) per line → throw OutOfStockException on 0.
     *     b. Create Order (type=product_cart, reserved_until = now + window).
     *     c. Insert OrderItems.
     *     d. createCheckout($order) → gateway session.
     *  6. Return 201 with order_id + gateway config.
     *  7. Catch OutOfStockException → 409 (tx auto-rolled-back).
     */
    public function store(StoreCartCheckoutRequest $request): JsonResponse
    {
        $items   = $request->validated()['items'];
        $user    = $request->user();

        // Load all referenced products in one query, must be published
        $productIds = array_column($items, 'product_id');
        $products   = Product::whereIn('id', $productIds)
                             ->where('is_published', true)
                             ->get()
                             ->keyBy('id');

        // 422 if any product is missing or unpublished
        foreach ($productIds as $pid) {
            if (! $products->has($pid)) {
                return response()->json([
                    'message'    => 'One or more products are unavailable or unpublished.',
                    'product_id' => $pid,
                ], 422);
            }
        }

        // Build lines
        $lines = [];
        foreach ($items as $item) {
            $product          = $products[$item['product_id']];
            $unitPriceCents   = (int) round((float) $product->price * 100);
            $lineTotalCents   = $unitPriceCents * (int) $item['quantity'];

            $lines[] = [
                'product_id'       => $product->id,
                'product_title'    => $product->title,
                'quantity'         => (int) $item['quantity'],
                'unit_price_cents' => $unitPriceCents,
                'line_total_cents' => $lineTotalCents,
            ];
        }

        // Compute money fields — round-on-subtotal, half-up, integer cents
        $subtotalCents = array_sum(array_column($lines, 'line_total_cents'));
        $ivaRate       = (float) config('commerce.tax.iva_rate', 0.15);
        $taxCents      = (int) round($subtotalCents * $ivaRate, 0, PHP_ROUND_HALF_UP);
        $totalCents    = $subtotalCents + $taxCents;

        $windowMinutes = (int) config('commerce.reservation.window_minutes', 15);

        try {
            $result = DB::transaction(function () use (
                $user, $lines, $subtotalCents, $taxCents, $totalCents, $windowMinutes
            ) {
                // Step 5a: atomically reserve stock for each line
                foreach ($lines as $line) {
                    $affected = $this->stockReservation->reserveLine(
                        $line['product_id'],
                        $line['quantity']
                    );

                    if ($affected === 0) {
                        throw new OutOfStockException($line['product_id']);
                    }
                }

                // Step 5b: create the pending Order
                $order = Order::create([
                    'user_id'               => $user->id,
                    'type'                  => 'product_cart',
                    'client_transaction_id' => 'ORD-' . Str::uuid(),
                    'gateway'               => $this->gateway->name(),
                    'amount_cents'          => $totalCents,      // amount_cents = total_cents per design §6
                    'subtotal_cents'        => $subtotalCents,
                    'tax_cents'             => $taxCents,
                    'total_cents'           => $totalCents,
                    'currency'              => 'USD',
                    'status'                => 'pending',
                    'reserved_until'        => now()->addMinutes($windowMinutes),
                ]);

                // Step 5c: insert OrderItems
                foreach ($lines as $line) {
                    OrderItem::create([
                        'order_id'         => $order->id,
                        'product_id'       => $line['product_id'],
                        'product_title'    => $line['product_title'],
                        'quantity'         => $line['quantity'],
                        'unit_price_cents' => $line['unit_price_cents'],
                        'line_total_cents' => $line['line_total_cents'],
                    ]);
                }

                // Step 5d: create gateway checkout session
                $session = $this->gateway->createCheckout($order);

                return ['order' => $order, 'session' => $session];
            });
        } catch (OutOfStockException $e) {
            // Transaction auto-rolled-back — all stock decrements undone
            return response()->json([
                'message'    => 'Insufficient stock for one or more items.',
                'product_id' => $e->productId,
            ], 409);
        } catch (\Throwable $e) {
            Log::error('Cart checkout failed: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Checkout failed. Please try again.'], 500);
        }

        return response()->json([
            'data' => [
                'order_id' => $result['order']->id,
                'provider' => $result['session']->provider,
                'config'   => $result['session']->config,
            ],
        ], 201);
    }
}
