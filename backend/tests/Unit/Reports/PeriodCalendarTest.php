<?php

namespace Tests\Unit\Reports;

use App\Reports\Granularity;
use App\Reports\PeriodCalendar;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * PeriodCalendarTest
 *
 * Table-driven coverage for design D3's PHP-only period builder: no driver
 * date SQL exists anywhere in the reporting layer BECAUSE this class does
 * every bit of calendar math itself, in Carbon, before any query runs.
 *
 * No database is touched — this is pure date arithmetic — so this stays a
 * fast Unit test with no RefreshDatabase.
 */
class PeriodCalendarTest extends TestCase
{
    private function calendar(): PeriodCalendar
    {
        return new PeriodCalendar();
    }

    // -------------------------------------------------------------------------
    // Daily
    // -------------------------------------------------------------------------

    public function test_daily_periods_cover_a_short_range_with_half_open_bounds(): void
    {
        $from = CarbonImmutable::parse('2026-08-01');
        $to = CarbonImmutable::parse('2026-08-04'); // exclusive — 3 whole days

        $result = $this->calendar()->build($from, $to, Granularity::Day);

        $this->assertSame(Granularity::Day, $result->effectiveGranularity);
        $this->assertFalse($result->degraded);
        $this->assertCount(3, $result->periods);
        $this->assertSame(['2026-08-01', '2026-08-02', '2026-08-03'], array_map(
            fn ($period) => $period->label,
            $result->periods,
        ));

        // Half-open: the first period's `to` is the exact start of the
        // second period — no gap, no overlap.
        $this->assertTrue($result->periods[0]->to->equalTo($result->periods[1]->from));
        // The final period's `to` must not exceed the requested `to`.
        $this->assertTrue($result->periods[2]->to->equalTo($to));
    }

    public function test_a_boundary_exactly_at_to_is_excluded(): void
    {
        // [2026-08-01, 2026-08-02) is exactly one day — 2026-08-02 itself
        // must NOT start a period, proving `to` is exclusive.
        $from = CarbonImmutable::parse('2026-08-01');
        $to = CarbonImmutable::parse('2026-08-02');

        $result = $this->calendar()->build($from, $to, Granularity::Day);

        $this->assertCount(1, $result->periods);
        $this->assertSame('2026-08-01', $result->periods[0]->label);
    }

    // -------------------------------------------------------------------------
    // Weekly
    // -------------------------------------------------------------------------

    public function test_weekly_periods_span_multiple_weeks(): void
    {
        $from = CarbonImmutable::parse('2026-08-03'); // a Monday
        $to = CarbonImmutable::parse('2026-08-24');   // three weeks later

        $result = $this->calendar()->build($from, $to, Granularity::Week);

        $this->assertSame(Granularity::Week, $result->effectiveGranularity);
        $this->assertFalse($result->degraded);
        $this->assertCount(3, $result->periods);
        $this->assertSame(['2026-08-03', '2026-08-10', '2026-08-17'], array_map(
            fn ($period) => $period->label,
            $result->periods,
        ));
    }

    // -------------------------------------------------------------------------
    // Monthly — including the documented month-overflow trap
    // -------------------------------------------------------------------------

    public function test_monthly_periods_do_not_collapse_on_the_month_overflow_trap(): void
    {
        // Anchoring from the 31st is exactly the DashboardController trap:
        // naively calling ->addMonths() from a day the target month lacks
        // (e.g. Feb has no 31st) overflows into the following month and
        // collapses two distinct months onto the same label.
        $from = CarbonImmutable::parse('2026-01-31');
        $to = CarbonImmutable::parse('2026-04-15');

        $result = $this->calendar()->build($from, $to, Granularity::Month);

        $this->assertSame(Granularity::Month, $result->effectiveGranularity);
        $this->assertFalse($result->degraded);
        // Jan, Feb, Mar, Apr — four DISTINCT labels, none collapsed.
        $this->assertSame(['2026-01', '2026-02', '2026-03', '2026-04'], array_map(
            fn ($period) => $period->label,
            $result->periods,
        ));
    }

    public function test_monthly_periods_zero_fill_across_a_leap_february(): void
    {
        $from = CarbonImmutable::parse('2026-01-01');
        $to = CarbonImmutable::parse('2026-03-01');

        $result = $this->calendar()->build($from, $to, Granularity::Month);

        $this->assertCount(2, $result->periods);
        $this->assertSame('2026-01', $result->periods[0]->label);
        $this->assertSame('2026-02', $result->periods[1]->label);
    }

    // -------------------------------------------------------------------------
    // Auto-degrade (adjustment #1) — NEVER a 422
    // -------------------------------------------------------------------------

    public function test_day_granularity_over_the_cap_degrades_to_week(): void
    {
        $from = CarbonImmutable::parse('2026-01-01');
        $to = $from->addDays(100); // > 92-day cap

        $result = $this->calendar()->build($from, $to, Granularity::Day);

        $this->assertSame(Granularity::Day, $result->requestedGranularity);
        $this->assertSame(Granularity::Week, $result->effectiveGranularity);
        $this->assertTrue($result->degraded);
        $this->assertLessThanOrEqual(53, count($result->periods));
    }

    public function test_week_granularity_over_the_cap_degrades_to_month(): void
    {
        $from = CarbonImmutable::parse('2024-01-01');
        $to = $from->addDays(400); // > 53-week cap

        $result = $this->calendar()->build($from, $to, Granularity::Week);

        $this->assertSame(Granularity::Week, $result->requestedGranularity);
        $this->assertSame(Granularity::Month, $result->effectiveGranularity);
        $this->assertTrue($result->degraded);
        $this->assertLessThanOrEqual(36, count($result->periods));
    }

    public function test_month_granularity_over_the_cap_clips_to_the_largest_month_range(): void
    {
        $from = CarbonImmutable::parse('2020-01-01');
        $to = $from->addMonths(40); // > 36-month cap, nowhere coarser to degrade to

        $result = $this->calendar()->build($from, $to, Granularity::Month);

        $this->assertSame(Granularity::Month, $result->requestedGranularity);
        $this->assertSame(Granularity::Month, $result->effectiveGranularity);
        $this->assertTrue($result->degraded);
        $this->assertCount(36, $result->periods);
        // Clipping keeps the MOST RECENT months (closest to `to`), not the
        // oldest — the tail of the 40-month request, not the head. The 4
        // oldest months (2020-01..2020-04) are dropped; 2020-05..2023-04
        // survive.
        $this->assertSame('2020-05', $result->periods[0]->label);
        $this->assertSame('2023-04', $result->periods[count($result->periods) - 1]->label);
    }

    public function test_ranges_within_every_cap_never_degrade(): void
    {
        $from = CarbonImmutable::parse('2026-01-01');

        $result = $this->calendar()->build($from, $from->addDays(92), Granularity::Day);
        $this->assertFalse($result->degraded);
        $this->assertSame(Granularity::Day, $result->effectiveGranularity);
    }
}
