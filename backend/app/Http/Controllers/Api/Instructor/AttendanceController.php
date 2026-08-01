<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Instructor\RecordAttendanceRequest;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Attendance for live sessions.
 *
 * Writes the same lesson_progress rows the on-demand player writes when a
 * student ticks a lesson — deliberately, so CertificateController needs no
 * live-specific branch: "completed every lesson" already means "attended every
 * session" once the instructor is the one writing the rows.
 */
class AttendanceController extends Controller
{
    /**
     * GET /api/instructor/lessons/{lesson}/attendance
     * The roster for one session: every enrolled student and whether they
     * have been marked present.
     */
    public function index(Request $request, Lesson $lesson): JsonResponse
    {
        $course = $this->authorizeLiveLesson($request, $lesson);

        $attendedIds = $lesson->completedByUsers()->pluck('users.id')->all();

        $roster = $course->students()
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.email'])
            ->map(fn (User $student) => [
                'id'       => $student->id,
                'name'     => $student->name,
                'email'    => $student->email,
                'attended' => in_array($student->id, $attendedIds, true),
            ]);

        return response()->json(['data' => $roster]);
    }

    /**
     * PUT /api/instructor/lessons/{lesson}/attendance
     * Replace the attendance list for one session with the given students.
     */
    public function update(RecordAttendanceRequest $request, Lesson $lesson): JsonResponse
    {
        $course = $this->authorizeLiveLesson($request, $lesson);

        $submittedIds = $request->validated('user_ids');

        // Only students actually enrolled in THIS course may be marked — a
        // stray id would otherwise mint lesson_progress for someone who never
        // bought the course, and with it a certificate.
        $enrolledIds = $course->students()->pluck('users.id')->all();
        $unknown     = array_diff($submittedIds, $enrolledIds);

        if ($unknown !== []) {
            return response()->json([
                'message' => 'Some of the selected users are not enrolled in this course.',
                'errors'  => ['user_ids' => ['Some of the selected users are not enrolled in this course.']],
            ], 422);
        }

        // sync() is what makes this idempotent AND makes unchecking work:
        // a student removed from the list loses the progress row, so the
        // certificate gate closes again.
        $lesson->completedByUsers()->sync(
            array_fill_keys($submittedIds, ['completed_at' => now()])
        );

        return $this->index($request, $lesson);
    }

    /**
     * Abort unless the caller may author this course and the lesson really is
     * a live session — attendance has no meaning on a pre-recorded lesson.
     */
    private function authorizeLiveLesson(Request $request, Lesson $lesson): \App\Models\Course
    {
        $course = $lesson->section->course;

        if ($request->user()->cannot('manage', $course)) {
            abort(response()->json([
                'message' => 'You do not own this course.',
            ], 403));
        }

        if (! $course->isLive()) {
            abort(response()->json([
                'message' => 'Attendance is only recorded for live courses.',
            ], 422));
        }

        return $course;
    }
}
