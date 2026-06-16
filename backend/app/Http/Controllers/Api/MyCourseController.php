<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MyCourseResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyCourseController extends Controller
{
    /**
     * GET /api/my-courses — List enrolled courses with progress.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $courses = $user->enrolledCourses()
            ->with('instructor')
            ->withCount('lessons')
            ->get();

        $completedIds = $user->completedLessons()->pluck('lessons.id')->toArray();

        $courses->each(function ($course) use ($completedIds) {
            $lessonIds = $course->lessons()->pluck('lessons.id')->toArray();

            $course->total_lessons     = count($lessonIds);
            $course->completed_lessons = count(array_intersect($lessonIds, $completedIds));
        });

        return response()->json([
            'data' => MyCourseResource::collection($courses),
        ]);
    }
}
