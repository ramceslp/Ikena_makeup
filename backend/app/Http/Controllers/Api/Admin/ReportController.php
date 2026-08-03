<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Reports\Export\LedgerCsvStream;
use App\Reports\Money\RevenueStreams;
use App\Reports\PeriodCalendar;
use App\Reports\Queries\CompositionQuery;
use App\Reports\Queries\FunnelQuery;
use App\Reports\Queries\LedgerQuery;
use App\Reports\Queries\ReceivablesQuery;
use App\Reports\Queries\SummaryQuery;
use App\Reports\Queries\TimelineQuery;
use App\Reports\Queries\TopCoursesQuery;
use App\Reports\Queries\TopProductsQuery;
use App\Reports\Queries\TopServicesQuery;
use App\Reports\ReportFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     * GET /api/admin/reports/ledger
     *
     * Paginated, filterable (date range, stream) cross-source row list
     * (spec `admin-reporting`'s "Sales ledger" requirement). Rows are
     * mapped to an explicit key list rather than returned as raw stdClass
     * rows, so callers get stable JSON keys regardless of how a given
     * driver stringifies an aggregate column.
     */
    public function ledger(Request $request): JsonResponse
    {
        $filter = ReportFilter::fromRequest($request, $this->calendar);
        $paginator = (new LedgerQuery($this->streams))->run($filter);

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($row) => [
                'occurred_at' => (string) $row->occurred_at,
                'amount_cents' => (int) $row->amount_cents,
                'stream' => $row->stream,
                'label' => $row->label,
                'counterparty' => $row->counterparty,
            ])->all(),
            'meta' => array_merge($this->filterMeta($filter), [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ]),
        ]);
    }

    /**
     * GET /api/admin/reports/ledger/export
     *
     * Streamed CSV export of EVERY row matching the filter (not just the
     * current page) — spec `report-export`'s "CSV export matches on-screen
     * ledger" requirement. `from`/`to` are required by `ReportFilter`
     * itself, which satisfies the "mandatory date range on export" design
     * constraint without any export-specific validation here.
     */
    public function ledgerExport(Request $request): StreamedResponse
    {
        $filter = ReportFilter::fromRequest($request, $this->calendar);

        return (new LedgerCsvStream(new LedgerQuery($this->streams)))->respond($filter);
    }

    /**
     * GET /api/admin/reports/rankings/products
     *
     * Top products by revenue, with margin computed from the
     * `unit_cost_cents` snapshot and a coverage indicator (PR4a scope).
     */
    public function topProducts(Request $request): JsonResponse
    {
        $filter = ReportFilter::fromRequest($request, $this->calendar);
        $data = (new TopProductsQuery($this->streams))->run($filter);

        return response()->json(['data' => $data, 'meta' => $this->filterMeta($filter)]);
    }

    /**
     * GET /api/admin/reports/rankings/services
     *
     * Top services by delivered revenue divided by duration_hours — no
     * margin figure (services have no cost basis).
     */
    public function topServices(Request $request): JsonResponse
    {
        $filter = ReportFilter::fromRequest($request, $this->calendar);
        $data = (new TopServicesQuery($this->streams))->run($filter);

        return response()->json(['data' => $data, 'meta' => $this->filterMeta($filter)]);
    }

    /**
     * GET /api/admin/reports/rankings/courses
     *
     * Top courses by paid enrollment revenue, with free enrollments counted
     * apart from the revenue figure.
     */
    public function topCourses(Request $request): JsonResponse
    {
        $filter = ReportFilter::fromRequest($request, $this->calendar);
        $data = (new TopCoursesQuery($this->streams))->run($filter);

        return response()->json(['data' => $data, 'meta' => $this->filterMeta($filter)]);
    }

    /**
     * GET /api/admin/reports/funnel
     *
     * Order-status and appointment-status counts for the selected range.
     */
    public function funnel(Request $request): JsonResponse
    {
        $filter = ReportFilter::fromRequest($request, $this->calendar);
        $data = (new FunnelQuery($this->streams))->run($filter);

        return response()->json(['data' => $data, 'meta' => $this->filterMeta($filter)]);
    }

    /**
     * GET /api/admin/reports/receivables
     *
     * Current outstanding money across the three receivable buckets
     * (design D6) — a snapshot of "now", not scoped to a date range, so it
     * takes no ReportFilter.
     */
    public function receivables(): JsonResponse
    {
        return response()->json(['data' => (new ReceivablesQuery())->run()]);
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
