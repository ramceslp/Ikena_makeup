<?php

namespace Tests\Feature\Commands;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ReleaseExpiredReservationsTest
 *
 * Tests the stock:release-expired artisan command:
 *  - Expired pending product_cart order → canceled + stock restored
 *  - Paid order → unchanged
 *  - Already-canceled order → idempotent (stock NOT released again)
 *  - Confirm-vs-release race: conditional UPDATE arbiter ensures no double mutation
 *
 * Phase 9.1 (RED) → GREEN after ReleaseExpiredReservations command created.
 */
class ReleaseExpiredReservationsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeProduct(int $stock = 10): Product
    {
        return Product::factory()->create([
            'price'     => 50.00,
            'stock_qty' => $stock,
        ]);
    }

    private function makeCartOrder(
        User $user,
        string $status = 'pending',
        ?\DateTime $reservedUntil = null,
    ): Order {
        return Order::create([
            'user_id'               => $user->id,
            'type'                  => 'product_cart',
            'client_transaction_id' => 'ORD-' . Str::uuid(),
            'gateway'               => 'fake',
            'amount_cents'          => 5000,
            'currency'              => 'USD',
            'status'                => $status,
            'reserved_until'        => $reservedUntil ?? now()->subMinutes(5),
        ]);
    }

    private function attachItem(Order $order, Product $product, int $qty = 2): OrderItem
    {
        return OrderItem::create([
            'order_id'         => $order->id,
            'product_id'       => $product->id,
            'product_title'    => $product->title,
            'quantity'         => $qty,
            'unit_price_cents' => 2500,
            'line_total_cents' => 2500 * $qty,
        ]);
    }

    // -------------------------------------------------------------------------
    // Expired pending order → canceled + stock restored
    // -------------------------------------------------------------------------

    public function test_expired_pending_order_gets_canceled_and_stock_restored(): void
    {
        $user    = $this->makeUser();
        $product = $this->makeProduct(8); // was 10, decremented 2 at checkout

        $order = $this->makeCartOrder($user, 'pending', now()->subMinutes(16));
        $this->attachItem($order, $product, 2);

        $this->artisan('stock:release-expired')->assertExitCode(0);

        // Order canceled
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'canceled']);

        // Stock restored: 8 + 2 = 10
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_qty' => 10]);
    }

    // -------------------------------------------------------------------------
    // Paid order → unchanged (command skips non-pending orders via scope)
    // -------------------------------------------------------------------------

    public function test_paid_order_not_affected(): void
    {
        $user    = $this->makeUser();
        $product = $this->makeProduct(8);

        $order = $this->makeCartOrder($user, 'paid', now()->subMinutes(30));
        $this->attachItem($order, $product, 2);

        $this->artisan('stock:release-expired')->assertExitCode(0);

        // Order still paid
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);

        // Stock unchanged
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_qty' => 8]);
    }

    // -------------------------------------------------------------------------
    // Already-canceled order → idempotent (stock NOT released twice)
    // -------------------------------------------------------------------------

    public function test_already_canceled_order_is_idempotent(): void
    {
        $user    = $this->makeUser();
        $product = $this->makeProduct(10); // already restored

        $order = $this->makeCartOrder($user, 'canceled', now()->subMinutes(20));
        $this->attachItem($order, $product, 2);

        $this->artisan('stock:release-expired')->assertExitCode(0);

        // Order still canceled (no change)
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'canceled']);

        // Stock unchanged (still 10, NOT incremented to 12)
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_qty' => 10]);
    }

    // -------------------------------------------------------------------------
    // Not-yet-expired order → left alone
    // -------------------------------------------------------------------------

    public function test_not_yet_expired_order_is_left_alone(): void
    {
        $user    = $this->makeUser();
        $product = $this->makeProduct(8);

        $order = $this->makeCartOrder($user, 'pending', now()->addMinutes(5)); // expires in future
        $this->attachItem($order, $product, 2);

        $this->artisan('stock:release-expired')->assertExitCode(0);

        // Order still pending
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);

        // Stock unchanged
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_qty' => 8]);
    }

    // -------------------------------------------------------------------------
    // Confirm-vs-release race: conditional UPDATE arbiter
    // -------------------------------------------------------------------------

    public function test_release_does_not_restore_stock_if_confirm_wins_race(): void
    {
        // Simulate confirm() winning: order is already 'paid' at the DB level
        // when the release command's conditional UPDATE runs.
        // The release command reads the order (status=pending at cursor time),
        // but by the time it runs UPDATE WHERE status='pending', it's already paid.
        // So the UPDATE affects 0 rows → release skips stock restore.

        $user    = $this->makeUser();
        $product = $this->makeProduct(8); // stock decremented at checkout

        $order = $this->makeCartOrder($user, 'pending', now()->subMinutes(16));
        $this->attachItem($order, $product, 2);

        // Simulate confirm() winning the race: mark the order as paid at DB level
        // BEFORE the release command runs (the command would see this via affected=0)
        \Illuminate\Support\Facades\DB::table('orders')
            ->where('id', $order->id)
            ->where('status', 'pending')
            ->update(['status' => 'paid', 'paid_at' => now()]);

        $this->artisan('stock:release-expired')->assertExitCode(0);

        // Order still paid (release did not cancel it)
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);

        // Stock NOT restored (confirm won the race — stock stays decremented for fulfilled order)
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_qty' => 8]);
    }

    // -------------------------------------------------------------------------
    // Multiple orders in one run
    // -------------------------------------------------------------------------

    public function test_releases_multiple_expired_orders_in_one_run(): void
    {
        $user     = $this->makeUser();
        $productA = $this->makeProduct(8);
        $productB = $this->makeProduct(9);

        $orderA = $this->makeCartOrder($user, 'pending', now()->subMinutes(20));
        $this->attachItem($orderA, $productA, 2);

        $orderB = $this->makeCartOrder($user, 'pending', now()->subMinutes(25));
        $this->attachItem($orderB, $productB, 1);

        $this->artisan('stock:release-expired')->assertExitCode(0);

        $this->assertDatabaseHas('orders', ['id' => $orderA->id, 'status' => 'canceled']);
        $this->assertDatabaseHas('orders', ['id' => $orderB->id, 'status' => 'canceled']);

        $this->assertDatabaseHas('products', ['id' => $productA->id, 'stock_qty' => 10]);
        $this->assertDatabaseHas('products', ['id' => $productB->id, 'stock_qty' => 10]);
    }
}
