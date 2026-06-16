<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'             => ['sometimes', 'string', 'max:255'],
            'description'       => ['sometimes', 'string'],
            'price'             => ['sometimes', 'numeric', 'min:0'],
            'duration_hours'    => ['sometimes', 'integer', 'min:0', 'max:255'],
            'availability_type' => ['sometimes', Rule::in(['immediate', 'by_appointment'])],
            'category_id'       => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'is_published'      => ['sometimes', 'boolean'],
        ];
    }
}
