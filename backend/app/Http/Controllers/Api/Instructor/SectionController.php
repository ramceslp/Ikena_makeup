<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Instructor\ReorderRequest;
use App\Http\Requests\Instructor\StoreSectionRequest;
use App\Http\Requests\Instructor\UpdateSectionRequest;
use App\Http\Resources\Instructor\InstructorSectionResource;
use App\Models\Course;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    /**
     * POST /api/instructor/courses/{slug}/sections
     * Create a new section (position = max + 1).
     */
    public function store(StoreSectionRequest $request, string $slug): JsonResponse
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        $this->authorizeCourseOwnership($request, $course);

        $position = $course->sections()->max('position');
        $position = $position === null ? 0 : $position + 1;

        $section = $course->sections()->create([
            'title'    => $request->validated('title'),
            'position' => $position,
        ]);

        return response()->json([
            'data' => new InstructorSectionResource($section),
        ], 201);
    }

    /**
     * PATCH /api/instructor/sections/{id}
     * Update section title.
     */
    public function update(UpdateSectionRequest $request, Section $section): JsonResponse
    {
        $this->authorizeCourseOwnership($request, $section->course);

        $section->update($request->validated());

        return response()->json([
            'data' => new InstructorSectionResource($section),
        ]);
    }

    /**
     * DELETE /api/instructor/sections/{id}
     * Delete section (cascade lessons via DB FK).
     */
    public function destroy(Request $request, Section $section): JsonResponse
    {
        $this->authorizeCourseOwnership($request, $section->course);

        $section->delete();

        return response()->json(null, 204);
    }

    /**
     * PATCH /api/instructor/courses/{slug}/sections/reorder
     * Reorder sections — ordered_ids must exactly match course's section IDs.
     */
    public function reorder(ReorderRequest $request, string $slug): JsonResponse
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        $this->authorizeCourseOwnership($request, $course);

        $orderedIds = $request->validated('ordered_ids');
        $existingIds = $course->sections()->pluck('id')->sort()->values()->all();
        $providedIds = collect($orderedIds)->sort()->values()->all();

        if ($existingIds !== $providedIds) {
            return response()->json([
                'message' => 'The ordered_ids must exactly match the sections of this course.',
                'errors'  => ['ordered_ids' => ['The provided IDs do not match the course sections.']],
            ], 422);
        }

        foreach ($orderedIds as $position => $id) {
            Section::where('id', $id)->update(['position' => $position]);
        }

        $sections = $course->sections()->orderBy('position')->get();

        return response()->json([
            'data' => InstructorSectionResource::collection($sections),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function authorizeCourseOwnership(Request $request, Course $course): void
    {
        if ($request->user()->id !== $course->instructor_id) {
            abort(response()->json([
                'message' => 'You do not own this course.',
            ], 403));
        }
    }
}
