<?php

namespace App\Reports\Queries;

use App\Models\Service;
use App\Reports\Money\RevenueStreams;
use App\Reports\Money\StreamKey;
use App\Reports\ReportFilter;

/**
 * TopServicesQuery — spec's "Revenue-per-hour for services" requirement
 * ([Slice 4]): ranks services by DELIVERED revenue (deposit + settlement
 * streams — the same "delivered" definition `CompositionQuery` uses;
 * retained deposits are excluded) divided by `services.duration_hours`.
 * Services have no cost basis (`RevenueStreams` never snapshots one), so
 * this ranking carries no margin figure at all — unlike `TopProductsQuery`.
 */
final class TopServicesQuery
{
    private const DELIVERED_STREAMS = [
        StreamKey::AppointmentDeposit,
        StreamKey::AppointmentSettlement,
    ];

    public function __construct(private readonly RevenueStreams $streams)
    {
    }

    /**
     * @return array<int, array{
     *     service_id: int,
     *     title: string,
     *     revenue_cents: int,
     *     duration_hours: int,
     *     revenue_per_hour_cents: int,
     * }>
     */
    public function run(ReportFilter $filter): array
    {
        $revenueByService = [];

        foreach (self::DELIVERED_STREAMS as $stream) {
            $anchor = $this->streams->anchorColumn($stream);
            $amount = $this->streams->amountColumn($stream);

            $rows = $this->streams->query($stream)
                ->where($anchor, '>=', $filter->from)
                ->where($anchor, '<', $filter->to)
                ->selectRaw("service_id, SUM({$amount}) as amount_cents")
                ->groupBy('service_id')
                ->get();

            foreach ($rows as $row) {
                $serviceId = (int) $row->service_id;
                $revenueByService[$serviceId] = ($revenueByService[$serviceId] ?? 0) + (int) $row->amount_cents;
            }
        }

        if (empty($revenueByService)) {
            return [];
        }

        $services = Service::query()
            ->whereIn('id', array_keys($revenueByService))
            ->get(['id', 'title', 'duration_hours'])
            ->keyBy('id');

        $ranking = [];
        foreach ($revenueByService as $serviceId => $revenueCents) {
            $service = $services->get($serviceId);
            $durationHours = $service?->duration_hours ?? 1;

            $ranking[] = [
                'service_id' => $serviceId,
                'title' => $service?->title ?? 'Servicio eliminado',
                'revenue_cents' => $revenueCents,
                'duration_hours' => (int) $durationHours,
                'revenue_per_hour_cents' => intdiv($revenueCents, max(1, (int) $durationHours)),
            ];
        }

        usort($ranking, fn ($a, $b) => $b['revenue_per_hour_cents'] <=> $a['revenue_per_hour_cents']);

        return $ranking;
    }
}
