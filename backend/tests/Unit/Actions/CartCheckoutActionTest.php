<?php

namespace Tests\Unit\Actions;

use App\Actions\CartCheckoutAction;
use App\Exceptions\OutOfStockException;
use App\Exceptions\ProductUnavailableException;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CartCheckoutActionTest
 *
 * Covers the same stock-reservation and pricing scenarios that
 * CartCheckoutControllerTest already exercises over HTTP, but calling
 * App\Actions\CartCheckoutAction directly — this is the reusable unit that
 * CartCheckoutController delegates to (and that the checkout-handoff redeem
 * endpoint, PR2, will reuse). Extraction must not change these outcomes.
 */
class CartCheckoutActionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeProduct(array $attrs = []): Product
    {
        return Product::factory()->create(array_merge([
            'price' => 100.00,
            'stock_qty' => 10,
            'is_published' => true,
        ], $attrs));
    }

    private function action(): CartCheckoutAction
    {
        return app(CartCheckoutAction::class);
    }

    public function test_missing_or_unpublished_product_throws_product_unavailable_exception(): void
    {
        $user = $this->makeUser();

        $this->expectException(ProductUnavailableException::class);

        ($this->action())($user, [
            ['product_id' => 999999, 'quantity' => 1],
        ]);
    }

    public function test_oversell_throws_out_of_stock_exception_and_rolls_back(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct(['price' => 50.00, 'stock_qty' => 2]);

        try {
            ($this->action())($user, [
                ['product_id' => $product->id, 'quantity' => 5],
            ]);
            $this->fail('Expected OutOfStockException was not thrown.');
        } catch (OutOfStockException $e) {
            $this->assertSame($product->id, $e->productId);
        }

        // Stock must NOT go negative — the transaction rolled back.
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_qty' => 2]);
    }

    public function test_happy_path_creates_order_decrements_stock_and_returns_session(): void
    {
        $user = $this->makeUser();
        $productA = $this->makeProduct(['price' => 100.00, 'stock_qty' => 10]);
        $productB = $this->makeProduct(['price' => 50.00, 'stock_qty' => 3]);

        $result = ($this->action())($user, [
            ['product_id' => $productA->id, 'quantity' => 2],
            ['product_id' => $productB->id, 'quantity' => 1],
        ]);

        $this->assertArrayHasKey('order', $result);
        $this->assertArrayHasKey('session', $result);

        $order = $result['order'];

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'user_id' => $user->id,
            'type' => 'product_cart',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $productA->id,
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('products', ['id' => $productA->id, 'stock_qty' => 8]);
        $this->assertDatabaseHas('products', ['id' => $productB->id, 'stock_qty' => 2]);

        $this->assertNotNull($order->reserved_until);
        $this->assertTrue($order->reserved_until->isFuture());
    }

    // -------------------------------------------------------------------------
    // PR4a's cost snapshot (D1's sibling for products) — written here so
    // TopProducts margin (PR4a) never has to trust the live, mutable
    // products.cost for a historical sale.
    // -------------------------------------------------------------------------

    public function test_checkout_snapshots_unit_cost_cents_from_product_cost(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct(['price' => 100.00, 'cost' => 40.00, 'stock_qty' => 10]);

        $result = ($this->action())($user, [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $result['order']->id,
            'product_id' => $product->id,
            'unit_cost_cents' => 4000,
        ]);
    }

    /**
     * Mirrors the "Product cost changes after sale" spec scenario for
     * TopProducts (PR4a): the sold line item's snapshot must not follow a
     * later change to the live products.cost.
     */
    public function test_product_cost_change_after_sale_does_not_affect_stored_snapshot(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct(['price' => 100.00, 'cost' => 30.00, 'stock_qty' => 10]);

        $result = ($this->action())($user, [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        $orderItem = \App\Models\OrderItem::where('order_id', $result['order']->id)
            ->where('product_id', $product->id)
            ->first();

        $this->assertSame(3000, $orderItem->unit_cost_cents);

        $product->update(['cost' => 50.00]);

        $this->assertSame(3000, $orderItem->fresh()->unit_cost_cents);
    }

    public function test_iva_parity_subtotal_21400_tax_3210_total_24610(): void
    {
        // Product at $107.00 x 2 = $214.00 subtotal -> 21400 cents
        // IVA: round(21400 * 0.15) = 3210
        // total: 21400 + 3210 = 24610
        $user = $this->makeUser();
        $product = $this->makeProduct(['price' => 107.00, 'stock_qty' => 10]);

        $result = ($this->action())($user, [
            ['product_id' => $product->id, 'quantity' => 2],
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $result['order']->id,
            'subtotal_cents' => 21400,
            'tax_cents' => 3210,
            'total_cents' => 24610,
            'amount_cents' => 24610,
        ]);
    }
}
