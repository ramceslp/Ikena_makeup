<?php

namespace App\Reports\Queries;

use App\Models\Enrollment;
use App\Reports\Money\RevenueStreams;
use App\Reports\Money\StreamKey;
use App\Reports\ReportFilter;

/**
 * SummaryQuery — the KPI summary requirement (spec `admin-reporting`):
 * confirmed revenue, retained deposits reported as a SEPARATE line (never
 * merged into delivered-service revenue — spec's "Retained deposits on
 * cancellation" requirement), and free-course enrollments counted apart
 * from every revenue figure.
 *
 * Runs one cheap `SUM` per stream (5 queries) and merges in PHP — design
 * D4: "5 cheap SUMs, not row-by-row grouping." No date-SQL branching: every
 * bound comes from `ReportFilter::$from`/`$to`, already resolved by
 * `PeriodCalendar` (design D3).
 *
 * DEVIATION from the design's Interfaces/Contracts sketch
 * (`receivable_cents`/`projection_cents`): those fields belong to the
 * receivable-bucket computation, which is explicitly PR4a scope (out of
 * scope for this PR per the PR2a apply instructions) — reading
 * `Appointment::scopeEndedBefore()` is a query object of its own with its
 * own tests, not a one-line addition here. Omitted rather than stubbed with
 * a fake value.
 */
final class SummaryQuery
{
    /**
     * The streams that make up DELIVERED, non-retained confirmed revenue.
     * `AppointmentDepositRetained` is deliberately excluded — it is reported
     * as its own line (`retained_deposits_cents`), never summed into this
     * one.
     */
    private const CONFIRMED_REVENUE_STREAMS = [
        StreamKey::CourseSale,
        StreamKey::ProductSale,
        StreamKey::AppointmentDeposit,
        StreamKey::AppointmentSettlement,
    ];

    public function __construct(private readonly RevenueStreams $streams)
    {
    }

    /**
     * @return array{
     *     by_stream: array<string, int>,
     *     confirmed_revenue_cents: int,
     *     retained_deposits_cents: int,
     *     orders_count: int,
     *     free_enrollments_count: int,
     * }
     */
    public function run(ReportFilter $filter): array
    {
        $byStream = [];

        foreach (StreamKey::cases() as $stream) {
            $byStream[$stream->value] = $this->streamTotal($stream, $filter);
        }

        $confirmedRevenueCents = 0;
        foreach (self::CONFIRMED_REVENUE_STREAMS as $stream) {
            $confirmedRevenueCents += $byStream[$stream->value];
        }

        return [
            'by_stream' => $byStream,
            'confirmed_revenue_cents' => $confirmedRevenueCents,
            'retained_deposits_cents' => $byStream[StreamKey::AppointmentDepositRetained->value],
            'orders_count' => $this->ordersCount($filter),
            'free_enrollments_count' => $this->freeEnrollmentsCount($filter),
        ];
    }

    private function streamTotal(StreamKey $stream, ReportFilter $filter): int
    {
        $anchorColumn = $this->streams->anchorColumn($stream);
        $amountColumn = $this->streams->amountColumn($stream);

        return (int) $this->streams->query($stream)
            ->where($anchorColumn, '>=', $filter->from)
            ->where($anchorColumn, '<', $filter->to)
            ->sum($amountColumn);
    }

    /**
     * `orders` rows only — the two streams that actually enumerate
     * `orders` (course_sale, product_sale). Appointment money never counts
     * toward this (design D4: appointment orders are structurally
     * invisible to the money layer).
     */
    private function ordersCount(ReportFilter $filter): int
    {
        $count = 0;

        foreach ([StreamKey::CourseSale, StreamKey::ProductSale] as $stream) {
            $anchorColumn = $this->streams->anchorColumn($stream);

            $count += $this->streams->query($stream)
                ->where($anchorColumn, '>=', $filter->from)
                ->where($anchorColumn, '<', $filter->to)
                ->count();
        }

        return $count;
    }

    /**
     * Free enrollments (`price_paid = 0`, no linked order — CourseController
     * ::enroll's free path never creates one) anchored on `created_at`: they
     * are not part of any money stream, so there is no other timestamp to
     * anchor them on.
     */
    private function freeEnrollmentsCount(ReportFilter $filter): int
    {
        return Enrollment::query()
            ->where('price_paid', 0)
            ->where('created_at', '>=', $filter->from)
            ->where('created_at', '<', $filter->to)
            ->count();
    }
}
