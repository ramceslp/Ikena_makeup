<?php

namespace App\Reports\Queries;

use App\Models\Appointment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * ReceivablesQuery — spec's three receivable-bucket requirements ([Slice 4])
 * and design D6's bucket table. Receivables are CURRENT outstanding money,
 * not scoped to a report date range — every predicate here is relative to
 * `$now` (defaults to `now()`), not a `ReportFilter`.
 *
 * Every unsettled (`settled_at IS NULL`), non-cancelled appointment falls
 * into exactly one bucket (spec: "buckets are mutually exclusive and
 * complete"):
 *   A · deposit pending   — `deposit_collected_at IS NULL`, not yet ended
 *   B · balance scheduled — deposit collected, not yet ended
 *   C · balance overdue   — `scheduled_end_time` + 24h grace has passed
 *     (`Appointment::scopeEndedBefore`), REGARDLESS of deposit state
 *
 * Reads `appointments` only — zero joins to the orders table (a dividend of
 * design D1's write-once money channels living directly on the appointment
 * row).
 */
final class ReceivablesQuery
{
    /**
     * @return array{
     *     bucket_a: array{count: int, outstanding_cents: int},
     *     bucket_b: array{count: int, outstanding_cents: int},
     *     bucket_c: array{count: int, outstanding_cents: int},
     *     total_receivable_cents: int,
     *     projection_cents: int,
     * }
     */
    public function run(?Carbon $now = null): array
    {
        $now = $now ?? now();
        $cutoff = $now->copy()->subDay();

        $unsettled = Appointment::query()
            ->whereNull('settled_at')
            ->where('status', '!=', 'cancelled');

        $overdueIds = (clone $unsettled)->endedBefore($cutoff)->pluck('id');

        $bucketC = Appointment::query()->whereIn('id', $overdueIds);
        $notEnded = (clone $unsettled)->whereNotIn('id', $overdueIds);

        $a = $this->summarize((clone $notEnded)->whereNull('deposit_collected_at'), full: true);
        $b = $this->summarize((clone $notEnded)->whereNotNull('deposit_collected_at'), full: false);
        $c = $this->summarize($bucketC, full: false);

        return [
            'bucket_a' => $a,
            'bucket_b' => $b,
            'bucket_c' => $c,
            'total_receivable_cents' => $a['outstanding_cents'] + $b['outstanding_cents'] + $c['outstanding_cents'],
            'projection_cents' => $b['outstanding_cents'] + $c['outstanding_cents'],
        ];
    }

    /**
     * @return array{count: int, outstanding_cents: int}
     */
    private function summarize(Builder $query, bool $full): array
    {
        $expression = $full
            ? 'service_price_cents'
            : 'CASE WHEN service_price_cents > deposit_collected_cents THEN service_price_cents - deposit_collected_cents ELSE 0 END';

        $row = $query->selectRaw("COUNT(*) as count, COALESCE(SUM({$expression}), 0) as outstanding_cents")->first();

        return [
            'count' => (int) $row->count,
            'outstanding_cents' => (int) $row->outstanding_cents,
        ];
    }
}
