<?php

namespace App\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Validated DTO for the date-range/granularity report endpoints (Summary,
 * Timeline, Composition — design D4/D7 data flow). Wraps `PeriodCalendar`
 * directly at construction time so a caller can never observe a filter
 * whose `effectiveGranularity` and `periods` disagree with each other.
 *
 * `to` is accepted from the request as an INCLUSIVE calendar date (the last
 * day the caller wants included — matching how every other admin filter in
 * this codebase already reads, see `Admin\AppointmentController::index`'s
 * `date_to`) and converted here to the exclusive half-open bound
 * `PeriodCalendar`/every query in this namespace expects. Half-open bounds
 * exist precisely so downstream code never has to reason about this
 * conversion again (design D3).
 *
 * `streams`/`search`/`perPage` from the design's Interfaces/Contracts
 * section are intentionally NOT part of this DTO yet — they are
 * `LedgerQuery`-only concerns that ship in PR3. Adding them now, unused,
 * would be dead surface area on a class the Summary/Timeline/Composition
 * endpoints already fully exercise without them.
 */
final readonly class ReportFilter
{
    /**
     * @param  Period[]  $periods
     */
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public Granularity $requestedGranularity,
        public Granularity $effectiveGranularity,
        public bool $degraded,
        public array $periods,
    ) {
    }

    public static function fromRequest(Request $request, PeriodCalendar $calendar): self
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'granularity' => ['nullable', 'string', Rule::in(array_map(
                fn (Granularity $g) => $g->value,
                Granularity::cases(),
            ))],
        ]);

        $from = CarbonImmutable::parse($validated['from'])->startOfDay();
        // Inclusive request date → exclusive half-open bound (see class docblock).
        $to = CarbonImmutable::parse($validated['to'])->startOfDay()->addDay();

        $requested = isset($validated['granularity'])
            ? Granularity::from($validated['granularity'])
            : Granularity::Day;

        $result = $calendar->build($from, $to, $requested);

        return new self(
            from: $from,
            to: $to,
            requestedGranularity: $result->requestedGranularity,
            effectiveGranularity: $result->effectiveGranularity,
            degraded: $result->degraded,
            periods: $result->periods,
        );
    }
}
