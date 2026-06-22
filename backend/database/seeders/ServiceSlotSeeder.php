<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceSlot;
use Illuminate\Database\Seeder;

class ServiceSlotSeeder extends Seeder
{
    /**
     * Idempotent seeder: slots are keyed on the natural tuple
     * (service_id, day_of_week, specific_date, start_time) so re-seeding
     * reconciles existing rows instead of duplicating availability.
     *
     * Seeds, for every published by_appointment service:
     *  - recurring weekly slots (Friday + Saturday),
     *  - one blocked slot (is_blocked = true) to exercise that state,
     *  - one specific-date slot (overrides the weekly pattern for one day).
     */
    public function run(): void
    {
        $services = Service::where('availability_type', 'by_appointment')->get();

        // day_of_week: 0=Sunday … 6=Saturday
        $recurring = [
            ['day_of_week' => 5, 'start_time' => '10:00', 'capacity' => 1, 'is_blocked' => false],
            ['day_of_week' => 5, 'start_time' => '14:00', 'capacity' => 1, 'is_blocked' => false],
            ['day_of_week' => 6, 'start_time' => '10:00', 'capacity' => 2, 'is_blocked' => false],
            // Blocked slot — shows the "no disponible" state in the admin grid.
            ['day_of_week' => 6, 'start_time' => '16:00', 'capacity' => 1, 'is_blocked' => true],
        ];

        $specificDate = now()->addWeeks(2)->next(\Carbon\Carbon::SATURDAY)->format('Y-m-d');

        foreach ($services as $service) {
            foreach ($recurring as $slot) {
                ServiceSlot::updateOrCreate(
                    [
                        'service_id'    => $service->id,
                        'day_of_week'   => $slot['day_of_week'],
                        'specific_date' => null,
                        'start_time'    => $slot['start_time'],
                    ],
                    [
                        'capacity'   => $slot['capacity'],
                        'is_blocked' => $slot['is_blocked'],
                    ]
                );
            }

            // One-off slot tied to a concrete date (e.g. an extra opening).
            ServiceSlot::updateOrCreate(
                [
                    'service_id'    => $service->id,
                    'day_of_week'   => null,
                    'specific_date' => $specificDate,
                    'start_time'    => '12:00',
                ],
                [
                    'capacity'   => 1,
                    'is_blocked' => false,
                ]
            );
        }
    }
}
