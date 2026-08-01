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

/**
 * Who can see a live session's join link, and when.
 *
 * The link is the only credential protecting a paid room, so the window rules
 * are asserted from the student's side of the API rather than by unit-testing
 * the model in isolation.
 */
class LiveSessionAccessTest extends TestCase
{
    use RefreshDatabase;

    private function liveCourseWithSession(string $startsAt): array
    {
        $course  = Course::factory()->live()->create();
        $section = Section::factory()->create(['course_id' => $course->id]);
        $lesson  = Lesson::factory()->live($startsAt)->create(['section_id' => $section->id]);

        return [$course, $lesson];
    }

    private function enrolledStudent(Course $course): User
    {
        $student = User::factory()->create(['role' => 'student']);

        Enrollment::create([
            'user_id'    => $student->id,
            'course_id'  => $course->id,
            'price_paid' => 0,
        ]);

        return $student;
    }

    // -------------------------------------------------------------------------
    // The meeting window
    // -------------------------------------------------------------------------

    public function test_link_is_hidden_long_before_the_session(): void
    {
        [$course, $lesson] = $this->liveCourseWithSession('2026-09-01 19:00:00');

        Sanctum::actingAs($this->enrolledStudent($course));
        $this->travelTo('2026-09-01 12:00:00');

        $this->getJson("/api/lessons/{$lesson->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.meeting_url', null)
            // The student still learns WHEN to come back. Sessions are stored
            // in the app timezone (America/Guayaquil) and serialized as UTC,
            // so 18:45 local — 15 min before the session — is 23:45Z.
            ->assertJsonPath('data.meeting_available_at', '2026-09-01T23:45:00.000000Z');
    }

    public function test_link_appears_within_the_lead_window(): void
    {
        [$course, $lesson] = $this->liveCourseWithSession('2026-09-01 19:00:00');

        Sanctum::actingAs($this->enrolledStudent($course));
        $this->travelTo('2026-09-01 18:50:00');

        $this->getJson("/api/lessons/{$lesson->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.meeting_url', 'https://meet.google.com/abc-defg-hij');
    }

    public function test_link_is_available_during_the_session(): void
    {
        [$course, $lesson] = $this->liveCourseWithSession('2026-09-01 19:00:00');

        Sanctum::actingAs($this->enrolledStudent($course));
        $this->travelTo('2026-09-01 20:00:00');

        $this->getJson("/api/lessons/{$lesson->id}")
            ->assertJsonPath('data.meeting_url', 'https://meet.google.com/abc-defg-hij');
    }

    /**
     * The factory's live() state runs 5400s (90 min), so 20:35 is past the end.
     */
    public function test_link_is_withdrawn_after_the_session_ends(): void
    {
        [$course, $lesson] = $this->liveCourseWithSession('2026-09-01 19:00:00');

        Sanctum::actingAs($this->enrolledStudent($course));
        $this->travelTo('2026-09-01 20:35:00');

        $this->getJson("/api/lessons/{$lesson->id}")
            ->assertJsonPath('data.meeting_url', null);
    }

    public function test_non_enrolled_user_gets_403_and_never_sees_the_link(): void
    {
        [$course, $lesson] = $this->liveCourseWithSession('2026-09-01 19:00:00');

        Sanctum::actingAs(User::factory()->create(['role' => 'student']));
        $this->travelTo('2026-09-01 18:50:00');

        $this->getJson("/api/lessons/{$lesson->id}")
            ->assertStatus(403);
    }

    public function test_on_demand_lesson_reports_no_live_session(): void
    {
        $course  = Course::factory()->create();
        $section = Section::factory()->create(['course_id' => $course->id]);
        $lesson  = Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($this->enrolledStudent($course));

        $this->getJson("/api/lessons/{$lesson->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.is_live_session', false)
            ->assertJsonPath('data.meeting_url', null);
    }

    // -------------------------------------------------------------------------
    // Progress on a live course is attendance, not self-service
    // -------------------------------------------------------------------------

    public function test_student_cannot_self_mark_a_live_lesson(): void
    {
        [$course, $lesson] = $this->liveCourseWithSession('2026-09-01 19:00:00');
        $student = $this->enrolledStudent($course);

        Sanctum::actingAs($student);

        $this->postJson("/api/lessons/{$lesson->id}/complete")
            ->assertStatus(403)
            ->assertJsonPath('message', 'Attendance for a live course is recorded by the instructor.');

        $this->assertDatabaseMissing('lesson_progress', [
            'user_id'   => $student->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_student_can_still_self_mark_an_on_demand_lesson(): void
    {
        $course  = Course::factory()->create();
        $section = Section::factory()->create(['course_id' => $course->id]);
        $lesson  = Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($this->enrolledStudent($course));

        $this->postJson("/api/lessons/{$lesson->id}/complete")
            ->assertStatus(200)
            ->assertJsonPath('data.completed', true);
    }
}
