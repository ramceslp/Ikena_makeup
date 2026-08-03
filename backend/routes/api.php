<?php

use App\Http\Controllers\Api\Admin\AgendaBlockController as AdminAgendaBlockController;
use App\Http\Controllers\Api\Admin\AppointmentController as AdminAppointmentController;
use App\Http\Controllers\Api\Admin\CertificateSettingController as AdminCertificateSettingController;
use App\Http\Controllers\Api\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Api\Admin\InstructorController as AdminInstructorController;
use App\Http\Controllers\Api\Admin\PostController as AdminPostController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\PushNotificationController as AdminPushNotificationController;
use App\Http\Controllers\Api\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CartCheckoutController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\CertificateSettingController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CheckoutHandoffController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CourseReviewController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\Instructor\CourseController as InstructorCourseController;
use App\Http\Controllers\Api\Instructor\DashboardController as InstructorDashboardController;
use App\Http\Controllers\Api\Instructor\AttendanceController;
use App\Http\Controllers\Api\Instructor\LessonController as InstructorLessonController;
use App\Http\Controllers\Api\Instructor\SectionController as InstructorSectionController;
use App\Http\Controllers\Api\Instructor\SubmissionController as InstructorSubmissionController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\MyCourseController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\PracticeSubmissionController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\SubmissionFileController;
use App\Http\Controllers\Api\VisitorEventController;
use App\Http\Middleware\RejectScopedCheckoutToken;
use App\Models\PracticeSubmission;
use Illuminate\Support\Facades\Route;

// Public routes
//
// 'throttle:auth' is tighter than the baseline 'throttle:api' the whole group
// already carries (see bootstrap/app.php): these three mint full-access
// Sanctum tokens, so they are the brute-force and password-spraying surface.
// The limiter keys on IP AND email independently — see
// AppServiceProvider::configureRateLimiting().
Route::middleware('throttle:auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/auth/google', [AuthController::class, 'google']);
});

Route::get('/categories', [CategoryController::class, 'index']);

// Public catalog/detail — personalized (is_enrolled, my_review) when a bearer
// token is present, so they resolve the Sanctum user via 'auth.optional'.
Route::middleware('auth.optional')->group(function () {
    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/courses/{slug}', [CourseController::class, 'show']);
});

Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{slug}', [ServiceController::class, 'show']);

// Public product catalog — no auth required
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{slug}', [ProductController::class, 'show']);

// Public post (news) endpoints.
// Static segments MUST be declared BEFORE the parameterized {slug} route
// to prevent "latest" and "featured" from being captured as slug values.
Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/latest', [PostController::class, 'latest']);
Route::get('/posts/featured', [PostController::class, 'featured']);
Route::get('/posts/{slug}', [PostController::class, 'show']);
Route::get('/services/{serviceId}/available-days', [BookingController::class, 'availableDays']);
Route::get('/services/{serviceId}/available-slots', [BookingController::class, 'availableSlots']);
Route::get('/courses/{course:slug}/reviews', [CourseReviewController::class, 'index'])
    ->middleware('auth.optional');
// 'throttle:verify' because enumerating 'IKENA-' + 10 chars leaks a student
// name and a course title per hit — this is a PII-scraping limit.
Route::get('/certificates/verify/{code}', [CertificateController::class, 'verify'])
    ->middleware('throttle:verify');

// Practice-submission photos. Public in the routing sense but NOT public
// access: the 'signed' middleware is the access control, and the URL is only
// ever minted for a caller that already passed the ownership/role gate on the
// endpoint that serialised the submission (see SubmissionFileController).
//
// It cannot sit behind auth:sanctum — both clients render these in
// <img :src="submission.before_url">, and an <img> tag sends no Authorization
// header.
//
// Throttling: the instructor review list renders 15 submissions x 2 photos, so
// the 60/min 'api' baseline would lock the screen on the second page load. A
// route-level throttle does NOT replace the group one — both would apply and
// the tighter baseline would still bind — so the baseline is explicitly
// dropped here and 'throttle:media' (300/min) put in its place.
Route::get('/submissions/{submission}/{variant}', [SubmissionFileController::class, 'show'])
    ->withoutMiddleware('throttle:api')
    ->middleware(['signed', 'throttle:media'])
    ->whereIn('variant', PracticeSubmission::VARIANTS)
    ->name('submissions.file');

// Public certificate branding (logo, business name, copy, signer, design variant)
// for the certificate canvas. No auth — returns defaults when unseeded.
Route::get('/certificate-settings', [CertificateSettingController::class, 'show']);

// Visitor analytics ingestion — public, unauthenticated by design (design D4:
// sdd/visitor-analytics/design). Emitted from router.afterEach and
// cart.addItem() once PR3 lands; inert until then. 'auth.optional' resolves a
// bearer token when present (for user_id attribution) but never rejects a
// guest — a 401 is structurally impossible here.
//
// NOTE: the 'throttle:analytics' override (see the same trap documented
// above at 'submissions/{submission}/{variant}') is added in a later task
// once its limiter is registered — until then this route still carries the
// default 60/min 'throttle:api' group limiter.
Route::post('/analytics/events', [VisitorEventController::class, 'store'])
    ->middleware('auth.optional');

