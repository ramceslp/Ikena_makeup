<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\PracticeSubmission;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CertificateTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers — mirror CourseReviewTest / PracticeSubmissionTest conventions
    // -------------------------------------------------------------------------

    /**
     * Create an instructor-owned published course with:
     *   - one section
     *   - $regularCount regular (non-practice) lessons
     *   - $practiceCount practice lessons
     *
     * @return array{0: Course, 1: User, 2: \Illuminate\Database\Eloquent\Collection, 3: \Illuminate\Database\Eloquent\Collection}
     *             [course, instructor, regularLessons, practiceLessons]
     */
    private function createCourse(int $regularCount = 2, int $practiceCount = 1, bool $offersCertificate = true): array
    {
        $instructor = User::factory()->instructor()->create();
        $course     = Course::factory()->create([
            'instructor_id'      => $instructor->id,
            'is_published'       => true,
            'offers_certificate' => $offersCertificate,
        ]);
        $section = Section::factory()->create(['course_id' => $course->id]);

        $regularLessons = Lesson::factory()->count($regularCount)->create([
            'section_id'  => $section->id,
            'is_practice' => false,
        ]);

        $practiceLessons = Lesson::factory()->count($practiceCount)->create([
            'section_id'  => $section->id,
            'is_practice' => true,
        ]);

        return [$course, $instructor, $regularLessons, $practiceLessons];
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

    private function completeAllLessons(User $user, Course $course): void
    {
        $lessonIds = $course->lessons()->pluck('lessons.id');
        foreach ($lessonIds as $id) {
            $user->completedLessons()->attach($id, ['completed_at' => now()]);
        }
    }

    private function approveSubmission(User $user, Lesson $practiceLesson, User $instructor): void
    {
        PracticeSubmission::factory()->create([
            'lesson_id' => $practiceLesson->id,
            'user_id'   => $user->id,
            'status'    => 'approved',
            'graded_by' => $instructor->id,
            'graded_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // 1. Unauthenticated → 401
    // -------------------------------------------------------------------------

    public function test_unauthenticated_user_gets_401(): void
    {
        [$course] = $this->createCourse();

        $this->getJson("/api/courses/{$course->slug}/certificate")
             ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // 2. Not enrolled → 403
    // -------------------------------------------------------------------------

    public function test_not_enrolled_user_gets_403(): void
    {
        [$course] = $this->createCourse();

        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->getJson("/api/courses/{$course->slug}/certificate")
             ->assertStatus(403)
             ->assertJsonPath('message', 'No estás inscrito en este curso.');
    }

    // -------------------------------------------------------------------------
    // 3. Enrolled but NOT all lessons completed → 403
    // -------------------------------------------------------------------------

    public function test_enrolled_but_incomplete_lessons_gets_403(): void
    {
        [$course, $instructor, $regularLessons, $practiceLessons] = $this->createCourse(2, 1);

        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->enroll($student, $course);
        // Complete only one of the two regular lessons — not all
        $this->completeLesson($student, $regularLessons[0]);

        $this->getJson("/api/courses/{$course->slug}/certificate")
             ->assertStatus(403)
             ->assertJsonPath('message', 'Aún no has completado todas las lecciones del curso.');
    }

    // -------------------------------------------------------------------------
    // 4a. All lessons completed but practice has NO submission → 403
    // -------------------------------------------------------------------------

    public function test_no_practice_submission_gets_403(): void
    {
        [$course, $instructor, $regularLessons, $practiceLessons] = $this->createCourse(1, 1);

        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->enroll($student, $course);
        $this->completeAllLessons($student, $course);
        // No practice submission at all

        $this->getJson("/api/courses/{$course->slug}/certificate")
             ->assertStatus(403)
             ->assertJsonPath('message', 'Debes tener todas las prácticas aprobadas para obtener el certificado.');
    }

    // -------------------------------------------------------------------------
    // 4b. Practice submission exists but status = 'pending' → 403
    // -------------------------------------------------------------------------

    public function test_pending_practice_submission_gets_403(): void
    {
        [$course, $instructor, $regularLessons, $practiceLessons] = $this->createCourse(1, 1);

        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->enroll($student, $course);
        $this->completeAllLessons($student, $course);

        // Pending submission
        PracticeSubmission::factory()->create([
            'lesson_id' => $practiceLessons[0]->id,
            'user_id'   => $student->id,
            'status'    => 'pending',
        ]);

        $this->getJson("/api/courses/{$course->slug}/certificate")
             ->assertStatus(403)
             ->assertJsonPath('message', 'Debes tener todas las prácticas aprobadas para obtener el certificado.');
    }

    // -------------------------------------------------------------------------
    // 4c. Practice submission exists but status = 'needs_work' → 403
    // -------------------------------------------------------------------------

    public function test_needs_work_practice_submission_gets_403(): void
    {
        [$course, $instructor, $regularLessons, $practiceLessons] = $this->createCourse(1, 1);

        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->enroll($student, $course);
        $this->completeAllLessons($student, $course);

        PracticeSubmission::factory()->create([
            'lesson_id' => $practiceLessons[0]->id,
            'user_id'   => $student->id,
            'status'    => 'needs_work',
            'graded_by' => $instructor->id,
            'graded_at' => now(),
        ]);

        $this->getJson("/api/courses/{$course->slug}/certificate")
             ->assertStatus(403)
             ->assertJsonPath('message', 'Debes tener todas las prácticas aprobadas para obtener el certificado.');
    }

    // -------------------------------------------------------------------------
    // 5. All conditions met → 201 with certificate data
    // -------------------------------------------------------------------------

    public function test_eligible_student_gets_certificate_201(): void
    {
        [$course, $instructor, $regularLessons, $practiceLessons] = $this->createCourse(2, 1);

        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->enroll($student, $course);
        $this->completeAllLessons($student, $course);
        $this->approveSubmission($student, $practiceLessons[0], $instructor);

        $response = $this->getJson("/api/courses/{$course->slug}/certificate");

        $response->assertStatus(201);

        $data = $response->json('data');
        $this->assertNotEmpty($data['code']);
        $this->assertEquals($student->name, $data['student_name']);
        $this->assertEquals($course->title, $data['course_title']);
        $this->assertEquals($instructor->name, $data['instructor_name']);
        $this->assertNotNull($data['issued_at']);

        // Exactly one row in DB
        $this->assertDatabaseCount('certificates', 1);
        $this->assertDatabaseHas('certificates', [
            'user_id'   => $student->id,
            'course_id' => $course->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // 6. Course with ZERO practice lessons + all lessons completed → issues cert
    // -------------------------------------------------------------------------

    public function test_course_with_no_practice_lessons_issues_certificate(): void
    {
        [$course, $instructor, $regularLessons, $practiceLessons] = $this->createCourse(2, 0);

        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->enroll($student, $course);
        $this->completeAllLessons($student, $course);

        $response = $this->getJson("/api/courses/{$course->slug}/certificate");

        // 201 since no practice requirement
        $response->assertStatus(201);
        $this->assertNotEmpty($response->json('data.code'));
        $this->assertDatabaseCount('certificates', 1);
    }

    // -------------------------------------------------------------------------
    // 7. Idempotent: calling twice returns same code; only 1 row in DB
    // -------------------------------------------------------------------------

    public function test_certificate_issuance_is_idempotent(): void
    {
        [$course, $instructor, $regularLessons, $practiceLessons] = $this->createCourse(1, 1);

        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->enroll($student, $course);
        $this->completeAllLessons($student, $course);
        $this->approveSubmission($student, $practiceLessons[0], $instructor);

        $first  = $this->getJson("/api/courses/{$course->slug}/certificate");
        $second = $this->getJson("/api/courses/{$course->slug}/certificate");

        $first->assertStatus(201);
        // Second call returns 200 (already exists)
        $second->assertStatus(200);

        // Same code
        $this->assertEquals(
            $first->json('data.code'),
            $second->json('data.code')
        );

        // Only one DB row
        $this->assertDatabaseCount('certificates', 1);
    }

    // -------------------------------------------------------------------------
    // 8. Course with zero lessons → 403 (cannot certify an empty course)
    // -------------------------------------------------------------------------

    public function test_empty_course_with_zero_lessons_gets_403(): void
    {
        $instructor = User::factory()->instructor()->create();
        $course     = Course::factory()->create([
            'instructor_id'      => $instructor->id,
            'is_published'       => true,
            'offers_certificate' => true,
        ]);
        // No sections, no lessons

        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->enroll($student, $course);
        // No lessons to complete

        $this->getJson("/api/courses/{$course->slug}/certificate")
             ->assertStatus(403)
             ->assertJsonPath('message', 'Aún no has completado todas las lecciones del curso.');
    }

    // -------------------------------------------------------------------------
    // 9. Public verify endpoint — valid code → 200; unknown code → 404
    // -------------------------------------------------------------------------

    public function test_verify_valid_code_returns_200_with_data(): void
    {
        [$course, $instructor] = $this->createCourse(1, 0);
        $student = User::factory()->create();

        $certificate = Certificate::factory()->create([
            'user_id'   => $student->id,
            'course_id' => $course->id,
            'code'      => 'IKENA-TEST123456',
            'issued_at' => now(),
        ]);

        // No auth needed
        $response = $this->getJson("/api/certificates/verify/{$certificate->code}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertTrue($data['valid']);
        $this->assertEquals($student->name, $data['student_name']);
        $this->assertEquals($course->title, $data['course_title']);
        $this->assertEquals($certificate->code, $data['code']);
        $this->assertNotNull($data['issued_at']);
    }

    public function test_verify_unknown_code_returns_404(): void
    {
        $response = $this->getJson('/api/certificates/verify/IKENA-DOESNOTEXIST');

        $response->assertStatus(404)
                 ->assertJsonPath('message', 'Certificado no encontrado.');
    }

    // -------------------------------------------------------------------------
    // 10. Isolation — two eligible students get their own separate certificates
    // -------------------------------------------------------------------------

    public function test_two_eligible_students_get_separate_certificates(): void
    {
        [$course, $instructor, $regularLessons, $practiceLessons] = $this->createCourse(1, 1, true);

        $studentA = User::factory()->create();
        $studentB = User::factory()->create();

        foreach ([$studentA, $studentB] as $student) {
            $this->enroll($student, $course);
            $this->completeAllLessons($student, $course);
            $this->approveSubmission($student, $practiceLessons[0], $instructor);
        }

        Sanctum::actingAs($studentA);
        $responseA = $this->getJson("/api/courses/{$course->slug}/certificate");
        $responseA->assertStatus(201);

        Sanctum::actingAs($studentB);
        $responseB = $this->getJson("/api/courses/{$course->slug}/certificate");
        $responseB->assertStatus(201);

        // Different codes
        $this->assertNotEquals(
            $responseA->json('data.code'),
            $responseB->json('data.code')
        );

        // Two separate rows
        $this->assertDatabaseCount('certificates', 2);
    }

    // -------------------------------------------------------------------------
    // 11. Course without offers_certificate → 403 (even if otherwise eligible)
    // -------------------------------------------------------------------------

    public function test_course_without_offers_certificate_returns_403(): void
    {
        // offersCertificate = false
        [$course, $instructor, $regularLessons, $practiceLessons] = $this->createCourse(1, 0, false);

        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->enroll($student, $course);
        $this->completeAllLessons($student, $course);

        $this->getJson("/api/courses/{$course->slug}/certificate")
             ->assertStatus(403)
             ->assertJsonPath('message', 'Este curso no ofrece certificado.');
    }

    // -------------------------------------------------------------------------
    // 12. Idempotency still bypasses offers_certificate gate (already issued)
    // -------------------------------------------------------------------------

    public function test_existing_certificate_returned_even_when_offers_certificate_later_set_false(): void
    {
        // A certificate that was already issued (stored in DB) should always
        // be returned by the idempotency path, regardless of current flag.
        [$course, $instructor] = $this->createCourse(1, 0, false);

        $student = User::factory()->create();

        $certificate = Certificate::factory()->create([
            'user_id'   => $student->id,
            'course_id' => $course->id,
            'code'      => 'IKENA-OLDCODE1234',
            'issued_at' => now(),
        ]);

        Sanctum::actingAs($student);

        // Should return 200 (idempotency path before the gate)
        $response = $this->getJson("/api/courses/{$course->slug}/certificate");

        $response->assertStatus(200)
                 ->assertJsonPath('data.code', 'IKENA-OLDCODE1234');
    }
}
