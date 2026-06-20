<?php

namespace Tests\Unit\Commerce;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Commerce\StockReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * StockReservationTest
 *
 * Verifies the StockReservation domain action:
 *  - reserveLine() decrements stock atomically, returns affected rows
 *  - reserveLine() returns 0 (no mutation) when stock insufficient (oversell guard)
 *  - release() increments stock back from order_items
 *
 * Phase 7.1 (RED) — tests must fail until StockReservation is created.
 */
class StockReservationTest extends TestCase
{
    use RefreshDatabase;

    private StockReservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reservation = new StockReservation();
    }

    // -------------------------------------------------------------------------
    // reserveLine() — happy path
    // -------------------------------------------------------------------------

    public function test_reserve_line_decrements_stock_and_returns_1(): void
    {
        $product = Product::factory()->create([
            'price'     => 50.00,
            'stock_qty' => 10,
        ]);

        $affected = $this->reservation->reserveLine($product->id, 3);

        $this->assertSame(1, $affected, 'Expected 1 affected row on successful reserve');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_qty' => 7]);
    }

    public function test_reserve_line_decrements_by_exact_quantity(): void
    {
        $product = Product::factory()->create(['price' => 20.00, 'stock_qty' => 5]);

        $this->reservation->reserveLine($product->id, 5);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_qty' => 0]);
    }

    // -------------------------------------------------------------------------
    // reserveLine() — oversell guard (no negative stock)
    // -------------------------------------------------------------------------

    public function test_reserve_line_returns_0_when_stock_insufficient(): void
    {
        $product = Product::factory()->create(['price' => 50.00, 'stock_qty' => 2]);

        $affected = $this->reservation->reserveLine($product->id, 5);

        $this->assertSame(0, $affected, 'Expected 0 affected rows when stock is insufficient');
        // Stock must NOT go negative
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_qty' => 2]);
    }

    public function test_reserve_line_returns_0_when_stock_is_zero(): void
    {
        $product = Product::factory()->create(['price' => 50.00, 'stock_qty' => 0]);

        $affected = $this->reservation->reserveLine($product->id, 1);

        $this->assertSame(0, $affected);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_qty' => 0]);
    }

    // -------------------------------------------------------------------------
    // release() — increments stock back from order items
    // -------------------------------------------------------------------------

    public function test_release_restores_stock_from_order_items(): void
    {
        $user = User::factory()->create();
        $productA = Product::factory()->create(['price' => 30.00, 'stock_qty' => 3]); // was 5, decremented by 2
        $productB = Product::factory()->create(['price' => 20.00, 'stock_qty' => 9]); // was 10, decremented by 1

        // Simulate a product_cart order that already decremented stock
        $order = Order::create([
            'user_id'               => $user->id,
            'type'                  => 'product_cart',
            'client_transaction_id' => 'ORD-release-test-001',
            'gateway'               => 'fake',
            'amount_cents'          => 8000,
            'status'                => 'pending',
            'currency'              => 'USD',
        ]);

        OrderItem::create([
            'order_id'         => $order->id,
            'product_id'       => $productA->id,
            'product_title'    => $productA->title,
            'quantity'         => 2,
            'unit_price_cents' => 3000,
            'line_total_cents' => 6000,
        ]);

        OrderItem::create([
            'order_id'         => $order->id,
            'product_id'       => $productB->id,
            'product_title'    => $productB->title,
            'quantity'         => 1,
            'unit_price_cents' => 2000,
            'line_total_cents' => 2000,
        ]);

        $this->reservation->release($order);

        // Product A should have stock += 2 (3 → 5)
        $this->assertDatabaseHas('products', ['id' => $productA->id, 'stock_qty' => 5]);
        // Product B should have stock += 1 (9 → 10)
        $this->assertDatabaseHas('products', ['id' => $productB->id, 'stock_qty' => 10]);
    }

    public function test_release_skips_item_with_null_product_id(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 30.00, 'stock_qty' => 8]);

        $order = Order::create([
            'user_id'               => $user->id,
            'type'                  => 'product_cart',
            'client_transaction_id' => 'ORD-release-null-001',
            'gateway'               => 'fake',
            'amount_cents'          => 3000,
            'status'                => 'pending',
            'currency'              => 'USD',
        ]);

        // Item with null product_id (deleted product) — should be skipped
        OrderItem::create([
            'order_id'         => $order->id,
            'product_id'       => null,
            'product_title'    => 'Deleted Product',
            'quantity'         => 2,
            'unit_price_cents' => 1500,
            'line_total_cents' => 3000,
        ]);

        // Item with valid product_id
        OrderItem::create([
            'order_id'         => $order->id,
            'product_id'       => $product->id,
            'product_title'    => $product->title,
            'quantity'         => 1,
            'unit_price_cents' => 3000,
            'line_total_cents' => 3000,
        ]);

        $this->reservation->release($order);

        // Only the product with valid product_id should have stock restored
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_qty' => 9]);
    }
}
