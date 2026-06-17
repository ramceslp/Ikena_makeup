<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\ServiceSlot;
use App\Services\Booking\SlotAvailabilityResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlotAvailabilityResolverTest extends TestCase
{
    use RefreshDatabase;

    private SlotAvailabilityResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new SlotAvailabilityResolver();
    }

    // =========================================================================
    // Recurring day_of_week slot
    // =========================================================================

    public function test_recurring_slot_generates_occurrences_in_60_day_window(): void
    {
        $tz      = config('booking.timezone');
        $now     = Carbon::now($tz);
        $service = Service::factory()->create(['availability_type' => 'by_appointment']);

        // Create a slot for every Monday (day_of_week=1)
        ServiceSlot::factory()->create([
            'service_id'    => $service->id,
            'day_of_week'   => 1, // Monday
            'specific_date' => null,
            'start_time'    => '10:00',
            'is_blocked'    => false,
        ]);

        $slots = $this->resolver->resolve($service, 60);

        // Within 60 days there are approximately 8-9 Mondays
        $this->assertGreaterThanOrEqual(7, count($slots));
        $this->assertLessThanOrEqual(9, count($slots));

        // Each occurrence must have the expected keys
        foreach ($slots as $slot) {
            $this->assertArrayHasKey('slot_id', $slot);
            $this->assertArrayHasKey('date_label', $slot);
            $this->assertArrayHasKey('start_time', $slot);
            $this->assertArrayHasKey('capacity_remaining', $slot);
        }
    }

    // =========================================================================
    // Specific-date slot
    // =========================================================================

    public function test_specific_date_slot_appears_in_window(): void
    {
        $tz      = config('booking.timezone');
        $future  = Carbon::now($tz)->addDays(5)->format('Y-m-d');
        $service = Service::factory()->create(['availability_type' => 'by_appointment']);

        ServiceSlot::factory()->create([
            'service_id'    => $service->id,
            'day_of_week'   => null,
            'specific_date' => $future,
            'start_time'    => '14:00',
            'is_blocked'    => false,
        ]);

        $slots = $this->resolver->resolve($service, 60);

        $this->assertCount(1, $slots);
        $this->assertSame($future, $slots[0]['date_label']);
        $this->assertSame('14:00', $slots[0]['start_time']);
    }

    // =========================================================================
    // Blocked slot excluded
    // =========================================================================

    public function test_blocked_slot_is_excluded(): void
    {
        $tz      = config('booking.timezone');
        $future  = Carbon::now($tz)->addDays(5)->format('Y-m-d');
        $service = Service::factory()->create(['availability_type' => 'by_appointment']);

        ServiceSlot::factory()->create([
            'service_id'    => $service->id,
            'day_of_week'   => null,
            'specific_date' => $future,
            'start_time'    => '10:00',
            'is_blocked'    => true,
        ]);

        $slots = $this->resolver->resolve($service, 60);
        $this->assertCount(0, $slots);
    }

    // =========================================================================
    // Booked (non-cancelled) slot excluded
    // =========================================================================

    public function test_slot_with_non_cancelled_appointment_is_excluded(): void
    {
        $tz      = config('booking.timezone');
        $future  = Carbon::now($tz)->addDays(5)->format('Y-m-d');
        $service = Service::factory()->create(['availability_type' => 'by_appointment']);

        ServiceSlot::factory()->create([
            'service_id'    => $service->id,
            'day_of_week'   => null,
            'specific_date' => $future,
            'start_time'    => '10:00',
            'is_blocked'    => false,
        ]);

        // Create a non-cancelled appointment occupying the slot
        Appointment::factory()->create([
            'service_id'     => $service->id,
            'scheduled_date' => $future,
            'scheduled_time' => '10:00',
            'slot_key'       => Appointment::makeSlotKey($service->id, $future, '10:00'),
            'status'         => 'pending',
        ]);

        $slots = $this->resolver->resolve($service, 60);
        $this->assertCount(0, $slots);
    }

    // =========================================================================
    // Cancelled appointment does NOT block slot
    // =========================================================================

    public function test_cancelled_appointment_does_not_block_slot(): void
    {
        $tz      = config('booking.timezone');
        $future  = Carbon::now($tz)->addDays(5)->format('Y-m-d');
        $service = Service::factory()->create(['availability_type' => 'by_appointment']);

        ServiceSlot::factory()->create([
            'service_id'    => $service->id,
            'day_of_week'   => null,
            'specific_date' => $future,
            'start_time'    => '10:00',
            'is_blocked'    => false,
        ]);

        // Cancelled appointment: slot_key is null
        Appointment::factory()->create([
            'service_id'     => $service->id,
            'scheduled_date' => $future,
            'scheduled_time' => '10:00',
            'slot_key'       => null, // cancelled = null
            'status'         => 'cancelled',
        ]);

        $slots = $this->resolver->resolve($service, 60);

        // Slot should still be available since the appointment is cancelled
        $this->assertCount(1, $slots);
    }
}
