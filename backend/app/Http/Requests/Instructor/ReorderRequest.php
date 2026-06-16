<?php

namespace App\Http\Requests\Instructor;

use Illuminate\Foundation\Http\FormRequest;

class ReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ordered_ids'   => ['required', 'array'],
            'ordered_ids.*' => ['integer'],
        ];
    }
}
