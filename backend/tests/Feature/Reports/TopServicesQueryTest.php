<?php

namespace Tests\Feature\Reports;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use App\Reports\Money\RevenueStreams;
use App\Reports\PeriodCalendar;
use App\Reports\Queries\TopServicesQuery;
use App\Reports\ReportFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * TopServicesQueryTest
 *
 * Reports testsuite (backend/phpunit.mysql.xml). Pins spec's
 * "Revenue-per-hour for services" requirement ([Slice 4]): services are
 * ranked by DELIVERED revenue (deposit + settlement streams, retained
 * deposits excluded) divided by `services.duration_hours`, and carry no
 * margin figure at all.
 */
class TopServicesQueryTest extends TestCase
{
    use RefreshDatabase;

    private function query(): TopServicesQuery
    {
        return new TopServicesQuery(new RevenueStreams());
    }

    private function filterFor(string $from, string $to): ReportFilter
    {
        $request = Request::create('/api/admin/reports/rankings/services', 'GET', [
            'from' => $from,
            'to' => $to,
        ]);

        return ReportFilter::fromRequest($request, new PeriodCalendar());
    }

    private function makeAppointment(Service $service, array $overrides = []): Appointment
    {
        $user = User::factory()->create();

        return Appointment::create(array_merge([
            'service_id' => $service->id,
            'user_id' => $user->id,
            'order_id' => null,
            'scheduled_date' => '2026-08-10',
            'scheduled_time' => '10:00',
            'slot_key' => "{$service->id}|2026-08-10|10:00|".uniqid(),
            'whatsapp' => '+593099912345',
            'payment_mode' => 'gateway',
            'deposit_amount_cents' => 2400,
            'service_price_cents' => 8000,
            'status' => 'pending',
        ], $overrides));
    }

    public function test_revenue_per_hour_divides_delivered_revenue_by_duration_hours(): void
    {
        $service = Service::factory()->create(['duration_hours' => 2]);

        $this->makeAppointment($service, [
            'deposit_collected_cents' => 2000,
            'deposit_collected_at' => '2026-08-05 10:00:00',
            'settled_amount_cents' => 6000,
            'settled_at' => '2026-08-06 10:00:00',
            'status' => 'paid',
        ]);

        $result = $this->query()->run($this->filterFor('2026-08-01', '2026-08-10'));

        $this->assertCount(1, $result);
        $this->assertSame($service->id, $result[0]['service_id']);
        $this->assertSame(8000, $result[0]['revenue_cents']);
        $this->assertSame(2, $result[0]['duration_hours']);
        $this->assertSame(4000, $result[0]['revenue_per_hour_cents']);
        $this->assertArrayNotHasKey('margin_cents', $result[0]);
    }

    public function test_retained_deposits_are_excluded_from_service_revenue(): void
    {
        $service = Service::factory()->create(['duration_hours' => 1]);

        $this->makeAppointment($service, [
            'deposit_collected_cents' => 3000,
            'deposit_collected_at' => '2026-08-05 10:00:00',
            'status' => 'cancelled',
        ]);

        $result = $this->query()->run($this->filterFor('2026-08-01', '2026-08-10'));

        $this->assertSame([], $result);
    }

    public function test_ranking_orders_by_revenue_per_hour_descending(): void
    {
        $fast = Service::factory()->create(['duration_hours' => 1]);
        $slow = Service::factory()->create(['duration_hours' => 4]);

        // fast: 4000/1h = 4000/h
        $this->makeAppointment($fast, [
            'deposit_collected_cents' => 4000,
            'deposit_collected_at' => '2026-08-05 10:00:00',
            'status' => 'confirmed',
        ]);

        // slow: 8000/4h = 2000/h
        $this->makeAppointment($slow, [
            'deposit_collected_cents' => 8000,
            'deposit_collected_at' => '2026-08-06 10:00:00',
            'status' => 'confirmed',
        ]);

        $result = $this->query()->run($this->filterFor('2026-08-01', '2026-08-10'));

        $this->assertSame($fast->id, $result[0]['service_id']);
        $this->assertSame($slow->id, $result[1]['service_id']);
    }
}
