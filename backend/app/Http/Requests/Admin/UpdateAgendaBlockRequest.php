<?php

namespace App\Http\Requests\Admin;

use App\Models\AgendaBlock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAgendaBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // admin middleware already guards the route
    }

    public function rules(): array
    {
        return [
            'day_of_week'       => ['sometimes', 'nullable', 'integer', 'min:0', 'max:6'],
            'specific_date'     => ['sometimes', 'nullable', 'date'],
            'open_time'         => ['sometimes', 'date_format:H:i'],
            'close_time'        => ['sometimes', 'date_format:H:i'],
            'concurrency_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'soft_threshold'    => ['sometimes', 'nullable', 'integer', 'min:1'],
            'is_blocked'        => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Business-rule validation beyond field-level rules. Partial updates only
     * re-check invariants that are affected by the fields actually submitted,
     * merged against the existing block's current values.
     *
     *  - VAGA-002: if BOTH day_of_week / specific_date are explicitly provided,
     *    enforce mutual exclusion (mirrors UpdateSlotRequest convention).
     *  - VAGA-003: if either open_time or close_time changes, re-check the
     *    merged (existing ?? incoming) window is strictly ordered.
     *  - VAGA-004: if concurrency_limit or soft_threshold changes, re-check
     *    the merged effective values.
     *  - VAGA-005: if day_of_week, specific_date, open_time, or close_time
     *    changes, re-check the merged window against sibling blocks,
     *    excluding this block itself.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return; // stop early — field rules already failed
            }

            /** @var AgendaBlock|null $block */
            $block = $this->route('block');

            if (! $block) {
                return;
            }

            $this->validateXor($v);
            $this->validateTimeRange($v, $block);
            $this->validateCapAndThreshold($v, $block);

            if ($v->errors()->isEmpty()) {
                $this->validateNoOverlap($v, $block);
            }
        });
    }

    private function validateXor(Validator $v): void
    {
        $hasDow  = $this->has('day_of_week');
        $hasDate = $this->has('specific_date');

        if (! ($hasDow && $hasDate)) {
            return;
        }

        $dow  = $this->input('day_of_week');
        $date = $this->input('specific_date');

        $dowSet  = ! is_null($dow);
        $dateSet = ! is_null($date) && $date !== '';

        if ($dowSet && $dateSet) {
            $v->errors()->add('day_of_week', 'Only one of day_of_week or specific_date may be set, not both.');
        }

        if (! $dowSet && ! $dateSet) {
            $v->errors()->add('day_of_week', 'Either day_of_week or specific_date is required.');
        }
    }

    private function validateTimeRange(Validator $v, AgendaBlock $block): void
    {
        if (! $this->has('open_time') && ! $this->has('close_time')) {
            return;
        }

        $open  = substr($this->input('open_time', $block->open_time), 0, 5);
        $close = substr($this->input('close_time', $block->close_time), 0, 5);

        if ($open >= $close) {
            $v->errors()->add('close_time', 'close_time must be strictly after open_time.');
        }
    }

    private function validateCapAndThreshold(Validator $v, AgendaBlock $block): void
    {
        if (! $this->has('concurrency_limit') && ! $this->has('soft_threshold')) {
            return;
        }

        $limit     = $this->input('concurrency_limit', $block->concurrency_limit);
        $threshold = $this->input('soft_threshold', $block->soft_threshold);

        if ($threshold === null) {
            return;
        }

        $effectiveLimit = $limit ?? (int) config('booking.venue.default_concurrency_limit');

        if ($threshold >= $effectiveLimit) {
            $v->errors()->add('soft_threshold', 'soft_threshold must be strictly less than concurrency_limit.');
        }
    }

    /**
     * VAGA-005 — same overlap formula as StoreAgendaBlockRequest, merged
     * with the block's current values, excluding this block from the
     * sibling lookup.
     */
    private function validateNoOverlap(Validator $v, AgendaBlock $block): void
    {
        if (! $this->has('day_of_week') && ! $this->has('specific_date')
            && ! $this->has('open_time') && ! $this->has('close_time')
        ) {
            return;
        }

        $dow   = $this->input('day_of_week', $block->day_of_week);
        $date  = $this->input('specific_date', $block->specific_date?->format('Y-m-d'));
        $open  = substr($this->input('open_time', $block->open_time), 0, 5);
        $close = substr($this->input('close_time', $block->close_time), 0, 5);

        $query = AgendaBlock::query()->where('id', '!=', $block->id);

        if (! is_null($dow)) {
            $query->where('day_of_week', $dow);
        } else {
            $query->whereDate('specific_date', $date);
        }

        $overlaps = $query->get()->contains(function (AgendaBlock $existing) use ($open, $close) {
            $existingOpen  = substr($existing->open_time, 0, 5);
            $existingClose = substr($existing->close_time, 0, 5);

            return $existingOpen < $close && $existingClose > $open;
        });

        if ($overlaps) {
            $v->errors()->add('open_time', 'This block overlaps an existing agenda block for the same day.');
        }
    }
}
