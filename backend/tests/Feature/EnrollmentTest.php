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

    private function createCourseWithLessons(int $lessonCount = 3): Course
    {
        $instructor = User::factory()->instructor()->create();
        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'is_published'  => true,
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
