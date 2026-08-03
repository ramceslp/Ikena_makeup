<?php

namespace App\Reports;

use Carbon\CarbonImmutable;

/**
 * One half-open time bucket: `[from, to)` — `from` inclusive, `to`
 * exclusive. Half-open bounds are deliberate (design D3): an inclusive
 * `BETWEEN`-style range needs an off-by-one adjustment at every boundary
 * (is `23:59:59.999999` close enough to midnight?); `>= from AND < to`
 * never does, on either SQLite or MySQL.
 */
final readonly class Period
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public string $label,
    ) {
    }
}
