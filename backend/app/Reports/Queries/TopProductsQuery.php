<?php

namespace App\Reports\Queries;

use App\Models\Product;
use App\Reports\Money\RevenueStreams;
use App\Reports\ReportFilter;

/**
 * TopProductsQuery — spec's "Top products ranking with margin" requirement
 * ([Slice 4]): ranks products by revenue and computes margin from
 * `order_items.unit_cost_cents` — the snapshot taken at sale time
 * (`CartCheckoutAction`), never the live, mutable `products.cost`.
 *
 * Coverage indicator: `unit_cost_cents` is admin-entered manual data whose
 * column default is 0 — indistinguishable at the DB level from a genuinely
 * free product. Rather than silently reporting margin as if every 0-cost
 * line really cost nothing (which would overstate margin), only lines with
 * `unit_cost_cents > 0` contribute to `margin_cents`; `known_cost_revenue_cents`
 * reports how much of `revenue_cents` that margin figure actually covers.
 */
final class TopProductsQuery
{
    public function __construct(private readonly RevenueStreams $streams)
    {
    }

    /**
     * @return array<int, array{
     *     product_id: int,
     *     title: string,
     *     quantity: int,
     *     revenue_cents: int,
     *     known_cost_revenue_cents: int,
     *     margin_cents: int,
     * }>
     */
    public function run(ReportFilter $filter): array
    {
        $rows = $this->streams->productSaleItemsQuery()
            ->where('orders.paid_at', '>=', $filter->from)
            ->where('orders.paid_at', '<', $filter->to)
            ->selectRaw(
                'order_items.product_id as product_id, '.
                'SUM(order_items.quantity) as quantity, '.
                'SUM(order_items.unit_price_cents * order_items.quantity) as revenue_cents, '.
                'SUM(CASE WHEN order_items.unit_cost_cents > 0 THEN order_items.unit_price_cents * order_items.quantity ELSE 0 END) as known_cost_revenue_cents, '.
                'SUM(CASE WHEN order_items.unit_cost_cents > 0 THEN (order_items.unit_price_cents - order_items.unit_cost_cents) * order_items.quantity ELSE 0 END) as margin_cents'
            )
            ->groupBy('order_items.product_id')
            ->orderByDesc('revenue_cents')
            ->get();

        $titles = Product::query()->whereIn('id', $rows->pluck('product_id'))->pluck('title', 'id');

        return $rows->map(fn ($row) => [
            'product_id' => (int) $row->product_id,
            'title' => $titles->get((int) $row->product_id, 'Producto eliminado'),
            'quantity' => (int) $row->quantity,
            'revenue_cents' => (int) $row->revenue_cents,
            'known_cost_revenue_cents' => (int) $row->known_cost_revenue_cents,
            'margin_cents' => (int) $row->margin_cents,
        ])->all();
    }
}
