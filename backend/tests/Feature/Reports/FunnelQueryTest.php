<?php

namespace Tests\Feature\Reports;

use App\Models\Appointment;
use App\Models\Course;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Reports\Money\RevenueStreams;
use App\Reports\PeriodCalendar;
use App\Reports\Queries\FunnelQuery;
use App\Reports\ReportFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * FunnelQueryTest
 *
 * Reports testsuite (backend/phpunit.mysql.xml). Pins spec's "Order-status
 * funnel" requirement ([Slice 4]): a count of orders per normalized status
 * with no status split across two spellings (design D5 already normalizes
 * every writer to 'canceled', single L), plus its appointment-status
 * sibling.
 */
class FunnelQueryTest extends TestCase
{
    use RefreshDatabase;

    private function query(): FunnelQuery
    {
        return new FunnelQuery(new RevenueStreams());
    }

    private function filterFor(string $from, string $to): ReportFilter
    {
        $request = Request::create('/api/admin/reports/funnel', 'GET', [
            'from' => $from,
            'to' => $to,
        ]);

        return ReportFilter::fromRequest($request, new PeriodCalendar());
    }

    private function makeInstructor(): User
    {
        return User::factory()->instructor()->create();
    }

    public function test_order_funnel_counts_every_status_with_zero_fill(): void
    {
        $course = Course::factory()->create(['instructor_id' => $this->makeInstructor()->id]);

        // created_at is not mass-assignable — force it inside the filter range,
        // rather than relying on it coincidentally matching whenever the
        // suite happens to run.
        Order::create([
            'user_id' => User::factory()->create()->id,
            'course_id' => $course->id,
            'type' => 'course',
            'client_transaction_id' => 'ORD-funnel-paid',
            'gateway' => 'fake',
            'amount_cents' => 9900,
            'currency' => 'USD',
            'status' => 'paid',
        ])->forceFill(['created_at' => '2026-08-05 10:00:00'])->save();

        Order::create([
            'user_id' => User::factory()->create()->id,
            'course_id' => $course->id,
            'type' => 'course',
            'client_transaction_id' => 'ORD-funnel-pending',
            'gateway' => 'fake',
            'amount_cents' => 9900,
            'currency' => 'USD',
            'status' => 'pending',
        ])->forceFill(['created_at' => '2026-08-05 10:00:00'])->save();

        $result = $this->query()->run($this->filterFor('2026-08-01', '2026-08-10'));

        $this->assertSame([
            'pending' => 1,
            'paid' => 1,
            'failed' => 0,
            'canceled' => 0,
        ], $result['orders']);
    }

    public function test_appointment_funnel_counts_every_status_with_zero_fill(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create();

        Appointment::create([
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
            'status' => 'confirmed',
        ])->forceFill(['created_at' => '2026-08-05 10:00:00'])->save();

        $result = $this->query()->run($this->filterFor('2026-08-01', '2026-08-10'));

        $this->assertSame([
            'pending' => 0,
            'confirmed' => 1,
            'paid' => 0,
            'cancelled' => 0,
        ], $result['appointments']);
    }

    public function test_events_outside_the_filter_range_are_excluded(): void
    {
        $course = Course::factory()->create(['instructor_id' => $this->makeInstructor()->id]);

        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'course_id' => $course->id,
            'type' => 'course',
            'client_transaction_id' => 'ORD-funnel-outside',
            'gateway' => 'fake',
            'amount_cents' => 9900,
            'currency' => 'USD',
            'status' => 'paid',
        ]);
        // created_at is not mass-assignable — force it outside the filter range.
        $order->forceFill(['created_at' => '2026-07-01 10:00:00'])->save();

        $result = $this->query()->run($this->filterFor('2026-08-01', '2026-08-10'));

        $this->assertSame(0, array_sum($result['orders']));
    }
}
