<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // admin middleware already guards the route
    }

    public function rules(): array
    {
        return [
            'day_of_week'   => ['sometimes', 'nullable', 'integer', 'min:0', 'max:6'],
            'specific_date' => ['sometimes', 'nullable', 'date'],
            'start_time'    => ['sometimes', 'date_format:H:i'],
            'capacity'      => ['sometimes', 'integer', 'min:1'],
            'is_blocked'    => ['sometimes', 'boolean'],
        ];
    }

    /**
     * If BOTH fields are explicitly provided in the request, enforce mutual exclusion.
     * Partial updates that only send one field are allowed without re-checking the other.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $hasDow  = $this->has('day_of_week');
            $hasDate = $this->has('specific_date');

            if ($hasDow && $hasDate) {
                $dow  = $this->input('day_of_week');
                $date = $this->input('specific_date');

                $dowSet  = ! is_null($dow);
                $dateSet = ! is_null($date) && $date !== '';

                if ($dowSet && $dateSet) {
                    $v->errors()->add('day_of_week', 'Only one of day_of_week or specific_date may be set, not both.');
                }
            }
        });
    }
}
