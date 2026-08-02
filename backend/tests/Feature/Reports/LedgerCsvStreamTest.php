<?php

namespace Tests\Feature\Reports;

use App\Models\Order;
use App\Models\User;
use App\Reports\Export\LedgerCsvStream;
use App\Reports\Money\RevenueStreams;
use App\Reports\PeriodCalendar;
use App\Reports\Queries\LedgerQuery;
use App\Reports\ReportFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * LedgerCsvStreamTest
 *
 * Reports testsuite (backend/phpunit.mysql.xml). Pins spec's "CSV export
 * matches on-screen ledger" requirement: UTF-8 BOM so accented characters
 * render correctly in Excel, CSV-injection prefixing on any cell starting
 * with `= + - @` (design D7), and row-count parity against `LedgerQuery`
 * for an identical filter.
 */
class LedgerCsvStreamTest extends TestCase
{
    use RefreshDatabase;

    private function filterFor(string $from, string $to): ReportFilter
    {
        $request = Request::create('/api/admin/reports/ledger/export', 'GET', [
            'from' => $from,
            'to' => $to,
        ]);

        return ReportFilter::fromRequest($request, new PeriodCalendar());
    }

    private function stream(): LedgerCsvStream
    {
        return new LedgerCsvStream(new LedgerQuery(new RevenueStreams()));
    }

    private function renderedCsv(ReportFilter $filter): string
    {
        ob_start();
        $this->stream()->respond($filter)->sendContent();

        return ob_get_clean();
    }

    private function makeOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'type' => 'product_cart',
            'client_transaction_id' => 'ORD-csv-'.uniqid(),
            'gateway' => 'fake',
            'amount_cents' => 1500,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => '2026-08-05 10:00:00',
        ], $overrides));
    }

    public function test_csv_starts_with_a_utf8_bom_and_contains_the_header_row(): void
    {
        $csv = $this->renderedCsv($this->filterFor('2026-08-01', '2026-08-31'));

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Fecha,Origen,Cliente,"Monto (USD)"', $csv);
    }

    public function test_accented_counterparty_name_round_trips_correctly(): void
    {
        $this->makeOrder(['user_id' => User::factory()->create(['name' => 'José Muñoz'])->id]);

        $csv = $this->renderedCsv($this->filterFor('2026-08-01', '2026-08-31'));

        $this->assertStringContainsString('José Muñoz', $csv);
    }

    public function test_a_cell_starting_with_a_formula_prefix_is_quote_prefixed_against_csv_injection(): void
    {
        $this->makeOrder(['user_id' => User::factory()->create(['name' => "=cmd|' /C calc'!A0"])->id]);

        $csv = $this->renderedCsv($this->filterFor('2026-08-01', '2026-08-31'));

        $this->assertStringContainsString("'=cmd", $csv);
    }

    /**
     * A counterparty name containing a backslash immediately followed by a
     * quote must not corrupt the row. PHP's `fputcsv` defaults `$escape` to
     * `\\`, which suppresses the RFC 4180 quote-doubling for that sequence
     * and emits a structurally broken field: `"a\"b,c"` re-parses as `a\b`
     * plus a spurious extra column, silently truncating the value and
     * shifting every later column on that row. Passing `escape: ''` restores
     * standard CSV quoting (and is the PHP 9 default, so it also clears the
     * 8.4 deprecation this call site would otherwise emit once per row).
     */
    public function test_a_backslash_quote_in_a_counterparty_name_does_not_corrupt_the_row(): void
    {
        $name = 'Ana\\"Q, Ltd';
        $this->makeOrder(['user_id' => User::factory()->create(['name' => $name])->id]);

        $csv = $this->renderedCsv($this->filterFor('2026-08-01', '2026-08-31'));
        $dataRow = explode("\n", trim(substr($csv, 3)))[1];

        $fields = str_getcsv($dataRow, ',', '"', '');

        $this->assertCount(4, $fields);
        $this->assertSame($name, $fields[2]);
    }

    public function test_export_row_count_matches_the_on_screen_ledger_for_an_identical_filter(): void
    {
        foreach (['2026-08-02', '2026-08-05', '2026-08-09'] as $i => $date) {
            $this->makeOrder([
                'client_transaction_id' => "ORD-csv-parity-{$i}",
                'amount_cents' => 1000 * ($i + 1),
                'paid_at' => "{$date} 10:00:00",
            ]);
        }

        $filter = $this->filterFor('2026-08-01', '2026-08-31');
        $onScreenTotal = (new LedgerQuery(new RevenueStreams()))->run($filter)->total();

        $csv = $this->renderedCsv($filter);
        // Header + N data rows, each fputcsv-terminated with "\n".
        $lineCount = substr_count($csv, "\n") - 1;

        $this->assertSame(3, $onScreenTotal);
        $this->assertSame($onScreenTotal, $lineCount);
    }
}
