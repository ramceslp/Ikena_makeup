<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PracticeSubmissionResource;
use App\Models\Lesson;
use App\Models\PracticeSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PracticeSubmissionController extends Controller
{
    /**
     * POST /api/lessons/{lesson}/submissions
     *
     * Upload before+after photos for a practice lesson (upsert).
     */
    public function store(Request $request, Lesson $lesson): JsonResponse
    {
        $user = $request->user();

        // Instructor cannot submit to their own course
        if ($lesson->section->course->instructor_id === $user->id) {
            return response()->json([
                'message' => 'Los instructores no pueden enviar entregas en su propio curso.',
            ], 403);
        }

        // Lesson must be flagged as practice
        if (! $lesson->is_practice) {
            return response()->json([
                'message' => 'Esta lección no admite entregas de práctica.',
            ], 403);
        }

        // Student must be enrolled
        if (! $user->enrolledCourses()->where('courses.id', $lesson->section->course_id)->exists()) {
            return response()->json([
                'message' => 'Debes estar inscrito para enviar una entrega.',
            ], 403);
        }

        $request->validate([
            'before' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'after'  => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $existing = PracticeSubmission::where('lesson_id', $lesson->id)
            ->where('user_id', $user->id)
            ->first();

        // Delete old files if resubmitting
        if ($existing) {
            Storage::disk('public')->delete([$existing->before_path, $existing->after_path]);
        }

        // Store new files
        $beforePath = $request->file('before')->store("submissions/{$user->id}", 'public');
        $afterPath  = $request->file('after')->store("submissions/{$user->id}", 'public');

        $submission = PracticeSubmission::updateOrCreate(
            ['lesson_id' => $lesson->id, 'user_id' => $user->id],
            [
                'before_path' => $beforePath,
                'after_path'  => $afterPath,
                'status'      => 'pending',
                'feedback'    => null,
                'graded_by'   => null,
                'graded_at'   => null,
            ]
        );

        $submission->load('user');

        return response()->json(
            ['data' => new PracticeSubmissionResource($submission)],
            $existing ? 200 : 201
        );
    }
}