// Checkout-handoff redeem — public because the browser opened from the app
// is a separate, logged-out session (see CheckoutHandoffController::redeem
// and design Decision 1, sdd/mobile-capacitor-setup/design.md).
//
// 'throttle:opaque-token' because the 40-char single-use token is the ONLY
// thing authorising this call: it is public, and redeeming someone else's
// token spends their money. Unthrottled, it is a guessing target.
Route::post('/checkout/handoff/redeem', [CheckoutHandoffController::class, 'redeem'])
    ->middleware('throttle:opaque-token');

// Protected routes — require Sanctum Bearer token
//
// 'reject-scoped-checkout-token' is a deny-list guard (see
// App\Http\Middleware\RejectScopedCheckoutToken) that rejects the scoped
// 'checkout-confirm' Sanctum token minted by
// CheckoutHandoffController::redeem() on every route in this group EXCEPT
// POST /api/payments/confirm, which explicitly opts out below via
// withoutMiddleware() — that route is the one action that token is allowed
// to perform.
Route::middleware(['auth:sanctum', RejectScopedCheckoutToken::class])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Profile — POST instead of PATCH/PUT because PHP does not parse multipart
    // (file upload) bodies for PUT/PATCH requests.
    Route::post('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
    Route::get('/profile/orders', [ProfileController::class, 'orders']);
    // The customer's own agenda (?scope=upcoming|past). Distinct from the
    // admin list at /admin/appointments, which spans every user.
    Route::get('/profile/appointments', [ProfileController::class, 'appointments']);

    Route::get('/my-courses', [MyCourseController::class, 'index']);

    Route::post('/courses/{slug}/enroll', [CourseController::class, 'enroll']);

    Route::post('/courses/{course:slug}/checkout', [CheckoutController::class, 'checkout']);

    // Excluded from 'reject-scoped-checkout-token' — this is the one route the
    // scoped 'checkout-confirm' token minted by CheckoutHandoffController::redeem()
    // is allowed to call.
    Route::post('/payments/confirm', [CheckoutController::class, 'confirm'])
        ->withoutMiddleware(RejectScopedCheckoutToken::class)
        ->middleware('throttle:opaque-token');

    Route::post('/bookings', [BookingController::class, 'store']);

    // Cart checkout — product_cart orders (requires auth:sanctum)
    Route::post('/cart/checkout', [CartCheckoutController::class, 'store']);

    // Checkout-handoff — snapshot cart/booking behind a single-use token for
    // the mobile app to hand off to the web checkout flow (mobile-capacitor-setup PR2).
    Route::post('/checkout/handoff', [CheckoutHandoffController::class, 'store']);

    // Push notification device tokens (mobile-capacitor-setup PR3).
    Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
    Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);

    Route::get('/lessons/{lesson}', [LessonController::class, 'show']);
    Route::post('/lessons/{lesson}/complete', [LessonController::class, 'complete']);
    // 'throttle:uploads' — two 5 MB images per request, so this is the
    // cheapest disk-exhaustion surface in the API.
    Route::post('/lessons/{lesson}/submissions', [PracticeSubmissionController::class, 'store'])
        ->middleware('throttle:uploads');

    Route::post('/courses/{course:slug}/reviews', [CourseReviewController::class, 'store']);
    Route::delete('/courses/{course:slug}/reviews', [CourseReviewController::class, 'destroy']);

    Route::get('/courses/{course:slug}/certificate', [CertificateController::class, 'show']);

    // Admin routes
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/ping', fn () => response()->json(['message' => 'pong']));

        // Course catalog governance — spans every instructor, unlike the
        // ownership-scoped /instructor/courses endpoints. Sections and lessons
        // are intentionally absent: admins author content through the
        // instructor editor, which CoursePolicy::manage opens to them.
        Route::get('/courses', [AdminCourseController::class, 'index']);
        Route::post('/courses', [AdminCourseController::class, 'store']);
        Route::get('/courses/{course}', [AdminCourseController::class, 'show']);
        Route::patch('/courses/{course}', [AdminCourseController::class, 'update']);
        Route::delete('/courses/{course}', [AdminCourseController::class, 'destroy']);
        Route::post('/courses/{course}/publish', [AdminCourseController::class, 'publish']);
        Route::post('/courses/{course}/unpublish', [AdminCourseController::class, 'unpublish']);

        // Instructor picker for the course form
        Route::get('/instructors', [AdminInstructorController::class, 'index']);

        // Services CRUD
        Route::get('/services', [AdminServiceController::class, 'index']);
        Route::post('/services', [AdminServiceController::class, 'store']);
        Route::get('/services/{service}', [AdminServiceController::class, 'show']);
        Route::post('/services/{service}', [AdminServiceController::class, 'update']);
        Route::delete('/services/{service}', [AdminServiceController::class, 'destroy']);

        // Service image management
        Route::post('/services/{service}/images', [AdminServiceController::class, 'storeImages']);
        Route::delete('/services/{service}/images/{image}', [AdminServiceController::class, 'destroyImage']);
        Route::patch('/services/{service}/images/reorder', [AdminServiceController::class, 'reorderImages']);

        // Products CRUD
        Route::get('/products', [AdminProductController::class, 'index']);
        Route::post('/products', [AdminProductController::class, 'store']);
        Route::get('/products/{product}', [AdminProductController::class, 'show']);
        Route::post('/products/{product}', [AdminProductController::class, 'update']);
        Route::delete('/products/{product}', [AdminProductController::class, 'destroy']);

        // Product image management
        Route::post('/products/{product}/images', [AdminProductController::class, 'storeImages']);
        Route::delete('/products/{product}/images/{image}', [AdminProductController::class, 'destroyImage']);
        Route::post('/products/{product}/images/reorder', [AdminProductController::class, 'reorderImages']);

        // Posts (news) CRUD
        Route::get('/posts', [AdminPostController::class, 'index']);
        Route::post('/posts', [AdminPostController::class, 'store']);
        Route::get('/posts/{post}', [AdminPostController::class, 'show']);
        Route::post('/posts/{post}', [AdminPostController::class, 'update']);
        Route::delete('/posts/{post}', [AdminPostController::class, 'destroy']);

        // Post cover image management
        Route::post('/posts/{post}/cover', [AdminPostController::class, 'storeCoverImage']);
        Route::delete('/posts/{post}/cover', [AdminPostController::class, 'destroyCoverImage']);

        // Post body image management
        Route::post('/posts/{post}/images', [AdminPostController::class, 'storeImages']);
        Route::delete('/posts/{post}/images/{image}', [AdminPostController::class, 'destroyImage']);
        Route::post('/posts/{post}/images/reorder', [AdminPostController::class, 'reorderImages']);

        // Certificate branding settings (singleton)
        Route::get('/certificate-settings', [AdminCertificateSettingController::class, 'show']);
        Route::post('/certificate-settings', [AdminCertificateSettingController::class, 'update']);

        // Appointment management
        Route::get('/appointments', [AdminAppointmentController::class, 'index']);
        Route::patch('/appointments/{appointment}/mark-paid', [AdminAppointmentController::class, 'markPaid']);
        Route::patch('/appointments/{appointment}/cancel', [AdminAppointmentController::class, 'cancel']);

        // Push notifications — unified send history + custom broadcasts
        Route::get('/push-notifications', [AdminPushNotificationController::class, 'index']);
        Route::post('/push-notifications', [AdminPushNotificationController::class, 'store']);
        Route::get('/push-notifications/stats', [AdminPushNotificationController::class, 'stats']);
        Route::get('/push-notifications/destinations', [AdminPushNotificationController::class, 'destinations']);

        // Venue agenda block CRUD (VAGA-001)
        Route::get('/agenda', [AdminAgendaBlockController::class, 'index']);
        Route::post('/agenda', [AdminAgendaBlockController::class, 'store']);
        Route::get('/agenda/{block}', [AdminAgendaBlockController::class, 'show']);
        Route::patch('/agenda/{block}', [AdminAgendaBlockController::class, 'update']);
        Route::delete('/agenda/{block}', [AdminAgendaBlockController::class, 'destroy']);

        // Reports center — read-only settlement/revenue aggregates
        // (admin-reports-center design D3/D4). Thin controller, delegates
        // to app/Reports/Queries/*; ledger + export are PR3, rankings/
        // funnel/receivables are PR4a.
        Route::prefix('reports')->group(function () {
            Route::get('/summary', [AdminReportController::class, 'summary']);
            Route::get('/timeline', [AdminReportController::class, 'timeline']);
            Route::get('/composition', [AdminReportController::class, 'composition']);
            // /export MUST be registered before /{...} would ever be added —
            // no such wildcard exists here today, but keeping the more
            // specific route first avoids the trap if one ever is.
            Route::get('/ledger/export', [AdminReportController::class, 'ledgerExport']);
            Route::get('/ledger', [AdminReportController::class, 'ledger']);
            Route::get('/rankings/products', [AdminReportController::class, 'topProducts']);
            Route::get('/rankings/services', [AdminReportController::class, 'topServices']);
            Route::get('/rankings/courses', [AdminReportController::class, 'topCourses']);
            Route::get('/funnel', [AdminReportController::class, 'funnel']);
            Route::get('/receivables', [AdminReportController::class, 'receivables']);
        });
    });

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

        // Attendance for live sessions. Lives on the instructor side because
        // students may not mark their own progress on a live course — see the
        // guard in Api\LessonController::complete.
        Route::get('/lessons/{lesson}/attendance', [AttendanceController::class, 'index']);
        Route::put('/lessons/{lesson}/attendance', [AttendanceController::class, 'update']);
    });
});
