<?php

namespace App\Reports\Queries;

use App\Reports\Money\RevenueStreams;
use App\Reports\Money\StreamKey;
use App\Reports\ReportFilter;

/**
 * TimelineQuery — the revenue-timeline requirement (spec `admin-reporting`):
 * groups by each stream's own time anchor (`RevenueStreams::anchorColumn()`
 * — `paid_at`/`deposit_collected_at`/`settled_at`), NEVER `created_at`, and
 * is zero-filled across every period `PeriodCalendar` produced, even ones
 * with no activity.
 *
 * Emits exactly ONE query per stream (5 total), each with one ANSI
 * `SUM(CASE WHEN anchor >= ? AND anchor < ? THEN amount ELSE 0 END)`
 * projection PER period — design D3's whole point: this is the only SQL
 * shape a timeline needs, and it is identical on SQLite and MySQL, so there
 * is no `DATE_FORMAT`/`strftime` divergence left to test for. Bind values
 * are `CarbonImmutable` instances; Laravel's
 * `Connection::prepareBindings()` formats any `DateTimeInterface` binding
 * through the grammar's date format before it reaches PDO, so this needs no
 * manual string conversion.
 */
final class TimelineQuery
{
    public function __construct(private readonly RevenueStreams $streams)
    {
    }

    /**
     * @return array<int, array{
     *     label: string,
     *     from: string,
     *     to: string,
     *     total_cents: int,
     *     by_stream: array<string, int>,
     * }>
     */
    public function run(ReportFilter $filter): array
    {
        $buckets = [];

        foreach ($filter->periods as $index => $period) {
            $buckets[$index] = [
                'label' => $period->label,
                'from' => $period->from->toIso8601String(),
                'to' => $period->to->toIso8601String(),
                'total_cents' => 0,
                'by_stream' => array_fill_keys(
                    array_map(fn (StreamKey $s) => $s->value, StreamKey::cases()),
                    0,
                ),
            ];
        }

        if (empty($filter->periods)) {
            return [];
        }

        foreach (StreamKey::cases() as $stream) {
            $this->addStreamTotals($stream, $filter, $buckets);
        }

        return array_values($buckets);
    }

    /**
     * @param  array<int, array{label: string, from: string, to: string, total_cents: int, by_stream: array<string,int>}>  $buckets
     */
    private function addStreamTotals(StreamKey $stream, ReportFilter $filter, array &$buckets): void
    {
        $anchorColumn = $this->streams->anchorColumn($stream);
        $amountColumn = $this->streams->amountColumn($stream);

        $selects = [];
        $bindings = [];

        foreach ($filter->periods as $index => $period) {
            $selects[] = "SUM(CASE WHEN {$anchorColumn} >= ? AND {$anchorColumn} < ? THEN {$amountColumn} ELSE 0 END) AS period_{$index}";
            $bindings[] = $period->from;
            $bindings[] = $period->to;
        }

        $row = $this->streams->query($stream)
            ->selectRaw(implode(', ', $selects), $bindings)
            ->first();

        foreach ($filter->periods as $index => $period) {
            $value = (int) ($row->{"period_{$index}"} ?? 0);
            $buckets[$index]['by_stream'][$stream->value] = $value;
            $buckets[$index]['total_cents'] += $value;
        }
    }
}
