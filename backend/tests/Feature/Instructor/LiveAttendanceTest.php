<?php

namespace Tests\Feature\Instructor;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Instructor-recorded attendance for live sessions, and the certificate that
 * hangs off it.
 */
class LiveAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function enroll(User $student, Course $course): void
    {
        Enrollment::create([
            'user_id'    => $student->id,
            'course_id'  => $course->id,
            'price_paid' => 0,
        ]);
    }

    private function liveCourse(User $instructor, array $attrs = []): Course
    {
        return Course::factory()->live()->create(array_merge(
            ['instructor_id' => $instructor->id],
            $attrs
        ));
    }

    // -------------------------------------------------------------------------
    // Roster
    // -------------------------------------------------------------------------

    public function test_roster_lists_enrolled_students_with_attendance_flag(): void
    {
        $instructor = User::factory()->instructor()->create();
        $course     = $this->liveCourse($instructor);
        $section    = Section::factory()->create(['course_id' => $course->id]);
        $lesson     = Lesson::factory()->live()->create(['section_id' => $section->id]);

        $ana  = User::factory()->create(['role' => 'student', 'name' => 'Ana']);
        $beto = User::factory()->create(['role' => 'student', 'name' => 'Beto']);
        $this->enroll($ana, $course);
        $this->enroll($beto, $course);

        Sanctum::actingAs($instructor);

        $this->getJson("/api/instructor/lessons/{$lesson->id}/attendance")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Ana')
            ->assertJsonPath('data.0.attended', false);
    }

    public function test_another_instructor_cannot_read_the_roster(): void
    {
        $owner     = User::factory()->instructor()->create();
        $intruder  = User::factory()->instructor()->create();
        $course    = $this->liveCourse($owner);
        $section   = Section::factory()->create(['course_id' => $course->id]);
        $lesson    = Lesson::factory()->live()->create(['section_id' => $section->id]);

        Sanctum::actingAs($intruder);

        $this->getJson("/api/instructor/lessons/{$lesson->id}/attendance")
            ->assertStatus(403);
    }

    public function test_attendance_is_rejected_on_an_on_demand_course(): void
    {
        $instructor = User::factory()->instructor()->create();
        $course     = Course::factory()->create(['instructor_id' => $instructor->id]);
        $section    = Section::factory()->create(['course_id' => $course->id]);
        $lesson     = Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($instructor);

        $this->getJson("/api/instructor/lessons/{$lesson->id}/attendance")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Attendance is only recorded for live courses.');
    }

    // -------------------------------------------------------------------------
    // Recording
    // -------------------------------------------------------------------------

    public function test_instructor_marks_attendance(): void
    {
        $instructor = User::factory()->instructor()->create();
        $course     = $this->liveCourse($instructor);
        $section    = Section::factory()->create(['course_id' => $course->id]);
        $lesson     = Lesson::factory()->live()->create(['section_id' => $section->id]);

        $student = User::factory()->create(['role' => 'student']);
        $this->enroll($student, $course);

        Sanctum::actingAs($instructor);

        $this->putJson("/api/instructor/lessons/{$lesson->id}/attendance", [
            'user_ids' => [$student->id],
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.0.attended', true);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id'   => $student->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_unmarking_a_student_removes_the_progress_row(): void
    {
        $instructor = User::factory()->instructor()->create();
        $course     = $this->liveCourse($instructor);
        $section    = Section::factory()->create(['course_id' => $course->id]);
        $lesson     = Lesson::factory()->live()->create(['section_id' => $section->id]);

        $student = User::factory()->create(['role' => 'student']);
        $this->enroll($student, $course);

        Sanctum::actingAs($instructor);

        $this->putJson("/api/instructor/lessons/{$lesson->id}/attendance", [
            'user_ids' => [$student->id],
        ])->assertStatus(200);

        $this->putJson("/api/instructor/lessons/{$lesson->id}/attendance", [
            'user_ids' => [],
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.0.attended', false);

        $this->assertDatabaseMissing('lesson_progress', [
            'user_id'   => $student->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    /**
     * A stray id here would mint lesson_progress for someone who never bought
     * the course — and lesson_progress is exactly what the certificate gate
     * counts.
     */
    public function test_marking_a_non_enrolled_user_is_rejected(): void
    {
        $instructor = User::factory()->instructor()->create();
        $course     = $this->liveCourse($instructor);
        $section    = Section::factory()->create(['course_id' => $course->id]);
        $lesson     = Lesson::factory()->live()->create(['section_id' => $section->id]);

        $outsider = User::factory()->create(['role' => 'student']);

        Sanctum::actingAs($instructor);

        $this->putJson("/api/instructor/lessons/{$lesson->id}/attendance", [
            'user_ids' => [$outsider->id],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_ids']);

        $this->assertDatabaseMissing('lesson_progress', [
            'user_id'   => $outsider->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // The payoff: attendance drives the existing certificate gate unchanged
    // -------------------------------------------------------------------------

    public function test_full_attendance_yields_a_certificate(): void
    {
        $instructor = User::factory()->instructor()->create();
        $course     = $this->liveCourse($instructor, ['offers_certificate' => true]);
        $section    = Section::factory()->create(['course_id' => $course->id]);
        $lessons    = Lesson::factory()->live()->count(2)->create(['section_id' => $section->id]);

        $student = User::factory()->create(['role' => 'student']);
        $this->enroll($student, $course);

        Sanctum::actingAs($instructor);
        foreach ($lessons as $lesson) {
            $this->putJson("/api/instructor/lessons/{$lesson->id}/attendance", [
                'user_ids' => [$student->id],
            ])->assertStatus(200);
        }

        Sanctum::actingAs($student);

        $this->getJson("/api/courses/{$course->slug}/certificate")
            ->assertStatus(201);

        $this->assertDatabaseHas('certificates', [
            'user_id'   => $student->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_partial_attendance_withholds_the_certificate(): void
    {
        $instructor = User::factory()->instructor()->create();
        $course     = $this->liveCourse($instructor, ['offers_certificate' => true]);
        $section    = Section::factory()->create(['course_id' => $course->id]);
        $lessons    = Lesson::factory()->live()->count(2)->create(['section_id' => $section->id]);

        $student = User::factory()->create(['role' => 'student']);
        $this->enroll($student, $course);

        Sanctum::actingAs($instructor);
        $this->putJson("/api/instructor/lessons/{$lessons[0]->id}/attendance", [
            'user_ids' => [$student->id],
        ])->assertStatus(200);

        Sanctum::actingAs($student);

        $this->getJson("/api/courses/{$course->slug}/certificate")
            ->assertStatus(403);

        $this->assertSame(0, Certificate::where('user_id', $student->id)->count());
    }
}
