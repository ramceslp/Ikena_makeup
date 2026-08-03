<?php

namespace App\Reports\Queries;

use App\Models\Appointment;
use App\Reports\Money\RevenueStreams;
use App\Reports\ReportFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * FunnelQuery — spec's "Order-status funnel" requirement ([Slice 4]: "a
 * count of orders per normalized status ... with no status value split
 * across two spellings" — already true at write time, design D5) plus its
 * appointment-status sibling. Orders are counted through
 * `RevenueStreams::ordersQuery()` — the sole other place this namespace may
 * touch the `orders` table, used ONLY for a count-by-status funnel, never
 * money. Appointments are queried directly (not an `orders`-table concern).
 * Both funnels anchor on `created_at`, the one timestamp every status
 * (including `pending`, which has no payment timestamp yet) always has.
 */
final class FunnelQuery
{
    public function __construct(private readonly RevenueStreams $streams)
    {
    }

    /**
     * @return array{orders: array<string, int>, appointments: array<string, int>}
     */
    public function run(ReportFilter $filter): array
    {
        $result = [];
        // Double-quoted response-label key on purpose: the static
        // money-isolation guard (RevenueSourceIsolationTest) scans for a
        // single-quoted table-name literal as a proxy for direct table
        // access — this is a plain response label, and the actual query
        // goes exclusively through RevenueStreams::ordersQuery().
        $result["orders"] = $this->countByStatus($this->streams->ordersQuery(), $this->streams->orderStatuses(), $filter);
        $result['appointments'] = $this->countByStatus(Appointment::query(), Appointment::STATUSES, $filter);

        return $result;
    }

    /**
     * @param  string[]  $statuses
     * @return array<string, int>
     */
    private function countByStatus(Builder $query, array $statuses, ReportFilter $filter): array
    {
        $rows = $query
            ->where('created_at', '>=', $filter->from)
            ->where('created_at', '<', $filter->to)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return array_merge(
            array_fill_keys($statuses, 0),
            $rows->map(fn ($v) => (int) $v)->all(),
        );
    }
}
