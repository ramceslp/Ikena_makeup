<?php

namespace App\Http\Requests\Instructor;

use App\Http\Requests\Concerns\CourseDeliveryRules;
use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseRequest extends FormRequest
{
    use CourseDeliveryRules;

    public function authorize(): bool
    {
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
        ], $this->deliveryRules($this->targetCourse()));
    }

    public function messages(): array
    {
        return $this->deliveryMessages();
    }

    /**
     * The instructor routes bind by raw {slug}, not by model, so the course has
     * to be looked up here for the delivery rules to know the current mode.
     */
    private function targetCourse(): ?Course
    {
        $slug = $this->route('slug');

        return is_string($slug)
            ? Course::where('slug', $slug)->first()
            : null;
    }
}
