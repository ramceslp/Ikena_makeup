<?php

namespace Tests\Unit;

use App\Models\AgendaBlock;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use App\Services\Booking\VenueAvailabilityResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * VenueAvailabilityResolverTest
 *
 * Unit tests for VenueAvailabilityResolver covering VAVL-001, VAVL-002, VAVL-003.
 *
 * All tests use specific_date AgendaBlocks (not day_of_week recurring blocks)
 * for deterministic, date-pinned results. Tests run on SQLite :memory:.
 */
class VenueAvailabilityResolverTest extends TestCase
{
    use RefreshDatabase;

    private VenueAvailabilityResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new VenueAvailabilityResolver();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Return tomorrow's date string (Y-m-d) in the business timezone.
     * Using tomorrow ensures the date falls within the 60-day look-ahead window.
     */
    private function tomorrowDate(): string
    {
        return Carbon::now(config('booking.timezone'))->addDay()->format('Y-m-d');
    }

    /**
     * Create a published by_appointment service with a fixed duration.
     */
    private function makeService(int $durationHours = 1): Service
    {
        return Service::factory()->create([
            'duration_hours'    => $durationHours,
            'availability_type' => 'by_appointment',
            'is_published'      => true,
        ]);
    }

    /**
     * Create a specific-date AgendaBlock.
     */
    private function makeBlock(string $date, array $overrides = []): AgendaBlock
    {
        return AgendaBlock::create(array_merge([
            'day_of_week'       => null,
            'specific_date'     => $date,
            'open_time'         => '09:00',
            'close_time'        => '18:00',
            'concurrency_limit' => 3,
            'soft_threshold'    => null,
            'is_blocked'        => false,
        ], $overrides));
    }

    /**
     * Create an appointment on a specific date/time with scheduled_end_time set.
     * The slot_key is nullable since the UNIQUE constraint has been dropped.
     */
    private function makeAppointment(string $date, string $startTime, string $endTime, string $status = 'pending'): Appointment
    {
        return Appointment::factory()->create([
            'scheduled_date'     => $date,
            'scheduled_time'     => $startTime,
            'scheduled_end_time' => $endTime,
            'status'             => $status,
            'slot_key'           => null,
        ]);
    }

    // =========================================================================
    // VAVL-001 — Candidate Start Generation
    // =========================================================================

    /**
     * VAVL-001: Candidates generated at configured granularity (default 30 min).
     * Block 09:00–12:00, service duration 1h → candidates: 09:00, 09:30, 10:00, 10:30, 11:00.
     * 11:30 excluded: 11:30 + 1h = 12:30 > 12:00.
     */
    public function test_generates_candidates_at_configured_granularity(): void
    {
        $date    = $this->tomorrowDate();
        $service = $this->makeService(durationHours: 1);

        $this->makeBlock($date, [
            'open_time'         => '09:00',
            'close_time'        => '12:00',
            'concurrency_limit' => 5,
        ]);

        $results = $this->resolver->resolve($service);

        $timesOnDate = collect($results)
            ->where('date_label', $date)
            ->pluck('start_time')
            ->values()
            ->toArray();

        $this->assertSame(['09:00', '09:30', '10:00', '10:30', '11:00'], $timesOnDate);
    }

    /**
     * VAVL-001: Last slot is excluded when start + duration exceeds close_time.
     * Explicitly verifies 11:30 is absent from a 09:00–12:00 block with 1h service.
     */
    public function test_last_slot_excluded_when_start_plus_duration_exceeds_close_time(): void
    {
        $date    = $this->tomorrowDate();
        $service = $this->makeService(durationHours: 1);

        $this->makeBlock($date, [
            'open_time'  => '09:00',
            'close_time' => '12:00',
        ]);

        $results      = $this->resolver->resolve($service);
        $timesOnDate  = collect($results)->where('date_label', $date)->pluck('start_time')->toArray();

        $this->assertNotContains('11:30', $timesOnDate,
            '11:30 must be excluded: 11:30 + 1h = 12:30 exceeds close_time 12:00');
    }

