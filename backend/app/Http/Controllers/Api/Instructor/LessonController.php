<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Instructor\ReorderRequest;
use App\Http\Requests\Instructor\StoreLessonRequest;
use App\Http\Requests\Instructor\UpdateLessonRequest;
use App\Http\Resources\Instructor\InstructorLessonResource;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    /**
     * POST /api/instructor/sections/{id}/lessons
     * Create a new lesson (position = max + 1).
     */
    public function store(StoreLessonRequest $request, Section $section): JsonResponse
    {
        $this->authorizeSectionOwnership($request, $section);

        $position = $section->lessons()->max('position');
        $position = $position === null ? 0 : $position + 1;

        $lesson = $section->lessons()->create(array_merge(
            $request->validated(),
            ['position' => $position]
        ));

        return response()->json([
            'data' => new InstructorLessonResource($lesson),
        ], 201);
    }

    /**
     * PATCH /api/instructor/lessons/{id}
     * Update lesson fields.
     */
    public function update(UpdateLessonRequest $request, Lesson $lesson): JsonResponse
    {
        $this->authorizeSectionOwnership($request, $lesson->section);

        $lesson->update($request->validated());

        return response()->json([
            'data' => new InstructorLessonResource($lesson),
        ]);
    }

    /**
     * DELETE /api/instructor/lessons/{id}
     * Delete lesson.
     */
    public function destroy(Request $request, Lesson $lesson): JsonResponse
    {
        $this->authorizeSectionOwnership($request, $lesson->section);

        $lesson->delete();

        return response()->json(null, 204);
    }

    /**
     * PATCH /api/instructor/sections/{id}/lessons/reorder
     * Reorder lessons — ordered_ids must exactly match section's lesson IDs.
     */
    public function reorder(ReorderRequest $request, Section $section): JsonResponse
    {
        $this->authorizeSectionOwnership($request, $section);

        $orderedIds = $request->validated('ordered_ids');
        $existingIds = $section->lessons()->pluck('id')->sort()->values()->all();
        $providedIds = collect($orderedIds)->sort()->values()->all();

        if ($existingIds !== $providedIds) {
            return response()->json([
                'message' => 'The ordered_ids must exactly match the lessons of this section.',
                'errors'  => ['ordered_ids' => ['The provided IDs do not match the section lessons.']],
            ], 422);
        }

        foreach ($orderedIds as $position => $id) {
            Lesson::where('id', $id)->update(['position' => $position]);
        }

        $lessons = $section->lessons()->orderBy('position')->get();

        return response()->json([
            'data' => InstructorLessonResource::collection($lessons),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function authorizeSectionOwnership(Request $request, Section $section): void
    {
        if ($request->user()->id !== $section->course->instructor_id) {
            abort(response()->json([
                'message' => 'You do not own this course.',
            ], 403));
        }
    }
}
