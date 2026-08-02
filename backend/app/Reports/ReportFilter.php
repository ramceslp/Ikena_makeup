<?php

namespace App\Reports;

use App\Reports\Money\StreamKey;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Validated DTO for the report endpoints (Summary, Timeline, Composition,
 * and — as of PR3 — Ledger). Wraps `PeriodCalendar` directly at
 * construction time so a caller can never observe a filter whose
 * `effectiveGranularity` and `periods` disagree with each other.
 *
 * `to` is accepted from the request as an INCLUSIVE calendar date (the last
 * day the caller wants included — matching how every other admin filter in
 * this codebase already reads, see `Admin\AppointmentController::index`'s
 * `date_to`) and converted here to the exclusive half-open bound
 * `PeriodCalendar`/every query in this namespace expects. Half-open bounds
 * exist precisely so downstream code never has to reason about this
 * conversion again (design D3).
 *
 * `streams`/`perPage` are `LedgerQuery`-only concerns (design's
 * Interfaces/Contracts sketch), added in PR3. `search` from that same
 * sketch is deliberately still omitted — no spec scenario requires it, and
 * adding unused surface area is not worth the extra validation/plumbing.
 */
final readonly class ReportFilter
{
    /**
     * @param  Period[]  $periods
     * @param  StreamKey[]  $streams
     */
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public Granularity $requestedGranularity,
        public Granularity $effectiveGranularity,
        public bool $degraded,
        public array $periods,
        public array $streams = [],
        public int $perPage = 25,
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
            'stream' => ['nullable', 'array'],
            'stream.*' => ['string', Rule::in(array_map(
                fn (StreamKey $s) => $s->value,
                StreamKey::cases(),
            ))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $from = CarbonImmutable::parse($validated['from'])->startOfDay();
        // Inclusive request date → exclusive half-open bound (see class docblock).
        $to = CarbonImmutable::parse($validated['to'])->startOfDay()->addDay();

        $requested = isset($validated['granularity'])
            ? Granularity::from($validated['granularity'])
            : Granularity::Day;

        $result = $calendar->build($from, $to, $requested);

        $streams = array_map(
            fn (string $value) => StreamKey::from($value),
            $validated['stream'] ?? [],
        );

        return new self(
            from: $from,
            to: $to,
            requestedGranularity: $result->requestedGranularity,
            effectiveGranularity: $result->effectiveGranularity,
            degraded: $result->degraded,
            periods: $result->periods,
            streams: $streams,
            perPage: $validated['per_page'] ?? 25,
        );
    }
}
