<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Instructor\StoreCourseRequest;
use App\Http\Requests\Instructor\UpdateCourseRequest;
use App\Http\Resources\Instructor\InstructorCourseCardResource;
use App\Http\Resources\Instructor\InstructorCourseDetailResource;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    /**
     * GET /api/instructor/courses
     * List all courses (published + draft) owned by the authenticated instructor.
     */
    public function index(Request $request): JsonResponse
    {
        $courses = Course::query()
            ->where('instructor_id', $request->user()->id)
            ->withCount(['sections', 'lessons', 'students'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => InstructorCourseCardResource::collection($courses),
        ]);
    }

    /**
     * POST /api/instructor/courses
     * Create a new course as draft.
     */
    public function store(StoreCourseRequest $request): JsonResponse
    {
        $data = $request->validated();

        $slug = $this->uniqueSlug(Str::slug($data['title']));

        $course = Course::create([
            'instructor_id' => $request->user()->id,
            'title'         => $data['title'],
            'slug'          => $slug,
            'description'   => $data['description'],
            'price'         => $data['price'] ?? 0,
            'thumbnail'     => $data['thumbnail'] ?? null,
            'is_published'  => false,
        ]);

        $course->loadCount('lessons');
        $course->load('sections.lessons');

        return response()->json([
            'data' => new InstructorCourseDetailResource($course),
        ], 201);
    }

    /**
     * GET /api/instructor/courses/{slug}
     * Show course detail with sections, lessons (including video_url).
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        $this->authorizeCourseOwnership($request, $course);

        $course->load('sections.lessons');
        $course->loadCount('lessons');

        return response()->json([
            'data' => new InstructorCourseDetailResource($course),
        ]);
    }

    /**
     * PATCH /api/instructor/courses/{slug}
     * Update course fields; regenerate slug when title changes.
     */
    public function update(UpdateCourseRequest $request, string $slug): JsonResponse
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        $this->authorizeCourseOwnership($request, $course);

        $data = $request->validated();

        if (isset($data['title']) && $data['title'] !== $course->title) {
            $data['slug'] = $this->uniqueSlug(Str::slug($data['title']), $course->id);
        }

        $course->update($data);

        $course->load('sections.lessons');
        $course->loadCount('lessons');

        return response()->json([
            'data' => new InstructorCourseDetailResource($course),
        ]);
    }

    /**
     * DELETE /api/instructor/courses/{slug}
     * Delete course (cascade handled by DB foreign keys).
     */
    public function destroy(Request $request, string $slug): JsonResponse
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        $this->authorizeCourseOwnership($request, $course);

        $course->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/instructor/courses/{slug}/publish
     * Publish course — 422 if course has 0 lessons.
     */
    public function publish(Request $request, string $slug): JsonResponse
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        $this->authorizeCourseOwnership($request, $course);

        $lessonCount = $course->lessons()->count();

        if ($lessonCount === 0) {
            return response()->json([
                'message' => 'Cannot publish a course with no lessons.',
            ], 422);
        }

        $course->update(['is_published' => true]);

        $course->load('sections.lessons');
        $course->loadCount('lessons');

        return response()->json([
            'data' => new InstructorCourseDetailResource($course),
        ]);
    }

    /**
     * POST /api/instructor/courses/{slug}/unpublish
     * Unpublish course.
     */
    public function unpublish(Request $request, string $slug): JsonResponse
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        $this->authorizeCourseOwnership($request, $course);

        $course->update(['is_published' => false]);

        $course->load('sections.lessons');
        $course->loadCount('lessons');

        return response()->json([
            'data' => new InstructorCourseDetailResource($course),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Abort with 403 if the authenticated user does not own the course.
     */
    private function authorizeCourseOwnership(Request $request, Course $course): void
    {
        if ($request->user()->id !== $course->instructor_id) {
            abort(response()->json([
                'message' => 'You do not own this course.',
            ], 403));
        }
    }

    /**
     * Generate a slug that is unique in the courses table.
     * Appends an incrementing suffix when collisions are found.
     *
     * @param  int|null  $excludeId  Course ID to exclude (for updates)
     */
    private function uniqueSlug(string $base, ?int $excludeId = null): string
    {
        $slug = $base;
        $counter = 1;

        while (true) {
            $query = Course::where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            if (! $query->exists()) {
                return $slug;
            }

            $slug = "{$base}-{$counter}";
            $counter++;
        }
    }
}
