<?php

namespace App\Services\Booking;

use App\Models\AgendaBlock;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * VenueAvailabilityResolver
 *
 * Generates candidate appointment start times for a given service across all
 * active AgendaBlocks in the configured look-ahead window, then filters out
 * candidates that have reached the venue-wide concurrency limit.
 *
 * Key design decisions (see design document):
 * - Overlap is VENUE-WIDE (all non-cancelled appointments, regardless of service).
 * - scheduled_end_time is denormalized on Appointment to avoid JOIN on the hot path.
 * - Candidates where start + duration_hours > close_time are excluded (loop bound).
 * - is_near_capacity and warning_message are surfaced when count >= soft_threshold.
 *
 * Layering (single implementation of candidate generation — see calendar
 * picker slice 1):
 * - resolve()            — full look-ahead window, flat occurrence list. Loops
 *                           days and delegates to resolveDay() per day.
 * - resolveDay()          — single calendar day, flat occurrence list. Used
 *                           directly by the day-scoped available-slots endpoint.
 * - resolveDaySummary()   — full look-ahead window, one lightweight
 *                           { date, available_count } row per day that has an
 *                           active AgendaBlock. Used by the available-days
 *                           endpoint for the calendar picker's first step.
 * - occurrencesForBlocks() — shared core: given a set of blocks + a single
 *                           date, generates every candidate and computes its
 *                           overlap count. This is the ONE place candidate
 *                           generation happens; resolveDay() and
 *                           resolveDaySummary() both call it.
 *
 * Performance: overlap counting is BATCHED per day, not per candidate. A
 * single query loads that day's non-cancelled appointment intervals
 * (scheduled_time, scheduled_end_time) once; overlap counts for every
 * candidate on that day are then computed in PHP against that in-memory set.
 * This replaces the previous one-COUNT-query-per-candidate approach (which
 * could issue ~900 queries for a single request) while preserving the exact
 * half-open overlap semantics: existing.start < proposed_end AND
 * existing.end > proposed_start.
 *
 * Return shape (per occurrence):
 *   slot_id           (int)         — AgendaBlock id
 *   date_label        (string)      — ISO date 'Y-m-d'
 *   start_time        (string)      — 'HH:MM'
 *   capacity_remaining (int)        — effective_limit - overlap_count
 *   is_near_capacity  (bool)        — true when count >= soft_threshold (non-null)
 *   warning_message   (string|null) — configured warning string or null
 */
class VenueAvailabilityResolver
{
    /**
     * Resolve available venue slots for a service in the look-ahead window.
     *
     * @param  int|null  $windowDays  Override look-ahead days (defaults to config).
     * @return array<int, array{slot_id: int, date_label: string, start_time: string,
     *                          capacity_remaining: int, is_near_capacity: bool,
     *                          warning_message: string|null}>
     */
    public function resolve(Service $service, ?int $windowDays = null): array
    {
        $tz = config('booking.timezone');
        $windowDays = $windowDays ?? (int) config('booking.venue.look_ahead_days');

        $today = Carbon::now($tz)->startOfDay();
        $horizon = $today->copy()->addDays($windowDays);

        $occurrences = [];

        $current = $today->copy();
        while ($current->lt($horizon)) {
            array_push($occurrences, ...$this->resolveDay($service, $current->format('Y-m-d')));

            $current->addDay();
        }

        return $occurrences;
    }

    /**
     * Resolve available candidate start times for a single calendar day.
     *
     * Same per-occurrence shape as resolve(), scoped to one date. Returns an
     * empty array when no active AgendaBlock covers this date (venue closed)
     * or every candidate is at/over its concurrency limit (fully booked).
     *
     * @param  string  $date  'Y-m-d'
     * @return array<int, array{slot_id: int, date_label: string, start_time: string,
     *                          capacity_remaining: int, is_near_capacity: bool,
     *                          warning_message: string|null}>
     */
    public function resolveDay(Service $service, string $date): array
    {
        $tz = config('booking.timezone');
        $dow = Carbon::createFromFormat('Y-m-d', $date, $tz)->dayOfWeek;

        $blocks = $this->blocksForDate($dow, $date);

        return $this->occurrencesForBlocks($blocks, $service, $date);
    }

