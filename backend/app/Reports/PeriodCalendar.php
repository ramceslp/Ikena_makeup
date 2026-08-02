<?php

namespace App\Reports;

use Carbon\CarbonImmutable;

/**
 * Builds the list of report periods entirely in PHP (Carbon), so no report
 * query anywhere in this codebase ever needs a driver-specific date-grouping
 * expression (design D3): MySQL's `DATE_FORMAT` and SQLite's `strftime`
 * disagree on ISO week numbering (`%v`/`%x` vs `%W`), and that divergence is
 * exactly the class of bug this eliminates by construction. `PeriodCalendar`
 * computes every boundary once, in PHP, and every downstream query just
 * emits one ANSI-portable aggregate per period:
 * `SUM(CASE WHEN occurred_at >= ? AND occurred_at < ? THEN amount_cents ELSE 0 END)`.
 *
 * TIMEZONE (adjustment #5, architecture/admin-reports-design-adjustments):
 * period boundaries are computed in the APPLICATION timezone
 * (`config('app.timezone')` = `America/Guayaquil`, pinned equal to
 * `config('booking.timezone')` by `ConfigTimezoneTest`), NOT UTC. This is
 * correct, not a shortcut, for two independent reasons:
 *
 *  1. Every timestamp this class buckets (`orders.paid_at`,
 *     `appointments.deposit_collected_at`/`settled_at`) is written by
 *     `now()`, which Laravel resolves through `app.timezone` — the stored
 *     value IS venue-local wall-clock time, not UTC. Grouping in the same
 *     timezone the data was written in is simply comparing like with like;
 *     bucketing in UTC would silently shift late-in-the-day transactions
 *     into the following day's bucket.
 *  2. Ecuador observes NO daylight saving time
 *     (`ConfigTimezoneTest::test_venue_timezone_has_no_dst_transitions`), so
 *     there is no ambiguous or skipped hour for a period boundary to land
 *     on — the usual argument against storing/bucketing local time (DST
 *     jumps) simply does not apply here.
 *
 * DO NOT "fix" this to UTC. Doing so would offset every period boundary by
 * Ecuador's fixed -05:00 offset relative to the wall-clock values actually
 * stored in the database.
 *
 * MONTH-OVERFLOW TRAP (documented precedent:
 * `DashboardController::buildSalesOverTime()`): calling `->subMonths()` or
 * `->addMonths()` from a day-of-month a target month does not have (e.g. the
 * 31st, stepped into a 30-day month) overflows into the FOLLOWING month, and
 * two distinct calendar months can silently collapse onto the same label.
 * `generateMonthly()` avoids this structurally: the cursor is always
 * `->startOfMonth()` FIRST, and every step is `->addMonthNoOverflow()` from
 * that day-1 anchor — there is never a day-of-month greater than 1 for an
 * overflow to happen from.
 */
final class PeriodCalendar
{
    /**
     * Design-chosen caps (adjustment #1) on the number of periods a single
     * report response may return, so a wide date range at a fine
     * granularity cannot force an unbounded number of `SUM(CASE WHEN)`
     * columns onto one query.
     */
    private const DAY_CAP = 92;
    private const WEEK_CAP = 53;
    private const MONTH_CAP = 36;

    /**
     * Builds the effective `Period[]` for a requested `[from, to)` range and
     * granularity, auto-degrading to a coarser granularity whenever the
     * requested one would produce more periods than its cap allows
     * (adjustment #1 — NEVER a 422; the frontend renders whatever
     * granularity actually comes back, via `effectiveGranularity`).
     *
     * Degrade order: day → week → month. If month generation itself still
     * exceeds `MONTH_CAP` (a genuinely enormous range), there is no coarser
     * granularity left, so the period list is clipped to the most recent
     * `MONTH_CAP` months instead — "the largest month range" the API will
     * ever return in one response. `degraded` is true whenever the returned
     * periods do not fully represent the originally requested range,
     * whether by a granularity change or by this clipping — either way the
     * caller must not assume the response covers exactly what was asked.
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to, Granularity $requested): PeriodCalendarResult
    {
        $granularity = $requested;
        $periods = $this->generate($from, $to, $granularity);
        $degraded = false;

        while ($this->exceedsCap($granularity, count($periods))) {
            $next = $granularity->coarser();

            if ($next === null) {
                // Already at Month and still over cap — clip to the most
                // recent MONTH_CAP months instead of degrading further
                // (there is nowhere coarser to go).
                $periods = array_slice($periods, -self::MONTH_CAP);
                $degraded = true;

                break;
            }

            $granularity = $next;
            $periods = $this->generate($from, $to, $granularity);
            $degraded = true;
        }

        return new PeriodCalendarResult($requested, $granularity, $degraded, $periods);
    }

    private function exceedsCap(Granularity $granularity, int $count): bool
    {
        return $count > match ($granularity) {
            Granularity::Day => self::DAY_CAP,
            Granularity::Week => self::WEEK_CAP,
            Granularity::Month => self::MONTH_CAP,
        };
    }

    /**
     * @return Period[]
     */
    private function generate(CarbonImmutable $from, CarbonImmutable $to, Granularity $granularity): array
    {
        return match ($granularity) {
            Granularity::Day => $this->generateDaily($from, $to),
            Granularity::Week => $this->generateWeekly($from, $to),
            Granularity::Month => $this->generateMonthly($from, $to),
        };
    }

    /**
     * @return Period[]
     */
    private function generateDaily(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $periods = [];
        $cursor = $from->startOfDay();

        while ($cursor->lt($to)) {
            $next = $cursor->addDay();
            $periods[] = new Period($cursor, $next, $cursor->format('Y-m-d'));
            $cursor = $next;
        }

        return $periods;
    }

    /**
     * @return Period[]
     */
    private function generateWeekly(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $periods = [];
        $cursor = $from->startOfWeek();

        while ($cursor->lt($to)) {
            $next = $cursor->addWeek();
            $periods[] = new Period($cursor, $next, $cursor->format('Y-m-d'));
            $cursor = $next;
        }

        return $periods;
    }

    /**
     * @return Period[]
     */
    private function generateMonthly(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $periods = [];
        // Anchor to day 1 BEFORE stepping — see the month-overflow docblock
        // above. Every cursor from here on is always a day-1 date, so
        // addMonthNoOverflow() can never overflow.
        $cursor = $from->startOfMonth();

        while ($cursor->lt($to)) {
            $next = $cursor->addMonthNoOverflow();
            $periods[] = new Period($cursor, $next, $cursor->format('Y-m'));
            $cursor = $next;
        }

        return $periods;
    }
}
