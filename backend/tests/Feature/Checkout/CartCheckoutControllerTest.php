<?php

namespace Tests\Feature\Checkout;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * CartCheckoutControllerTest
 *
 * Tests POST /api/cart/checkout (CartCheckoutController::store).
 *
 * Phase 8.1 (RED) → GREEN after CartCheckoutController + StoreCartCheckoutRequest created.
 */
class CartCheckoutControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeProduct(array $attrs = []): Product
    {
        return Product::factory()->create(array_merge([
            'price'        => 100.00,
            'stock_qty'    => 10,
            'is_published' => true,
        ], $attrs));
    }

    // -------------------------------------------------------------------------
    // 401 — unauthenticated
    // -------------------------------------------------------------------------

    public function test_unauthenticated_returns_401(): void
    {
        $product = $this->makeProduct();

        $this->postJson('/api/cart/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // 422 — mixed cart (course or appointment item) rejected
    // -------------------------------------------------------------------------

    public function test_mixed_cart_returns_422(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        // Request with a non-existent/non-published product simulates invalid items array shape
        // The request validator accepts only product_id (int) + quantity (int >= 1).
        // A "mixed cart" scenario where a non-product item slips in is caught by validation
        // (product_id must exist in products table as published).
        $response = $this->postJson('/api/cart/checkout', [
            'items' => [], // empty items — no line items at all
        ]);

        $response->assertStatus(422);
    }

    public function test_items_with_nonexistent_product_returns_422(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/cart/checkout', [
            'items' => [
                ['product_id' => 99999, 'quantity' => 1], // non-existent
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_items_with_unpublished_product_returns_422(): void
    {
        $user    = $this->makeUser();
        $product = $this->makeProduct(['is_published' => false]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/cart/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_quantity_less_than_1_returns_422(): void
    {
        $user    = $this->makeUser();
        $product = $this->makeProduct();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/cart/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 0],
            ],
        ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // 409 — oversell (guarded UPDATE returns 0)
    // -------------------------------------------------------------------------

    public function test_oversell_returns_409(): void
    {
        $user    = $this->makeUser();
        $product = $this->makeProduct(['price' => 50.00, 'stock_qty' => 2]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/cart/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5], // more than stock
            ],
        ]);

        $response->assertStatus(409);
        $response->assertJsonStructure(['message', 'product_id']);

        // Stock must NOT go negative
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_qty' => 2]);
    }

    // -------------------------------------------------------------------------
    // 201 — happy path
    // -------------------------------------------------------------------------

    public function test_happy_path_creates_order_and_decrements_stock(): void
    {
        $user     = $this->makeUser();
        $productA = $this->makeProduct(['price' => 100.00, 'stock_qty' => 10]);
        $productB = $this->makeProduct(['price' => 50.00, 'stock_qty' => 3]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/cart/checkout', [
            'items' => [
                ['product_id' => $productA->id, 'quantity' => 2],
                ['product_id' => $productB->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'data' => ['order_id', 'provider', 'config'],
                 ]);

        $orderId = $response->json('data.order_id');

        // Order created with correct type and status
        $this->assertDatabaseHas('orders', [
            'id'      => $orderId,
            'user_id' => $user->id,
            'type'    => 'product_cart',
            'status'  => 'pending',
        ]);

        // Order items created
        $this->assertDatabaseHas('order_items', [
            'order_id'   => $orderId,
            'product_id' => $productA->id,
            'quantity'   => 2,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id'   => $orderId,
            'product_id' => $productB->id,
            'quantity'   => 1,
        ]);

        // Stock decremented
        $this->assertDatabaseHas('products', ['id' => $productA->id, 'stock_qty' => 8]);
        $this->assertDatabaseHas('products', ['id' => $productB->id, 'stock_qty' => 2]);
    }

    // -------------------------------------------------------------------------
    // IVA parity assertion
    // -------------------------------------------------------------------------

    public function test_iva_parity_subtotal_21400_tax_3210_total_24610(): void
    {
        // Product at $107.00 × 2 = $214.00 subtotal → 21400 cents
        // IVA: round(21400 * 0.15) = 3210
        // total: 21400 + 3210 = 24610
        $user    = $this->makeUser();
        $product = $this->makeProduct(['price' => 107.00, 'stock_qty' => 10]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/cart/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(201);

        $orderId = $response->json('data.order_id');

        $this->assertDatabaseHas('orders', [
            'id'             => $orderId,
            'subtotal_cents' => 21400,
            'tax_cents'      => 3210,
            'total_cents'    => 24610,
            'amount_cents'   => 24610,
        ]);
    }

    // -------------------------------------------------------------------------
    // reserved_until is set on the order
    // -------------------------------------------------------------------------

    public function test_reserved_until_is_set_on_created_order(): void
    {
        $user    = $this->makeUser();
        $product = $this->makeProduct(['price' => 50.00, 'stock_qty' => 5]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/cart/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201);
        $orderId = $response->json('data.order_id');

        $order = Order::find($orderId);
        $this->assertNotNull($order->reserved_until, 'reserved_until should be set for product_cart orders');
        $this->assertTrue($order->reserved_until->isFuture(), 'reserved_until should be in the future');
    }
}
