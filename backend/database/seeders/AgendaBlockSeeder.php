<?php

namespace Database\Seeders;

use App\Models\AgendaBlock;
use Illuminate\Database\Seeder;

class AgendaBlockSeeder extends Seeder
{
    /**
     * Idempotent seeder: blocks are keyed on the natural tuple
     * (day_of_week, specific_date, open_time) so re-seeding reconciles
     * existing rows instead of duplicating venue availability.
     *
     * Agenda blocks are venue-wide (no service_id — see VenueAvailabilityResolver),
     * replacing the per-service ServiceSlotSeeder pattern for the new
     * concurrency model. Seeds:
     *  - recurring weekday blocks (Mon–Fri, higher cap),
     *  - a recurring Saturday block with a tighter cap and soft_threshold,
     *  - a blocked recurring window (shows the "no disponible" admin state),
     *  - a specific-date block (e.g. an isolated one-off closure/opening).
     */
    public function run(): void
    {
        // day_of_week: 0=Sunday … 6=Saturday
        $recurring = [
            ['day_of_week' => 1, 'open_time' => '09:00', 'close_time' => '18:00', 'concurrency_limit' => 3, 'soft_threshold' => 2, 'is_blocked' => false],
            ['day_of_week' => 2, 'open_time' => '09:00', 'close_time' => '18:00', 'concurrency_limit' => 3, 'soft_threshold' => 2, 'is_blocked' => false],
            ['day_of_week' => 3, 'open_time' => '09:00', 'close_time' => '18:00', 'concurrency_limit' => 3, 'soft_threshold' => 2, 'is_blocked' => false],
            ['day_of_week' => 4, 'open_time' => '09:00', 'close_time' => '18:00', 'concurrency_limit' => 3, 'soft_threshold' => 2, 'is_blocked' => false],
            ['day_of_week' => 5, 'open_time' => '09:00', 'close_time' => '18:00', 'concurrency_limit' => 3, 'soft_threshold' => 2, 'is_blocked' => false],
            ['day_of_week' => 6, 'open_time' => '09:00', 'close_time' => '14:00', 'concurrency_limit' => 2, 'soft_threshold' => 1, 'is_blocked' => false],
            // Blocked window — shows the "no disponible" state in the admin grid.
            ['day_of_week' => 6, 'open_time' => '14:00', 'close_time' => '18:00', 'concurrency_limit' => 1, 'soft_threshold' => null, 'is_blocked' => true],
        ];

        foreach ($recurring as $block) {
            AgendaBlock::updateOrCreate(
                [
                    'day_of_week'   => $block['day_of_week'],
                    'specific_date' => null,
                    'open_time'     => $block['open_time'],
                ],
                [
                    'close_time'        => $block['close_time'],
                    'concurrency_limit' => $block['concurrency_limit'],
                    'soft_threshold'    => $block['soft_threshold'],
                    'is_blocked'        => $block['is_blocked'],
                ]
            );
        }

        // One-off block tied to a concrete date (e.g. an extra opening).
        //
        // NOTE: specific_date is cast to `date` on the model, which on SQLite
        // is stored as a full datetime string. A plain array-keyed
        // updateOrCreate() (which builds an equality `where()`) would compare
        // the raw 'Y-m-d' string against that stored value and never match,
        // silently inserting a duplicate row on every re-seed. whereDate()
        // is required here — same gotcha documented for Slice 2's overlap
        // queries (see StoreAgendaBlockRequest::validateNoOverlap()).
        $specificDate = now()->addWeeks(2)->next(\Carbon\Carbon::SATURDAY)->format('Y-m-d');

        $oneOff = AgendaBlock::whereNull('day_of_week')
            ->whereDate('specific_date', $specificDate)
            ->where('open_time', '10:00')
            ->first();

        $oneOffAttributes = [
            'day_of_week'       => null,
            'specific_date'     => $specificDate,
            'open_time'         => '10:00',
            'close_time'        => '13:00',
            'concurrency_limit' => 1,
            'soft_threshold'    => null,
            'is_blocked'        => false,
        ];

        if ($oneOff) {
            $oneOff->update($oneOffAttributes);
        } else {
            AgendaBlock::create($oneOffAttributes);
        }
    }
}
