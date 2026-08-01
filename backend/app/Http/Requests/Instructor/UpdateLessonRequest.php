<?php

namespace App\Http\Requests\Instructor;

use App\Http\Requests\Concerns\LessonDeliveryRules;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLessonRequest extends FormRequest
{
    use LessonDeliveryRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'title'       => ['sometimes', 'string', 'max:255'],
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
        $lesson = $this->route('lesson');

        return $lesson instanceof Lesson ? $lesson->section?->course : null;
    }
}
