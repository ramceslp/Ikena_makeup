<?php

namespace App\Reports\Queries;

use App\Models\Course;
use App\Models\Enrollment;
use App\Reports\Money\RevenueStreams;
use App\Reports\Money\StreamKey;
use App\Reports\ReportFilter;

/**
 * TopCoursesQuery — spec's "Top courses ranking" requirement ([Slice 4]):
 * ranks courses by PAID enrollment revenue (the `course_sale` stream, read
 * exclusively through `RevenueStreams::query()`), excluding free
 * (`price_paid=0`, no order) enrollments from the revenue figure while
 * still counting them in a separate total. Free enrollments anchor on
 * `created_at` — the same convention `SummaryQuery::freeEnrollmentsCount()`
 * uses, since they have no payment timestamp to anchor on.
 */
final class TopCoursesQuery
{
    public function __construct(private readonly RevenueStreams $streams)
    {
    }

    /**
     * @return array<int, array{
     *     course_id: int,
     *     title: string,
     *     revenue_cents: int,
     *     paid_enrollment_count: int,
     *     free_enrollment_count: int,
     * }>
     */
    public function run(ReportFilter $filter): array
    {
        $anchor = $this->streams->anchorColumn(StreamKey::CourseSale);
        $amount = $this->streams->amountColumn(StreamKey::CourseSale);

        $paidRows = $this->streams->query(StreamKey::CourseSale)
            ->where($anchor, '>=', $filter->from)
            ->where($anchor, '<', $filter->to)
            ->selectRaw("course_id, SUM({$amount}) as revenue_cents, COUNT(*) as paid_enrollment_count")
            ->groupBy('course_id')
            ->get()
            ->keyBy('course_id');

        $freeCounts = Enrollment::query()
            ->where('price_paid', 0)
            ->where('created_at', '>=', $filter->from)
            ->where('created_at', '<', $filter->to)
            ->selectRaw('course_id, COUNT(*) as free_enrollment_count')
            ->groupBy('course_id')
            ->get()
            ->keyBy('course_id');

        $courseIds = $paidRows->keys()->merge($freeCounts->keys())->unique();

        if ($courseIds->isEmpty()) {
            return [];
        }

        $titles = Course::query()->whereIn('id', $courseIds)->pluck('title', 'id');

        return $courseIds->map(function ($courseId) use ($paidRows, $freeCounts, $titles) {
            $paid = $paidRows->get($courseId);
            $free = $freeCounts->get($courseId);

            return [
                'course_id' => (int) $courseId,
                'title' => $titles->get((int) $courseId, 'Curso eliminado'),
                'revenue_cents' => (int) ($paid->revenue_cents ?? 0),
                'paid_enrollment_count' => (int) ($paid->paid_enrollment_count ?? 0),
                'free_enrollment_count' => (int) ($free->free_enrollment_count ?? 0),
            ];
        })->sortByDesc('revenue_cents')->values()->all();
    }
}
