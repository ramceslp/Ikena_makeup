<?php

namespace App\Reports\Queries;

use App\Reports\Money\RevenueStreams;
use App\Reports\Money\StreamKey;
use App\Reports\ReportFilter;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

/**
 * LedgerQuery — the sales-ledger requirement (spec `admin-reporting`): a
 * paginated, filterable list of every confirmed money row across the five
 * revenue streams (design D4), UNION ALL'd into one uniform row shape
 * (design's Interfaces/Contracts sketch: occurred_at | amount_cents |
 * stream | counterparty | label). This is the only query object that needs
 * UNION ALL — Summary/Timeline/Composition all aggregate per-stream sums; a
 * ledger is inherently a cross-source ROW list.
 *
 * Goes through `RevenueStreams::query()` exclusively for every stream, same
 * as every other query object in this namespace — never references the
 * Order model or its table directly itself, so `RevenueSourceIsolationTest`'s
 * static guard passes unchanged.
 *
 * `run()` and `cursor()` share `buildUnion()` so the paginated on-screen
 * ledger and the CSV export (`Export\LedgerCsvStream`) can never diverge in
 * which rows, or which order, they return for an identical filter (spec's
 * "CSV export matches on-screen ledger" parity requirement) — `cursor()`
 * simply omits the LIMIT/OFFSET a page applies.
 */
final class LedgerQuery
{
    /**
     * Human-readable Spanish label per stream (UI copy is Spanish — the
     * project convention; code/identifiers stay English). Kept here rather
     * than on `StreamKey` because it is presentation, not domain, and only
     * the ledger/export need it.
     */
    private const LABELS = [
        StreamKey::CourseSale->value => 'Venta de curso',
        StreamKey::ProductSale->value => 'Venta de producto',
        StreamKey::AppointmentDeposit->value => 'Anticipo de cita',
        StreamKey::AppointmentDepositRetained->value => 'Anticipo retenido',
        StreamKey::AppointmentSettlement->value => 'Liquidación de cita',
    ];

    public function __construct(private readonly RevenueStreams $streams)
    {
    }

    public function run(ReportFilter $filter): LengthAwarePaginator
    {
        return $this->buildUnion($filter)->paginate($filter->perPage);
    }

    /**
     * @return LazyCollection<int, object{occurred_at: string, amount_cents: int, stream: string, label: string, counterparty: ?string}>
     */
    public function cursor(ReportFilter $filter): LazyCollection
    {
        return $this->buildUnion($filter)->cursor();
    }

    private function buildUnion(ReportFilter $filter): QueryBuilder
    {
        $streams = empty($filter->streams) ? StreamKey::cases() : $filter->streams;

        $queries = array_map(
            fn (StreamKey $stream) => $this->streamQuery($stream, $filter),
            $streams,
        );

        $union = array_shift($queries);

        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        // Ordering belongs on the OUTER union, not on each branch — a
        // per-branch order only sorts within that branch, not across the
        // merged cross-source result.
        return $union->orderByDesc('occurred_at');
    }

    private function streamQuery(StreamKey $stream, ReportFilter $filter): QueryBuilder
    {
        $anchor = $this->streams->anchorColumn($stream);
        $amount = $this->streams->amountColumn($stream);

        return $this->streams->query($stream)
            ->where($anchor, '>=', $filter->from)
            ->where($anchor, '<', $filter->to)
            // Unqualified `user_id` resolves against the base table (Order
            // or Appointment) — `users` carries no column of that name, so
            // this join cannot make it ambiguous.
            ->leftJoin('users', 'user_id', '=', 'users.id')
            ->selectRaw(
                "{$anchor} as occurred_at, {$amount} as amount_cents, ? as stream, ? as label, users.name as counterparty",
                [$stream->value, self::LABELS[$stream->value]],
            )
            ->toBase();
    }
}
