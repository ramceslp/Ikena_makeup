<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\PracticeSubmission;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * PracticeSubmissionPrivacyTest — practice photos must not be world-readable.
 *
 * These are before/after photographs of students' faces. They were written to
 * the 'public' disk (visibility: public) under submissions/{user_id}/, and
 * public/storage is a symlink into it — so every photo was readable by anyone
 * with the URL: no token, no enrollment, no account. PracticeSubmissionResource
 * handed those permanent URLs to every instructor listing submissions.
 *
 * The fix stores them on the private 'local' disk and serves them through a
 * temporary signed route.
 *
 * Why signed URLs rather than an auth:sanctum route: both clients render these
 * in <img :src="submission.before_url"> (frontend ReviewTasksList.vue,
 * TaskReviewModal.vue, player/PracticeSubmission.vue), and an <img> tag cannot
 * send an Authorization header — this API has no cookie session to fall back on
 * (supports_credentials is false). So authorisation happens when the URL is
 * MINTED (the JSON endpoints are already role- and ownership-gated) and the URL
 * itself is unguessable and short-lived.
 *
 * Residual risk, accepted and documented: someone already authorised to see a
 * photo can forward a working link for the length of the TTL. That is a large
 * improvement on "permanent, unauthenticated, enumerable" but it is not the
 * same as per-request authorisation.
 */
class PracticeSubmissionPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function practiceLesson(?User $instructor = null): Lesson
    {
        $instructor = $instructor ?: User::factory()->instructor()->create();

        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'is_published'  => true,
        ]);

        $section = Section::factory()->create(['course_id' => $course->id]);

        return Lesson::factory()->create([
            'section_id'  => $section->id,
            'is_practice' => true,
        ]);
    }

    private function enrolledStudent(Lesson $lesson): User
    {
        $student = User::factory()->create(['role' => 'student']);
        $student->enrolledCourses()->attach($lesson->section->course_id, ['price_paid' => 0]);

        return $student;
    }

    private function submitPhotos(Lesson $lesson, User $student): PracticeSubmission
    {
        Sanctum::actingAs($student);

        $this->postJson("/api/lessons/{$lesson->id}/submissions", [
            'before' => UploadedFile::fake()->image('before.jpg'),
            'after'  => UploadedFile::fake()->image('after.jpg'),
        ])->assertStatus(201);

        return PracticeSubmission::where('lesson_id', $lesson->id)
            ->where('user_id', $student->id)
            ->firstOrFail();
    }

    // =========================================================================
    // Storage location
    // =========================================================================

    public function test_uploaded_photos_are_not_written_to_the_public_disk(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $lesson     = $this->practiceLesson();
        $submission = $this->submitPhotos($lesson, $this->enrolledStudent($lesson));

        Storage::disk('public')->assertMissing($submission->before_path);
        Storage::disk('public')->assertMissing($submission->after_path);
    }

    public function test_uploaded_photos_are_written_to_the_private_disk(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $lesson     = $this->practiceLesson();
        $submission = $this->submitPhotos($lesson, $this->enrolledStudent($lesson));

        Storage::disk('local')->assertExists($submission->before_path);
        Storage::disk('local')->assertExists($submission->after_path);
    }

    public function test_resubmitting_deletes_the_previous_files_from_the_private_disk(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $lesson  = $this->practiceLesson();
        $student = $this->enrolledStudent($lesson);

        $first      = $this->submitPhotos($lesson, $student);
        $oldBefore  = $first->before_path;
        $oldAfter   = $first->after_path;

        $this->postJson("/api/lessons/{$lesson->id}/submissions", [
            'before' => UploadedFile::fake()->image('before-2.jpg'),
            'after'  => UploadedFile::fake()->image('after-2.jpg'),
        ])->assertStatus(200);

        Storage::disk('local')->assertMissing($oldBefore);
        Storage::disk('local')->assertMissing($oldAfter);

        $second = $first->fresh();
        Storage::disk('local')->assertExists($second->before_path);
        Storage::disk('local')->assertExists($second->after_path);
    }

    // =========================================================================
    // The URLs handed to clients
    // =========================================================================

    public function test_photo_url_is_signed_and_expiring(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $lesson     = $this->practiceLesson();
        $submission = $this->submitPhotos($lesson, $this->enrolledStudent($lesson));

        $this->assertStringContainsString('signature=', $submission->before_url);
        $this->assertStringContainsString('expires=', $submission->before_url);
    }

    public function test_photo_url_is_not_a_public_storage_url(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $lesson     = $this->practiceLesson();
        $submission = $this->submitPhotos($lesson, $this->enrolledStudent($lesson));

        $this->assertStringNotContainsString('/storage/', $submission->before_url);
        $this->assertStringNotContainsString('/storage/', $submission->after_url);
    }

    // =========================================================================
    // Serving
    // =========================================================================

    public function test_a_valid_signed_url_serves_the_photo(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $lesson     = $this->practiceLesson();
        $submission = $this->submitPhotos($lesson, $this->enrolledStudent($lesson));

        // Deliberately unauthenticated: an <img> tag sends no bearer token, so
        // the signature alone must be sufficient.
        $this->get($submission->before_url)->assertStatus(200);
    }

    public function test_an_unsigned_request_is_rejected(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $lesson     = $this->practiceLesson();
        $submission = $this->submitPhotos($lesson, $this->enrolledStudent($lesson));

        $this->get("/api/submissions/{$submission->id}/before")->assertStatus(403);
    }

    public function test_a_tampered_signature_is_rejected(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $lesson     = $this->practiceLesson();
        $submission = $this->submitPhotos($lesson, $this->enrolledStudent($lesson));

        $tampered = preg_replace('/signature=\w/', 'signature=0', $submission->before_url);

        $this->get($tampered)->assertStatus(403);
    }

    public function test_swapping_the_submission_id_invalidates_the_signature(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $lesson  = $this->practiceLesson();
        $mine    = $this->submitPhotos($lesson, $this->enrolledStudent($lesson));
        $someone = $this->submitPhotos($lesson, $this->enrolledStudent($lesson));

        // The id is part of the signed payload, so pointing my link at another
        // student's submission must not verify.
        $forged = str_replace(
            "/submissions/{$mine->id}/",
            "/submissions/{$someone->id}/",
            $mine->before_url,
        );

        $this->get($forged)->assertStatus(403);
    }

    public function test_an_expired_signature_is_rejected(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $lesson     = $this->practiceLesson();
        $submission = $this->submitPhotos($lesson, $this->enrolledStudent($lesson));
        $url        = $submission->before_url;

        $this->travel(31)->minutes();

        $this->get($url)->assertStatus(403);
    }

    public function test_an_unknown_variant_is_rejected(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $lesson     = $this->practiceLesson();
        $submission = $this->submitPhotos($lesson, $this->enrolledStudent($lesson));

        $url = URL::temporarySignedRoute('submissions.file', now()->addMinutes(30), [
            'submission' => $submission->id,
            'variant'    => 'sideways',
        ]);

        $this->get($url)->assertStatus(404);
    }

    public function test_a_missing_file_returns_404_rather_than_a_server_error(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $lesson     = $this->practiceLesson();
        $submission = $this->submitPhotos($lesson, $this->enrolledStudent($lesson));

        Storage::disk('local')->delete($submission->before_path);

        $this->get($submission->before_url)->assertStatus(404);
    }

    // =========================================================================
    // Throttling must not break an image-heavy review screen
    // =========================================================================

    public function test_serving_many_photos_in_one_page_load_is_not_throttled(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $lesson     = $this->practiceLesson();
        $submission = $this->submitPhotos($lesson, $this->enrolledStudent($lesson));

        // The instructor review list renders 15 submissions x 2 photos, so the
        // 60/min baseline 'api' limiter would lock the screen on the second
        // page load. This route needs its own, higher budget.
        for ($i = 0; $i < 70; $i++) {
            $this->get($submission->before_url)->assertStatus(200);
        }
    }
}
