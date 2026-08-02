<?php

namespace App\Reports\Export;

use App\Reports\Queries\LedgerQuery;
use App\Reports\ReportFilter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * LedgerCsvStream — the "CSV export matches on-screen ledger" requirement
 * (spec `report-export`). Streams EVERY row `LedgerQuery::cursor()` returns
 * for the same filter the on-screen ledger applies — never a separate
 * query — so the two can never silently diverge (design D7's parity
 * guarantee).
 *
 * UTF-8 BOM (`\xEF\xBB\xBF`) so accented characters render correctly in
 * Excel (spec requirement). CSV-injection prefixing: any cell starting with
 * `= + - @` is prefixed with a leading `'` — a formula-injection
 * mitigation (design D7) for a file a spreadsheet application will open and
 * evaluate.
 *
 * `cursor()` (not `get()`) keeps memory flat regardless of ledger size —
 * design D7: "Streamed UTF-8 CSV ... StreamedResponse + cursor()."
 */
final class LedgerCsvStream
{
    private const INJECTION_PREFIXES = ['=', '+', '-', '@'];

    private const HEADER = ['Fecha', 'Origen', 'Cliente', 'Monto (USD)'];

    public function __construct(private readonly LedgerQuery $query)
    {
    }

    public function respond(ReportFilter $filter): StreamedResponse
    {
        return new StreamedResponse(function () use ($filter) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, self::HEADER);

            foreach ($this->query->cursor($filter) as $row) {
                fputcsv($handle, [
                    $this->sanitize((string) $row->occurred_at),
                    $this->sanitize((string) $row->label),
                    $this->sanitize((string) ($row->counterparty ?? '')),
                    number_format(((int) $row->amount_cents) / 100, 2, '.', ''),
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="ledger.csv"',
        ]);
    }

    /**
     * Prefixes a leading `'` on any cell starting with a formula character
     * (`= + - @`) so a spreadsheet application never evaluates ledger data
     * as a formula (design D7's CSV-injection mitigation).
     */
    private function sanitize(string $value): string
    {
        if ($value !== '' && in_array($value[0], self::INJECTION_PREFIXES, true)) {
            return "'".$value;
        }

        return $value;
    }
}
