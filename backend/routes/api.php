<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CourseReviewController;
use App\Http\Controllers\Api\Instructor\CourseController as InstructorCourseController;
use App\Http\Controllers\Api\Instructor\DashboardController as InstructorDashboardController;
use App\Http\Controllers\Api\Instructor\LessonController as InstructorLessonController;
use App\Http\Controllers\Api\Instructor\SectionController as InstructorSectionController;
use App\Http\Controllers\Api\Instructor\SubmissionController as InstructorSubmissionController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\MyCourseController;
use App\Http\Controllers\Api\PracticeSubmissionController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/google', [AuthController::class, 'google']);

Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{slug}', [CourseController::class, 'show']);
Route::get('/courses/{course:slug}/reviews', [CourseReviewController::class, 'index']);

// Protected routes — require Sanctum Bearer token
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Profile — POST instead of PATCH/PUT because PHP does not parse multipart
    // (file upload) bodies for PUT/PATCH requests.
    Route::post('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
    Route::get('/profile/orders', [ProfileController::class, 'orders']);

    Route::get('/my-courses', [MyCourseController::class, 'index']);

    Route::post('/courses/{slug}/enroll', [CourseController::class, 'enroll']);

    Route::post('/courses/{course:slug}/checkout', [CheckoutController::class, 'checkout']);
    Route::post('/payments/confirm', [CheckoutController::class, 'confirm']);

    Route::get('/lessons/{lesson}', [LessonController::class, 'show']);
    Route::post('/lessons/{lesson}/complete', [LessonController::class, 'complete']);
    Route::post('/lessons/{lesson}/submissions', [PracticeSubmissionController::class, 'store']);

    Route::post('/courses/{course:slug}/reviews', [CourseReviewController::class, 'store']);
    Route::delete('/courses/{course:slug}/reviews', [CourseReviewController::class, 'destroy']);

    // Instructor authoring routes
    Route::middleware('instructor')->prefix('instructor')->group(function () {
        // Dashboard analytics (read-only aggregates)
        Route::get('/dashboard', [InstructorDashboardController::class, 'index']);

        // Submissions (student practice grading)
        Route::get('/submissions', [InstructorSubmissionController::class, 'index']);
        Route::patch('/submissions/{submission}', [InstructorSubmissionController::class, 'update']);

        // Course CRUD + publish
        Route::get('/courses', [InstructorCourseController::class, 'index']);
        Route::post('/courses', [InstructorCourseController::class, 'store']);
        Route::get('/courses/{slug}', [InstructorCourseController::class, 'show']);
        Route::patch('/courses/{slug}', [InstructorCourseController::class, 'update']);
        Route::delete('/courses/{slug}', [InstructorCourseController::class, 'destroy']);
        Route::post('/courses/{slug}/publish', [InstructorCourseController::class, 'publish']);
        Route::post('/courses/{slug}/unpublish', [InstructorCourseController::class, 'unpublish']);

        // Sections (course-scoped)
        Route::post('/courses/{slug}/sections', [InstructorSectionController::class, 'store']);
        Route::patch('/courses/{slug}/sections/reorder', [InstructorSectionController::class, 'reorder']);

        // Sections (id-scoped)
        Route::patch('/sections/{section}', [InstructorSectionController::class, 'update']);
        Route::delete('/sections/{section}', [InstructorSectionController::class, 'destroy']);

        // Lessons (section-scoped)
        Route::post('/sections/{section}/lessons', [InstructorLessonController::class, 'store']);
        Route::patch('/sections/{section}/lessons/reorder', [InstructorLessonController::class, 'reorder']);

        // Lessons (id-scoped)
        Route::patch('/lessons/{lesson}', [InstructorLessonController::class, 'update']);
        Route::delete('/lessons/{lesson}', [InstructorLessonController::class, 'destroy']);
    });
});
