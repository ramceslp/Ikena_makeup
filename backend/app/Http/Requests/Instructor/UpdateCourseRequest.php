<?php

namespace App\Http\Requests\Instructor;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'price'       => ['sometimes', 'numeric', 'min:0'],
            'thumbnail'   => ['nullable', 'url'],
        ];
    }
}
