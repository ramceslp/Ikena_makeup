<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BestsellerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function instructor(): User
    {
        return User::factory()->instructor()->create();
    }

    private function publishedCourse(string $slug): Course
    {
        return Course::factory()->create([
            'instructor_id' => $this->instructor()->id,
            'is_published'  => true,
            'slug'          => $slug,
        ]);
    }

    private function addPaidOrders(Course $course, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            Order::factory()->paid()->create([
                'course_id' => $course->id,
                'user_id'   => User::factory()->create()->id,
            ]);
        }
    }

    private function addPendingOrders(Course $course, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            Order::factory()->pending()->create([
                'course_id' => $course->id,
                'user_id'   => User::factory()->create()->id,
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // is_bestseller on catalog cards
    // -------------------------------------------------------------------------

    public function test_course_with_most_paid_orders_is_bestseller(): void
    {
        $courseA = $this->publishedCourse('course-a');
        $courseB = $this->publishedCourse('course-b');
        $courseC = $this->publishedCourse('course-c');

        $this->addPaidOrders($courseA, 3);
        $this->addPaidOrders($courseB, 1);
        // courseC has 0 paid orders

        $response = $this->getJson('/api/courses')->assertStatus(200);

        $data = collect($response->json('data'));

        $this->assertTrue(
            $data->firstWhere('id', $courseA->id)['is_bestseller'],
            'Course A (3 paid) should be bestseller'
        );
        $this->assertFalse(
            $data->firstWhere('id', $courseB->id)['is_bestseller'],
            'Course B (1 paid) should NOT be bestseller'
        );
        $this->assertFalse(
            $data->firstWhere('id', $courseC->id)['is_bestseller'],
            'Course C (0 paid) should NOT be bestseller'
        );
    }

    public function test_pending_orders_do_not_count_toward_bestseller(): void
    {
        $courseA = $this->publishedCourse('course-pending-a');
        $courseB = $this->publishedCourse('course-pending-b');

        // courseA has 5 PENDING orders, courseB has 1 PAID order
        $this->addPendingOrders($courseA, 5);
        $this->addPaidOrders($courseB, 1);

        $response = $this->getJson('/api/courses')->assertStatus(200);
        $data = collect($response->json('data'));

        $this->assertFalse(
            $data->firstWhere('id', $courseA->id)['is_bestseller'],
            'Pending orders must not count'
        );
        $this->assertTrue(
            $data->firstWhere('id', $courseB->id)['is_bestseller'],
            'Course B with 1 paid order is the bestseller'
        );
    }

    public function test_tied_courses_at_max_are_all_bestsellers(): void
    {
        $courseA = $this->publishedCourse('tie-a');
        $courseB = $this->publishedCourse('tie-b');
        $courseC = $this->publishedCourse('tie-c');

        // A and B are tied at 2 paid orders each
        $this->addPaidOrders($courseA, 2);
        $this->addPaidOrders($courseB, 2);
        $this->addPaidOrders($courseC, 1);

        $response = $this->getJson('/api/courses')->assertStatus(200);
        $data = collect($response->json('data'));

        $this->assertTrue($data->firstWhere('id', $courseA->id)['is_bestseller']);
        $this->assertTrue($data->firstWhere('id', $courseB->id)['is_bestseller']);
        $this->assertFalse($data->firstWhere('id', $courseC->id)['is_bestseller']);
    }

    public function test_zero_paid_sales_means_no_bestseller(): void
    {
        $courseA = $this->publishedCourse('zero-a');
        $courseB = $this->publishedCourse('zero-b');

        // No paid orders at all
        $this->addPendingOrders($courseA, 3);

        $response = $this->getJson('/api/courses')->assertStatus(200);
        $data = collect($response->json('data'));

        $this->assertFalse($data->firstWhere('id', $courseA->id)['is_bestseller']);
        $this->assertFalse($data->firstWhere('id', $courseB->id)['is_bestseller']);
    }

    public function test_is_bestseller_field_present_for_unauthenticated_guest(): void
    {
        $course = $this->publishedCourse('guest-bestseller-test');

        $response = $this->getJson('/api/courses')->assertStatus(200);
        $item = $response->json('data.0');

        $this->assertArrayHasKey('is_bestseller', $item);
    }

    // -------------------------------------------------------------------------
    // is_bestseller on course detail
    // -------------------------------------------------------------------------

    public function test_course_detail_includes_is_bestseller_true(): void
    {
        $courseA = $this->publishedCourse('detail-best-a');
        $this->publishedCourse('detail-best-b'); // another published course

        $this->addPaidOrders($courseA, 5);

        $response = $this->getJson("/api/courses/{$courseA->slug}")->assertStatus(200);

        $this->assertTrue($response->json('data.is_bestseller'));
    }

    public function test_course_detail_includes_is_bestseller_false(): void
    {
        $courseA = $this->publishedCourse('detail-not-best-a');
        $courseB = $this->publishedCourse('detail-not-best-b');

        $this->addPaidOrders($courseB, 5);

        $response = $this->getJson("/api/courses/{$courseA->slug}")->assertStatus(200);

        $this->assertFalse($response->json('data.is_bestseller'));
    }
}
