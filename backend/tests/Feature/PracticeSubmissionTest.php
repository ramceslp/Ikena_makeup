<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\PracticeSubmission;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PracticeSubmissionTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createPracticeCourse(): array
    {
        $instructor = User::factory()->instructor()->create();
        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'is_published'  => true,
        ]);
        $section = Section::factory()->create(['course_id' => $course->id]);
        $practiceLesson = Lesson::factory()->create([
            'section_id'  => $section->id,
            'is_practice' => true,
        ]);
        $regularLesson = Lesson::factory()->create([
            'section_id'  => $section->id,
            'is_practice' => false,
        ]);

        return [$instructor, $course, $section, $practiceLesson, $regularLesson];
    }

    private function enroll(User $user, Course $course): void
    {
        Enrollment::create([
            'user_id'    => $user->id,
            'course_id'  => $course->id,
            'price_paid' => 0,
        ]);
    }

    private function fakeImages(): array
    {
        return [
            'before' => UploadedFile::fake()->image('before.jpg'),
            'after'  => UploadedFile::fake()->image('after.jpg'),
        ];
    }

    // -------------------------------------------------------------------------
    // 1. Unauthenticated → 401
    // -------------------------------------------------------------------------

    public function test_unauthenticated_cannot_submit(): void
    {
        Storage::fake('public');
        [, , , $practiceLesson] = $this->createPracticeCourse();

        $this->postJson("/api/lessons/{$practiceLesson->id}/submissions", $this->fakeImages())
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // 2. Instructor submitting to own course → 403
    // -------------------------------------------------------------------------

    public function test_instructor_cannot_submit_to_own_course(): void
    {
        Storage::fake('public');
        [$instructor, , , $practiceLesson] = $this->createPracticeCourse();

        Sanctum::actingAs($instructor);

        $this->postJson("/api/lessons/{$practiceLesson->id}/submissions", $this->fakeImages())
            ->assertStatus(403)
            ->assertJsonPath('message', 'Los instructores no pueden enviar entregas en su propio curso.');
    }

    // -------------------------------------------------------------------------
    // 3. Non-practice lesson (enrolled student) → 403 with practice message
    // -------------------------------------------------------------------------

    public function test_non_practice_lesson_returns_403(): void
    {
        Storage::fake('public');
        [, $course, , , $regularLesson] = $this->createPracticeCourse();

        $student = User::factory()->create();
        $this->enroll($student, $course);
        Sanctum::actingAs($student);

        $this->postJson("/api/lessons/{$regularLesson->id}/submissions", $this->fakeImages())
            ->assertStatus(403)
            ->assertJsonPath('message', 'Esta lección no admite entregas de práctica.');
    }

    // -------------------------------------------------------------------------
    // 4. Not-enrolled student on practice lesson → 403
    // -------------------------------------------------------------------------

    public function test_not_enrolled_student_gets_403(): void
    {
        Storage::fake('public');
        [, , , $practiceLesson] = $this->createPracticeCourse();

        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->postJson("/api/lessons/{$practiceLesson->id}/submissions", $this->fakeImages())
            ->assertStatus(403)
            ->assertJsonPath('message', 'Debes estar inscrito para enviar una entrega.');
    }

    // -------------------------------------------------------------------------
    // 5. Valid first submission → 201, files stored, DB row pending
    // -------------------------------------------------------------------------

    public function test_enrolled_student_can_submit_practice(): void
    {
        Storage::fake('public');
        [, $course, , $practiceLesson] = $this->createPracticeCourse();

        $student = User::factory()->create();
        $this->enroll($student, $course);
        Sanctum::actingAs($student);

        $response = $this->postJson("/api/lessons/{$practiceLesson->id}/submissions", $this->fakeImages());

        $response->assertStatus(201)
                 ->assertJsonPath('data.status', 'pending')
                 ->assertJsonPath('data.lesson_id', $practiceLesson->id);

        // One DB row
        $this->assertDatabaseCount('practice_submissions', 1);
        $this->assertDatabaseHas('practice_submissions', [
            'lesson_id' => $practiceLesson->id,
            'user_id'   => $student->id,
            'status'    => 'pending',
        ]);

        // Files exist on disk
        $submission = PracticeSubmission::first();
        Storage::disk('public')->assertExists($submission->before_path);
        Storage::disk('public')->assertExists($submission->after_path);
    }

    // -------------------------------------------------------------------------
    // 6. Validation: missing before → 422; non-image → 422; oversized → 422
    // -------------------------------------------------------------------------

    public function test_missing_before_returns_422(): void
    {
        Storage::fake('public');
        [, $course, , $practiceLesson] = $this->createPracticeCourse();

        $student = User::factory()->create();
        $this->enroll($student, $course);
        Sanctum::actingAs($student);

        $this->postJson("/api/lessons/{$practiceLesson->id}/submissions", [
            'after' => UploadedFile::fake()->image('after.jpg'),
        ])->assertStatus(422)->assertJsonValidationErrors(['before']);
    }

    public function test_non_image_file_returns_422(): void
    {
        Storage::fake('public');
        [, $course, , $practiceLesson] = $this->createPracticeCourse();

        $student = User::factory()->create();
        $this->enroll($student, $course);
        Sanctum::actingAs($student);

        $this->postJson("/api/lessons/{$practiceLesson->id}/submissions", [
            'before' => UploadedFile::fake()->create('doc.pdf', 100),
            'after'  => UploadedFile::fake()->image('after.jpg'),
        ])->assertStatus(422)->assertJsonValidationErrors(['before']);
    }

    public function test_oversized_image_returns_422(): void
    {
        Storage::fake('public');
        [, $course, , $practiceLesson] = $this->createPracticeCourse();

        $student = User::factory()->create();
        $this->enroll($student, $course);
        Sanctum::actingAs($student);

        $this->postJson("/api/lessons/{$practiceLesson->id}/submissions", [
            'before' => UploadedFile::fake()->image('big.jpg')->size(6000),
            'after'  => UploadedFile::fake()->image('after.jpg'),
        ])->assertStatus(422)->assertJsonValidationErrors(['before']);
    }

    // -------------------------------------------------------------------------
    // 7. Resubmit → 200, stays 1 row, status reset, old files deleted, grading cleared
    // -------------------------------------------------------------------------

    public function test_resubmit_upserts_and_replaces_files(): void
    {
        Storage::fake('public');
        [$instructor, $course, , $practiceLesson] = $this->createPracticeCourse();

        $student = User::factory()->create();
        $this->enroll($student, $course);
        Sanctum::actingAs($student);

        // First submit
        $first = $this->postJson("/api/lessons/{$practiceLesson->id}/submissions", $this->fakeImages())
            ->assertStatus(201);

        $submission = PracticeSubmission::first();
        $oldBefore  = $submission->before_path;
        $oldAfter   = $submission->after_path;

        // Simulate grading (manually set status + graded_by)
        $submission->update([
            'status'     => 'approved',
            'feedback'   => 'Great work!',
            'graded_by'  => $instructor->id,
            'graded_at'  => now(),
        ]);

        // Second submit (resubmit)
        $second = $this->postJson("/api/lessons/{$practiceLesson->id}/submissions", $this->fakeImages())
            ->assertStatus(200);

        // Still one row
        $this->assertDatabaseCount('practice_submissions', 1);

        // Status reset to pending, grading cleared
        $this->assertDatabaseHas('practice_submissions', [
            'lesson_id'  => $practiceLesson->id,
            'user_id'    => $student->id,
            'status'     => 'pending',
            'feedback'   => null,
            'graded_by'  => null,
            'graded_at'  => null,
        ]);

        // Old files deleted
        Storage::disk('public')->assertMissing($oldBefore);
        Storage::disk('public')->assertMissing($oldAfter);

        // New files exist
        $submission->refresh();
        Storage::disk('public')->assertExists($submission->before_path);
        Storage::disk('public')->assertExists($submission->after_path);
    }

    // -------------------------------------------------------------------------
    // 8. Lesson show exposes is_practice and my_submission
    // -------------------------------------------------------------------------

    public function test_lesson_show_exposes_is_practice_and_my_submission_null(): void
    {
        Storage::fake('public');
        [, $course, , $practiceLesson] = $this->createPracticeCourse();

        $student = User::factory()->create();
        $this->enroll($student, $course);
        Sanctum::actingAs($student);

        $response = $this->getJson("/api/lessons/{$practiceLesson->id}");
        $response->assertStatus(200)
                 ->assertJsonPath('data.is_practice', true)
                 ->assertJsonPath('data.my_submission', null);
    }

    public function test_lesson_show_my_submission_populated_after_submit(): void
    {
        Storage::fake('public');
        [, $course, , $practiceLesson] = $this->createPracticeCourse();

        $student = User::factory()->create();
        $this->enroll($student, $course);
        Sanctum::actingAs($student);

        $this->postJson("/api/lessons/{$practiceLesson->id}/submissions", $this->fakeImages())
            ->assertStatus(201);

        $response = $this->getJson("/api/lessons/{$practiceLesson->id}");
        $response->assertStatus(200)
                 ->assertJsonPath('data.is_practice', true);

        $this->assertNotNull($response->json('data.my_submission'));
        $this->assertEquals('pending', $response->json('data.my_submission.status'));
    }
}
