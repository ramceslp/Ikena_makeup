<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'             => ['required', 'string', 'max:255'],
            'description'       => ['required', 'string'],
            'price'             => ['required', 'numeric', 'min:0'],
            'duration_hours'    => ['required', 'integer', 'min:0', 'max:255'],
            'availability_type' => ['sometimes', 'nullable', Rule::in(['immediate', 'by_appointment'])],
            'category_id'       => ['nullable', 'integer', 'exists:categories,id'],
            'is_published'      => ['sometimes', 'boolean'],
        ];
    }
}
