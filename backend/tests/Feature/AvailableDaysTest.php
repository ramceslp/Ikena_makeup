<?php

namespace Tests\Feature;

use App\Models\AgendaBlock;
use App\Models\Appointment;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AvailableDaysTest
 *
 * Tests for GET /api/services/{serviceId}/available-days (public).
 *
 * Contract (see VenueAvailabilityResolver::resolveDaySummary docblock):
 *  - One row per calendar day in the look-ahead window that has at least one
 *    active AgendaBlock (day_of_week or specific_date match), shaped
 *    { date, available_count }.
 *  - Days with NO matching AgendaBlock at all (venue closed) are OMITTED
 *    entirely from the response.
 *  - Days WITH a matching AgendaBlock but zero bookable candidates (fully
 *    booked) are INCLUDED with available_count: 0.
 *  This distinguishes "venue closed" from "day exists but full" for the
 *  frontend calendar picker.
 */
class AvailableDaysTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(int $durationHours = 1): Service
    {
        return Service::factory()->create([
            'availability_type' => 'by_appointment',
            'is_published' => true,
            'duration_hours' => $durationHours,
        ]);
    }

    private function today(): string
    {
        return Carbon::today()->format('Y-m-d');
    }

    public function test_available_days_returns_date_and_available_count_shape(): void
    {
        $service = $this->makeService();
        AgendaBlock::factory()->create([
            'day_of_week' => Carbon::today()->dayOfWeek,
            'specific_date' => null,
            'open_time' => '09:00',
            'close_time' => '18:00',
            'concurrency_limit' => 2,
        ]);

        $response = $this->getJson("/api/services/{$service->id}/available-days");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['date', 'available_count']]]);
    }

    public function test_available_days_omits_days_with_no_agenda_block(): void
    {
        $service = $this->makeService();

        // Single specific_date block far in the future — every OTHER day in the
        // window has no matching block at all and must be omitted (venue closed).
        $onlyOpenDate = Carbon::today()->addDays(10)->format('Y-m-d');
        AgendaBlock::factory()->specificDate($onlyOpenDate)->create([
            'open_time' => '09:00',
            'close_time' => '18:00',
            'concurrency_limit' => 2,
        ]);

        $response = $this->getJson("/api/services/{$service->id}/available-days");
        $response->assertStatus(200);

        $dates = collect($response->json('data'))->pluck('date')->values();
        $this->assertEquals([$onlyOpenDate], $dates->all());

        $row = collect($response->json('data'))->firstWhere('date', $onlyOpenDate);
        $this->assertGreaterThan(0, $row['available_count']);
    }

    public function test_available_days_includes_fully_booked_day_with_zero_available_count(): void
    {
        $service = $this->makeService(durationHours: 1);
        $date = $this->today();

        // Single 1-hour candidate window (09:00-10:00), hard cap = 1, and one
        // existing appointment fills it — the day HAS a block but zero candidates.
        AgendaBlock::factory()->create([
            'day_of_week' => Carbon::today()->dayOfWeek,
            'specific_date' => null,
            'open_time' => '09:00',
            'close_time' => '10:00',
            'concurrency_limit' => 1,
        ]);

        Appointment::factory()->create([
            'scheduled_date' => $date,
            'scheduled_time' => '09:00',
            'scheduled_end_time' => '10:00',
            'status' => 'pending',
            'slot_key' => null,
        ]);

        $response = $this->getJson("/api/services/{$service->id}/available-days");
        $response->assertStatus(200);

        $today = collect($response->json('data'))->firstWhere('date', $date);

        $this->assertNotNull($today, 'Fully booked day must still appear (block exists) with available_count 0.');
        $this->assertEquals(0, $today['available_count']);
    }

    public function test_available_days_available_count_matches_slots_endpoint_count_for_same_day(): void
    {
        $service = $this->makeService();
        $date = $this->today();

        AgendaBlock::factory()->create([
            'day_of_week' => Carbon::today()->dayOfWeek,
            'specific_date' => null,
            'open_time' => '09:00',
            'close_time' => '12:00',
            'concurrency_limit' => 2,
        ]);

        $daysResponse = $this->getJson("/api/services/{$service->id}/available-days");
        $slotsResponse = $this->getJson("/api/services/{$service->id}/available-slots?date={$date}");

        $daysResponse->assertStatus(200);
        $slotsResponse->assertStatus(200);

        $availableCount = collect($daysResponse->json('data'))->firstWhere('date', $date)['available_count'];
        $slotCount = count($slotsResponse->json('data'));

        $this->assertEquals($slotCount, $availableCount);
        $this->assertGreaterThan(0, $slotCount);
    }

    public function test_available_days_returns_empty_array_for_immediate_type_service(): void
    {
        $service = Service::factory()->create([
            'availability_type' => 'immediate',
            'is_published' => true,
        ]);

        $response = $this->getJson("/api/services/{$service->id}/available-days");

        $response->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_available_days_returns_404_for_unpublished_service(): void
    {
        $service = Service::factory()->create([
            'is_published' => false,
            'availability_type' => 'by_appointment',
        ]);

        $this->getJson("/api/services/{$service->id}/available-days")
            ->assertStatus(404);
    }
}
