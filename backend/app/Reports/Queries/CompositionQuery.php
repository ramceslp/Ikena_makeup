<?php

namespace App\Reports\Queries;

use App\Reports\Money\RevenueStreams;
use App\Reports\Money\StreamKey;
use App\Reports\ReportFilter;

/**
 * CompositionQuery — the "Composition by order type" requirement (spec
 * `admin-reporting`): breaks confirmed revenue down by order type
 * (service, product, course) for the selected range.
 *
 * "service" merges the two DELIVERED appointment streams
 * (`appointment_deposit` + `appointment_settlement` — both exclude
 * cancelled appointments already, see `RevenueStreams`); retained deposits
 * are reported on their own line, exactly as `SummaryQuery` does, so the
 * two endpoints agree on what "delivered" means.
 *
 * `total_cents` implements the spec's literal reconciliation invariant:
 * "the three type totals plus retained deposits MUST sum to total
 * [...] with no double count" — i.e. `total_cents` is the grand total of
 * every cent RevenueStreams enumerates for the period (all 5 streams), NOT
 * the same figure as `SummaryQuery::run()['confirmed_revenue_cents']`
 * (which deliberately excludes retained deposits, per the "Retained
 * deposits on cancellation" requirement: retained money must never be
 * merged into delivered-service revenue). The two figures measure
 * different things by design; do not conflate them.
 */
final class CompositionQuery
{
    /**
     * @var array<string, StreamKey[]>
     */
    private const TYPE_STREAMS = [
        'course' => [StreamKey::CourseSale],
        'product' => [StreamKey::ProductSale],
        'service' => [StreamKey::AppointmentDeposit, StreamKey::AppointmentSettlement],
    ];

    public function __construct(private readonly RevenueStreams $streams)
    {
    }

    /**
     * @return array{
     *     by_type: array{course: int, product: int, service: int},
     *     retained_deposits_cents: int,
     *     total_cents: int,
     * }
     */
    public function run(ReportFilter $filter): array
    {
        $byType = [];

        foreach (self::TYPE_STREAMS as $type => $streams) {
            $byType[$type] = 0;

            foreach ($streams as $stream) {
                $byType[$type] += $this->streamTotal($stream, $filter);
            }
        }

        $retainedDepositsCents = $this->streamTotal(StreamKey::AppointmentDepositRetained, $filter);

        return [
            'by_type' => $byType,
            'retained_deposits_cents' => $retainedDepositsCents,
            'total_cents' => array_sum($byType) + $retainedDepositsCents,
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
}
