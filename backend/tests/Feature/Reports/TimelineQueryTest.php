<?php

namespace Tests\Feature\Reports;

use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use App\Reports\Money\RevenueStreams;
use App\Reports\PeriodCalendar;
use App\Reports\Queries\TimelineQuery;
use App\Reports\ReportFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * TimelineQueryTest
 *
 * Reports testsuite (backend/phpunit.mysql.xml) — the SAME fixtures and
 * assertions run against SQLite (default CI) and MySQL
 * (backend-tests-mysql), which is what actually proves "identical grouping
 * across drivers" (spec's admin-reporting requirement): the query emits
 * exactly one ANSI `SUM(CASE WHEN occurred_at >= ? AND occurred_at < ?)`
 * per period, with zero driver-specific date functions, so there is no
 * branch left to diverge in the first place (design D3).
 */
class TimelineQueryTest extends TestCase
{
    use RefreshDatabase;

    private function query(): TimelineQuery
    {
        return new TimelineQuery(new RevenueStreams());
    }

    private function filterFor(string $from, string $to, string $granularity = 'day'): ReportFilter
    {
        $request = Request::create('/api/admin/reports/timeline', 'GET', [
            'from' => $from,
            'to' => $to,
            'granularity' => $granularity,
        ]);

        return ReportFilter::fromRequest($request, new PeriodCalendar());
    }

    private function makeInstructor(): User
    {
        return User::factory()->instructor()->create();
    }

    public function test_timeline_groups_by_paid_at_not_created_at(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['instructor_id' => $this->makeInstructor()->id]);

        // Created on the 1st, but only actually PAID on the 3rd — must land
        // in the 3rd's bucket, not the 1st's.
        Order::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'type' => 'course',
            'client_transaction_id' => 'ORD-timeline-late-pay',
            'gateway' => 'fake',
            'amount_cents' => 9900,
            'currency' => 'USD',
            'status' => 'paid',
            'created_at' => '2026-08-01 09:00:00',
            'paid_at' => '2026-08-03 09:00:00',
        ]);

        $result = $this->query()->run($this->filterFor('2026-08-01', '2026-08-05'));

        $byLabel = collect($result)->keyBy('label');

        $this->assertSame(0, $byLabel['2026-08-01']['by_stream']['course_sale']);
        $this->assertSame(9900, $byLabel['2026-08-03']['by_stream']['course_sale']);
    }

    public function test_abandoned_cart_with_null_paid_at_contributes_zero(): void
    {
        $user = User::factory()->create();

        // Checkout started, never completed — paid_at stays NULL. status is
        // still 'pending', which already excludes it from course_sale, but
        // this pins the NULL-paid_at case explicitly (spec scenario).
        Order::create([
            'user_id' => $user->id,
            'type' => 'product_cart',
            'client_transaction_id' => 'ORD-timeline-abandoned',
            'gateway' => 'fake',
            'amount_cents' => 3000,
            'currency' => 'USD',
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $result = $this->query()->run($this->filterFor('2026-08-01', '2026-08-05'));

        $total = array_sum(array_column($result, 'total_cents'));
        $this->assertSame(0, $total);
    }

    public function test_timeline_is_zero_filled_across_every_period_with_no_data(): void
    {
        // `to` is an inclusive calendar date (ReportFilter converts it to
        // the exclusive half-open bound) — 08-01..08-03 inclusive is 3 days.
        $result = $this->query()->run($this->filterFor('2026-08-01', '2026-08-03'));

        $this->assertCount(3, $result);
        foreach ($result as $bucket) {
            $this->assertSame(0, $bucket['total_cents']);
        }
        $this->assertSame(['2026-08-01', '2026-08-02', '2026-08-03'], array_column($result, 'label'));
    }

    public function test_timeline_buckets_sum_to_the_summary_total_for_the_same_range(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['instructor_id' => $this->makeInstructor()->id]);

        Order::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'type' => 'course',
            'client_transaction_id' => 'ORD-timeline-sum-a',
            'gateway' => 'fake',
            'amount_cents' => 2000,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => '2026-08-01 10:00:00',
        ]);

        Order::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'type' => 'course',
            'client_transaction_id' => 'ORD-timeline-sum-b',
            'gateway' => 'fake',
            'amount_cents' => 3000,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => '2026-08-03 10:00:00',
        ]);

        $result = $this->query()->run($this->filterFor('2026-08-01', '2026-08-05'));

        $this->assertSame(5000, array_sum(array_column($result, 'total_cents')));
    }
}
