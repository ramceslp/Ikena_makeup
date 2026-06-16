<?php

namespace Tests\Feature\Instructor;

use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InstructorDashboardTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function instructor(): User
    {
        return User::factory()->instructor()->create();
    }

    private function student(): User
    {
        return User::factory()->create(['role' => 'student']);
    }

    private function courseFor(User $instructor, array $attrs = []): Course
    {
        return Course::factory()->create(array_merge(
            ['instructor_id' => $instructor->id, 'is_published' => false],
            $attrs
        ));
    }

    private function paidOrderFor(User $student, Course $course, int $amountCents = 5000, ?Carbon $paidAt = null): Order
    {
        return Order::factory()->paid()->create([
            'user_id'      => $student->id,
            'course_id'    => $course->id,
            'amount_cents' => $amountCents,
            'paid_at'      => $paidAt ?? now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // 1. Guest (unauthenticated) → 401
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/instructor/dashboard')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // 2. Student role → 403 (instructor middleware)
    // -------------------------------------------------------------------------

    public function test_student_role_returns_403(): void
    {
        Sanctum::actingAs($this->student());

        $this->getJson('/api/instructor/dashboard')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Instructor role required.');
    }

    // -------------------------------------------------------------------------
    // 3. Instructor with NO courses → 200, all zeros, 6 zero-filled months
    // -------------------------------------------------------------------------

    public function test_instructor_with_no_courses_returns_zero_kpis_and_six_months(): void
    {
        $instructor = $this->instructor();
        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/instructor/dashboard')->assertStatus(200);

        $response->assertJsonPath('data.kpis.total_revenue_cents', 0);
        $response->assertJsonPath('data.kpis.total_sales', 0);
        $response->assertJsonPath('data.kpis.total_students', 0);
        $response->assertJsonPath('data.kpis.total_courses', 0);
        $response->assertJsonPath('data.kpis.published_courses', 0);
        $response->assertJsonPath('data.kpis.currency', 'USD');

        $salesOverTime = $response->json('data.sales_over_time');
        $this->assertCount(6, $salesOverTime, 'sales_over_time must always have exactly 6 entries');

        foreach ($salesOverTime as $month) {
            $this->assertEquals(0, $month['revenue_cents']);
            $this->assertEquals(0, $month['sales']);
        }
    }

    // -------------------------------------------------------------------------
    // 4. Only PAID orders count — pending and failed must be excluded
    // -------------------------------------------------------------------------

    public function test_only_paid_orders_are_counted_in_revenue_and_sales(): void
    {
        $instructor = $this->instructor();
        $student    = $this->student();
        $course     = $this->courseFor($instructor);
        Sanctum::actingAs($instructor);

        // One paid order
        $this->paidOrderFor($student, $course, 5000);

        // One pending order — must NOT count
        Order::factory()->pending()->create([
            'user_id'      => $student->id,
            'course_id'    => $course->id,
            'amount_cents' => 3000,
        ]);

        // One failed order — must NOT count
        Order::factory()->create([
            'user_id'      => $student->id,
            'course_id'    => $course->id,
            'amount_cents' => 2000,
            'status'       => 'failed',
        ]);

        $response = $this->getJson('/api/instructor/dashboard')->assertStatus(200);

        $response->assertJsonPath('data.kpis.total_revenue_cents', 5000);
        $response->assertJsonPath('data.kpis.total_sales', 1);
    }

    // -------------------------------------------------------------------------
    // 5. Ownership isolation — other instructor's orders must NOT leak
    // -------------------------------------------------------------------------

    public function test_other_instructor_orders_do_not_leak_into_totals(): void
    {
        $instructor1 = $this->instructor();
        $instructor2 = $this->instructor();
        $student     = $this->student();

        $course1 = $this->courseFor($instructor1);
        $course2 = $this->courseFor($instructor2);

        // instructor1 has one paid order
        $this->paidOrderFor($student, $course1, 4000);

        // instructor2 has two paid orders — must NOT appear in instructor1's dashboard
        $this->paidOrderFor($student, $course2, 9000);
        $this->paidOrderFor($student, $course2, 9000);

        Sanctum::actingAs($instructor1);

        $response = $this->getJson('/api/instructor/dashboard')->assertStatus(200);

        $response->assertJsonPath('data.kpis.total_revenue_cents', 4000);
        $response->assertJsonPath('data.kpis.total_sales', 1);
    }

    // -------------------------------------------------------------------------
    // 6. total_students counts DISTINCT users across courses
    // -------------------------------------------------------------------------

    public function test_total_students_counts_distinct_users(): void
    {
        $instructor = $this->instructor();
        $student    = $this->student();
        $course1    = $this->courseFor($instructor);
        $course2    = $this->courseFor($instructor);
        Sanctum::actingAs($instructor);

        // Same student enrolled in BOTH courses — should count as 1
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course1->id, 'price_paid' => 0]);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course2->id, 'price_paid' => 0]);

        // Second student enrolled in one course — total distinct = 2
        $student2 = $this->student();
        Enrollment::create(['user_id' => $student2->id, 'course_id' => $course1->id, 'price_paid' => 0]);

        $response = $this->getJson('/api/instructor/dashboard')->assertStatus(200);

        $response->assertJsonPath('data.kpis.total_students', 2);
    }

    // -------------------------------------------------------------------------
    // 7. published_courses vs total_courses (mix of published + draft)
    // -------------------------------------------------------------------------

    public function test_published_and_total_courses_counts_are_correct(): void
    {
        $instructor = $this->instructor();
        Sanctum::actingAs($instructor);

        $this->courseFor($instructor, ['is_published' => true]);
        $this->courseFor($instructor, ['is_published' => true]);
        $this->courseFor($instructor, ['is_published' => false]);

        $response = $this->getJson('/api/instructor/dashboard')->assertStatus(200);

        $response->assertJsonPath('data.kpis.total_courses', 3);
        $response->assertJsonPath('data.kpis.published_courses', 2);
    }

    // -------------------------------------------------------------------------
    // 8. sales_over_time groups by month, zero-fills missing months
    // -------------------------------------------------------------------------

    public function test_sales_over_time_groups_by_month_and_zero_fills(): void
    {
        $instructor = $this->instructor();
        $student    = $this->student();
        $course     = $this->courseFor($instructor);
        Sanctum::actingAs($instructor);

        // Months relative to "now" (2026-06-15 per env)
        $twoMonthsAgo = Carbon::now()->subMonths(2)->startOfMonth()->addDays(5);
        $lastMonth    = Carbon::now()->subMonth()->startOfMonth()->addDays(10);

        // Two paid orders two months ago, one paid order last month
        $this->paidOrderFor($student, $course, 2000, $twoMonthsAgo);
        $this->paidOrderFor($student, $course, 3000, $twoMonthsAgo);
        $this->paidOrderFor($student, $course, 7000, $lastMonth);

        $response = $this->getJson('/api/instructor/dashboard')->assertStatus(200);

        $salesOverTime = $response->json('data.sales_over_time');
        $this->assertCount(6, $salesOverTime);

        // Build period keys
        $twoAgoKey  = $twoMonthsAgo->format('Y-m');
        $lastMonKey = $lastMonth->format('Y-m');

        // Index by period for easy lookup
        $byPeriod = collect($salesOverTime)->keyBy('period');

        $this->assertEquals(5000, $byPeriod[$twoAgoKey]['revenue_cents'],  "Revenue for $twoAgoKey should be 5000");
        $this->assertEquals(2,    $byPeriod[$twoAgoKey]['sales'],           "Sales for $twoAgoKey should be 2");

        $this->assertEquals(7000, $byPeriod[$lastMonKey]['revenue_cents'], "Revenue for $lastMonKey should be 7000");
        $this->assertEquals(1,    $byPeriod[$lastMonKey]['sales'],          "Sales for $lastMonKey should be 1");

        // All OTHER months must be zero
        foreach ($salesOverTime as $entry) {
            if (! in_array($entry['period'], [$twoAgoKey, $lastMonKey])) {
                $this->assertEquals(0, $entry['revenue_cents'], "Period {$entry['period']} should have 0 revenue");
                $this->assertEquals(0, $entry['sales'],          "Period {$entry['period']} should have 0 sales");
            }
        }
    }

    // -------------------------------------------------------------------------
    // Response structure contract
    // -------------------------------------------------------------------------

    public function test_response_has_expected_json_structure(): void
    {
        $instructor = $this->instructor();
        Sanctum::actingAs($instructor);

        $this->getJson('/api/instructor/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'kpis' => [
                        'total_revenue_cents',
                        'currency',
                        'total_sales',
                        'total_students',
                        'total_courses',
                        'published_courses',
                        'average_rating',
                    ],
                    'sales_over_time' => [
                        '*' => ['period', 'revenue_cents', 'sales'],
                    ],
                ],
            ]);
    }

    // -------------------------------------------------------------------------
    // average_rating KPI
    // -------------------------------------------------------------------------

    public function test_average_rating_reflects_reviews_across_instructor_courses(): void
    {
        $instructor = $this->instructor();
        $student1   = $this->student();
        $student2   = $this->student();

        $course1 = $this->courseFor($instructor);
        $course2 = $this->courseFor($instructor);

        CourseReview::factory()->create(['course_id' => $course1->id, 'user_id' => $student1->id, 'rating' => 4]);
        CourseReview::factory()->create(['course_id' => $course2->id, 'user_id' => $student2->id, 'rating' => 2]);

        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/instructor/dashboard')->assertStatus(200);

        // (4 + 2) / 2 = 3.0
        $this->assertEquals(3.0, $response->json('data.kpis.average_rating'));
    }

    public function test_average_rating_is_null_when_no_reviews(): void
    {
        $instructor = $this->instructor();
        $this->courseFor($instructor);

        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/instructor/dashboard')->assertStatus(200);

        $this->assertNull($response->json('data.kpis.average_rating'));
    }

    public function test_average_rating_isolates_own_courses_from_other_instructors(): void
    {
        $instructor1 = $this->instructor();
        $instructor2 = $this->instructor();
        $student     = $this->student();

        $course1 = $this->courseFor($instructor1);
        $course2 = $this->courseFor($instructor2);

        CourseReview::factory()->create(['course_id' => $course1->id, 'user_id' => $student->id, 'rating' => 5]);
        // instructor2's course gets a low rating — must NOT affect instructor1's average
        $student2 = $this->student();
        CourseReview::factory()->create(['course_id' => $course2->id, 'user_id' => $student2->id, 'rating' => 1]);

        Sanctum::actingAs($instructor1);

        $response = $this->getJson('/api/instructor/dashboard')->assertStatus(200);

        // instructor1 only has the rating=5 review — must equal 5.0, not (5+1)/2
        $this->assertEquals(5.0, $response->json('data.kpis.average_rating'));
    }
}
