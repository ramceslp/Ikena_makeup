<?php

namespace App\Actions;

use App\Models\Course;

/**
 * The publishability rules for a course, in one place.
 *
 * Publishing is the point of no return: it is what makes a course visible in
 * the catalog and what fires the push notification. The instructor and admin
 * controllers each had their own copy of the "no empty course" check; adding
 * the live-course rules to both copies is how they would drift, so the whole
 * decision moved here.
 */
class CoursePublishGuard
{
    /**
     * @return string|null  A human-readable reason the course cannot be
     *                      published, or null when it is publishable.
     */
    public function reasonCannotPublish(Course $course): ?string
    {
        if ($course->lessons()->count() === 0) {
            return 'Cannot publish a course with no lessons.';
        }

        if (! $course->isLive()) {
            return null;
        }

        if ($course->starts_on === null || $course->ends_on === null) {
            return 'Cannot publish a live course without a start and end date.';
        }

        // A live lesson with no link or no date is a session students paid for
        // and cannot attend. Caught here rather than at write time so authors
        // can still draft the schedule before the links exist.
        $incomplete = $course->lessons()
            ->where(function ($query) {
                $query->whereNull('lessons.meeting_url')
                      ->orWhereNull('lessons.starts_at');
            })
            ->count();

        if ($incomplete > 0) {
            return 'Cannot publish a live course while some lessons have no meeting link or no scheduled date.';
        }

        return null;
    }
}
