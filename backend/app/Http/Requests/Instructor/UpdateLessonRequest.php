<?php

namespace App\Http\Requests\Instructor;

use App\Rules\VideoUrl;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'video_url'   => ['nullable', new VideoUrl()],
            'duration'    => ['nullable', 'integer', 'min:0'],
            'is_free'     => ['sometimes', 'boolean'],
            'is_practice' => ['sometimes', 'boolean'],
        ];
    }
}
