<?php

namespace App\Http\Requests\Instructor;

use App\Http\Requests\Concerns\LessonDeliveryRules;
use App\Models\Course;
use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;

class StoreLessonRequest extends FormRequest
{
    use LessonDeliveryRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration'    => ['nullable', 'integer', 'min:0'],
            'is_free'     => ['sometimes', 'boolean'],
            'is_practice' => ['sometimes', 'boolean'],
        ], $this->lessonDeliveryRules($this->targetCourse()));
    }

    public function messages(): array
    {
        return $this->lessonDeliveryMessages();
    }

    private function targetCourse(): ?Course
    {
        $section = $this->route('section');

        return $section instanceof Section ? $section->course : null;
    }
}
