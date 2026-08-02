<?php

namespace Tests\Feature\Reports;

use App\Models\Appointment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Reports\Granularity;
use App\Reports\Money\RevenueStreams;
use App\Reports\PeriodCalendar;
use App\Reports\Queries\SummaryQuery;
use App\Reports\ReportFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * SummaryQueryTest
 *
 * Reports testsuite (backend/phpunit.mysql.xml) — runs on SQLite AND MySQL.
 * Pins spec's "KPI summary" requirement: confirmed revenue (delivered
 * services + product + course sales), retained deposits reported
 * separately (never merged into delivered-service revenue — spec's
 * "Retained deposits on cancellation" requirement), and free-course
 * enrollments counted apart from every revenue figure.
 */
class SummaryQueryTest extends TestCase
{
    use RefreshDatabase;

    private function query(): SummaryQuery
    {
        return new SummaryQuery(new RevenueStreams());
    }

    private function filterFor(string $from, string $to): ReportFilter
    {
        $request = Request::create('/api/admin/reports/summary', 'GET', [
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

    public function test_confirmed_revenue_reconciles_against_a_hand_summed_fixture_and_excludes_retained_deposits(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['instructor_id' => $this->makeInstructor()->id]);

        // course_sale: 9900
        Order::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'type' => 'course',
            'client_transaction_id' => 'ORD-summary-course',
            'gateway' => 'fake',
            'amount_cents' => 9900,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => '2026-08-05 10:00:00',
        ]);

        // product_sale: 4500
        Order::create([
            'user_id' => $user->id,
            'type' => 'product_cart',
            'client_transaction_id' => 'ORD-summary-product',
            'gateway' => 'fake',
            'amount_cents' => 4500,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => '2026-08-06 10:00:00',
        ]);

        // Delivered service: deposit 2000 + settlement 6000 = 8000
        $this->makeAppointment([
            'deposit_collected_cents' => 2000,
            'deposit_collected_at' => '2026-08-07 10:00:00',
            'settled_amount_cents' => 6000,
            'settled_at' => '2026-08-08 10:00:00',
            'status' => 'paid',
        ]);

        // Retained deposit (cancelled after deposit): 3000 — real money, but
        // must be excluded from confirmed_revenue_cents.
        $this->makeAppointment([
            'deposit_collected_cents' => 3000,
            'deposit_collected_at' => '2026-08-09 10:00:00',
            'status' => 'cancelled',
        ]);

        $result = $this->query()->run($this->filterFor('2026-08-01', '2026-08-31'));

        $this->assertSame(9900 + 4500 + 2000 + 6000, $result['confirmed_revenue_cents']);
        $this->assertSame(3000, $result['retained_deposits_cents']);
        $this->assertSame(9900, $result['by_stream']['course_sale']);
        $this->assertSame(4500, $result['by_stream']['product_sale']);
        $this->assertSame(2000, $result['by_stream']['appointment_deposit']);
        $this->assertSame(6000, $result['by_stream']['appointment_settlement']);
        $this->assertSame(3000, $result['by_stream']['appointment_deposit_retained']);
        // orders_count only counts paid orders.paid_at rows (course+product) —
        // appointment money never enumerates orders (D4).
        $this->assertSame(2, $result['orders_count']);
    }

    public function test_free_course_enrollments_are_counted_separately_and_never_inside_revenue(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create([
            'instructor_id' => $this->makeInstructor()->id,
            'price' => 0,
        ]);

        Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'price_paid' => 0,
        ]);

        $result = $this->query()->run($this->filterFor('2026-08-01', '2026-08-31'));

        $this->assertSame(1, $result['free_enrollments_count']);
        $this->assertSame(0, $result['confirmed_revenue_cents']);
    }

    public function test_events_outside_the_filter_range_are_excluded(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['instructor_id' => $this->makeInstructor()->id]);

        Order::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'type' => 'course',
            'client_transaction_id' => 'ORD-summary-outside',
            'gateway' => 'fake',
            'amount_cents' => 9900,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => '2026-07-15 10:00:00', // before the August filter
        ]);

        $result = $this->query()->run($this->filterFor('2026-08-01', '2026-08-31'));

        $this->assertSame(0, $result['confirmed_revenue_cents']);
        $this->assertSame(0, $result['orders_count']);
    }
}
