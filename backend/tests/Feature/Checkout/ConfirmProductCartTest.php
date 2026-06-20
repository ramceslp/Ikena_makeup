<?php

namespace Tests\Feature\Checkout;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * ConfirmProductCartTest
 *
 * Tests the product_cart branch of POST /api/payments/confirm
 * (CheckoutController::confirm).
 *
 * Phase 8.4 (RED) → GREEN after confirm() product_cart branch is implemented.
 */
class ConfirmProductCartTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeProduct(int $stock = 8): Product
    {
        return Product::factory()->create([
            'price'     => 50.00,
            'stock_qty' => $stock,
        ]);
    }

    /**
     * Create a pending product_cart order with one item.
     * Stock is pre-decremented (simulates what CartCheckoutController does).
     */
    private function makePendingCartOrder(
        User $user,
        Product $product,
        int $qty = 2,
        ?string $ctid = null,
    ): Order {
        $ctid ??= 'ORD-' . Str::uuid();

        $order = Order::create([
            'user_id'               => $user->id,
            'type'                  => 'product_cart',
            'client_transaction_id' => $ctid,
            'gateway'               => 'fake',
            'amount_cents'          => 10000,
            'subtotal_cents'        => 10000,
            'tax_cents'             => 0,
            'total_cents'           => 10000,
            'currency'              => 'USD',
            'status'                => 'pending',
            'reserved_until'        => now()->addMinutes(15),
        ]);

        OrderItem::create([
            'order_id'         => $order->id,
            'product_id'       => $product->id,
            'product_title'    => $product->title,
            'quantity'         => $qty,
            'unit_price_cents' => 5000,
            'line_total_cents' => 5000 * $qty,
        ]);

        return $order;
    }

    // -------------------------------------------------------------------------
    // Confirm success — product_cart order paid, no enrollment, stock unchanged
    // -------------------------------------------------------------------------

    public function test_confirm_success_transitions_product_cart_to_paid(): void
    {
        $user    = $this->makeUser();
        $product = $this->makeProduct(8); // was 10, decremented by 2 at checkout
        $order   = $this->makePendingCartOrder($user, $product, 2);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payments/confirm', [
            'id'                  => 1,
            'clientTransactionId' => $order->client_transaction_id,
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.status', 'paid');

        // Order marked paid
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);

        // No enrollment created (product_cart orders don't enroll users)
        $this->assertDatabaseMissing('enrollments', ['user_id' => $user->id]);

        // Stock UNCHANGED (already decremented at checkout, stays decremented on payment)
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_qty' => 8]);
    }

    // -------------------------------------------------------------------------
    // Already-paid — idempotent
    // -------------------------------------------------------------------------

    public function test_confirm_already_paid_is_idempotent(): void
    {
        $user    = $this->makeUser();
        $product = $this->makeProduct(8);

        $order = Order::create([
            'user_id'               => $user->id,
            'type'                  => 'product_cart',
            'client_transaction_id' => 'ORD-paid-idempotent',
            'gateway'               => 'fake',
            'amount_cents'          => 10000,
            'currency'              => 'USD',
            'status'                => 'paid',
            'paid_at'               => now()->subMinutes(2),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payments/confirm', [
            'id'                  => 1,
            'clientTransactionId' => $order->client_transaction_id,
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.status', 'paid');
    }

    // -------------------------------------------------------------------------
    // Gateway declined — status→failed, stock restored
    // -------------------------------------------------------------------------

    public function test_confirm_gateway_declined_restores_stock(): void
    {
        $user    = $this->makeUser();
        $product = $this->makeProduct(8); // stock after checkout decrement

        // Use 'decline' in ctid to trigger FakeGateway decline
        $order = $this->makePendingCartOrder($user, $product, 2, 'decline-cart-test-001');

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payments/confirm', [
            'id'                  => 1,
            'clientTransactionId' => $order->client_transaction_id,
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.status', 'failed');

        // Order marked failed
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'failed']);

        // Stock restored (8 + 2 = 10)
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_qty' => 10]);
    }

    // -------------------------------------------------------------------------
    // Confirm on canceled order — 409 (release won the race)
    // -------------------------------------------------------------------------

    public function test_confirm_on_canceled_order_returns_409(): void
    {
        $user    = $this->makeUser();
        $product = $this->makeProduct(10);

        $order = Order::create([
            'user_id'               => $user->id,
            'type'                  => 'product_cart',
            'client_transaction_id' => 'ORD-already-canceled',
            'gateway'               => 'fake',
            'amount_cents'          => 5000,
            'currency'              => 'USD',
            'status'                => 'canceled', // release command won the race
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payments/confirm', [
            'id'                  => 1,
            'clientTransactionId' => $order->client_transaction_id,
        ]);

        $response->assertStatus(409)
                 ->assertJsonStructure(['data' => ['status', 'message']]);
    }
}
