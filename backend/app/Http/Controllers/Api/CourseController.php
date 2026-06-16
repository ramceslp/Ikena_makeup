<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseCardResource;
use App\Http\Resources\CourseDetailResource;
use App\Http\Resources\MyCourseResource;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    /**
     * GET /api/courses — Public catalog with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Course::query()
            ->where('is_published', true)
            ->with('instructor')
            ->withCount(['lessons', 'sections', 'reviews'])
            ->withAvg('reviews as reviews_avg_rating', 'rating');

        // Search filter
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Price range filters
        if ($minPrice = $request->query('min_price')) {
            $query->where('price', '>=', (float) $minPrice);
        }

        if ($maxPrice = $request->query('max_price')) {
            $query->where('price', '<=', (float) $maxPrice);
        }

        // Sorting
        $sort = $request->query('sort', 'newest');
        match ($sort) {
            'price_asc'  => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            default      => $query->orderBy('created_at', 'desc'),
        };

        $courses = $query->paginate(12);

        // Resolve is_enrolled for authenticated users
        if ($user = $request->user()) {
            $enrolledIds = $user->enrolledCourses()->pluck('courses.id')->toArray();

            $courses->getCollection()->transform(function ($course) use ($enrolledIds) {
                $course->is_enrolled = in_array($course->id, $enrolledIds);
                return $course;
            });
        }

        return response()->json(CourseCardResource::collection($courses)->response()->getData(true));
    }

    /**
     * GET /api/courses/{slug} — Course detail with sections and lessons.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $course = Course::where('slug', $slug)
            ->where('is_published', true)
            ->with([
                'instructor',
                'sections.lessons',
            ])
            ->withCount(['lessons', 'reviews'])
            ->withAvg('reviews as reviews_avg_rating', 'rating')
            ->firstOrFail();

        $isEnrolled = false;
        $completedLessonIds = [];
        $myReview = null;

        if ($user = $request->user()) {
            $isEnrolled = $user->enrolledCourses()->where('courses.id', $course->id)->exists();

            if ($isEnrolled) {
                $completedLessonIds = $user->completedLessons()->pluck('lessons.id')->toArray();
            }

            $myReview = \App\Models\CourseReview::where('course_id', $course->id)
                ->where('user_id', $user->id)
                ->first();
        }

        $course->is_enrolled = $isEnrolled;
        $course->my_review   = $myReview;

        // Attach completed flag to each lesson
        foreach ($course->sections as $section) {
            foreach ($section->lessons as $lesson) {
                $lesson->is_completed = in_array($lesson->id, $completedLessonIds);
            }
        }

        return response()->json([
            'data' => new CourseDetailResource($course),
        ]);
    }

    /**
     * POST /api/courses/{slug}/enroll — Idempotent enrollment (MVP free purchase).
     */
    public function enroll(Request $request, string $slug): JsonResponse
    {
        $course = Course::where('slug', $slug)
            ->where('is_published', true)
            ->withCount('lessons')
            ->with('instructor')
            ->firstOrFail();

        $user = $request->user();

        Enrollment::firstOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            ['price_paid' => $course->price]
        );

        // Build MyCourse shape
        $totalLessons = $course->lessons_count;
        $completedLessons = $user->completedLessons()
            ->whereHas('section', fn ($q) => $q->where('course_id', $course->id))
            ->count();

        $course->total_lessons     = $totalLessons;
        $course->completed_lessons = $completedLessons;

        return response()->json([
            'data' => new MyCourseResource($course),
        ], 201);
    }
}
