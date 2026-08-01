<?php

namespace App\Http\Requests\Instructor;

use Illuminate\Foundation\Http\FormRequest;

class RecordAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership is checked in the controller against the course, which is
        // reachable only through the lesson — same shape as the other
        // instructor endpoints.
        return true;
    }

    public function rules(): array
    {
        return [
            // Present-but-empty is meaningful: it clears the whole roster.
            'user_ids'   => ['present', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
