<?php

namespace App\Http\Requests\Admin;

use App\Models\AgendaBlock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAgendaBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // admin middleware already guards the route
    }

    public function rules(): array
    {
        return [
            'day_of_week'       => ['nullable', 'integer', 'min:0', 'max:6'],
            'specific_date'     => ['nullable', 'date'],
            'open_time'         => ['required', 'date_format:H:i'],
            'close_time'        => ['required', 'date_format:H:i'],
            'concurrency_limit' => ['nullable', 'integer', 'min:1'],
            'soft_threshold'    => ['nullable', 'integer', 'min:1'],
            'is_blocked'        => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Business-rule validation beyond field-level rules:
     *
     *  - VAGA-002: exactly one of day_of_week / specific_date must be set (XOR).
     *  - VAGA-003: open_time must be strictly before close_time (same calendar day).
     *  - VAGA-004: soft_threshold, when set, must be strictly less than concurrency_limit.
     *  - VAGA-005: the new block's [open_time, close_time) window must not overlap
     *    any existing active block sharing the same day_of_week / specific_date.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return; // stop early — field rules already failed
            }

            $this->validateXor($v);
            $this->validateTimeRange($v);
            $this->validateCapAndThreshold($v);

            if ($v->errors()->isEmpty()) {
                $this->validateNoOverlap($v);
            }
        });
    }

    private function validateXor(Validator $v): void
    {
        $dow  = $this->input('day_of_week');
        $date = $this->input('specific_date');

        $dowSet  = ! is_null($dow);
        $dateSet = ! is_null($date) && $date !== '';

        if (! $dowSet && ! $dateSet) {
            $v->errors()->add('day_of_week', 'Either day_of_week or specific_date is required.');
        }

        if ($dowSet && $dateSet) {
            $v->errors()->add('day_of_week', 'Only one of day_of_week or specific_date may be set, not both.');
        }
    }

    private function validateTimeRange(Validator $v): void
    {
        $open  = $this->input('open_time');
        $close = $this->input('close_time');

        if ($open === null || $close === null) {
            return; // required rule already caught this
        }

        if (substr($open, 0, 5) >= substr($close, 0, 5)) {
            $v->errors()->add('close_time', 'close_time must be strictly after open_time.');
        }
    }

    private function validateCapAndThreshold(Validator $v): void
    {
        $limit     = $this->input('concurrency_limit');
        $threshold = $this->input('soft_threshold');

        if ($threshold === null) {
            return;
        }

        $effectiveLimit = $limit ?? (int) config('booking.venue.default_concurrency_limit');

        if ($threshold >= $effectiveLimit) {
            $v->errors()->add('soft_threshold', 'soft_threshold must be strictly less than concurrency_limit.');
        }
    }

    /**
     * VAGA-005 — overlap: A.open < B.close AND A.close > B.open.
     * Adjacent blocks (touching at exactly one boundary point) are allowed.
     */
    private function validateNoOverlap(Validator $v): void
    {
        $dow   = $this->input('day_of_week');
        $date  = $this->input('specific_date');
        $open  = substr($this->input('open_time'), 0, 5);
        $close = substr($this->input('close_time'), 0, 5);

        $query = AgendaBlock::query();

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
