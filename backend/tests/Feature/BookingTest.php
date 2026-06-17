<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceSlot;
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
     * Create a published by_appointment service with a recurring slot for every day.
     */
    private function makeBookableService(float $price = 100.00, int $depositPct = 30): array
    {
        $service = Service::factory()->create([
            'availability_type'  => 'by_appointment',
            'is_published'       => true,
            'price'              => $price,
            'deposit_percentage' => $depositPct,
        ]);

        // A slot for every day of the week so we always have available dates
        $slot = ServiceSlot::factory()->create([
            'service_id'    => $service->id,
            'day_of_week'   => Carbon::today()->dayOfWeek, // today's day → next occurrence is today or next week
            'specific_date' => null,
            'start_time'    => '10:00',
            'capacity'      => 1,
            'is_blocked'    => false,
        ]);

        return [$service, $slot];
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
                     'data' => [['id', 'date_label', 'start_time', 'capacity_remaining']],
                 ]);
    }

    public function test_available_slots_returns_404_for_unpublished_service(): void
    {
        $service = Service::factory()->create([
            'is_published'      => false,
            'availability_type' => 'by_appointment',
        ]);

        $this->getJson("/api/services/{$service->id}/available-slots")
             ->assertStatus(404);
    }

    public function test_available_slots_returns_empty_for_immediate_type_service(): void
    {
        $service = Service::factory()->create([
            'availability_type' => 'immediate',
            'is_published'      => true,
        ]);

        $response = $this->getJson("/api/services/{$service->id}/available-slots");

        $response->assertStatus(200)
                 ->assertJsonPath('data', []);
    }

    public function test_available_slots_slot_resource_includes_date_label_and_capacity_remaining(): void
    {
        [$service, $slot] = $this->makeBookableService();

        $response = $this->getJson("/api/services/{$service->id}/available-slots");

        $response->assertStatus(200);

        $firstSlot = $response->json('data.0');
        $this->assertArrayHasKey('date_label', $firstSlot);
        $this->assertArrayHasKey('capacity_remaining', $firstSlot);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}/', $firstSlot['date_label']);
        $this->assertEquals(1, $firstSlot['capacity_remaining']);
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
            'is_published'      => true,
        ]);

        $this->postJson('/api/bookings', [
            'service_id'     => $service->id,
            'scheduled_date' => now()->addDays(7)->format('Y-m-d'),
            'scheduled_time' => '10:00',
            'whatsapp'       => '+593099912345',
        ])->assertStatus(422);
    }

    public function test_booking_returns_422_for_unpublished_service(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $service = Service::factory()->create([
            'availability_type' => 'by_appointment',
            'is_published'      => false,
        ]);

        $this->postJson('/api/bookings', [
            'service_id'     => $service->id,
            'scheduled_date' => now()->addDays(7)->format('Y-m-d'),
            'scheduled_time' => '10:00',
            'whatsapp'       => '+593099912345',
        ])->assertStatus(422);
    }

    public function test_booking_returns_422_when_slot_does_not_exist(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        // No slots created for this service
        $service = Service::factory()->create([
            'availability_type' => 'by_appointment',
            'is_published'      => true,
        ]);

        $this->postJson('/api/bookings', [
            'service_id'     => $service->id,
            'scheduled_date' => now()->addDays(7)->format('Y-m-d'),
            'scheduled_time' => '10:00',
            'whatsapp'       => '+593099912345',
        ])->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // POST /api/bookings — 201 success with correct deposit
    // -------------------------------------------------------------------------

    public function test_booking_success_creates_appointment_and_order_with_correct_deposit(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        // price=100, deposit_percentage=30 → deposit = 100 * 30/100 * 100 = 3000 cents
        [$service, $slot] = $this->makeBookableService(100.00, 30);

        // Get an available slot date
        $slotsResponse = $this->getJson("/api/services/{$service->id}/available-slots");
        $slotsResponse->assertStatus(200);
        $availableSlot = $slotsResponse->json('data.0');

        $this->assertNotNull($availableSlot, 'Expected at least one available slot');

        $response = $this->postJson('/api/bookings', [
            'service_id'     => $service->id,
            'scheduled_date' => $availableSlot['date_label'],
            'scheduled_time' => $availableSlot['start_time'],
            'whatsapp'       => '+593099912345',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'data' => ['order_id', 'provider', 'config'],
                 ]);

        // Appointment must exist
        $this->assertDatabaseHas('appointments', [
            'service_id' => $service->id,
            'user_id'    => $user->id,
            'status'     => 'pending',
        ]);

        // Order must have correct deposit
        $orderId = $response->json('data.order_id');
        $this->assertDatabaseHas('orders', [
            'id'             => $orderId,
            'amount_cents'   => 3000,
            'appointment_id' => Appointment::where('service_id', $service->id)->value('id'),
            'course_id'      => null,
        ]);
    }

    public function test_booking_deposit_with_price_200_and_25_percent(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        // price=200, deposit_percentage=25 → 200 * 25/100 * 100 = 5000 cents
        [$service, $slot] = $this->makeBookableService(200.00, 25);

        $slotsResponse = $this->getJson("/api/services/{$service->id}/available-slots");
        $availableSlot = $slotsResponse->json('data.0');

        $response = $this->postJson('/api/bookings', [
            'service_id'     => $service->id,
            'scheduled_date' => $availableSlot['date_label'],
            'scheduled_time' => $availableSlot['start_time'],
            'whatsapp'       => '+593099912345',
        ]);

        $response->assertStatus(201);

        $orderId = $response->json('data.order_id');
        $this->assertDatabaseHas('orders', [
            'id'           => $orderId,
            'amount_cents' => 5000,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/bookings — 409 slot collision, no orphan order
    // -------------------------------------------------------------------------

    public function test_booking_returns_409_on_slot_collision_and_no_orphan_order(): void
    {
        $user1 = $this->makeUser();
        $user2 = $this->makeUser();

        [$service, $slot] = $this->makeBookableService();

        // Get available slot
        $slotsResponse = $this->getJson("/api/services/{$service->id}/available-slots");
        $slotsResponse->assertStatus(200);
        $availableSlot = $slotsResponse->json('data.0');
        $this->assertNotNull($availableSlot, 'Expected at least one available slot for 409 test');

        $slotDate = $availableSlot['date_label'];
        $slotTime = $availableSlot['start_time'];

        // User 1 books successfully
        Sanctum::actingAs($user1);
        $first = $this->postJson('/api/bookings', [
            'service_id'     => $service->id,
            'scheduled_date' => $slotDate,
            'scheduled_time' => $slotTime,
            'whatsapp'       => '+593099900001',
        ]);
        $first->assertStatus(201);

        // User 2 tries to book the same slot → 409
        Sanctum::actingAs($user2);
        $second = $this->postJson('/api/bookings', [
            'service_id'     => $service->id,
            'scheduled_date' => $slotDate,
            'scheduled_time' => $slotTime,
            'whatsapp'       => '+593099900002',
        ]);
        $second->assertStatus(409);

        // Assert NO order was created for user2 at all
        $this->assertEquals(
            0,
            Order::where('user_id', $user2->id)->count(),
            'No orphan order should have been created for the second (colliding) user'
        );

        // Exactly 1 appointment for this slot (only user1's)
        $totalAppts = Appointment::where('service_id', $service->id)->count();
        $this->assertEquals(
            1,
            $totalAppts,
            "Expected 1 appointment for service_id={$service->id}, got {$totalAppts}"
        );
    }

    // -------------------------------------------------------------------------
    // FIX 7 — UniqueConstraintViolationException catch path pin
    //
    // Context: the pre-check (exists()) path is tested above. The catch block
    // inside DB::transaction handles race-condition collisions on MySQL (InnoDB).
    // SQLite does not reliably support savepoint-level rollback on unique violations
    // within a RefreshDatabase outer transaction, so we cannot force the catch
    // path to fire on SQLite without poisoning the outer transaction wrapper.
    //
    // This test pins the *code contract* of the catch path by asserting the 409
    // response via the pre-check path (same response shape) and documents that
    // the catch block is tested on MySQL only. The REGRESSION GUARD comment on
    // the catch block in BookingController::store() is the complementary guard.
    // -------------------------------------------------------------------------

    public function test_409_catch_path_response_shape_matches_pre_check_path(): void
    {
        $user1 = $this->makeUser();
        $user2 = $this->makeUser();

        [$service] = $this->makeBookableService();

        $slotsResponse = $this->getJson("/api/services/{$service->id}/available-slots");
        $slotsResponse->assertStatus(200);
        $availableSlot = $slotsResponse->json('data.0');
        $this->assertNotNull($availableSlot);

        $slotDate = $availableSlot['date_label'];
        $slotTime = $availableSlot['start_time'];

        // User1 takes the slot
        Sanctum::actingAs($user1);
        $this->postJson('/api/bookings', [
            'service_id'     => $service->id,
            'scheduled_date' => $slotDate,
            'scheduled_time' => $slotTime,
            'whatsapp'       => '+593099900001',
        ])->assertStatus(201);

        // User2's request hits the pre-check → 409 (same response shape the catch block returns)
        // NOTE: on MySQL, a true race condition would bypass the pre-check and the catch block
        // would return an identical 409 response. This test pins the response contract for both
        // paths. The catch block cannot be directly exercised on SQLite :memory:; see
        // REGRESSION GUARD comment in BookingController::store().
        Sanctum::actingAs($user2);
        $response = $this->postJson('/api/bookings', [
            'service_id'     => $service->id,
            'scheduled_date' => $slotDate,
            'scheduled_time' => $slotTime,
            'whatsapp'       => '+593099900002',
        ]);

        $response->assertStatus(409)
                 ->assertJsonStructure(['message'])
                 ->assertJsonPath('message', 'This slot is no longer available. Please choose another time.');

        // No orphan order for user2
        $this->assertEquals(0, Order::where('user_id', $user2->id)->count());
    }
}
