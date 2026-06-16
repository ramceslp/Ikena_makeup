<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseReviewTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers — mirror LessonProgressTest / EnrollmentTest conventions
    // -------------------------------------------------------------------------

    /**
     * Create an instructor-owned published course with one section and N lessons.
     *
     * @return array{0: Course, 1: User, 2: \Illuminate\Database\Eloquent\Collection}
     */
    private function createCourseWithLessons(int $count = 2): array
    {
        $instructor = User::factory()->instructor()->create();
        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'is_published'  => true,
        ]);
        $section = Section::factory()->create(['course_id' => $course->id]);
        $lessons = Lesson::factory()->count($count)->create([
            'section_id' => $section->id,
        ]);

        return [$course, $instructor, $lessons];
    }

    private function enroll(User $user, Course $course): void
    {
        Enrollment::create([
            'user_id'    => $user->id,
            'course_id'  => $course->id,
            'price_paid' => 0,
        ]);
    }

    private function completeLesson(User $user, Lesson $lesson): void
    {
        $user->completedLessons()->attach($lesson->id, ['completed_at' => now()]);
    }

    // -------------------------------------------------------------------------
    // 1. GET /api/courses/{slug}/reviews — Public, paginated, latest first
    // -------------------------------------------------------------------------

    public function test_get_reviews_is_public_and_returns_paginated_json(): void
    {
        [$course, $instructor, $lessons] = $this->createCourseWithLessons();

        $student1 = User::factory()->create();
        $student2 = User::factory()->create();

        CourseReview::factory()->create([
            'course_id'  => $course->id,
            'user_id'    => $student1->id,
            'rating'     => 5,
            'body'       => 'Excellent!',
            'created_at' => now()->subDays(2),
        ]);

        CourseReview::factory()->create([
            'course_id'  => $course->id,
            'user_id'    => $student2->id,
            'rating'     => 3,
            'body'       => 'Decent.',
            'created_at' => now()->subDay(),
        ]);

        $response = $this->getJson("/api/courses/{$course->slug}/reviews");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => [
                             'id',
                             'rating',
                             'body',
                             'created_at',
                             'user' => ['id', 'name', 'avatar'],
                         ],
                     ],
                     'links',
                     'meta',
                 ]);

        // Latest first: student2's review (subDay) comes before student1's (subDays(2))
        $ids = collect($response->json('data'))->pluck('user.id')->toArray();
        $this->assertEquals([$student2->id, $student1->id], $ids);
    }

    // -------------------------------------------------------------------------
    // 2. POST without auth → 401
    // -------------------------------------------------------------------------

    public function test_post_review_without_auth_returns_401(): void
    {
        [$course] = $this->createCourseWithLessons();

        $this->postJson("/api/courses/{$course->slug}/reviews", [
            'rating' => 5,
            'body'   => 'Great!',
        ])->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // 3. POST by eligible user (enrolled + ≥1 completed lesson)
    // -------------------------------------------------------------------------

    public function test_eligible_user_can_create_review(): void
    {
        [$course, $instructor, $lessons] = $this->createCourseWithLessons();

        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->enroll($student, $course);
        $this->completeLesson($student, $lessons[0]);

        $response = $this->postJson("/api/courses/{$course->slug}/reviews", [
            'rating' => 4,
            'body'   => 'Very helpful!',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.rating', 4)
                 ->assertJsonPath('data.body', 'Very helpful!');

        $this->assertDatabaseHas('course_reviews', [
            'course_id' => $course->id,
            'user_id'   => $student->id,
            'rating'    => 4,
            'body'      => 'Very helpful!',
        ]);
    }

    // -------------------------------------------------------------------------
    // 4. POST by enrolled user with 0 completed lessons → 403
    // -------------------------------------------------------------------------

    public function test_enrolled_user_with_no_completed_lessons_gets_403(): void
    {
        [$course, $instructor, $lessons] = $this->createCourseWithLessons();

        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->enroll($student, $course);
        // No lesson completed

        $this->postJson("/api/courses/{$course->slug}/reviews", [
            'rating' => 5,
        ])->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // 5. POST by user NOT enrolled → 403
    // -------------------------------------------------------------------------

    public function test_not_enrolled_user_gets_403(): void
    {
        [$course] = $this->createCourseWithLessons();

        $student = User::factory()->create();
        Sanctum::actingAs($student);
        // Not enrolled at all

        $this->postJson("/api/courses/{$course->slug}/reviews", [
            'rating' => 5,
        ])->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // 6. POST by the course's own instructor → 403
    // -------------------------------------------------------------------------

    public function test_instructor_cannot_review_own_course(): void
    {
        [$course, $instructor] = $this->createCourseWithLessons();
        Sanctum::actingAs($instructor);

        $this->postJson("/api/courses/{$course->slug}/reviews", [
            'rating' => 5,
        ])->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // 7. POST validation: missing rating, rating=0, rating=6 → 422; body optional
    // -------------------------------------------------------------------------

    public function test_missing_rating_returns_422(): void
    {
        [$course, $instructor, $lessons] = $this->createCourseWithLessons();

        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->enroll($student, $course);
        $this->completeLesson($student, $lessons[0]);

        $this->postJson("/api/courses/{$course->slug}/reviews", [])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['rating']);
    }

    public function test_rating_zero_returns_422(): void
    {
        [$course, $instructor, $lessons] = $this->createCourseWithLessons();

        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->enroll($student, $course);
        $this->completeLesson($student, $lessons[0]);

        $this->postJson("/api/courses/{$course->slug}/reviews", ['rating' => 0])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['rating']);
    }

    public function test_rating_six_returns_422(): void
    {
        [$course, $instructor, $lessons] = $this->createCourseWithLessons();

        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->enroll($student, $course);
        $this->completeLesson($student, $lessons[0]);

        $this->postJson("/api/courses/{$course->slug}/reviews", ['rating' => 6])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['rating']);
    }

    public function test_body_is_optional(): void
    {
        [$course, $instructor, $lessons] = $this->createCourseWithLessons();

        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->enroll($student, $course);
        $this->completeLesson($student, $lessons[0]);

        $this->postJson("/api/courses/{$course->slug}/reviews", ['rating' => 5])
             ->assertStatus(201);
    }

    // -------------------------------------------------------------------------
    // 8. POST twice → upsert: only ONE row, rating updated
    // -------------------------------------------------------------------------

    public function test_posting_twice_upserts_and_does_not_duplicate(): void
    {
        [$course, $instructor, $lessons] = $this->createCourseWithLessons();

        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->enroll($student, $course);
        $this->completeLesson($student, $lessons[0]);

        $this->postJson("/api/courses/{$course->slug}/reviews", ['rating' => 3, 'body' => 'OK']);
        $response = $this->postJson("/api/courses/{$course->slug}/reviews", ['rating' => 5, 'body' => 'Actually great!']);

        // Only one row exists
        $this->assertEquals(
            1,
            CourseReview::where('course_id', $course->id)
                        ->where('user_id', $student->id)
                        ->count()
        );

        // Rating is updated to latest value
        $response->assertStatus(200)
                 ->assertJsonPath('data.rating', 5)
                 ->assertJsonPath('data.body', 'Actually great!');
    }

    // -------------------------------------------------------------------------
    // 9. DELETE own review → 204, row gone
    // -------------------------------------------------------------------------

    public function test_delete_own_review_returns_204(): void
    {
        [$course, $instructor, $lessons] = $this->createCourseWithLessons();

        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->enroll($student, $course);
        $this->completeLesson($student, $lessons[0]);

        CourseReview::factory()->create([
            'course_id' => $course->id,
            'user_id'   => $student->id,
            'rating'    => 4,
        ]);

        $this->deleteJson("/api/courses/{$course->slug}/reviews")
             ->assertStatus(204);

        $this->assertDatabaseMissing('course_reviews', [
            'course_id' => $course->id,
            'user_id'   => $student->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // 10. DELETE when user has no review → 404
    // -------------------------------------------------------------------------

    public function test_delete_when_no_review_returns_404(): void
    {
        [$course] = $this->createCourseWithLessons();

        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->deleteJson("/api/courses/{$course->slug}/reviews")
             ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // 11. Aggregates on course detail and catalog
    // -------------------------------------------------------------------------

    public function test_course_detail_returns_aggregate_fields_and_my_review(): void
    {
        [$course, $instructor, $lessons] = $this->createCourseWithLessons();

        $student1 = User::factory()->create();
        $student2 = User::factory()->create();

        CourseReview::factory()->create(['course_id' => $course->id, 'user_id' => $student1->id, 'rating' => 4]);
        CourseReview::factory()->create(['course_id' => $course->id, 'user_id' => $student2->id, 'rating' => 2]);

        // Authenticated as student1
        Sanctum::actingAs($student1);

        $response = $this->getJson("/api/courses/{$course->slug}");
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertArrayHasKey('average_rating', $data);
        $this->assertArrayHasKey('reviews_count', $data);
        $this->assertArrayHasKey('my_review', $data);

        $this->assertEquals(3.0, $data['average_rating']); // (4+2)/2 = 3.0
        $this->assertEquals(2, $data['reviews_count']);

        // my_review should reflect student1's review
        $this->assertNotNull($data['my_review']);
        $this->assertEquals(4, $data['my_review']['rating']);
    }

    public function test_course_detail_my_review_is_null_for_guest(): void
    {
        [$course, $instructor, $lessons] = $this->createCourseWithLessons();

        $student = User::factory()->create();
        CourseReview::factory()->create(['course_id' => $course->id, 'user_id' => $student->id, 'rating' => 5]);

        // Guest (unauthenticated)
        $response = $this->getJson("/api/courses/{$course->slug}");
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNull($data['my_review']);
        $this->assertEquals(5.0, $data['average_rating']);
    }

    public function test_catalog_returns_average_rating_and_reviews_count_on_cards(): void
    {
        [$course, $instructor, $lessons] = $this->createCourseWithLessons();

        $student1 = User::factory()->create();
        $student2 = User::factory()->create();

        CourseReview::factory()->create(['course_id' => $course->id, 'user_id' => $student1->id, 'rating' => 5]);
        CourseReview::factory()->create(['course_id' => $course->id, 'user_id' => $student2->id, 'rating' => 3]);

        $response = $this->getJson('/api/courses');
        $response->assertStatus(200);

        $item = collect($response->json('data'))->firstWhere('id', $course->id);
        $this->assertNotNull($item);
        $this->assertArrayHasKey('average_rating', $item);
        $this->assertArrayHasKey('reviews_count', $item);
        $this->assertEquals(4.0, $item['average_rating']); // (5+3)/2 = 4.0
        $this->assertEquals(2, $item['reviews_count']);
    }

    public function test_average_rating_is_null_when_no_reviews(): void
    {
        [$course] = $this->createCourseWithLessons();

        $response = $this->getJson("/api/courses/{$course->slug}");
        $response->assertStatus(200);

        $this->assertNull($response->json('data.average_rating'));
        $this->assertEquals(0, $response->json('data.reviews_count'));
    }
}
