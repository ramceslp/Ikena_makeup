<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CertificateResource;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\PracticeSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CertificateController extends Controller
{
    /**
     * GET /api/courses/{course:slug}/certificate
     *
     * Issue (or return existing) certificate for the authenticated user
     * on a given course, once all completion conditions are met.
     */
    public function show(Request $request, Course $course): JsonResponse
    {
        $user = $request->user();

        // Idempotency: return existing certificate if already issued
        $existing = Certificate::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if ($existing) {
            $existing->load(['user', 'course.instructor']);

            return response()->json(['data' => new CertificateResource($existing)]);
        }

        // Enrollment check
        if (! $user->enrolledCourses()->where('courses.id', $course->id)->exists()) {
            return response()->json(['message' => 'No estás inscrito en este curso.'], 403);
        }

        // Completion check: course must have ≥1 lesson and user must have completed ALL
        $lessonIds = $course->lessons()->pluck('lessons.id');
        $total     = $lessonIds->count();
        $completed = $user->completedLessons()->whereIn('lessons.id', $lessonIds)->count();

        if ($total === 0 || $completed < $total) {
            return response()->json(['message' => 'Aún no has completado todas las lecciones del curso.'], 403);
        }

        // Practices approved check: every practice lesson must have an approved submission
        $practiceIds = $course->lessons()->where('lessons.is_practice', true)->pluck('lessons.id');
        $approved    = PracticeSubmission::whereIn('lesson_id', $practiceIds)
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->count();

        if ($approved < $practiceIds->count()) {
            return response()->json(['message' => 'Debes tener todas las prácticas aprobadas para obtener el certificado.'], 403);
        }

        // Issue the certificate (idempotent via firstOrCreate)
        $certificate = Certificate::firstOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            ['code' => $this->generateUniqueCode(), 'issued_at' => now()]
        );

        $certificate->load(['user', 'course.instructor']);

        $statusCode = $certificate->wasRecentlyCreated ? 201 : 200;

        return response()->json(['data' => new CertificateResource($certificate)], $statusCode);
    }

    /**
     * GET /api/certificates/verify/{code}
     *
     * Public endpoint to verify a certificate by its code.
     */
    public function verify(string $code): JsonResponse
    {
        $certificate = Certificate::where('code', $code)
            ->with(['user', 'course.instructor'])
            ->first();

        if (! $certificate) {
            return response()->json(['message' => 'Certificado no encontrado.'], 404);
        }

        return response()->json([
            'data' => [
                'valid'          => true,
                'student_name'   => $certificate->user->name,
                'course_title'   => $certificate->course->title,
                'issued_at'      => $certificate->issued_at,
                'code'           => $certificate->code,
            ],
        ]);
    }

    /**
     * Generate a unique certificate code that does not already exist in the DB.
     */
    private function generateUniqueCode(): string
    {
        do {
            $code = 'IKENA-' . strtoupper(Str::random(10));
        } while (Certificate::where('code', $code)->exists());

        return $code;
    }
}