    /**
     * VAVL-001: A block with is_blocked=true generates no candidates.
     */
    public function test_blocked_block_generates_no_candidates(): void
    {
        $date    = $this->tomorrowDate();
        $service = $this->makeService(durationHours: 1);

        $this->makeBlock($date, ['is_blocked' => true]);

        $results = $this->resolver->resolve($service);

        $timesOnDate = collect($results)->where('date_label', $date)->pluck('start_time')->toArray();

        $this->assertEmpty($timesOnDate, 'Blocked block must generate zero candidates.');
    }

    /**
     * VAVL-001: Service duration exceeds open window → zero candidates from that block.
     * Block 09:00–10:00, service duration 2h: 09:00 + 2h = 11:00 > 10:00 → no candidates.
     */
    public function test_service_duration_exceeding_block_window_yields_zero_candidates(): void
    {
        $date    = $this->tomorrowDate();
        $service = $this->makeService(durationHours: 2);

        $this->makeBlock($date, [
            'open_time'  => '09:00',
            'close_time' => '10:00',
        ]);

        $results     = $this->resolver->resolve($service);
        $timesOnDate = collect($results)->where('date_label', $date)->pluck('start_time')->toArray();

        $this->assertEmpty($timesOnDate,
            'No candidates when service duration exceeds the block open window.');
    }

    // =========================================================================
    // VAVL-002 — Hard Cap Enforcement in Resolver
    // =========================================================================

    /**
     * VAVL-002: When overlap_count >= concurrency_limit, candidate is excluded.
     * Block concurrency_limit=2; 2 non-cancelled appointments at 10:00–11:00
     * → 10:00 candidate excluded; 09:00 and 11:00 candidates included.
     */
    public function test_hard_cap_met_excludes_candidate(): void
    {
        $date    = $this->tomorrowDate();
        $service = $this->makeService(durationHours: 1);

        $this->makeBlock($date, [
            'open_time'         => '09:00',
            'close_time'        => '18:00',
            'concurrency_limit' => 2,
        ]);

        // Two non-cancelled appointments overlapping [10:00, 11:00]
        $this->makeAppointment($date, '10:00', '11:00', 'pending');
        $this->makeAppointment($date, '10:00', '11:00', 'confirmed');

        $results = $this->resolver->resolve($service);

        $timesOnDate = collect($results)->where('date_label', $date)->pluck('start_time')->toArray();

        $this->assertNotContains('10:00', $timesOnDate,
            'Candidate at 10:00 must be excluded: overlap_count (2) equals concurrency_limit (2).');
        $this->assertContains('09:00', $timesOnDate,
            'Candidate at 09:00 must be included: no appointments overlap [09:00, 10:00).');
        $this->assertContains('11:00', $timesOnDate,
            'Candidate at 11:00 must be included: appointments end at 11:00 (not strictly > 11:00).');
    }

    /**
     * VAVL-002: capacity_remaining equals concurrency_limit minus overlap_count.
     * Block concurrency_limit=3; 2 appointments at 10:00 → capacity_remaining=1.
     */
    public function test_capacity_remaining_equals_limit_minus_overlap_count(): void
    {
        $date    = $this->tomorrowDate();
        $service = $this->makeService(durationHours: 1);

        $this->makeBlock($date, [
            'open_time'         => '09:00',
            'close_time'        => '18:00',
            'concurrency_limit' => 3,
        ]);

        $this->makeAppointment($date, '10:00', '11:00', 'pending');
        $this->makeAppointment($date, '10:00', '11:00', 'confirmed');

        $results = $this->resolver->resolve($service);

        $slot10 = collect($results)
            ->where('date_label', $date)
            ->where('start_time', '10:00')
            ->first();

        $this->assertNotNull($slot10, '10:00 candidate must be present (overlap_count=2 < limit=3).');
        $this->assertSame(1, $slot10['capacity_remaining'],
            'capacity_remaining must be 3 - 2 = 1.');
    }

