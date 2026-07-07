<?php

namespace App\Services\Booking;

use App\Models\AgendaBlock;
use App\Models\Service;
use Carbon\Carbon;
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
     * @param  Service   $service
     * @param  int|null  $windowDays  Override look-ahead days (defaults to config).
     * @return array<int, array{slot_id: int, date_label: string, start_time: string,
     *                          capacity_remaining: int, is_near_capacity: bool,
     *                          warning_message: string|null}>
     */
    public function resolve(Service $service, ?int $windowDays = null): array
    {
        $tz          = config('booking.timezone');
        $granularity = (int) config('booking.venue.candidate_granularity_minutes');
        $windowDays  = $windowDays ?? (int) config('booking.venue.look_ahead_days');
        $durationMin = (int) $service->duration_hours * 60;

        $today   = Carbon::now($tz)->startOfDay();
        $horizon = $today->copy()->addDays($windowDays);

        $occurrences = [];

        // Iterate each calendar day in the window
        $current = $today->copy();
        while ($current->lt($horizon)) {
            $dateStr = $current->format('Y-m-d');
            $dow     = $current->dayOfWeek; // 0=Sunday … 6=Saturday

            // Load active blocks matching this date (day_of_week OR specific_date).
            // orWhereDate() is used for specific_date because Eloquent's 'date' cast
            // stores values as 'Y-m-d H:i:s' in SQLite; whereDate() wraps with the
            // database's date extraction function for a driver-agnostic comparison.
            $blocks = AgendaBlock::where('is_blocked', false)
                ->where(function ($query) use ($dow, $dateStr) {
                    $query->where('day_of_week', $dow)
                          ->orWhereDate('specific_date', $dateStr);
                })
                ->get();

            foreach ($blocks as $block) {
                $openMin  = $this->timeToMinutes($block->open_time);
                $closeMin = $this->timeToMinutes($block->close_time);

                // Generate candidates: start at open_time, advance by granularity,
                // include only if start + duration <= close_time (boundary inclusive).
                for ($c = $openMin; $c + $durationMin <= $closeMin; $c += $granularity) {
                    $startStr = $this->minutesToTime($c);
                    $endStr   = $this->minutesToTime($c + $durationMin);

                    $count = $this->overlapCount($dateStr, $startStr, $endStr);

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
                    $nearCapacity   = $softThreshold !== null && $count >= $softThreshold;
                    $warningMessage = $nearCapacity
                        ? config('booking.venue.warning_message')
                        : null;

                    $occurrences[] = [
                        'slot_id'            => $block->id,
                        'date_label'         => $dateStr,
                        'start_time'         => $startStr,
                        'capacity_remaining' => $remaining,
                        'is_near_capacity'   => $nearCapacity,
                        'warning_message'    => $warningMessage,
                    ];
                }
            }

            $current->addDay();
        }

        return $occurrences;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Count non-cancelled appointments whose interval overlaps [startStr, endStr).
     *
     * Overlap condition (half-open intervals):
     *   existing.start < proposed_end AND existing.end > proposed_start
     *
     * Uses denormalized scheduled_end_time to avoid joining services on the hot path.
     * This is a VENUE-WIDE count (no service_id filter) per the design.
     *
     * @param  string  $date      'Y-m-d'
     * @param  string  $startStr  'HH:MM' — candidate start
     * @param  string  $endStr    'HH:MM' — candidate end (start + duration)
     * @return int
     */
    private function overlapCount(string $date, string $startStr, string $endStr): int
    {
        // whereDate() is used for scheduled_date because the Appointment model's
        // 'date' cast stores dates as 'Y-m-d H:i:s' in SQLite.
        // whereDate() wraps with the DB's date extraction function (strftime on SQLite,
        // DATE() on MySQL) for a driver-agnostic comparison.
        return (int) DB::table('appointments')
            ->where('status', '!=', 'cancelled')
            ->whereDate('scheduled_date', $date)
            ->where('scheduled_time', '<', $endStr)
            ->where('scheduled_end_time', '>', $startStr)
            ->count();
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