    /**
     * Resolve a lightweight day-availability summary across the look-ahead
     * window, for the calendar picker's day-selection step.
     *
     * Contract (documented explicitly — this is the frontend/backend contract):
     *  - A day with NO active AgendaBlock covering it (no day_of_week or
     *    specific_date match — the venue is simply closed that day) is
     *    OMITTED from the returned array entirely.
     *  - A day WITH an active AgendaBlock but zero bookable candidates
     *    (every candidate is at/over its concurrency limit — fully booked)
     *    IS included, with available_count: 0.
     *  This lets the frontend distinguish "closed" (day absent) from
     *  "full" (day present, available_count 0) without a separate flag.
     *
     * @param  int|null  $windowDays  Override look-ahead days (defaults to config).
     * @return array<int, array{date: string, available_count: int}>
     */
    public function resolveDaySummary(Service $service, ?int $windowDays = null): array
    {
        $tz = config('booking.timezone');
        $windowDays = $windowDays ?? (int) config('booking.venue.look_ahead_days');

        $today = Carbon::now($tz)->startOfDay();
        $horizon = $today->copy()->addDays($windowDays);

        $summary = [];

        $current = $today->copy();
        while ($current->lt($horizon)) {
            $dateStr = $current->format('Y-m-d');
            $dow = $current->dayOfWeek;

            $blocks = $this->blocksForDate($dow, $dateStr);

            if ($blocks->isEmpty()) {
                // Venue closed this day — omit entirely (see docblock contract).
                $current->addDay();

                continue;
            }

            $occurrences = $this->occurrencesForBlocks($blocks, $service, $dateStr);

            $summary[] = [
                'date' => $dateStr,
                'available_count' => count($occurrences),
            ];

            $current->addDay();
        }

        return $summary;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Load active AgendaBlocks matching a given date (day_of_week OR specific_date).
     *
     * orWhereDate() is used for specific_date because Eloquent's 'date' cast
     * stores values as 'Y-m-d H:i:s' in SQLite; whereDate() wraps with the
     * database's date extraction function for a driver-agnostic comparison.
     *
     * @return Collection<int, AgendaBlock>
     */
    private function blocksForDate(int $dow, string $dateStr): Collection
    {
        return AgendaBlock::where('is_blocked', false)
            ->where(function ($query) use ($dow, $dateStr) {
                $query->where('day_of_week', $dow)
                    ->orWhereDate('specific_date', $dateStr);
            })
            ->get();
    }

    /**
     * Generate every candidate occurrence for the given blocks on the given
     * date, filtering out candidates at/over their concurrency limit.
     *
     * This is the SINGLE implementation of candidate generation, shared by
     * resolveDay() and resolveDaySummary() — see class docblock.
     *
     * Fetches the day's non-cancelled appointment intervals ONCE (a single
     * query), then computes each candidate's overlap count in PHP against
     * that in-memory set instead of issuing one COUNT query per candidate.
     *
     * @param  Collection<int, AgendaBlock>  $blocks
     */
    private function occurrencesForBlocks(Collection $blocks, Service $service, string $dateStr): array
    {
        if ($blocks->isEmpty()) {
            return [];
        }

        $granularity = (int) config('booking.venue.candidate_granularity_minutes');
        $durationMin = (int) $service->duration_hours * 60;
        $intervals = $this->dayAppointmentIntervals($dateStr);

        $occurrences = [];

        foreach ($blocks as $block) {
            $openMin = $this->timeToMinutes($block->open_time);
            $closeMin = $this->timeToMinutes($block->close_time);

            // Generate candidates: start at open_time, advance by granularity,
            // include only if start + duration <= close_time (boundary inclusive).
            for ($c = $openMin; $c + $durationMin <= $closeMin; $c += $granularity) {
                $startStr = $this->minutesToTime($c);
                $endStr = $this->minutesToTime($c + $durationMin);

                $count = $this->overlapCount($intervals, $startStr, $endStr);

                $effectiveLimit = $block->concurrency_limit
                    ?? (int) config('booking.venue.default_concurrency_limit');

                $remaining = $effectiveLimit - $count;

                // Hard cap: exclude candidates at or over the limit
                if ($remaining <= 0) {
                    continue;
                }

                $softThreshold = $block->soft_threshold
                    ?? config('booking.venue.default_soft_threshold');

                // Soft threshold: flag when count has reached the warning level
                $nearCapacity = $softThreshold !== null && $count >= $softThreshold;
                $warningMessage = $nearCapacity
                    ? config('booking.venue.warning_message')
                    : null;

                $occurrences[] = [
                    'slot_id' => $block->id,
                    'date_label' => $dateStr,
                    'start_time' => $startStr,
                    'capacity_remaining' => $remaining,
                    'is_near_capacity' => $nearCapacity,
                    'warning_message' => $warningMessage,
                ];
            }
        }

        return $occurrences;
    }

    /**
     * Load non-cancelled appointment intervals for a single date in ONE query.
     *
     * whereDate() is used for scheduled_date because the Appointment model's
     * 'date' cast stores dates as 'Y-m-d H:i:s' in SQLite. whereDate() wraps
     * with the DB's date extraction function (strftime on SQLite, DATE() on
     * MySQL) for a driver-agnostic comparison — same convention as the
     * AgendaBlock date lookups above.
     *
     * scheduled_time/scheduled_end_time are `time()` columns: MySQL returns
     * them as 'HH:MM:SS', SQLite returns exactly what was inserted ('HH:MM').
     * Both are normalized to 'HH:MM' here so the PHP-side string comparison
     * in overlapCount() (against 'HH:MM' candidate start/end strings) is
     * driver-agnostic — mirrors the substr(...,0,5) convention used
     * elsewhere in this codebase (e.g. Appointment::makeSlotKey()).
     *
     * @return array<int, array{start: string, end: string}>
     */
    private function dayAppointmentIntervals(string $date): array
    {
        return DB::table('appointments')
            ->where('status', '!=', 'cancelled')
            ->whereDate('scheduled_date', $date)
            ->get(['scheduled_time', 'scheduled_end_time'])
            ->map(fn ($row) => [
                'start' => substr($row->scheduled_time, 0, 5),
                'end' => substr($row->scheduled_end_time, 0, 5),
            ])
            ->all();
    }

    /**
     * Count intervals overlapping [startStr, endStr) using half-open interval
     * semantics: existing.start < proposed_end AND existing.end > proposed_start.
     *
     * This is a VENUE-WIDE count (no service_id filter) per the design.
     * Computed in PHP against an already-fetched interval set — see
     * dayAppointmentIntervals() — instead of a per-candidate COUNT query.
     *
     * @param  array<int, array{start: string, end: string}>  $intervals
     */
    private function overlapCount(array $intervals, string $startStr, string $endStr): int
    {
        $count = 0;

        foreach ($intervals as $interval) {
            if ($interval['start'] < $endStr && $interval['end'] > $startStr) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Convert a 'HH:MM' (or 'HH:MM:SS') time string to minutes since midnight.
     */
    private function timeToMinutes(string $time): int
    {
        $parts = explode(':', substr($time, 0, 5));

        return (int) $parts[0] * 60 + (int) $parts[1];
    }

    /**
     * Convert minutes since midnight to a zero-padded 'HH:MM' time string.
     */
    private function minutesToTime(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
