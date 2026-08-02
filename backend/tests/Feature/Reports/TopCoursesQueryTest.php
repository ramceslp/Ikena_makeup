<?php

namespace Tests\Feature\Reports;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use App\Reports\Money\RevenueStreams;
use App\Reports\PeriodCalendar;
use App\Reports\Queries\TopCoursesQuery;
use App\Reports\ReportFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * TopCoursesQueryTest
 *
 * Reports testsuite (backend/phpunit.mysql.xml). Pins spec's "Top courses
 * ranking" requirement ([Slice 4]): ranked by PAID enrollment revenue,
 * excluding free (`price_paid=0`, no order) enrollments from the revenue
 * figure while still counting them in a separate total.
 */
class TopCoursesQueryTest extends TestCase
{
    use RefreshDatabase;

    private function query(): TopCoursesQuery
    {
        return new TopCoursesQuery(new RevenueStreams());
    }

    private function filterFor(string $from, string $to): ReportFilter
    {
        $request = Request::create('/api/admin/reports/rankings/courses', 'GET', [
            'from' => $from,
            'to' => $to,
        ]);

        return ReportFilter::fromRequest($request, new PeriodCalendar());
    }

    private function makeInstructor(): User
    {
        return User::factory()->instructor()->create();
    }

    public function test_free_enrollments_are_excluded_from_revenue_but_counted_separately(): void
    {
        $course = Course::factory()->create([
            'instructor_id' => $this->makeInstructor()->id,
            'price' => 0,
        ]);

        Enrollment::create([
            'user_id' => User::factory()->create()->id,
            'course_id' => $course->id,
            'price_paid' => 0,
        ]);

        $result = $this->query()->run($this->filterFor('2026-08-01', '2026-08-10'));

        $this->assertCount(1, $result);
        $this->assertSame(0, $result[0]['revenue_cents']);
        $this->assertSame(0, $result[0]['paid_enrollment_count']);
        $this->assertSame(1, $result[0]['free_enrollment_count']);
    }

    public function test_paid_enrollment_revenue_ranks_courses_descending(): void
    {
        $instructor = $this->makeInstructor();
        $courseA = Course::factory()->create(['instructor_id' => $instructor->id]);
        $courseB = Course::factory()->create(['instructor_id' => $instructor->id]);

        Order::create([
            'user_id' => User::factory()->create()->id,
            'course_id' => $courseA->id,
            'type' => 'course',
            'client_transaction_id' => 'ORD-topcourses-a',
            'gateway' => 'fake',
            'amount_cents' => 9900,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => '2026-08-05 10:00:00',
        ]);

        Order::create([
            'user_id' => User::factory()->create()->id,
            'course_id' => $courseB->id,
            'type' => 'course',
            'client_transaction_id' => 'ORD-topcourses-b',
            'gateway' => 'fake',
            'amount_cents' => 4900,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => '2026-08-06 10:00:00',
        ]);

        $result = $this->query()->run($this->filterFor('2026-08-01', '2026-08-10'));

        $this->assertSame($courseA->id, $result[0]['course_id']);
        $this->assertSame(9900, $result[0]['revenue_cents']);
        $this->assertSame(1, $result[0]['paid_enrollment_count']);
        $this->assertSame($courseB->id, $result[1]['course_id']);
    }

    public function test_pending_course_orders_never_count_as_revenue(): void
    {
        $course = Course::factory()->create(['instructor_id' => $this->makeInstructor()->id]);

        Order::create([
            'user_id' => User::factory()->create()->id,
            'course_id' => $course->id,
            'type' => 'course',
            'client_transaction_id' => 'ORD-topcourses-pending',
            'gateway' => 'fake',
            'amount_cents' => 9900,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $result = $this->query()->run($this->filterFor('2026-08-01', '2026-08-10'));

        $this->assertSame([], $result);
    }
}
