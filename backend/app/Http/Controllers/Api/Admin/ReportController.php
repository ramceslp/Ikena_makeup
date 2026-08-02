<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Reports\Money\RevenueStreams;
use App\Reports\PeriodCalendar;
use App\Reports\Queries\CompositionQuery;
use App\Reports\Queries\SummaryQuery;
use App\Reports\Queries\TimelineQuery;
use App\Reports\ReportFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ReportController — thin (design's File Changes table: "delegates to Query
 * Objects"). Every action follows the same three steps: validate the
 * request into a `ReportFilter` (which resolves the effective granularity
 * through `PeriodCalendar` — adjustment #1, NEVER a 422 for a wide range),
 * run the matching query object, and merge the filter's own metadata
 * (`requested_granularity`/`effective_granularity`/`degraded`) into every
 * response so the frontend can render whatever granularity actually came
 * back instead of assuming it matches the request.
 *
 * Registered inside the EXISTING `Route::middleware('admin')` group
 * (routes/api.php:181) — `auth:sanctum` (outer group) + `EnsureUserIsAdmin`
 * apply automatically; no route-specific auth wiring needed here.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly PeriodCalendar $calendar,
        private readonly RevenueStreams $streams,
    ) {
    }

    /**
     * GET /api/admin/reports/summary
     *
     * KPI summary: confirmed revenue (by stream + total), retained
     * deposits, free-course enrollment count (spec `admin-reporting`).
     */
    public function summary(Request $request): JsonResponse
    {
        $filter = ReportFilter::fromRequest($request, $this->calendar);
        $data = (new SummaryQuery($this->streams))->run($filter);

        return response()->json([
            'data' => array_merge($this->filterMeta($filter), $data),
        ]);
    }

    /**
     * GET /api/admin/reports/timeline
     *
     * Revenue timeline, zero-filled across every period, grouped on each
     * stream's own time anchor — never created_at.
     */
    public function timeline(Request $request): JsonResponse
    {
        $filter = ReportFilter::fromRequest($request, $this->calendar);
        $periods = (new TimelineQuery($this->streams))->run($filter);

        return response()->json([
            'data' => array_merge($this->filterMeta($filter), ['periods' => $periods]),
        ]);
    }

    /**
     * GET /api/admin/reports/composition
     *
     * Confirmed revenue broken down by order type (service/product/course)
     * plus retained deposits.
     */
    public function composition(Request $request): JsonResponse
    {
        $filter = ReportFilter::fromRequest($request, $this->calendar);
        $data = (new CompositionQuery($this->streams))->run($filter);

        return response()->json([
            'data' => array_merge($this->filterMeta($filter), $data),
        ]);
    }

    /**
     * Metadata every report response carries so the frontend can react to
     * degradation (adjustment #1) without re-deriving it client-side.
     *
     * @return array{
     *     from: string,
     *     to: string,
     *     requested_granularity: string,
     *     effective_granularity: string,
     *     degraded: bool,
     * }
     */
    private function filterMeta(ReportFilter $filter): array
    {
        return [
            'from' => $filter->from->toDateString(),
            // Converted back to the inclusive calendar date the caller sent
            // (ReportFilter stores the exclusive half-open bound internally
            // — see its docblock).
            'to' => $filter->to->subDay()->toDateString(),
            'requested_granularity' => $filter->requestedGranularity->value,
            'effective_granularity' => $filter->effectiveGranularity->value,
            'degraded' => $filter->degraded,
        ];
    }
}
