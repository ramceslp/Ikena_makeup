<?php

namespace App\Http\Controllers\Api;

use App\Actions\CartCheckoutAction;
use App\Exceptions\OutOfStockException;
use App\Exceptions\ProductUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCartCheckoutRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * CartCheckoutController
 *
 * POST /api/cart/checkout — delegates atomic stock reservation, pricing, and
 * Order creation to CartCheckoutAction (see App\Actions\CartCheckoutAction),
 * which is shared with the checkout-handoff redeem endpoint so money/stock
 * rules stay single-sourced. This controller only translates the action's
 * result / thrown exceptions into HTTP responses.
 */
class CartCheckoutController extends Controller
{
    public function __construct(
        private readonly CartCheckoutAction $checkout,
    ) {}

    /**
     * POST /api/cart/checkout
     *
     * Flow:
     *  1. Validate request (StoreCartCheckoutRequest).
     *  2. Delegate to CartCheckoutAction (product lookup, pricing, atomic
     *     stock reservation, Order + OrderItems creation, gateway checkout).
     *  3. Translate ProductUnavailableException -> 422, OutOfStockException
     *     -> 409, anything else -> 500 (logged).
     *  4. Return 201 with order_id + gateway config on success.
     */
    public function store(StoreCartCheckoutRequest $request): JsonResponse
    {
        $items = $request->validated()['items'];
        $user = $request->user();

        try {
            $result = ($this->checkout)($user, $items);
        } catch (ProductUnavailableException $e) {
            return response()->json([
                'message' => 'One or more products are unavailable or unpublished.',
                'product_id' => $e->productId,
            ], 422);
        } catch (OutOfStockException $e) {
            // Transaction auto-rolled-back — all stock decrements undone
            return response()->json([
                'message' => 'Insufficient stock for one or more items.',
                'product_id' => $e->productId,
            ], 409);
        } catch (\Throwable $e) {
            Log::error('Cart checkout failed: '.$e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Checkout failed. Please try again.'], 500);
        }

        return response()->json([
            'data' => [
                'order_id' => $result['order']->id,
                'provider' => $result['session']->provider,
                'config' => $result['session']->config,
            ],
        ], 201);
    }
}
