<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Resources\PracticeSubmissionResource;
use App\Models\PracticeSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubmissionController extends Controller
{
    /**
     * GET /api/instructor/submissions
     *
     * List all submissions belonging to the authenticated instructor's courses.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['sometimes', 'nullable', Rule::in(['pending', 'approved', 'needs_work'])],
        ]);

        $courseIds = $request->user()->coursesTeaching()->pluck('id');

        $paginated = PracticeSubmission::whereHas(
            'lesson.section',
            fn ($q) => $q->whereIn('course_id', $courseIds)
        )
            ->with(['user', 'lesson'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(15);

        return response()->json(
            PracticeSubmissionResource::collection($paginated)->response()->getData(true)
        );
    }

    /**
     * PATCH /api/instructor/submissions/{submission}
     *
     * Grade a submission (approved or needs_work).
     */
    public function update(Request $request, PracticeSubmission $submission): JsonResponse
    {
        // Authorize: only the course's own instructor may grade
        if ($submission->lesson->section->course->instructor_id !== $request->user()->id) {
            return response()->json([
                'message' => 'No puedes calificar esta entrega.',
            ], 403);
        }

        $validated = $request->validate([
            'status'   => ['required', Rule::in(['approved', 'needs_work'])],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ]);

        $submission->update([
            'status'     => $validated['status'],
            'feedback'   => $validated['feedback'] ?? null,
            'graded_by'  => $request->user()->id,
            'graded_at'  => now(),
        ]);

        $submission->load(['user', 'lesson']);

        return response()->json([
            'data' => new PracticeSubmissionResource($submission),
        ]);
    }
}
