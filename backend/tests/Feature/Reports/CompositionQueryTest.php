<?php

namespace Tests\Feature\Reports;

use App\Models\Appointment;
use App\Models\Course;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Reports\Money\RevenueStreams;
use App\Reports\PeriodCalendar;
use App\Reports\Queries\CompositionQuery;
use App\Reports\ReportFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * CompositionQueryTest
 *
 * Reports testsuite (backend/phpunit.mysql.xml). Pins spec's "Composition
 * by order type" requirement: service/product/course totals plus retained
 * deposits reconcile, with no double count, against the grand total of
 * every stream RevenueStreams enumerates.
 */
class CompositionQueryTest extends TestCase
{
    use RefreshDatabase;

    private function query(): CompositionQuery
    {
        return new CompositionQuery(new RevenueStreams());
    }

    private function filterFor(string $from, string $to): ReportFilter
    {
        $request = Request::create('/api/admin/reports/composition', 'GET', [
            'from' => $from,
            'to' => $to,
            'granularity' => 'day',
        ]);

        return ReportFilter::fromRequest($request, new PeriodCalendar());
    }

    private function makeInstructor(): User
    {
        return User::factory()->instructor()->create();
    }

    private function makeAppointment(array $overrides = []): Appointment
    {
        $service = Service::factory()->create([
            'availability_type' => 'by_appointment',
            'is_published' => true,
            'price' => 80.00,
            'deposit_percentage' => 30,
        ]);
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

    public function test_type_totals_plus_retained_deposits_reconcile_with_no_double_count(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['instructor_id' => $this->makeInstructor()->id]);

        // course: 9900
        Order::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'type' => 'course',
            'client_transaction_id' => 'ORD-comp-course',
            'gateway' => 'fake',
            'amount_cents' => 9900,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => '2026-08-05 10:00:00',
        ]);

        // product: 4500
        Order::create([
            'user_id' => $user->id,
            'type' => 'product_cart',
            'client_transaction_id' => 'ORD-comp-product',
            'gateway' => 'fake',
            'amount_cents' => 4500,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => '2026-08-06 10:00:00',
        ]);

        // service (delivered): deposit 2000 + settlement 6000 = 8000
        $this->makeAppointment([
            'deposit_collected_cents' => 2000,
            'deposit_collected_at' => '2026-08-07 10:00:00',
            'settled_amount_cents' => 6000,
            'settled_at' => '2026-08-08 10:00:00',
            'status' => 'paid',
        ]);

        // retained deposit: 3000
        $this->makeAppointment([
            'deposit_collected_cents' => 3000,
            'deposit_collected_at' => '2026-08-09 10:00:00',
            'status' => 'cancelled',
        ]);

        $result = $this->query()->run($this->filterFor('2026-08-01', '2026-08-31'));

        $this->assertSame(9900, $result['by_type']['course']);
        $this->assertSame(4500, $result['by_type']['product']);
        $this->assertSame(8000, $result['by_type']['service']);
        $this->assertSame(3000, $result['retained_deposits_cents']);

        // Spec invariant: the three type totals PLUS retained deposits sum
        // to the total with no double count.
        $sumOfTypes = array_sum($result['by_type']);
        $this->assertSame($sumOfTypes + $result['retained_deposits_cents'], $result['total_cents']);
        $this->assertSame(9900 + 4500 + 8000 + 3000, $result['total_cents']);
    }

    public function test_composition_is_zero_when_nothing_happened_in_range(): void
    {
        $result = $this->query()->run($this->filterFor('2026-08-01', '2026-08-31'));

        $this->assertSame(0, $result['by_type']['course']);
        $this->assertSame(0, $result['by_type']['product']);
        $this->assertSame(0, $result['by_type']['service']);
        $this->assertSame(0, $result['retained_deposits_cents']);
        $this->assertSame(0, $result['total_cents']);
    }
}
