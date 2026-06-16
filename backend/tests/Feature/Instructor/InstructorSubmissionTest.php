<?php

namespace Tests\Feature\Instructor;

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

class InstructorSubmissionTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createInstructorCourseWithPracticeSubmission(string $status = 'pending'): array
    {
        $instructor = User::factory()->instructor()->create();
        $student    = User::factory()->create();
        $course     = Course::factory()->create(['instructor_id' => $instructor->id]);
        $section    = Section::factory()->create(['course_id' => $course->id]);
        $lesson     = Lesson::factory()->create(['section_id' => $section->id, 'is_practice' => true]);

        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'price_paid' => 0]);

        $submission = PracticeSubmission::factory()->create([
            'lesson_id'   => $lesson->id,
            'user_id'     => $student->id,
            'status'      => $status,
        ]);

        return [$instructor, $student, $course, $section, $lesson, $submission];
    }

    // -------------------------------------------------------------------------
    // 1. 401 unauthenticated; 403 for non-instructor
    // -------------------------------------------------------------------------

    public function test_unauthenticated_cannot_list_submissions(): void
    {
        $this->getJson('/api/instructor/submissions')->assertStatus(401);
    }

    public function test_student_cannot_access_instructor_submissions(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($student);

        $this->getJson('/api/instructor/submissions')->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // 2. Index returns only own instructor's submissions (ownership isolation)
    // -------------------------------------------------------------------------

    public function test_index_returns_only_own_instructor_submissions(): void
    {
        Storage::fake('public');

        [$instructor1, , , , , $submission1] = $this->createInstructorCourseWithPracticeSubmission();

        // Second instructor with their own submission
        [$instructor2, , , , , $submission2] = $this->createInstructorCourseWithPracticeSubmission();

        Sanctum::actingAs($instructor1);

        $response = $this->getJson('/api/instructor/submissions')->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($submission1->id, $ids);
        $this->assertNotContains($submission2->id, $ids);
    }

    // -------------------------------------------------------------------------
    // 3. Status filter ?status=pending returns only pending
    // -------------------------------------------------------------------------

    public function test_status_filter_returns_only_matching(): void
    {
        Storage::fake('public');

        [$instructor, $student, $course, $section, $lesson, $pendingSub] =
            $this->createInstructorCourseWithPracticeSubmission('pending');

        // Add an approved submission for same instructor
        $lesson2 = Lesson::factory()->create(['section_id' => $section->id, 'is_practice' => true]);
        $approvedSub = PracticeSubmission::factory()->approved()->create([
            'lesson_id' => $lesson2->id,
            'user_id'   => $student->id,
        ]);

        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/instructor/submissions?status=pending')->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($pendingSub->id, $ids);
        $this->assertNotContains($approvedSub->id, $ids);
    }

    // -------------------------------------------------------------------------
    // 4. Response has pagination envelope (data + meta/links)
    // -------------------------------------------------------------------------

    public function test_index_returns_pagination_envelope(): void
    {
        Storage::fake('public');

        [$instructor] = $this->createInstructorCourseWithPracticeSubmission();

        Sanctum::actingAs($instructor);

        $this->getJson('/api/instructor/submissions')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    // -------------------------------------------------------------------------
    // 5. Update by different instructor → 403
    // -------------------------------------------------------------------------

    public function test_different_instructor_cannot_grade_submission(): void
    {
        Storage::fake('public');

        [, , , , , $submission] = $this->createInstructorCourseWithPracticeSubmission();

        $otherInstructor = User::factory()->instructor()->create();
        Sanctum::actingAs($otherInstructor);

        $this->patchJson("/api/instructor/submissions/{$submission->id}", [
            'status' => 'approved',
        ])->assertStatus(403)
          ->assertJsonPath('message', 'No puedes calificar esta entrega.');
    }

    // -------------------------------------------------------------------------
    // 6. Update with invalid status → 422
    // -------------------------------------------------------------------------

    public function test_invalid_status_returns_422(): void
    {
        Storage::fake('public');

        [$instructor, , , , , $submission] = $this->createInstructorCourseWithPracticeSubmission();

        Sanctum::actingAs($instructor);

        $this->patchJson("/api/instructor/submissions/{$submission->id}", [
            'status' => 'foo',
        ])->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    // -------------------------------------------------------------------------
    // 7. Approve: status='approved' + feedback set → row updated with graded_by + graded_at
    // -------------------------------------------------------------------------

    public function test_approve_submission_updates_row_and_response(): void
    {
        Storage::fake('public');

        [$instructor, , , , , $submission] = $this->createInstructorCourseWithPracticeSubmission();

        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/instructor/submissions/{$submission->id}", [
            'status'   => 'approved',
            'feedback' => 'Excellent work!',
        ])->assertStatus(200);

        $this->assertDatabaseHas('practice_submissions', [
            'id'         => $submission->id,
            'status'     => 'approved',
            'feedback'   => 'Excellent work!',
            'graded_by'  => $instructor->id,
        ]);

        $this->assertNotNull($submission->fresh()->graded_at);

        $response->assertJsonPath('data.status', 'approved')
                 ->assertJsonPath('data.feedback', 'Excellent work!');
    }

    // -------------------------------------------------------------------------
    // 8. Needs work works similarly
    // -------------------------------------------------------------------------

    public function test_needs_work_submission_updates_correctly(): void
    {
        Storage::fake('public');

        [$instructor, , , , , $submission] = $this->createInstructorCourseWithPracticeSubmission();

        Sanctum::actingAs($instructor);

        $this->patchJson("/api/instructor/submissions/{$submission->id}", [
            'status'   => 'needs_work',
            'feedback' => 'Please redo the contour.',
        ])->assertStatus(200)
          ->assertJsonPath('data.status', 'needs_work');

        $this->assertDatabaseHas('practice_submissions', [
            'id'       => $submission->id,
            'status'   => 'needs_work',
            'feedback' => 'Please redo the contour.',
        ]);
    }
}
