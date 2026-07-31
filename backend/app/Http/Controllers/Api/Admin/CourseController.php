<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCourseRequest;
use App\Http\Requests\Admin\UpdateCourseRequest;
use App\Http\Resources\Admin\AdminCourseCardResource;
use App\Http\Resources\Admin\AdminCourseDetailResource;
use App\Models\Course;
use App\Services\Push\PushDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Catalog governance for courses.
 *
 * The instructor endpoints answer "my courses"; these answer "the academy's
 * courses". Keeping them apart is the point — one endpoint cannot carry both
 * an ownership-scoped and a catalog-wide listing semantic without the
 * authorization rules becoming ambiguous.
 *
 * Scope note: sections and lessons are NOT re-exposed here. Admins reach deep
 * authoring through the existing instructor editor, which CoursePolicy::manage
 * opens to them.
 */
class CourseController extends Controller
{
    /**
     * GET /api/admin/courses
     * Every course in the catalog (published + draft), across all instructors.
     *
     * Filters: search (title), instructor_id, is_published.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'instructor_id' => ['sometimes', 'nullable', 'integer'],
            'is_published'  => ['sometimes', 'nullable', 'boolean'],
        ]);

        $courses = Course::query()
            ->with(['instructor', 'category'])
            ->withCount(['sections', 'lessons', 'students'])
            ->when(
                filled($validated['search'] ?? null),
                fn ($query) => $query->where('title', 'like', '%' . $validated['search'] . '%')
            )
            ->when(
                filled($validated['instructor_id'] ?? null),
                fn ($query) => $query->where('instructor_id', $validated['instructor_id'])
            )
            // Compared against null rather than filled(): is_published=0 is a
            // meaningful filter (drafts only) that filled() would discard.
            ->when(
                ($validated['is_published'] ?? null) !== null,
                fn ($query) => $query->where('is_published', $validated['is_published'])
            )
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return response()->json(
            AdminCourseCardResource::collection($courses)->response()->getData(true)
        );
    }

    /**
     * POST /api/admin/courses
     * Create a course on behalf of an instructor. Always starts as a draft.
     */
    public function store(StoreCourseRequest $request): JsonResponse
    {
        $data = $request->validated();

        $course = Course::create([
            'instructor_id'      => $data['instructor_id'],
            'category_id'        => $data['category_id'] ?? null,
            'title'              => $data['title'],
            'slug'               => $this->uniqueSlug(Str::slug($data['title'])),
            'description'        => $data['description'],
            'price'              => $data['price'] ?? 0,
            'thumbnail'          => $data['thumbnail'] ?? null,
            // Hard-coded, exactly as the instructor endpoint does: publishing is
            // its own transition because it must enforce the "no empty course"
            // rule and is the single place the push notification fires from.
            'is_published'       => false,
            'offers_certificate' => $data['offers_certificate'] ?? false,
        ]);

        return response()->json([
            'data' => new AdminCourseDetailResource($this->loadDetail($course)),
        ], 201);
    }

    /**
     * GET /api/admin/courses/{course}
     */
    public function show(Course $course): JsonResponse
    {
        return response()->json([
            'data' => new AdminCourseDetailResource($this->loadDetail($course)),
        ]);
    }

    /**
     * PATCH /api/admin/courses/{course}
     * Update metadata; may reassign the course to another instructor.
     */
    public function update(UpdateCourseRequest $request, Course $course): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['title']) && $data['title'] !== $course->title) {
            $data['slug'] = $this->uniqueSlug(Str::slug($data['title']), $course->id);
        }

        $course->update($data);

        return response()->json([
            'data' => new AdminCourseDetailResource($this->loadDetail($course->fresh())),
        ]);
    }

    /**
     * DELETE /api/admin/courses/{course}
     * Cascade is handled by DB foreign keys.
     */
    public function destroy(Course $course): JsonResponse
    {
        $course->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/admin/courses/{course}/publish
     * 422 when the course has no lessons — same invariant the instructor
     * endpoint enforces. Admin authority does not extend to publishing an
     * empty course; that would ship a broken product page.
     */
    public function publish(Course $course, PushDispatcher $pushDispatcher): JsonResponse
    {
        if ($course->lessons()->count() === 0) {
            return response()->json([
                'message' => 'Cannot publish a course with no lessons.',
            ], 422);
        }

        $course->update(['is_published' => true]);

        // Fires at most once per course — PushDispatcher stamps
        // push_notified_at, so an unpublish/republish cycle does not re-notify.
        $pushDispatcher->forCourse($course);

        return response()->json([
            'data' => new AdminCourseDetailResource($this->loadDetail($course)),
        ]);
    }

    /**
     * POST /api/admin/courses/{course}/unpublish
     */
    public function unpublish(Course $course): JsonResponse
    {
        $course->update(['is_published' => false]);

        return response()->json([
            'data' => new AdminCourseDetailResource($this->loadDetail($course)),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Attach the relations and counts every detail response carries.
     */
    private function loadDetail(Course $course): Course
    {
        return $course->load('instructor')
            ->loadCount(['sections', 'lessons', 'students']);
    }

    /**
     * Generate a slug that is unique in the courses table.
     * Counter starts at 2 — first collision yields "my-course-2".
     *
     * @param  int|null  $excludeId  Course ID to exclude (for updates)
     */
    private function uniqueSlug(string $base, ?int $excludeId = null): string
    {
        $slug    = $base;
        $counter = 2;

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
