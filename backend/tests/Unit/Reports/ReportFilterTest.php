<?php

namespace Tests\Unit\Reports;

use App\Reports\Granularity;
use App\Reports\PeriodCalendar;
use App\Reports\ReportFilter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * ReportFilterTest
 *
 * Confirms the validated DTO wires straight into PeriodCalendar (adjustment
 * #1): a wide range NEVER throws, it degrades and reports the EFFECTIVE
 * granularity distinctly from what was requested.
 */
class ReportFilterTest extends TestCase
{
    private function calendar(): PeriodCalendar
    {
        return new PeriodCalendar();
    }

    public function test_a_narrow_range_keeps_the_requested_granularity(): void
    {
        $request = Request::create('/api/admin/reports/summary', 'GET', [
            'from' => '2026-08-01',
            'to' => '2026-08-05',
            'granularity' => 'day',
        ]);

        $filter = ReportFilter::fromRequest($request, $this->calendar());

        $this->assertSame(Granularity::Day, $filter->requestedGranularity);
        $this->assertSame(Granularity::Day, $filter->effectiveGranularity);
        $this->assertFalse($filter->degraded);
    }

    /**
     * The core adjustment #1 assertion: exceeding the day cap does NOT
     * throw a validation/422 exception. It resolves successfully with a
     * distinct effective_granularity.
     */
    public function test_a_wide_range_auto_degrades_instead_of_rejecting(): void
    {
        $request = Request::create('/api/admin/reports/summary', 'GET', [
            'from' => '2026-01-01',
            'to' => '2026-06-01', // ~151 days > 92-day cap
            'granularity' => 'day',
        ]);

        $filter = ReportFilter::fromRequest($request, $this->calendar());

        $this->assertSame(Granularity::Day, $filter->requestedGranularity);
        $this->assertNotSame($filter->requestedGranularity, $filter->effectiveGranularity);
        $this->assertSame(Granularity::Week, $filter->effectiveGranularity);
        $this->assertTrue($filter->degraded);
    }

    public function test_granularity_defaults_to_day_when_omitted(): void
    {
        $request = Request::create('/api/admin/reports/summary', 'GET', [
            'from' => '2026-08-01',
            'to' => '2026-08-02',
        ]);

        $filter = ReportFilter::fromRequest($request, $this->calendar());

        $this->assertSame(Granularity::Day, $filter->requestedGranularity);
    }

    public function test_to_is_treated_as_an_inclusive_calendar_date_converted_to_a_half_open_bound(): void
    {
        $request = Request::create('/api/admin/reports/summary', 'GET', [
            'from' => '2026-08-01',
            'to' => '2026-08-01', // same day, inclusive
            'granularity' => 'day',
        ]);

        $filter = ReportFilter::fromRequest($request, $this->calendar());

        // Exactly one daily period — the inclusive single day converts to
        // one half-open bucket, not zero.
        $this->assertCount(1, $filter->periods);
        $this->assertSame('2026-08-01', $filter->periods[0]->label);
    }

    public function test_missing_required_fields_still_validates(): void
    {
        $this->expectException(ValidationException::class);

        $request = Request::create('/api/admin/reports/summary', 'GET', []);

        ReportFilter::fromRequest($request, $this->calendar());
    }

    public function test_an_unknown_granularity_value_is_rejected_at_validation(): void
    {
        $this->expectException(ValidationException::class);

        $request = Request::create('/api/admin/reports/summary', 'GET', [
            'from' => '2026-08-01',
            'to' => '2026-08-02',
            'granularity' => 'fortnight',
        ]);

        ReportFilter::fromRequest($request, $this->calendar());
    }
}
