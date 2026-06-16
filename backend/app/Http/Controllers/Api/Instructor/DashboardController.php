<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Resources\Instructor\InstructorDashboardResource;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * GET /api/instructor/dashboard
     *
     * Returns aggregate KPIs and a 6-month sales timeline for the
     * authenticated instructor's own courses. Read-only — no mutations.
     */
    public function index(Request $request): JsonResponse
    {
        $instructor = $request->user();

        // ---------------------------------------------------------------
        // 1. Collect the instructor's course IDs (basis for all scoping)
        // ---------------------------------------------------------------
        $courseIds = $instructor
            ->coursesTeaching()
            ->pluck('id')
            ->all();

        // ---------------------------------------------------------------
        // 2. Course counts
        // ---------------------------------------------------------------
        $totalCourses     = count($courseIds);
        $publishedCourses = $totalCourses > 0
            ? $instructor->coursesTeaching()->where('is_published', true)->count()
            : 0;

        // ---------------------------------------------------------------
        // 3. Revenue & sales — only paid orders, scoped to own courses
        // ---------------------------------------------------------------
        $revenueAndSales = $totalCourses > 0
            ? Order::whereIn('course_id', $courseIds)
                ->where('status', 'paid')
                ->selectRaw('COALESCE(SUM(amount_cents), 0) as total_revenue_cents, COUNT(*) as total_sales')
                ->first()
            : null;

        $totalRevenueCents = $revenueAndSales ? (int) $revenueAndSales->total_revenue_cents : 0;
        $totalSales        = $revenueAndSales ? (int) $revenueAndSales->total_sales : 0;

        // ---------------------------------------------------------------
        // 4. Distinct enrolled students across all instructor's courses
        // ---------------------------------------------------------------
        $totalStudents = $totalCourses > 0
            ? Enrollment::whereIn('course_id', $courseIds)
                ->distinct('user_id')
                ->count('user_id')
            : 0;

        // ---------------------------------------------------------------
        // 5. Average rating across the instructor's courses
        // ---------------------------------------------------------------
        $avgRatingRaw = $totalCourses > 0
            ? CourseReview::whereIn('course_id', $courseIds)->avg('rating')
            : null;

        $averageRating = $avgRatingRaw !== null ? round((float) $avgRatingRaw, 1) : null;

        // ---------------------------------------------------------------
        // 6. Sales over time — last 6 calendar months (oldest → newest),
        //    zero-filled. Keyed on paid_at.
        //
        //    currency is hardcoded to USD — single gateway for now.
        //    TODO: switch to a per-order currency lookup when multi-currency
        //    is introduced.
        // ---------------------------------------------------------------
        $salesOverTime = $this->buildSalesOverTime($courseIds);

        // ---------------------------------------------------------------
        // 7. Build and return the resource payload
        // ---------------------------------------------------------------
        $payload = [
            'kpis' => [
                'total_revenue_cents' => $totalRevenueCents,
                'currency'            => 'USD', // hardcoded — single gateway, pending multi-currency
                'total_sales'         => $totalSales,
                'total_students'      => $totalStudents,
                'total_courses'       => $totalCourses,
                'published_courses'   => $publishedCourses,
                'average_rating'      => $averageRating,
            ],
            'sales_over_time' => $salesOverTime,
        ];

        return response()->json([
            'data' => new InstructorDashboardResource($payload),
        ]);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Build the 6-month sales timeline for the given course IDs.
     *
     * Generates the window in PHP (Carbon) so months with zero activity
     * are always present. The window is the current month plus the 5
     * preceding calendar months, ordered oldest → newest.
     *
     * Grouping is done in PHP after fetching only the relevant columns
     * (paid_at, amount_cents) — this avoids DB-specific date functions
     * (DATE_FORMAT for MySQL vs strftime for SQLite) and keeps the query
     * simple and portable across environments.
     *
     * @param  array<int>  $courseIds
     * @return array<int, array{period: string, revenue_cents: int, sales: int}>
     */
    private function buildSalesOverTime(array $courseIds): array
    {
        // Generate the 6-month window (oldest first)
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $months[] = Carbon::now()->subMonths($i)->format('Y-m');
        }

        // Zero-filled template indexed by "YYYY-MM"
        $template = array_fill_keys($months, ['revenue_cents' => 0, 'sales' => 0]);

        if (! empty($courseIds)) {
            $windowStart = Carbon::now()->subMonths(5)->startOfMonth();

            // Fetch only the columns needed for aggregation — no raw date SQL
            Order::whereIn('course_id', $courseIds)
                ->where('status', 'paid')
                ->whereNotNull('paid_at')
                ->where('paid_at', '>=', $windowStart)
                ->select(['paid_at', 'amount_cents'])
                ->get()
                ->each(function ($order) use (&$template): void {
                    $period = Carbon::parse($order->paid_at)->format('Y-m');

                    if (array_key_exists($period, $template)) {
                        $template[$period]['revenue_cents'] += (int) $order->amount_cents;
                        $template[$period]['sales']         += 1;
                    }
                });
        }

        // Expand into ordered array with period key included
        $result = [];
        foreach ($template as $period => $data) {
            $result[] = ['period' => $period, ...$data];
        }

        return $result;
    }
}
