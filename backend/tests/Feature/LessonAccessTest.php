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

class LessonAccessTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build: instructor → course → section → lesson.
     * Returns [$course, $section, $lesson].
     */
    private function createLessonInCourse(array $lessonAttributes = []): array
    {
        $instructor = User::factory()->instructor()->create();
        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'is_published'  => true,
        ]);
        $section = Section::factory()->create(['course_id' => $course->id]);
        $lesson  = Lesson::factory()->create(array_merge(
            ['section_id' => $section->id, 'is_free' => false],
            $lessonAttributes
        ));

        return [$course, $section, $lesson];
    }

    private function enrollUser(User $user, Course $course): void
    {
        Enrollment::create([
            'user_id'    => $user->id,
            'course_id'  => $course->id,
            'price_paid' => 0,
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/lessons/{id}
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        [, , $lesson] = $this->createLessonInCourse(['is_free' => false]);

        // Route is protected — Sanctum returns 401 without token
        $response = $this->getJson("/api/lessons/{$lesson->id}");
        $response->assertStatus(401);
    }

    public function test_free_lesson_is_accessible_without_enrollment(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        [, , $lesson] = $this->createLessonInCourse([
            'is_free'   => true,
            'video_url' => 'https://example.com/free-video.mp4',
        ]);

        $response = $this->getJson("/api/lessons/{$lesson->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.id', $lesson->id)
                 ->assertJsonPath('data.video_url', 'https://example.com/free-video.mp4');
    }

    public function test_paid_lesson_returns_403_when_not_enrolled(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        [, , $lesson] = $this->createLessonInCourse(['is_free' => false]);

        $response = $this->getJson("/api/lessons/{$lesson->id}");

        $response->assertStatus(403)
                 ->assertJsonPath('message', 'You do not have access to this course.');
    }

    public function test_paid_lesson_returns_200_with_video_url_when_enrolled(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        [$course, , $lesson] = $this->createLessonInCourse([
            'is_free'   => false,
            'video_url' => 'https://example.com/paid-video.mp4',
        ]);
        $this->enrollUser($student, $course);

        $response = $this->getJson("/api/lessons/{$lesson->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.video_url', 'https://example.com/paid-video.mp4');
    }

    public function test_lesson_response_includes_correct_structure(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        [$course, $section, $lesson] = $this->createLessonInCourse([
            'is_free' => true,
        ]);

        $response = $this->getJson("/api/lessons/{$lesson->id}");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         'id', 'section_id', 'title', 'description',
                         'video_url', 'duration', 'position', 'is_free', 'completed',
                     ],
                 ]);
    }

    public function test_completed_is_false_for_free_lesson_when_not_enrolled(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        [, , $lesson] = $this->createLessonInCourse(['is_free' => true]);

        $response = $this->getJson("/api/lessons/{$lesson->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.completed', false);
    }

    public function test_completed_is_true_when_enrolled_and_lesson_marked_done(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        [$course, , $lesson] = $this->createLessonInCourse(['is_free' => false]);
        $this->enrollUser($student, $course);
        $student->completedLessons()->attach($lesson->id, ['completed_at' => now()]);

        $response = $this->getJson("/api/lessons/{$lesson->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.completed', true);
    }
}
