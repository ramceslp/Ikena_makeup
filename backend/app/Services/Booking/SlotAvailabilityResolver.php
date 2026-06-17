<?php

namespace App\Services\Booking;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\ServiceSlot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Pure slot availability resolver.
 *
 * Composes recurring (day_of_week) and one-off (specific_date) slots within
 * a configurable day window, then removes:
 *  - blocked slots (is_blocked=true)
 *  - slots already taken by a non-cancelled appointment (slot_key NOT NULL)
 *
 * Returns an array of slot occurrences, each with:
 *   slot_id, date_label (ISO date), start_time, capacity_remaining
 */
class SlotAvailabilityResolver
{
    /**
     * Resolve available slot occurrences for a service.
     *
     * @param  Service  $service
     * @param  int      $windowDays  Look-ahead window in days (default 60)
     * @return array<int, array{slot_id: int, date_label: string, start_time: string, capacity_remaining: int}>
     */
    public function resolve(Service $service, int $windowDays = 60): array
    {
        $tz      = config('booking.timezone');
        $today   = Carbon::now($tz)->startOfDay();
        $horizon = $today->copy()->addDays($windowDays);

        // Load all active (non-blocked) slots for this service
        /** @var Collection<int, ServiceSlot> $slots */
        $slots = ServiceSlot::where('service_id', $service->id)
            ->where('is_blocked', false)
            ->get();

        // Load slot_keys of non-cancelled appointments in the window to detect taken slots
        $takenKeys = Appointment::where('service_id', $service->id)
            ->whereNotNull('slot_key')
            ->where('status', '!=', 'cancelled')
            ->pluck('slot_key')
            ->flip(); // flip for O(1) lookup

        $occurrences = [];

        foreach ($slots as $slot) {
            if ($slot->specific_date !== null) {
                // One-off slot: include only if within window and not taken
                $date = Carbon::instance($slot->specific_date)->startOfDay();

                if ($date->lt($today) || $date->gt($horizon)) {
                    continue;
                }

                $dateStr = $date->format('Y-m-d');
                $key     = Appointment::makeSlotKey($service->id, $dateStr, $slot->start_time);

                if (isset($takenKeys[$key])) {
                    continue; // slot already booked
                }

                $occurrences[] = [
                    'slot_id'           => $slot->id,
                    'date_label'        => $dateStr,
                    'start_time'        => $slot->start_time,
                    'capacity_remaining' => $slot->capacity,
                ];
            } elseif ($slot->day_of_week !== null) {
                // Recurring weekly slot: generate all occurrences in window
                $current = $today->copy()->next($slot->day_of_week);

                // If today is the same day of week, include today as well
                if ($today->dayOfWeek === $slot->day_of_week) {
                    $current = $today->copy();
                }

                while ($current->lte($horizon)) {
                    $dateStr = $current->format('Y-m-d');
                    $key     = Appointment::makeSlotKey($service->id, $dateStr, $slot->start_time);

                    if (! isset($takenKeys[$key])) {
                        $occurrences[] = [
                            'slot_id'           => $slot->id,
                            'date_label'        => $dateStr,
                            'start_time'        => $slot->start_time,
                            'capacity_remaining' => $slot->capacity,
                        ];
                    }

                    $current->addWeek();
                }
            }
        }

        return $occurrences;
    }
}
