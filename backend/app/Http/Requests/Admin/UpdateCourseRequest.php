<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\CourseDeliveryRules;
use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseRequest extends FormRequest
{
    use CourseDeliveryRules;

    public function authorize(): bool
    {
        // Role gate lives in the 'admin' middleware on the route group.
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'title'              => ['sometimes', 'string', 'max:255'],
            'description'        => ['sometimes', 'string'],
            'price'              => ['sometimes', 'numeric', 'min:0'],
            'thumbnail'          => ['nullable', 'url:http,https', 'max:2048'],
            'category_id'        => ['nullable', 'integer', 'exists:categories,id'],
            'offers_certificate' => ['sometimes', 'boolean'],

            // Reassigning a course to a different instructor is an
            // admin-only capability — the instructor endpoint has no
            // equivalent, since an instructor may not hand their course away.
            'instructor_id'      => [
                'sometimes',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->whereIn('role', ['instructor', 'admin'])
                ),
            ],
        ], $this->deliveryRules($this->route('course')));
    }

    public function messages(): array
    {
        return array_merge([
            'instructor_id.exists' => 'The selected user is not an instructor.',
        ], $this->deliveryMessages());
    }
}
