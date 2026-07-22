<?php

namespace Tests\Unit\Actions;

use App\Actions\CreateBookingAction;
use App\Exceptions\BookingCapacityExceededException;
use App\Exceptions\BookingSlotUnavailableException;
use App\Exceptions\ServiceUnavailableException;
use App\Models\AgendaBlock;
use App\Models\Appointment;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CreateBookingActionTest
 *
 * Covers the same capacity-recount scenarios that BookingTest already
 * exercises over HTTP, but calling App\Actions\CreateBookingAction directly —
 * this is the reusable unit that BookingController delegates to (and that
 * the checkout-handoff redeem endpoint, PR2, will reuse). Extraction must
 * not change these outcomes (BOOK-001/002/003/005).
 */
class CreateBookingActionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

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

    private function action(): CreateBookingAction
    {
        return app(CreateBookingAction::class);
    }

    public function test_no_agenda_block_covering_slot_throws_slot_unavailable_exception(): void
    {
        $user = $this->makeUser();
        // Published by_appointment service, but NO AgendaBlock created at all.
        $service = $this->makeService();

        $this->expectException(BookingSlotUnavailableException::class);

        ($this->action())($user, $service->id, $this->today(), '10:00', '+593099912345');
    }

    public function test_cap_exceeded_throws_capacity_exception_no_appointment_or_order_created(): void
    {
        $user = $this->makeUser();
        [$service] = $this->makeBookableService(blockOverrides: ['concurrency_limit' => 2]);

        $date = $this->today();
        $this->makeExistingAppointment($date, '10:00', '11:00', 'pending');
        $this->makeExistingAppointment($date, '10:00', '11:00', 'confirmed');

        try {
            ($this->action())($user, $service->id, $date, '10:00', '+593099912345');
            $this->fail('Expected BookingCapacityExceededException was not thrown.');
        } catch (BookingCapacityExceededException) {
            // expected
        }

        $this->assertEquals(0, Appointment::where('service_id', $service->id)->count());
        $this->assertEquals(0, Order::where('user_id', $user->id)->count());
    }

    public function test_success_creates_appointment_and_order_with_correct_deposit(): void
    {
        $user = $this->makeUser();
        // price=100, deposit_percentage=30 -> deposit = 100 * 30/100 * 100 = 3000 cents
        [$service] = $this->makeBookableService(100.00, 30);

        $result = ($this->action())($user, $service->id, $this->today(), '10:00', '+593099912345');

        $this->assertArrayHasKey('order', $result);
        $this->assertArrayHasKey('session', $result);
        $this->assertFalse($result['is_near_capacity']);

        $this->assertDatabaseHas('appointments', [
            'service_id' => $service->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $result['order']->id,
            'amount_cents' => 3000,
            'course_id' => null,
        ]);
    }

    public function test_scheduled_end_time_equals_start_plus_duration(): void
    {
        $user = $this->makeUser();
        [$service] = $this->makeBookableService(durationHours: 2);

        ($this->action())($user, $service->id, $this->today(), '10:00', '+593099912345');

        $appointment = Appointment::where('service_id', $service->id)->first();
        $this->assertNotNull($appointment);
        $this->assertSame('12:00', substr($appointment->scheduled_end_time, 0, 5));
    }

    public function test_includes_warning_when_soft_threshold_reached(): void
    {
        $user = $this->makeUser();
        [$service] = $this->makeBookableService(blockOverrides: [
            'concurrency_limit' => 3,
            'soft_threshold' => 1,
        ]);

        $date = $this->today();
        $this->makeExistingAppointment($date, '10:00', '11:00', 'pending');

        $result = ($this->action())($user, $service->id, $date, '10:00', '+593099912345');

        $this->assertTrue($result['is_near_capacity']);
    }

    // -------------------------------------------------------------------------
    // Re-validated business rules (fix #2) — mirrors StoreBookingRequest's
    // withValidator() checks, exercised here because CreateBookingAction is
    // called directly by the checkout-handoff redeem endpoint against a
    // snapshot that may be up to 10 minutes stale.
    // -------------------------------------------------------------------------

    public function test_unpublished_service_throws_service_unavailable_exception(): void
    {
        $user = $this->makeUser();
        [$service] = $this->makeBookableService();
        $service->update(['is_published' => false]);

        $this->expectException(ServiceUnavailableException::class);
        $this->expectExceptionMessage('The service is not published.');

        ($this->action())($user, $service->id, $this->today(), '10:00', '+593099912345');
    }

    public function test_non_by_appointment_service_throws_service_unavailable_exception(): void
    {
        $user = $this->makeUser();
        [$service] = $this->makeBookableService();
        $service->update(['availability_type' => 'walk_in']);

        $this->expectException(ServiceUnavailableException::class);
        $this->expectExceptionMessage('This service does not accept appointments.');

        ($this->action())($user, $service->id, $this->today(), '10:00', '+593099912345');
    }

    public function test_duration_overflowing_close_time_throws_service_unavailable_exception(): void
    {
        $user = $this->makeUser();
        // 2-hour service, block closes at 18:00 — a 17:00 start overflows to 19:00.
        [$service] = $this->makeBookableService(durationHours: 2, blockOverrides: [
            'open_time' => '09:00',
            'close_time' => '18:00',
        ]);

        $this->expectException(ServiceUnavailableException::class);
        $this->expectExceptionMessage('The requested duration exceeds the venue availability window.');

        ($this->action())($user, $service->id, $this->today(), '17:00', '+593099912345');
    }
}