    /**
     * VAVL-002: Cancelled appointment is not counted in overlap.
     * Block concurrency_limit=2; 2 appointments at 10:00 but 1 is cancelled
     * → overlap_count=1 → 10:00 candidate included.
     */
    public function test_cancelled_appointment_does_not_count_toward_overlap(): void
    {
        $date    = $this->tomorrowDate();
        $service = $this->makeService(durationHours: 1);

        $this->makeBlock($date, [
            'open_time'         => '09:00',
            'close_time'        => '18:00',
            'concurrency_limit' => 2,
        ]);

        // 1 active appointment + 1 cancelled appointment at 10:00
        $this->makeAppointment($date, '10:00', '11:00', 'pending');
        $this->makeAppointment($date, '10:00', '11:00', 'cancelled');

        $results = $this->resolver->resolve($service);

        $slot10 = collect($results)
            ->where('date_label', $date)
            ->where('start_time', '10:00')
            ->first();

        $this->assertNotNull($slot10, '10:00 must be present: only 1 non-cancelled appointment (count < limit=2).');
        $this->assertSame(1, $slot10['capacity_remaining'],
            'capacity_remaining must be 2 - 1 = 1 (cancelled appointment excluded).');
    }

    // =========================================================================
    // VAVL-003 — Soft Threshold Warning per Candidate
    // =========================================================================

    /**
     * VAVL-003: When overlap_count >= soft_threshold, is_near_capacity=true and
     * warning_message is set.
     * Block soft_threshold=1, concurrency_limit=3; 1 appointment at 10:00.
     */
    public function test_soft_threshold_reached_flags_near_capacity(): void
    {
        $date    = $this->tomorrowDate();
        $service = $this->makeService(durationHours: 1);

        $this->makeBlock($date, [
            'open_time'         => '09:00',
            'close_time'        => '18:00',
            'concurrency_limit' => 3,
            'soft_threshold'    => 1,
        ]);

        $this->makeAppointment($date, '10:00', '11:00', 'pending');

        $results = $this->resolver->resolve($service);

        $slot10 = collect($results)
            ->where('date_label', $date)
            ->where('start_time', '10:00')
            ->first();

        $this->assertNotNull($slot10);
        $this->assertTrue($slot10['is_near_capacity'],
            'is_near_capacity must be true when overlap_count (1) >= soft_threshold (1).');
        $this->assertSame(
            config('booking.venue.warning_message'),
            $slot10['warning_message'],
            'warning_message must match the configured string when near capacity.'
        );
    }

    /**
     * VAVL-003: When overlap_count < soft_threshold, is_near_capacity=false.
     * Block soft_threshold=2, concurrency_limit=3; 1 appointment at 10:00.
     */
    public function test_soft_threshold_not_reached_returns_false_flag(): void
    {
        $date    = $this->tomorrowDate();
        $service = $this->makeService(durationHours: 1);

        $this->makeBlock($date, [
            'open_time'         => '09:00',
            'close_time'        => '18:00',
            'concurrency_limit' => 3,
            'soft_threshold'    => 2,
        ]);

        $this->makeAppointment($date, '10:00', '11:00', 'pending');

        $results = $this->resolver->resolve($service);

        $slot10 = collect($results)
            ->where('date_label', $date)
            ->where('start_time', '10:00')
            ->first();

        $this->assertNotNull($slot10);
        $this->assertFalse($slot10['is_near_capacity'],
            'is_near_capacity must be false when overlap_count (1) < soft_threshold (2).');
        $this->assertNull($slot10['warning_message'],
            'warning_message must be null when not near capacity.');
    }

    /**
     * VAVL-003: When block soft_threshold is null, is_near_capacity is always false.
     * Even with appointments present, null threshold never triggers the warning.
     */
    public function test_null_soft_threshold_never_triggers_near_capacity(): void
    {
        $date    = $this->tomorrowDate();
        $service = $this->makeService(durationHours: 1);

        $this->makeBlock($date, [
            'open_time'         => '09:00',
            'close_time'        => '18:00',
            'concurrency_limit' => 3,
            'soft_threshold'    => null,
        ]);

        // Create appointment so overlap_count > 0
        $this->makeAppointment($date, '10:00', '11:00', 'pending');

        $results = $this->resolver->resolve($service);

        $slot10 = collect($results)
            ->where('date_label', $date)
            ->where('start_time', '10:00')
            ->first();

        $this->assertNotNull($slot10);
        $this->assertFalse($slot10['is_near_capacity'],
            'is_near_capacity must always be false when block.soft_threshold is null.');
        $this->assertNull($slot10['warning_message']);
    }
}
