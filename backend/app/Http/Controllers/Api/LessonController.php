<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    /**
     * GET /api/lessons/{id} — Return lesson with video_url if authorized.
     */
    public function show(Request $request, Lesson $lesson): JsonResponse
    {
        $user = $request->user();

        $isEnrolled = $this->userIsEnrolledInCourse($user, $lesson);

        if (! $lesson->is_free && ! $isEnrolled) {
            return response()->json([
                'message' => 'You do not have access to this course.',
            ], 403);
        }

        // Attach completed status
        $lesson->is_completed = $isEnrolled
            ? $user->completedLessons()->where('lessons.id', $lesson->id)->exists()
            : false;

        // Attach current user's practice submission (null if none or unauthenticated)
        $lesson->my_submission = $user
            ? \App\Models\PracticeSubmission::where('lesson_id', $lesson->id)
                ->where('user_id', $user->id)
                ->first()
            : null;

        return response()->json([
            'data' => new LessonResource($lesson),
        ]);
    }

    /**
     * POST /api/lessons/{id}/complete — Toggle completion status.
     */
    public function complete(Request $request, Lesson $lesson): JsonResponse
    {
        $user = $request->user();

        if (! $this->userIsEnrolledInCourse($user, $lesson)) {
            return response()->json([
                'message' => 'You do not have access to this course.',
            ], 403);
        }

        $existing = $user->completedLessons()->where('lessons.id', $lesson->id)->first();

        if ($existing) {
            // Toggle OFF — remove the progress row
            $user->completedLessons()->detach($lesson->id);
            $completed = false;
        } else {
            // Toggle ON — add the progress row
            $user->completedLessons()->attach($lesson->id, [
                'completed_at' => now(),
            ]);
            $completed = true;
        }

        // Calculate progress for this course
        $course       = $lesson->section->course;
        $lessonIds    = $course->lessons()->pluck('lessons.id')->toArray();
        $totalLessons = count($lessonIds);

        $completedLessons = $user->completedLessons()
            ->whereIn('lessons.id', $lessonIds)
            ->count();

        $percentage = $totalLessons > 0
            ? (int) round($completedLessons / $totalLessons * 100)
            : 0;

        return response()->json([
            'data' => [
                'lesson_id' => $lesson->id,
                'completed' => $completed,
                'progress'  => [
                    'completed_lessons' => $completedLessons,
                    'total_lessons'     => $totalLessons,
                    'percentage'        => $percentage,
                ],
            ],
        ]);
    }

    /**
     * Check if a user is enrolled in the course that contains the given lesson.
     */
    private function userIsEnrolledInCourse($user, Lesson $lesson): bool
    {
        if (! $user) {
            return false;
        }

        $courseId = $lesson->section->course_id;
        return $user->enrolledCourses()->where('courses.id', $courseId)->exists();
    }
}
