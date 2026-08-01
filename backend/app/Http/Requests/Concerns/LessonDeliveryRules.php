<?php

namespace App\Http\Requests\Concerns;

use App\Models\Course;
use App\Rules\MeetingUrl;
use App\Rules\VideoUrl;

/**
 * Delivery-aware validation for the lesson write endpoints.
 *
 * The two URL fields are mutually exclusive by course mode: an on-demand
 * course must not carry a join link, and a live course must not have its
 * session smuggled into video_url — which is exactly what would happen if the
 * flat VideoUrl rule stayed, since Meet and Zoom links fail it with a message
 * about YouTube.
 */
trait LessonDeliveryRules
{
    protected function lessonDeliveryRules(?Course $course): array
    {
        // meeting_url and starts_at stay nullable even for a live course:
        // authors routinely outline the schedule before the links exist. The
        // "every live lesson has a link and a date" invariant is enforced at
        // publish time instead, next to the existing "no empty course" guard.
        if ($course?->isLive()) {
            return [
                'video_url'   => ['nullable', new VideoUrl()],
                'meeting_url' => ['nullable', new MeetingUrl()],
                'starts_at'   => ['nullable', 'date'],
            ];
        }

        return [
            'video_url'   => ['nullable', new VideoUrl()],
            'meeting_url' => ['prohibited'],
            'starts_at'   => ['prohibited'],
        ];
    }

    protected function lessonDeliveryMessages(): array
    {
        return [
            'meeting_url.prohibited' => 'Only a live course can have a meeting link.',
            'starts_at.prohibited'   => 'Only a live course can have a scheduled session.',
        ];
    }
}
