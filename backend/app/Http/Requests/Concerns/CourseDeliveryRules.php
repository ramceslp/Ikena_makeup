<?php

namespace App\Http\Requests\Concerns;

use App\Models\Course;
use Illuminate\Validation\Rule;

/**
 * Shared delivery-mode validation for the four course endpoints
 * (instructor/admin × store/update).
 *
 * Unlike the flat rules those requests duplicate today, the live-course
 * invariant is conditional and order-dependent, so it lives in one place:
 * duplicating `required_if` + `after_or_equal` four times is how the four
 * copies silently drift apart.
 */
trait CourseDeliveryRules
{
    /**
     * @param  Course|null  $existing  The course being updated, when this is a
     *                                 PATCH. Passing it is what makes a partial
     *                                 update unable to null out the dates of a
     *                                 course that is already live.
     */
    protected function deliveryRules(?Course $existing = null): array
    {
        $willBeLive = $this->effectiveDeliveryMode($existing) === Course::DELIVERY_LIVE;

        return [
            'delivery_mode' => ['sometimes', 'string', Rule::in(Course::DELIVERY_MODES)],
            'starts_on'     => array_merge($this->calendarPresence($willBeLive, $existing), ['date']),
            'ends_on'       => array_merge(
                $this->calendarPresence($willBeLive, $existing),
                ['date', $this->endsAfterStartRule($existing)]
            ),
            'total_hours'   => ['nullable', 'numeric', 'min:0', 'max:9999.9'],
        ];
    }

    /**
     * How strictly the calendar fields must appear in THIS payload.
     *
     * A course that is already live carries valid dates, so a partial update
     * that never mentions them is fine — but one that mentions them with null
     * is not, which is exactly the `sometimes` + `required` pair. A course
     * only now becoming live has nothing stored, so the dates must be present.
     */
    private function calendarPresence(bool $willBeLive, ?Course $existing): array
    {
        if (! $willBeLive) {
            return ['nullable'];
        }

        return $existing?->isLive()
            ? ['sometimes', 'required']
            : ['required'];
    }

    /**
     * Compare ends_on against the start date that will actually apply.
     *
     * A PATCH carrying only ends_on has no starts_on field to reference, so
     * referencing one would silently validate nothing; fall back to the stored
     * date as a literal.
     */
    private function endsAfterStartRule(?Course $existing): string
    {
        if ($this->has('starts_on')) {
            return 'after_or_equal:starts_on';
        }

        return $existing?->starts_on
            ? 'after_or_equal:' . $existing->starts_on->toDateString()
            : 'after_or_equal:starts_on';
    }

    protected function deliveryMessages(): array
    {
        return [
            'starts_on.required'       => 'A live course needs a start date.',
            'ends_on.required'         => 'A live course needs an end date.',
            'ends_on.after_or_equal'   => 'The end date cannot be earlier than the start date.',
            'delivery_mode.in'         => 'The delivery mode must be on_demand or live.',
        ];
    }

    /**
     * The mode the course will have once this request is applied: the payload
     * value when present, otherwise whatever is already stored.
     */
    private function effectiveDeliveryMode(?Course $existing): string
    {
        return $this->input('delivery_mode')
            ?? $existing?->delivery_mode
            ?? Course::DELIVERY_ON_DEMAND;
    }
}
