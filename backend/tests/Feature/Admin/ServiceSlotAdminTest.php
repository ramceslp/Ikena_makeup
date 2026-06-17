<?php

namespace Tests\Feature\Admin;

use App\Models\Service;
use App\Models\ServiceSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServiceSlotAdminTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function student(): User
    {
        return User::factory()->create(['role' => 'student']);
    }

    private function byAppointmentService(): Service
    {
        return Service::factory()->create([
            'availability_type' => 'by_appointment',
            'is_published'      => true,
        ]);
    }

    // =========================================================================
    // Auth Matrix — 403 non-admin
    // =========================================================================

    public function test_non_admin_cannot_list_slots_403(): void
    {
        $service = $this->byAppointmentService();
        Sanctum::actingAs($this->student());
        $this->getJson("/api/admin/services/{$service->id}/slots")->assertStatus(403);
    }

    public function test_guest_cannot_list_slots_401(): void
    {
        $service = $this->byAppointmentService();
        $this->getJson("/api/admin/services/{$service->id}/slots")->assertStatus(401);
    }

    // =========================================================================
    // GET /api/admin/services/{service}/slots
    // =========================================================================

    public function test_admin_can_list_slots_for_service_200(): void
    {
        $service = $this->byAppointmentService();
        ServiceSlot::factory()->count(3)->create(['service_id' => $service->id]);

        Sanctum::actingAs($this->admin());
        $response = $this->getJson("/api/admin/services/{$service->id}/slots")->assertStatus(200);

        $this->assertCount(3, $response->json('data'));
    }

    // =========================================================================
    // POST /api/admin/services/{service}/slots
    // =========================================================================

    public function test_admin_can_create_recurring_slot_with_day_of_week(): void
    {
        $service = $this->byAppointmentService();
        Sanctum::actingAs($this->admin());

        $this->postJson("/api/admin/services/{$service->id}/slots", [
            'day_of_week'   => 1,
            'specific_date' => null,
            'start_time'    => '10:00',
            'capacity'      => 1,
        ])->assertStatus(201);

        $this->assertDatabaseHas('service_slots', [
            'service_id'  => $service->id,
            'day_of_week' => 1,
        ]);
    }

    public function test_admin_can_create_specific_date_slot(): void
    {
        $service = $this->byAppointmentService();
        Sanctum::actingAs($this->admin());

        $this->postJson("/api/admin/services/{$service->id}/slots", [
            'day_of_week'   => null,
            'specific_date' => '2026-07-04',
            'start_time'    => '14:00',
            'capacity'      => 1,
        ])->assertStatus(201);

        $this->assertDatabaseHas('service_slots', [
            'service_id' => $service->id,
        ]);

        $this->assertDatabaseHas('service_slots', [
            'service_id' => $service->id,
            'start_time' => '14:00',
        ]);
    }

    public function test_both_day_of_week_and_specific_date_returns_422(): void
    {
        $service = $this->byAppointmentService();
        Sanctum::actingAs($this->admin());

        $this->postJson("/api/admin/services/{$service->id}/slots", [
            'day_of_week'   => 2,
            'specific_date' => '2026-07-04',
            'start_time'    => '10:00',
        ])->assertStatus(422);
    }

    public function test_both_day_of_week_and_specific_date_null_returns_422(): void
    {
        $service = $this->byAppointmentService();
        Sanctum::actingAs($this->admin());

        $this->postJson("/api/admin/services/{$service->id}/slots", [
            'day_of_week'   => null,
            'specific_date' => null,
            'start_time'    => '10:00',
        ])->assertStatus(422);
    }

    // =========================================================================
    // PATCH /api/admin/services/{service}/slots/{slot}
    // =========================================================================

    public function test_admin_can_block_a_slot(): void
    {
        $service = $this->byAppointmentService();
        $slot    = ServiceSlot::factory()->create([
            'service_id' => $service->id,
            'is_blocked' => false,
        ]);

        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/services/{$service->id}/slots/{$slot->id}", [
            'is_blocked' => true,
        ])->assertStatus(200);

        $this->assertDatabaseHas('service_slots', [
            'id'         => $slot->id,
            'is_blocked' => true,
        ]);
    }

    public function test_non_admin_cannot_patch_slot_403(): void
    {
        $service = $this->byAppointmentService();
        $slot    = ServiceSlot::factory()->create(['service_id' => $service->id]);

        Sanctum::actingAs($this->student());
        $this->patchJson("/api/admin/services/{$service->id}/slots/{$slot->id}", [
            'is_blocked' => true,
        ])->assertStatus(403);
    }

    // =========================================================================
    // DELETE /api/admin/services/{service}/slots/{slot}
    // =========================================================================

    public function test_admin_can_delete_slot_204(): void
    {
        $service = $this->byAppointmentService();
        $slot    = ServiceSlot::factory()->create(['service_id' => $service->id]);

        Sanctum::actingAs($this->admin());
        $this->deleteJson("/api/admin/services/{$service->id}/slots/{$slot->id}")->assertStatus(204);

        $this->assertDatabaseMissing('service_slots', ['id' => $slot->id]);
    }

    public function test_non_admin_cannot_delete_slot_403(): void
    {
        $service = $this->byAppointmentService();
        $slot    = ServiceSlot::factory()->create(['service_id' => $service->id]);

        Sanctum::actingAs($this->student());
        $this->deleteJson("/api/admin/services/{$service->id}/slots/{$slot->id}")->assertStatus(403);
    }
}
