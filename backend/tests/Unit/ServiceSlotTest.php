<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\ServiceSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceSlotTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_slot_belongs_to_service(): void
    {
        $service = Service::factory()->create();
        $slot    = ServiceSlot::factory()->create(['service_id' => $service->id]);

        $this->assertInstanceOf(Service::class, $slot->service);
        $this->assertEquals($service->id, $slot->service->id);
    }

    public function test_scope_active_excludes_blocked_slots(): void
    {
        $service = Service::factory()->create();
        ServiceSlot::factory()->create(['service_id' => $service->id, 'is_blocked' => false]);
        ServiceSlot::factory()->create(['service_id' => $service->id, 'is_blocked' => true]);

        $active = ServiceSlot::query()->active()->get();
        $this->assertCount(1, $active);
        $this->assertFalse((bool) $active->first()->is_blocked);
    }

    public function test_is_blocked_cast_to_bool(): void
    {
        $slot = ServiceSlot::factory()->create(['is_blocked' => false]);
        $this->assertIsBool($slot->is_blocked);
    }

    public function test_specific_date_cast_to_date_when_set(): void
    {
        $slot = ServiceSlot::factory()->create([
            'specific_date' => '2026-07-04',
            'day_of_week'   => null,
        ]);

        $this->assertNotNull($slot->specific_date);
    }

    public function test_appointment_make_slot_key_returns_expected_format(): void
    {
        $service = Service::factory()->create();
        $key     = Appointment::makeSlotKey($service->id, '2026-07-04', '10:00');

        $this->assertSame("{$service->id}|2026-07-04|10:00", $key);
    }
}
