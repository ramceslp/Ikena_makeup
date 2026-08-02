<?php

namespace App\Reports;

/**
 * The outcome of `PeriodCalendar::build()`: the EFFECTIVE granularity
 * actually used — which may differ from the one requested (adjustment #1,
 * architecture/admin-reports-design-adjustments) — whether degradation (or
 * range-clipping) happened, and the resulting zero-filled `Period[]` list.
 *
 * `ReportController` MUST surface `effectiveGranularity` and `degraded` in
 * every timeline response: returning the requested granularity instead
 * would make the frontend render, say, daily x-axis labels over data that
 * is actually bucketed weekly.
 */
final readonly class PeriodCalendarResult
{
    /**
     * @param  Period[]  $periods
     */
    public function __construct(
        public Granularity $requestedGranularity,
        public Granularity $effectiveGranularity,
        public bool $degraded,
        public array $periods,
    ) {
    }
}
