<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * ProfileAgendaTest — GET /api/profile/appointments.
 *
 * The customer-facing agenda. Until this endpoint existed, appointments were
 * readable only through the admin list, so a user had no way to see their own
 * bookings; /profile/orders shows appointment ORDERS, keyed and sorted by
 * purchase date, which is the wrong axis for an agenda.
 */
class ProfileAgendaTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(): Service
    {
        return Service::factory()->create([
            'availability_type' => 'by_appointment',
            'is_published' => true,
            'duration_hours' => 1,
        ]);
    }

    private function makeAppointment(User $user, string $date, string $time, array $attrs = []): Appointment
    {
        $service = $attrs['service'] ?? $this->makeService();
        unset($attrs['service']);

        return Appointment::factory()->create(array_merge([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'scheduled_date' => $date,
            'scheduled_time' => $time,
            'scheduled_end_time' => Carbon::createFromFormat('H:i', $time)->addHour()->format('H:i:s'),
            'slot_key' => Appointment::makeSlotKey($service->id, $date, $time),
        ], $attrs));
    }

    private function daysFromToday(int $days): string
    {
        return Carbon::today()->addDays($days)->format('Y-m-d');
    }

    // -------------------------------------------------------------------------

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/profile/appointments')->assertStatus(401);
    }

    public function test_defaults_to_the_upcoming_scope(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $future = $this->makeAppointment($user, $this->daysFromToday(3), '10:00');
        $past = $this->makeAppointment($user, $this->daysFromToday(-3), '10:00');

        $response = $this->getJson('/api/profile/appointments')->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($future->id, $ids);
        $this->assertNotContains($past->id, $ids);
    }

    public function test_past_scope_returns_only_past_appointments(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $future = $this->makeAppointment($user, $this->daysFromToday(3), '10:00');
        $past = $this->makeAppointment($user, $this->daysFromToday(-3), '10:00');

        $response = $this->getJson('/api/profile/appointments?scope=past')->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($past->id, $ids);
        $this->assertNotContains($future->id, $ids);
    }

    public function test_rejects_an_unknown_scope(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/profile/appointments?scope=whenever')
            ->assertStatus(422)
            ->assertJsonValidationErrors('scope');
    }

    public function test_never_leaks_another_users_appointments(): void
    {
        $user = User::factory()->create();
        $someoneElse = User::factory()->create();
        Sanctum::actingAs($user);

        $mine = $this->makeAppointment($user, $this->daysFromToday(2), '10:00');
        $theirs = $this->makeAppointment($someoneElse, $this->daysFromToday(2), '14:00');

        $ids = collect($this->getJson('/api/profile/appointments')->json('data'))->pluck('id')->all();

        $this->assertSame([$mine->id], $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_upcoming_is_sorted_nearest_first(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $far = $this->makeAppointment($user, $this->daysFromToday(10), '10:00');
        $near = $this->makeAppointment($user, $this->daysFromToday(1), '10:00');
        $middle = $this->makeAppointment($user, $this->daysFromToday(5), '10:00');

        $ids = collect($this->getJson('/api/profile/appointments')->json('data'))->pluck('id')->all();

        $this->assertSame([$near->id, $middle->id, $far->id], $ids);
    }

    public function test_past_is_sorted_most_recent_first(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $oldest = $this->makeAppointment($user, $this->daysFromToday(-10), '10:00');
        $newest = $this->makeAppointment($user, $this->daysFromToday(-1), '10:00');

        $ids = collect($this->getJson('/api/profile/appointments?scope=past')->json('data'))->pluck('id')->all();

        $this->assertSame([$newest->id, $oldest->id], $ids);
    }

    /**
     * An appointment still running counts as upcoming, not history — the
     * boundary is scheduled_end_time, not scheduled_time.
     */
    public function test_an_appointment_in_progress_today_is_still_upcoming(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Carbon::setTestNow(Carbon::today()->setTime(10, 30));

        $inProgress = $this->makeAppointment($user, Carbon::today()->format('Y-m-d'), '10:00');

        $ids = collect($this->getJson('/api/profile/appointments')->json('data'))->pluck('id')->all();
        $this->assertContains($inProgress->id, $ids);

        $pastIds = collect($this->getJson('/api/profile/appointments?scope=past')->json('data'))->pluck('id')->all();
        $this->assertNotContains($inProgress->id, $pastIds);

        Carbon::setTestNow();
    }

    public function test_an_appointment_finished_earlier_today_is_past(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Carbon::setTestNow(Carbon::today()->setTime(16, 0));

        $finished = $this->makeAppointment($user, Carbon::today()->format('Y-m-d'), '09:00');

        $pastIds = collect($this->getJson('/api/profile/appointments?scope=past')->json('data'))->pluck('id')->all();
        $this->assertContains($finished->id, $pastIds);

        $upcomingIds = collect($this->getJson('/api/profile/appointments')->json('data'))->pluck('id')->all();
        $this->assertNotContains($finished->id, $upcomingIds);

        Carbon::setTestNow();
    }

    public function test_cancelled_appointments_are_included_with_their_status(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $cancelled = $this->makeAppointment($user, $this->daysFromToday(4), '10:00', [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'slot_key' => null,
        ]);

        $row = collect($this->getJson('/api/profile/appointments')->json('data'))
            ->firstWhere('id', $cancelled->id);

        $this->assertNotNull($row);
        $this->assertSame('cancelled', $row['status']);
        $this->assertNotNull($row['cancelled_at']);
    }

    public function test_row_carries_service_detail_and_order_payment_state(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $service = $this->makeService();
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => 'paid']);

        $appointment = $this->makeAppointment($user, $this->daysFromToday(2), '11:00', [
            'service' => $service,
            'order_id' => $order->id,
            'deposit_amount_cents' => 2500,
        ]);

        $row = collect($this->getJson('/api/profile/appointments')->json('data'))
            ->firstWhere('id', $appointment->id);

        $this->assertSame($service->title, $row['service']['title']);
        $this->assertSame($service->slug, $row['service']['slug']);
        $this->assertSame(2500, $row['deposit_amount_cents']);
        $this->assertSame('paid', $row['order']['status']);
        // HH:MM regardless of the DB driver's TIME formatting.
        $this->assertSame('11:00', $row['scheduled_time']);
    }

    /**
     * The customer asking about their own agenda must not receive the admin
     * list's shape — MyAppointmentResource deliberately omits `user`.
     */
    public function test_does_not_expose_the_admin_resource_user_block(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->makeAppointment($user, $this->daysFromToday(2), '10:00');

        $row = $this->getJson('/api/profile/appointments')->json('data.0');

        $this->assertArrayNotHasKey('user', $row);
        $this->assertArrayNotHasKey('payment_mode', $row);
    }

    public function test_response_is_paginated(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->makeAppointment($user, $this->daysFromToday(2), '10:00');

        $this->getJson('/api/profile/appointments')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'per_page', 'total']]);
    }
}
