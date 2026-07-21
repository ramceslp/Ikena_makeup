<?php

namespace Tests\Feature;

use App\Models\AgendaBlock;
use App\Models\Appointment;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * BookingTest
 *
 * Tests for:
 *  - GET /api/services/{serviceId}/available-slots  (public)
 *  - POST /api/bookings                             (auth required)
 *
 * Rewritten for the venue-agenda-concurrency change: bookable availability is
 * now defined by venue-wide AgendaBlocks (open_time/close_time + concurrency
 * caps) resolved via VenueAvailabilityResolver, instead of per-service
 * ServiceSlot rows. Covers BOOK-001–005 and DM-002.
 *
 * All use SQLite :memory: and PAYMENT_DRIVER=fake.
 */
class BookingTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    /**
     * Create a published by_appointment service.
     */
    private function makeService(float $price = 100.00, int $depositPct = 30, int $durationHours = 1): Service
    {
        return Service::factory()->create([
            'availability_type' => 'by_appointment',
            'is_published' => true,
            'price' => $price,
            'deposit_percentage' => $depositPct,
            'duration_hours' => $durationHours,
        ]);
    }

    /**
     * Create a venue-wide AgendaBlock covering today's weekday, wide open window
     * by default (09:00–18:00) so tests have room to place candidate times.
     */
    private function makeBlockForToday(array $overrides = []): AgendaBlock
    {
        return AgendaBlock::factory()->create(array_merge([
            'day_of_week' => Carbon::today()->dayOfWeek,
            'specific_date' => null,
            'open_time' => '09:00',
            'close_time' => '18:00',
            'concurrency_limit' => 2,
            'soft_threshold' => null,
            'is_blocked' => false,
        ], $overrides));
    }

    /**
     * Create a published by_appointment service plus a matching AgendaBlock for today.
     *
     * @return array{0: Service, 1: AgendaBlock}
     */
    private function makeBookableService(
        float $price = 100.00,
        int $depositPct = 30,
        int $durationHours = 1,
        array $blockOverrides = []
    ): array {
        $service = $this->makeService($price, $depositPct, $durationHours);
        $block = $this->makeBlockForToday($blockOverrides);

        return [$service, $block];
    }

    /**
     * Directly insert a non-cancelled appointment occupying [start, end) on the
     * given date, bypassing the API. Used to seed venue-wide overlap counts.
     */
    private function makeExistingAppointment(
        string $date,
        string $startTime,
        string $endTime,
        string $status = 'pending'
    ): Appointment {
        return Appointment::factory()->create([
            'scheduled_date' => $date,
            'scheduled_time' => $startTime,
            'scheduled_end_time' => $endTime,
            'status' => $status,
            'slot_key' => null,
        ]);
    }

    private function today(): string
    {
        return Carbon::today()->format('Y-m-d');
    }

    // -------------------------------------------------------------------------
    // GET /api/services/{serviceId}/available-slots
    // -------------------------------------------------------------------------

    public function test_available_slots_returns_slots_for_published_by_appointment_service(): void
    {
        [$service] = $this->makeBookableService();

        $response = $this->getJson("/api/services/{$service->id}/available-slots");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'date_label', 'start_time', 'capacity_remaining', 'is_near_capacity', 'warning_message']],
            ]);
    }

    public function test_available_slots_returns_404_for_unpublished_service(): void
    {
        $service = Service::factory()->create([
            'is_published' => false,
            'availability_type' => 'by_appointment',
        ]);

        $this->getJson("/api/services/{$service->id}/available-slots")
            ->assertStatus(404);
    }

    public function test_available_slots_returns_empty_for_immediate_type_service(): void
    {
        $service = Service::factory()->create([
            'availability_type' => 'immediate',
            'is_published' => true,
        ]);

        $response = $this->getJson("/api/services/{$service->id}/available-slots");

        $response->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_available_slots_slot_resource_includes_date_label_and_capacity_remaining(): void
    {
        [$service] = $this->makeBookableService();

        $response = $this->getJson("/api/services/{$service->id}/available-slots");

        $response->assertStatus(200);

        $firstSlot = $response->json('data.0');
        $this->assertArrayHasKey('date_label', $firstSlot);
        $this->assertArrayHasKey('capacity_remaining', $firstSlot);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}/', $firstSlot['date_label']);
        $this->assertEquals(2, $firstSlot['capacity_remaining']);
    }

    /**
     * VAVL-003 wired through SlotResource: occurrence entries expose
     * is_near_capacity / warning_message, false/null when no soft_threshold is set.
     */
    public function test_available_slots_includes_is_near_capacity_and_warning_message_fields(): void
    {
        [$service] = $this->makeBookableService(blockOverrides: ['soft_threshold' => null]);

        $response = $this->getJson("/api/services/{$service->id}/available-slots");

        $response->assertStatus(200);

        $firstSlot = $response->json('data.0');
        $this->assertArrayHasKey('is_near_capacity', $firstSlot);
        $this->assertArrayHasKey('warning_message', $firstSlot);
        $this->assertFalse($firstSlot['is_near_capacity']);
        $this->assertNull($firstSlot['warning_message']);
    }

    // -------------------------------------------------------------------------
    // GET /api/services/{serviceId}/available-slots?date=YYYY-MM-DD
    // -------------------------------------------------------------------------

    public function test_available_slots_date_param_returns_slots_for_single_day(): void
    {
        [$service] = $this->makeBookableService();
        $date = $this->today();

        $response = $this->getJson("/api/services/{$service->id}/available-slots?date={$date}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'date_label', 'start_time', 'capacity_remaining', 'is_near_capacity', 'warning_message']],
            ]);

        $dateLabels = collect($response->json('data'))->pluck('date_label')->unique()->values();
        $this->assertEquals([$date], $dateLabels->all());
    }

    /**
     * Backward-compatibility guard: the day-scoped resolver path must produce
     * the exact same occurrences as the corresponding slice of the full-window
     * path, proving resolve() and resolveDay() share one implementation.
     */
    public function test_available_slots_date_param_matches_full_window_subset_for_that_date(): void
    {
        [$service] = $this->makeBookableService();
        $date = $this->today();

        $full = $this->getJson("/api/services/{$service->id}/available-slots")->json('data');
        $scoped = $this->getJson("/api/services/{$service->id}/available-slots?date={$date}")->json('data');

        $fullForDate = collect($full)->where('date_label', $date)->values()->all();

        $this->assertNotEmpty($scoped);
        $this->assertEquals($fullForDate, $scoped);
    }

    public function test_available_slots_date_param_malformed_returns_422(): void
    {
        [$service] = $this->makeBookableService();

        $this->getJson("/api/services/{$service->id}/available-slots?date=not-a-date")
            ->assertStatus(422);
    }

    public function test_available_slots_date_param_out_of_range_future_returns_422(): void
    {
        [$service] = $this->makeBookableService();

        // Horizon is exclusive: today + look_ahead_days is the first out-of-range day.
        $lookAheadDays = (int) config('booking.venue.look_ahead_days');
        $outOfRange = Carbon::today()->addDays($lookAheadDays)->format('Y-m-d');

        $this->getJson("/api/services/{$service->id}/available-slots?date={$outOfRange}")
            ->assertStatus(422);
    }

    public function test_available_slots_date_param_past_date_returns_422(): void
    {
        [$service] = $this->makeBookableService();
        $past = Carbon::yesterday()->format('Y-m-d');

        $this->getJson("/api/services/{$service->id}/available-slots?date={$past}")
            ->assertStatus(422);
    }

    /**
     * Batched overlap counting must preserve the half-open interval semantics:
     * existing.start < proposed_end AND existing.end > proposed_start.
     * An appointment that merely touches the candidate's boundary (ends exactly
     * when the candidate starts) must NOT reduce capacity_remaining.
     */
    public function test_available_slots_date_param_touching_boundary_appointment_does_not_reduce_capacity(): void
    {
        [$service] = $this->makeBookableService(durationHours: 1, blockOverrides: ['concurrency_limit' => 1]);
        $date = $this->today();

        // Ends exactly at 10:00, when the candidate [10:00, 11:00) starts — no overlap.
        $this->makeExistingAppointment($date, '09:00', '10:00', 'pending');

        $response = $this->getJson("/api/services/{$service->id}/available-slots?date={$date}");
        $response->assertStatus(200);

        $slotAt10 = collect($response->json('data'))->firstWhere('start_time', '10:00');
        $this->assertNotNull($slotAt10);
        $this->assertEquals(1, $slotAt10['capacity_remaining']);
    }

    /**
     * A genuinely overlapping appointment must reduce capacity_remaining by
     * exactly the number of overlapping intervals (batched-count parity check).
     */
    public function test_available_slots_date_param_overlapping_appointment_reduces_capacity(): void
    {
        [$service] = $this->makeBookableService(durationHours: 1, blockOverrides: ['concurrency_limit' => 2]);
        $date = $this->today();

        // Overlaps candidate [10:00, 11:00) with [10:30, 11:30).
        $this->makeExistingAppointment($date, '10:30', '11:30', 'pending');

        $response = $this->getJson("/api/services/{$service->id}/available-slots?date={$date}");
        $slotAt10 = collect($response->json('data'))->firstWhere('start_time', '10:00');

        $this->assertNotNull($slotAt10);
        $this->assertEquals(1, $slotAt10['capacity_remaining']);
    }

    // -------------------------------------------------------------------------
    // POST /api/bookings — 401 unauthenticated
    // -------------------------------------------------------------------------

    public function test_booking_requires_authentication(): void
    {
        $this->postJson('/api/bookings', [])
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // POST /api/bookings — 422 validation
    // -------------------------------------------------------------------------

    public function test_booking_returns_422_for_immediate_type_service(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $service = Service::factory()->create([
            'availability_type' => 'immediate',
            'is_published' => true,
        ]);

        $this->postJson('/api/bookings', [
            'service_id' => $service->id,
            'scheduled_date' => now()->addDays(7)->format('Y-m-d'),
            'scheduled_time' => '10:00',
            'whatsapp' => '+593099912345',
        ])->assertStatus(422);
    }

    public function test_booking_returns_422_for_unpublished_service(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $service = Service::factory()->create([
            'availability_type' => 'by_appointment',
            'is_published' => false,
        ]);

        $this->postJson('/api/bookings', [
            'service_id' => $service->id,
            'scheduled_date' => now()->addDays(7)->format('Y-m-d'),
            'scheduled_time' => '10:00',
            'whatsapp' => '+593099912345',
        ])->assertStatus(422);
    }

    public function test_booking_returns_422_when_no_agenda_block_covers_requested_time(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        // No AgendaBlock created for this service's venue at all.
        $service = Service::factory()->create([
            'availability_type' => 'by_appointment',
            'is_published' => true,
        ]);

        $this->postJson('/api/bookings', [
            'service_id' => $service->id,
            'scheduled_date' => now()->addDays(7)->format('Y-m-d'),
            'scheduled_time' => '10:00',
            'whatsapp' => '+593099912345',
        ])->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // BOOK-001 — Booking accepted below hard cap; scheduled_end_time stored
    // -------------------------------------------------------------------------

    public function test_booking_success_creates_appointment_and_order_with_correct_deposit(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        // price=100, deposit_percentage=30 → deposit = 100 * 30/100 * 100 = 3000 cents
        [$service] = $this->makeBookableService(100.00, 30);

        $response = $this->postJson('/api/bookings', [
            'service_id' => $service->id,
            'scheduled_date' => $this->today(),
            'scheduled_time' => '10:00',
            'whatsapp' => '+593099912345',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['order_id', 'provider', 'config', 'is_near_capacity', 'warning_message'],
            ]);

        $response->assertJsonPath('data.is_near_capacity', false);
        $response->assertJsonPath('data.warning_message', null);

        // Appointment must exist
        $this->assertDatabaseHas('appointments', [
            'service_id' => $service->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        // Order must have correct deposit
        $orderId = $response->json('data.order_id');
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'amount_cents' => 3000,
            'appointment_id' => Appointment::where('service_id', $service->id)->value('id'),
            'course_id' => null,
        ]);
    }

    public function test_booking_deposit_with_price_200_and_25_percent(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        // price=200, deposit_percentage=25 → 200 * 25/100 * 100 = 5000 cents
        [$service] = $this->makeBookableService(200.00, 25);

        $response = $this->postJson('/api/bookings', [
            'service_id' => $service->id,
            'scheduled_date' => $this->today(),
            'scheduled_time' => '10:00',
            'whatsapp' => '+593099912345',
        ]);

        $response->assertStatus(201);

        $orderId = $response->json('data.order_id');
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'amount_cents' => 5000,
        ]);
    }

    /**
     * DM-001 / BOOK-001: scheduled_end_time = scheduled_time + service.duration_hours.
     */
    public function test_booking_stores_scheduled_end_time_equal_to_start_plus_duration(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        [$service] = $this->makeBookableService(durationHours: 2);

        $response = $this->postJson('/api/bookings', [
            'service_id' => $service->id,
            'scheduled_date' => $this->today(),
            'scheduled_time' => '10:00',
            'whatsapp' => '+593099912345',
        ]);

        $response->assertStatus(201);

        $appointment = Appointment::where('service_id', $service->id)->first();
        $this->assertNotNull($appointment);
        $this->assertSame('12:00', substr($appointment->scheduled_end_time, 0, 5));
    }

    // -------------------------------------------------------------------------
    // BOOK-002 — Hard cap rejection / acceptance at cap-1
    // -------------------------------------------------------------------------

    public function test_booking_returns_409_cap_exceeded_when_at_hard_cap(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        [$service] = $this->makeBookableService(blockOverrides: ['concurrency_limit' => 2]);

        $date = $this->today();

        // 2 non-cancelled appointments already overlap [10:00, 11:00) venue-wide.
        $this->makeExistingAppointment($date, '10:00', '11:00', 'pending');
        $this->makeExistingAppointment($date, '10:00', '11:00', 'confirmed');

        $response = $this->postJson('/api/bookings', [
            'service_id' => $service->id,
            'scheduled_date' => $date,
            'scheduled_time' => '10:00',
            'whatsapp' => '+593099912345',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('code', 'cap_exceeded');

        // No new appointment created for this service.
        $this->assertEquals(0, Appointment::where('service_id', $service->id)->count());
        $this->assertEquals(0, Order::where('user_id', $user->id)->count());
    }

    public function test_booking_succeeds_at_cap_minus_one(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        [$service] = $this->makeBookableService(blockOverrides: ['concurrency_limit' => 2]);

        $date = $this->today();

        // 1 existing overlapping appointment → overlap_count=1 < limit=2
        $this->makeExistingAppointment($date, '10:00', '11:00', 'pending');

        $response = $this->postJson('/api/bookings', [
            'service_id' => $service->id,
            'scheduled_date' => $date,
            'scheduled_time' => '10:00',
            'whatsapp' => '+593099912345',
        ]);

        $response->assertStatus(201);
        $this->assertEquals(1, Appointment::where('service_id', $service->id)->count());
    }

    // -------------------------------------------------------------------------
    // BOOK-003 — Soft threshold booking succeeds with warning flag
    // -------------------------------------------------------------------------

    public function test_booking_includes_warning_when_soft_threshold_reached(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        [$service] = $this->makeBookableService(blockOverrides: [
            'concurrency_limit' => 3,
            'soft_threshold' => 1,
        ]);

        $date = $this->today();

        // 1 existing overlapping appointment → overlap_count=1 >= soft_threshold=1, < limit=3
        $this->makeExistingAppointment($date, '10:00', '11:00', 'pending');

        $response = $this->postJson('/api/bookings', [
            'service_id' => $service->id,
            'scheduled_date' => $date,
            'scheduled_time' => '10:00',
            'whatsapp' => '+593099912345',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_near_capacity', true)
            ->assertJsonPath('data.warning_message', config('booking.venue.warning_message'));
    }

    // -------------------------------------------------------------------------
    // BOOK-004 — Duration overflow rejection / exact-fit boundary
    // -------------------------------------------------------------------------

    public function test_booking_returns_422_when_duration_exceeds_close_time(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        [$service] = $this->makeBookableService(durationHours: 2, blockOverrides: [
            'open_time' => '09:00',
            'close_time' => '12:00',
        ]);

        // 11:00 + 2h = 13:00 > close_time 12:00
        $this->postJson('/api/bookings', [
            'service_id' => $service->id,
            'scheduled_date' => $this->today(),
            'scheduled_time' => '11:00',
            'whatsapp' => '+593099912345',
        ])->assertStatus(422);

        $this->assertEquals(0, Appointment::where('service_id', $service->id)->count());
    }

    public function test_booking_accepted_when_duration_exactly_fits_close_time(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        [$service] = $this->makeBookableService(durationHours: 2, blockOverrides: [
            'open_time' => '09:00',
            'close_time' => '12:00',
        ]);

        // 10:00 + 2h = 12:00 == close_time 12:00 → boundary is valid
        $response = $this->postJson('/api/bookings', [
            'service_id' => $service->id,
            'scheduled_date' => $this->today(),
            'scheduled_time' => '10:00',
            'whatsapp' => '+593099912345',
        ]);

        $response->assertStatus(201);

        $appointment = Appointment::where('service_id', $service->id)->first();
        $this->assertSame('12:00', substr($appointment->scheduled_end_time, 0, 5));
    }

    // -------------------------------------------------------------------------
    // BOOK-005 — Race safety (sequential simulation at cap=1)
    // -------------------------------------------------------------------------

    public function test_sequential_requests_at_cap_one_second_request_is_rejected(): void
    {
        $user1 = $this->makeUser();
        $user2 = $this->makeUser();

        [$service] = $this->makeBookableService(blockOverrides: ['concurrency_limit' => 1]);

        $date = $this->today();

        Sanctum::actingAs($user1);
        $first = $this->postJson('/api/bookings', [
            'service_id' => $service->id,
            'scheduled_date' => $date,
            'scheduled_time' => '10:00',
            'whatsapp' => '+593099900001',
        ]);
        $first->assertStatus(201);

        Sanctum::actingAs($user2);
        $second = $this->postJson('/api/bookings', [
            'service_id' => $service->id,
            'scheduled_date' => $date,
            'scheduled_time' => '10:00',
            'whatsapp' => '+593099900002',
        ]);
        $second->assertStatus(409)
            ->assertJsonPath('code', 'cap_exceeded');

        $this->assertEquals(1, Appointment::where('service_id', $service->id)->count());
        $this->assertEquals(0, Order::where('user_id', $user2->id)->count());
    }

    // -------------------------------------------------------------------------
    // DM-002 — slot_key UNIQUE constraint removed
    // -------------------------------------------------------------------------

    public function test_two_bookings_same_service_date_time_both_succeed_when_capacity_allows(): void
    {
        $user1 = $this->makeUser();
        $user2 = $this->makeUser();

        [$service] = $this->makeBookableService(blockOverrides: ['concurrency_limit' => 2]);

        $date = $this->today();

        Sanctum::actingAs($user1);
        $first = $this->postJson('/api/bookings', [
            'service_id' => $service->id,
            'scheduled_date' => $date,
            'scheduled_time' => '10:00',
            'whatsapp' => '+593099900001',
        ]);
        $first->assertStatus(201);

        Sanctum::actingAs($user2);
        $second = $this->postJson('/api/bookings', [
            'service_id' => $service->id,
            'scheduled_date' => $date,
            'scheduled_time' => '10:00',
            'whatsapp' => '+593099900002',
        ]);
        $second->assertStatus(201);

        // whereDate() is required here (not plain where()) because scheduled_date
        // is a 'date'-cast Eloquent attribute stored with a time component on
        // SQLite — see VenueAvailabilityResolver::overlapCount() for the same convention.
        $this->assertEquals(2, Appointment::where('service_id', $service->id)
            ->whereDate('scheduled_date', $date)
            ->where('scheduled_time', '10:00')
            ->count());
    }
}
