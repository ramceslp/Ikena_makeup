<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // admin middleware already guards the route
    }

    public function rules(): array
    {
        return [
            'service_id'    => ['sometimes', 'integer', 'exists:services,id'],
            'day_of_week'   => ['nullable', 'integer', 'min:0', 'max:6'],
            'specific_date' => ['nullable', 'date'],
            'start_time'    => ['required', 'date_format:H:i'],
            'capacity'      => ['sometimes', 'integer', 'min:1'],
            'is_blocked'    => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Enforce mutual exclusion: exactly one of day_of_week / specific_date must be set.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
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
        });
    }
}
