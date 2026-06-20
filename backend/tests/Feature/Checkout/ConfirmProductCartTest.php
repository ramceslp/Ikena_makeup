<?php

namespace Tests\Feature\Checkout;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\DTOs\CheckoutSession;
use App\Services\Payments\DTOs\PaymentResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    // -------------------------------------------------------------------------
    // Early-return when order is ALREADY canceled before confirm() runs
    // (the controller's upfront status check at the top of confirmProductCart,
    // NOT the mid-flight race guard at the DB::update WHERE status='pending').
    // -------------------------------------------------------------------------

    public function test_confirm_on_already_canceled_order_returns_409_without_touching_stock(): void
    {
        $user    = $this->makeUser();
        // Original stock = 10; checkout decremented it to 8 (qty 2 reserved).
        $product = $this->makeProduct(8);

        // Build a pending order with a 'decline' ctid so FakeGateway returns declined.
        $order = $this->makePendingCartOrder($user, $product, 2, 'decline-race-test-001');

        // Simulate the release command winning BEFORE confirm() runs:
        //  (a) cancel the order atomically
        DB::update(
            "UPDATE orders SET status = 'canceled' WHERE id = ? AND status = 'pending'",
            [$order->id]
        );
        //  (b) restore stock (release command does this after claiming the transition)
        $product->increment('stock_qty', 2); // back to original 10

        Sanctum::actingAs($user);

        // Order is already canceled when the controller reads it. It hits the
        // upfront early-return (line ~235: if ($order->status === 'canceled'))
        // before calling the gateway at all — gateway is never invoked.
        $response = $this->postJson('/api/payments/confirm', [
            'id'                  => 1,
            'clientTransactionId' => $order->client_transaction_id,
        ]);

        // Must be 409 canceled
        $response->assertStatus(409)
                 ->assertJsonPath('data.status', 'canceled');

        // Order status stays 'canceled' — must NOT be overwritten to 'failed'
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'canceled']);

        // Stock must equal original (10), NOT original + qty again (12)
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_qty' => 10]);
    }

    // -------------------------------------------------------------------------
    // Race guard — declined payment loses mid-flight race to the release command
    //
    // This test exercises the DB::update WHERE status='pending' guard in the
    // declined branch (~line 299 of CheckoutController). The order is PENDING
    // when the controller reads it, so the upfront early-return is NOT hit and
    // the gateway IS called. The gateway stub's confirm() side-effect atomically
    // transitions the order to 'canceled' and restores stock (simulating the
    // release cron winning between gateway->confirm() and DB::update), then
    // returns a DECLINED PaymentResult. The DB::update WHERE status='pending'
    // then affects 0 rows (claimed === 0), so the controller must NOT call
    // stockReservation->release() again. Final stock must equal original (10),
    // NOT original + qty (12).
    //
    // Discriminating power: if the WHERE status='pending' guard were removed
    // (unconditional update + unconditional release), this test WOULD FAIL because
    // stock would become 10 + 2 = 12 instead of 10.
    // -------------------------------------------------------------------------

    public function test_declined_payment_loses_race_to_release_does_not_double_restore_stock(): void
    {
        $user    = $this->makeUser();
        // Original stock = 10; checkout decremented to 8 (qty 2 reserved).
        $product = $this->makeProduct(8);
        $originalStock = 10;
        $reservedQty   = 2;

        // Order is PENDING so the upfront early-return is bypassed and the gateway is called.
        $order = $this->makePendingCartOrder($user, $product, $reservedQty, 'race-guard-test-001');

        $orderId   = $order->id;
        $productId = $product->id;

        // Bind a gateway stub whose confirm() simulates the release command winning
        // mid-flight (between gateway->confirm() returning and the DB::update guard):
        //   1. Atomically transition the order to 'canceled' (as the release cron does).
        //   2. Restore stock to original (as StockReservation::release does).
        //   3. Return a DECLINED PaymentResult.
        // After this side-effect runs, the DB::update WHERE status='pending' in the
        // controller affects 0 rows (claimed === 0) → stockReservation->release() must
        // NOT be called, so stock stays at $originalStock.
        $gatewayStubCalled = false;

        $stub = new class($orderId, $productId, $originalStock, $reservedQty, $gatewayStubCalled) implements PaymentGatewayInterface {
            public bool $wasCalled = false;

            public function __construct(
                private int $orderId,
                private int $productId,
                private int $originalStock,
                private int $reservedQty,
                bool        $_ // placeholder — use $this->wasCalled instead
            ) {}

            public function createCheckout(\App\Models\Order $order): CheckoutSession
            {
                return new CheckoutSession(provider: 'stub', config: []);
            }

            public function confirm(string $gatewayId, string $clientTransactionId): PaymentResult
            {
                $this->wasCalled = true;

                // Simulate release command winning mid-flight:
                // (a) cancel the order (same conditional UPDATE the cron uses)
                DB::table('orders')
                    ->where('id', $this->orderId)
                    ->where('status', 'pending')
                    ->update(['status' => 'canceled']);

                // (b) restore stock to original (same as StockReservation::release)
                DB::table('products')
                    ->where('id', $this->productId)
                    ->update(['stock_qty' => $this->originalStock]);

                // Return DECLINED so the controller enters the declined branch.
                return new PaymentResult(
                    approved:  false,
                    gatewayId: $gatewayId,
                    status:    'failed',
                    raw:       ['statusCode' => 2, 'stub' => true],
                );
            }

            public function name(): string { return 'stub'; }
        };

        // Rebind the interface so CheckoutController receives our stub.
        $this->app->instance(PaymentGatewayInterface::class, $stub);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payments/confirm', [
            'id'                  => 1,
            'clientTransactionId' => $order->client_transaction_id,
        ]);

        // The stub must have been invoked (order was pending at controller entry).
        $this->assertTrue($stub->wasCalled, 'Gateway stub was not called — order was not pending at controller entry, so the upfront early-return fired instead of the race guard.');

        // 409 canceled — the race guard detected claimed === 0 and re-read canceled status.
        $response->assertStatus(409)
                 ->assertJsonPath('data.status', 'canceled');

        // Order stays 'canceled' — must NOT be overwritten to 'failed'.
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'canceled']);

        // Stock must equal original (10). If the guard were absent, release() would run
        // again and produce stock_qty = 10 + 2 = 12 — this assertion would fail.
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_qty' => $originalStock]);
    }
}
