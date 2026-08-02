<?php

namespace Tests\Feature\Reports;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Reports\Money\RevenueStreams;
use App\Reports\PeriodCalendar;
use App\Reports\Queries\TopProductsQuery;
use App\Reports\ReportFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * TopProductsQueryTest
 *
 * Reports testsuite (backend/phpunit.mysql.xml). Pins spec's "Top products
 * ranking with margin" requirement ([Slice 4]): margin computed from
 * `order_items.unit_cost_cents` (the snapshot taken at sale time), never
 * the live `products.cost` — and the coverage indicator this PR adds on top
 * of the spec: `unit_cost_cents` is manual data entry defaulting to 0, so
 * lines with no recorded cost must not silently report a false margin.
 */
class TopProductsQueryTest extends TestCase
{
    use RefreshDatabase;

    private function query(): TopProductsQuery
    {
        return new TopProductsQuery(new RevenueStreams());
    }

    private function filterFor(string $from, string $to): ReportFilter
    {
        $request = Request::create('/api/admin/reports/rankings/products', 'GET', [
            'from' => $from,
            'to' => $to,
        ]);

        return ReportFilter::fromRequest($request, new PeriodCalendar());
    }

    private function makePaidProductOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'type' => 'product_cart',
            'client_transaction_id' => 'ORD-topproducts-'.uniqid(),
            'gateway' => 'fake',
            'amount_cents' => 9900,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => '2026-08-05 10:00:00',
        ], $overrides));
    }

    public function test_margin_uses_the_unit_cost_snapshot_never_the_live_product_cost(): void
    {
        $product = Product::factory()->create(['cost' => 3.00]);
        $order = $this->makePaidProductOrder();

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_title' => $product->title,
            'quantity' => 2,
            'unit_price_cents' => 1000,
            'line_total_cents' => 2000,
            'unit_cost_cents' => 300, // snapshot taken at sale time
        ]);

        // Live cost changes AFTER the sale — must not retroactively affect margin.
        $product->update(['cost' => 5.00]);

        $result = $this->query()->run($this->filterFor('2026-08-01', '2026-08-10'));

        $this->assertCount(1, $result);
        $this->assertSame($product->id, $result[0]['product_id']);
        $this->assertSame(2000, $result[0]['revenue_cents']);
        // margin = (1000 - 300) * 2 = 1400, from the SNAPSHOT cost, not 5.00.
        $this->assertSame(1400, $result[0]['margin_cents']);
        $this->assertSame(2000, $result[0]['known_cost_revenue_cents']);
    }

    public function test_zero_unit_cost_is_treated_as_unknown_and_excluded_from_margin_and_coverage(): void
    {
        $product = Product::factory()->create(['cost' => 0]);
        $order = $this->makePaidProductOrder();

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_title' => $product->title,
            'quantity' => 1,
            'unit_price_cents' => 5000,
            'line_total_cents' => 5000,
            'unit_cost_cents' => 0, // never entered by the admin
        ]);

        $result = $this->query()->run($this->filterFor('2026-08-01', '2026-08-10'));

        $this->assertCount(1, $result);
        $this->assertSame(5000, $result[0]['revenue_cents']);
        // Cost unknown → margin must NOT silently report 5000 (100% margin).
        $this->assertSame(0, $result[0]['margin_cents']);
        $this->assertSame(0, $result[0]['known_cost_revenue_cents']);
    }

    public function test_ranking_orders_by_revenue_descending_and_ignores_out_of_range_orders(): void
    {
        $topProduct = Product::factory()->create(['cost' => 2.00]);
        $lowProduct = Product::factory()->create(['cost' => 1.00]);

        $orderTop = $this->makePaidProductOrder(['paid_at' => '2026-08-05 10:00:00']);
        OrderItem::create([
            'order_id' => $orderTop->id,
            'product_id' => $topProduct->id,
            'product_title' => $topProduct->title,
            'quantity' => 5,
            'unit_price_cents' => 1000,
            'line_total_cents' => 5000,
            'unit_cost_cents' => 200,
        ]);

        $orderLow = $this->makePaidProductOrder(['paid_at' => '2026-08-06 10:00:00']);
        OrderItem::create([
            'order_id' => $orderLow->id,
            'product_id' => $lowProduct->id,
            'product_title' => $lowProduct->title,
            'quantity' => 1,
            'unit_price_cents' => 1000,
            'line_total_cents' => 1000,
            'unit_cost_cents' => 100,
        ]);

        // Outside the filter range — must be excluded entirely.
        $orderOutside = $this->makePaidProductOrder(['paid_at' => '2026-07-01 10:00:00']);
        OrderItem::create([
            'order_id' => $orderOutside->id,
            'product_id' => $topProduct->id,
            'product_title' => $topProduct->title,
            'quantity' => 100,
            'unit_price_cents' => 1000,
            'line_total_cents' => 100000,
            'unit_cost_cents' => 200,
        ]);

        $result = $this->query()->run($this->filterFor('2026-08-01', '2026-08-10'));

        $this->assertCount(2, $result);
        $this->assertSame($topProduct->id, $result[0]['product_id']);
        $this->assertSame(5000, $result[0]['revenue_cents']);
        $this->assertSame($lowProduct->id, $result[1]['product_id']);
    }
}
