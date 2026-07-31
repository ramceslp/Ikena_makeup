<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Price is pinned to 0 because CourseFactory picks one at random from
     * [0, 9.99, 29.99, 49.99, 79.99], and POST /courses/{slug}/enroll only
     * serves FREE courses — a random paid price would make every enroll test
     * here flake on four runs out of five.
     */
    private function createCourseWithLessons(int $lessonCount = 3, float $price = 0): Course
    {
        $instructor = User::factory()->instructor()->create();
        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'is_published'  => true,
            'price'         => $price,
        ]);
        $section = Section::factory()->create(['course_id' => $course->id]);

        for ($i = 0; $i < $lessonCount; $i++) {
            Lesson::factory()->create([
                'section_id' => $section->id,
                'position'   => $i,
            ]);
        }

        return $course;
    }

    // -------------------------------------------------------------------------
    // POST /api/courses/{slug}/enroll
    // -------------------------------------------------------------------------

    public function test_enroll_requires_authentication(): void
    {
        $course = $this->createCourseWithLessons();

        $response = $this->postJson("/api/courses/{$course->slug}/enroll");
        $response->assertStatus(401);
    }

    public function test_enroll_creates_enrollment_and_returns_my_course_shape(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $course = $this->createCourseWithLessons(3);

        $response = $this->postJson("/api/courses/{$course->slug}/enroll");

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'data' => [
                         'id', 'title', 'slug', 'thumbnail', 'instructor',
                         'total_lessons', 'completed_lessons', 'progress_percentage',
                     ],
                 ]);

        $this->assertDatabaseHas('enrollments', [
            'user_id'   => $student->id,
            'course_id' => $course->id,
        ]);
    }

    /**
     * This endpoint hands out an Enrollment with no Order and no gateway. It
     * was reachable for PAID courses too, so any authenticated user could take
     * a paid course for free by calling it directly — the web client routed
     * paid courses to /checkout, but client-side routing is not an
     * authorization control.
     */
    public function test_enroll_refuses_a_paid_course(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $course = $this->createCourseWithLessons(3, 49.99);

        $this->postJson("/api/courses/{$course->slug}/enroll")
             ->assertStatus(422);

        $this->assertDatabaseMissing('enrollments', [
            'user_id'   => $student->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_enroll_is_idempotent_no_duplicate_enrollment(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $course = $this->createCourseWithLessons();

        $this->postJson("/api/courses/{$course->slug}/enroll");
        $response = $this->postJson("/api/courses/{$course->slug}/enroll");

        // Second call still succeeds (201)
        $response->assertStatus(201);

        // Exactly one enrollment row exists
        $this->assertEquals(
            1,
            Enrollment::where('user_id', $student->id)
                       ->where('course_id', $course->id)
                       ->count()
        );
    }

    public function test_enroll_returns_correct_total_lessons(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $course = $this->createCourseWithLessons(5);

        $response = $this->postJson("/api/courses/{$course->slug}/enroll");

        $response->assertStatus(201)
                 ->assertJsonPath('data.total_lessons', 5)
                 ->assertJsonPath('data.completed_lessons', 0)
                 ->assertJsonPath('data.progress_percentage', 0);
    }

    // -------------------------------------------------------------------------
    // GET /api/my-courses
    // -------------------------------------------------------------------------

    public function test_my_courses_requires_authentication(): void
    {
        $response = $this->getJson('/api/my-courses');
        $response->assertStatus(401);
    }

    public function test_my_courses_returns_enrolled_courses_with_progress(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $instructor = User::factory()->instructor()->create();
        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'is_published'  => true,
        ]);
        $section = Section::factory()->create(['course_id' => $course->id]);
        $lessons = Lesson::factory()->count(5)->create(['section_id' => $section->id]);

        Enrollment::create([
            'user_id'    => $student->id,
            'course_id'  => $course->id,
            'price_paid' => $course->price,
        ]);

        // Mark 2 lessons as completed
        $student->completedLessons()->attach($lessons[0]->id, ['completed_at' => now()]);
        $student->completedLessons()->attach($lessons[1]->id, ['completed_at' => now()]);

        $response = $this->getJson('/api/my-courses');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => [
                             'id', 'title', 'slug', 'thumbnail', 'instructor',
                             'total_lessons', 'completed_lessons', 'progress_percentage',
                         ],
                     ],
                 ]);

        $courseData = $response->json('data.0');
        $this->assertEquals(5, $courseData['total_lessons']);
        $this->assertEquals(2, $courseData['completed_lessons']);
        $this->assertEquals(40, $courseData['progress_percentage']); // round(2/5 * 100) = 40
    }

    /**
     * The mobile app has no lesson player and opens this link in the system
     * browser, so the API — not the app's build config — owns the web origin.
     */
    public function test_my_courses_exposes_an_absolute_web_player_url(): void
    {
        config(['app.frontend_url' => 'https://ikena.test']);

        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $course = $this->createCourseWithLessons(1);
        Enrollment::create([
            'user_id'    => $student->id,
            'course_id'  => $course->id,
            'price_paid' => $course->price,
        ]);

        $this->getJson('/api/my-courses')
             ->assertStatus(200)
             ->assertJsonPath('data.0.web_url', "https://ikena.test/learn/{$course->slug}");
    }

    public function test_my_courses_web_player_url_tolerates_a_trailing_slash_in_config(): void
    {
        config(['app.frontend_url' => 'https://ikena.test/']);

        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $course = $this->createCourseWithLessons(1);
        Enrollment::create([
            'user_id'    => $student->id,
            'course_id'  => $course->id,
            'price_paid' => $course->price,
        ]);

        $this->getJson('/api/my-courses')
             ->assertStatus(200)
             ->assertJsonPath('data.0.web_url', "https://ikena.test/learn/{$course->slug}");
    }

    public function test_my_courses_progress_percentage_is_zero_when_no_lessons_completed(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $course = $this->createCourseWithLessons(5);
        Enrollment::create([
            'user_id'    => $student->id,
            'course_id'  => $course->id,
            'price_paid' => 0,
        ]);

        $response = $this->getJson('/api/my-courses');
        $response->assertStatus(200);

        $courseData = $response->json('data.0');
        $this->assertEquals(0, $courseData['progress_percentage']);
        $this->assertEquals(0, $courseData['completed_lessons']);
    }

    public function test_my_courses_only_returns_courses_the_user_is_enrolled_in(): void
    {
        $student = User::factory()->create();
        $otherStudent = User::factory()->create();
        Sanctum::actingAs($student);

        $enrolledCourse = $this->createCourseWithLessons();
        $notEnrolledCourse = $this->createCourseWithLessons();

        Enrollment::create([
            'user_id'    => $student->id,
            'course_id'  => $enrolledCourse->id,
            'price_paid' => 0,
        ]);

        $response = $this->getJson('/api/my-courses');
        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($enrolledCourse->id, $ids);
        $this->assertNotContains($notEnrolledCourse->id, $ids);
    }
}
