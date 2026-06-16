<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseReviewResource;
use App\Models\Course;
use App\Models\CourseReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseReviewController extends Controller
{
    /**
     * GET /api/courses/{course:slug}/reviews
     *
     * Public list of reviews for a course, paginated (10/page), latest first.
     */
    public function index(Course $course): JsonResponse
    {
        $reviews = $course->reviews()
            ->with('user')
            ->latest()
            ->paginate(10);

        return response()->json(
            CourseReviewResource::collection($reviews)->response()->getData(true)
        );
    }

    /**
     * POST /api/courses/{course:slug}/reviews
     *
     * Create or update the authenticated user's review for a course.
     * Eligibility: enrolled + at least one completed lesson in that course.
     */
    public function store(Request $request, Course $course): JsonResponse
    {
        $user = $request->user();

        // Instructor may not review own course
        if ($course->instructor_id === $user->id) {
            return response()->json(['message' => 'Instructors cannot review their own course.'], 403);
        }

        // Must be enrolled
        $isEnrolled = $user->enrolledCourses()->where('courses.id', $course->id)->exists();
        if (! $isEnrolled) {
            return response()->json(['message' => 'You must be enrolled to leave a review.'], 403);
        }

        // Must have completed at least one lesson in this course
        $hasCompletedLesson = $user->completedLessons()
            ->whereHas('section', fn ($q) => $q->where('course_id', $course->id))
            ->exists();

        if (! $hasCompletedLesson) {
            return response()->json(['message' => 'You must complete at least one lesson before leaving a review.'], 403);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body'   => ['nullable', 'string', 'max:2000'],
        ]);

        $existing = CourseReview::where('course_id', $course->id)
            ->where('user_id', $user->id)
            ->first();

        $review = CourseReview::updateOrCreate(
            ['course_id' => $course->id, 'user_id' => $user->id],
            ['rating' => $validated['rating'], 'body' => $validated['body'] ?? null]
        );

        $review->load('user');

        $statusCode = $existing ? 200 : 201;

        return response()->json(['data' => new CourseReviewResource($review)], $statusCode);
    }

    /**
     * DELETE /api/courses/{course:slug}/reviews
     *
     * Delete the authenticated user's review for a course.
     */
    public function destroy(Request $request, Course $course): JsonResponse
    {
        $user = $request->user();

        $review = CourseReview::where('course_id', $course->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $review) {
            return response()->json(['message' => 'Review not found.'], 404);
        }

        $review->delete();

        return response()->json(null, 204);
    }
}
