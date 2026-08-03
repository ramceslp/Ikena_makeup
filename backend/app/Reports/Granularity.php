<?php

namespace App\Reports;

/**
 * The three report granularities. Design D3 — `PeriodCalendar` is the ONLY
 * place that turns a granularity into concrete date boundaries; every query
 * downstream only ever sees already-computed `Period` objects, never a
 * driver-specific date-grouping expression.
 */
enum Granularity: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    /**
     * The next coarser granularity, or null when already at the coarsest
     * (Month). Used by `PeriodCalendar`'s auto-degrade logic (adjustment #1,
     * architecture/admin-reports-design-adjustments): a range that would
     * produce too many periods at the requested granularity degrades to the
     * next one up rather than rejecting the request with a 422.
     */
    public function coarser(): ?self
    {
        return match ($this) {
            self::Day => self::Week,
            self::Week => self::Month,
            self::Month => null,
        };
    }
}
