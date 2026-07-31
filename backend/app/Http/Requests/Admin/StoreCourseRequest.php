<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Role gate lives in the 'admin' middleware on the route group.
        return true;
    }

    public function rules(): array
    {
        return [
            'title'              => ['required', 'string', 'max:255'],
            'description'        => ['required', 'string'],
            'price'              => ['sometimes', 'numeric', 'min:0'],
            'thumbnail'          => ['nullable', 'url'],
            'category_id'        => ['nullable', 'integer', 'exists:categories,id'],
            'offers_certificate' => ['sometimes', 'boolean'],

            // Unlike the instructor endpoint — which always assigns the course
            // to the authenticated author — an admin creates courses on behalf
            // of someone, so the owner is an explicit, validated input.
            'instructor_id'      => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->whereIn('role', ['instructor', 'admin'])
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'instructor_id.exists' => 'The selected user is not an instructor.',
        ];
    }
}
