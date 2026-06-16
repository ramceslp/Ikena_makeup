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

class LessonProgressTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create a course with N lessons and return [$course, $lessons].
     *
     * @return array{0: Course, 1: \Illuminate\Database\Eloquent\Collection}
     */
    private function createCourseWithLessons(int $count = 3): array
    {
        $instructor = User::factory()->instructor()->create();
        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'is_published'  => true,
        ]);
        $section = Section::factory()->create(['course_id' => $course->id]);
        $lessons = Lesson::factory()->count($count)->create([
            'section_id' => $section->id,
            'is_free'    => false,
        ]);

        return [$course, $lessons];
    }

    private function enroll(User $user, Course $course): void
    {
        Enrollment::create([
            'user_id'    => $user->id,
            'course_id'  => $course->id,
            'price_paid' => 0,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/lessons/{id}/complete
    // -------------------------------------------------------------------------

    public function test_complete_requires_authentication(): void
    {
        [, $lessons] = $this->createCourseWithLessons();

        $response = $this->postJson("/api/lessons/{$lessons[0]->id}/complete");
        $response->assertStatus(401);
    }

    public function test_complete_returns_403_when_not_enrolled(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        [, $lessons] = $this->createCourseWithLessons();

        $response = $this->postJson("/api/lessons/{$lessons[0]->id}/complete");
        $response->assertStatus(403)
                 ->assertJsonPath('message', 'You do not have access to this course.');
    }

    public function test_first_complete_call_marks_lesson_as_completed(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        [$course, $lessons] = $this->createCourseWithLessons(3);
        $this->enroll($student, $course);

        $response = $this->postJson("/api/lessons/{$lessons[0]->id}/complete");

        $response->assertStatus(200)
                 ->assertJsonPath('data.completed', true)
                 ->assertJsonPath('data.lesson_id', $lessons[0]->id);
    }

    public function test_second_complete_call_toggles_lesson_to_uncompleted(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        [$course, $lessons] = $this->createCourseWithLessons(3);
        $this->enroll($student, $course);

        $this->postJson("/api/lessons/{$lessons[0]->id}/complete"); // → completed = true
        $response = $this->postJson("/api/lessons/{$lessons[0]->id}/complete"); // → completed = false

        $response->assertStatus(200)
                 ->assertJsonPath('data.completed', false);

        // Pivot row should be gone from lesson_progress
        $this->assertDatabaseMissing('lesson_progress', [
            'user_id'   => $student->id,
            'lesson_id' => $lessons[0]->id,
        ]);
    }

    public function test_complete_recalculates_progress_percentage(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        [$course, $lessons] = $this->createCourseWithLessons(5);
        $this->enroll($student, $course);

        // Complete 2 out of 5
        $this->postJson("/api/lessons/{$lessons[0]->id}/complete");
        $response = $this->postJson("/api/lessons/{$lessons[1]->id}/complete");

        $response->assertStatus(200);

        $progress = $response->json('data.progress');
        $this->assertEquals(5, $progress['total_lessons']);
        $this->assertEquals(2, $progress['completed_lessons']);
        $this->assertEquals(40, $progress['percentage']); // round(2/5 * 100) = 40
    }

    public function test_complete_response_has_full_progress_structure(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        [$course, $lessons] = $this->createCourseWithLessons(3);
        $this->enroll($student, $course);

        $response = $this->postJson("/api/lessons/{$lessons[0]->id}/complete");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         'lesson_id',
                         'completed',
                         'progress' => [
                             'completed_lessons',
                             'total_lessons',
                             'percentage',
                         ],
                     ],
                 ]);
    }

    public function test_toggling_off_decreases_progress_percentage(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        [$course, $lessons] = $this->createCourseWithLessons(4);
        $this->enroll($student, $course);

        // Complete 2 lessons
        $this->postJson("/api/lessons/{$lessons[0]->id}/complete");
        $this->postJson("/api/lessons/{$lessons[1]->id}/complete");

        // Toggle first one off
        $response = $this->postJson("/api/lessons/{$lessons[0]->id}/complete");

        $response->assertStatus(200);
        $progress = $response->json('data.progress');

        // 1 completed out of 4 = 25%
        $this->assertEquals(1, $progress['completed_lessons']);
        $this->assertEquals(25, $progress['percentage']);
    }
}
