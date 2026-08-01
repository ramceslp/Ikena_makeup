<?php

namespace App\Http\Requests\Instructor;

use App\Http\Requests\Concerns\CourseDeliveryRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreCourseRequest extends FormRequest
{
    use CourseDeliveryRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'title'              => ['required', 'string', 'max:255'],
            'description'        => ['required', 'string'],
            'price'              => ['sometimes', 'numeric', 'min:0'],
            'thumbnail'          => ['nullable', 'url'],
            'category_id'        => ['nullable', 'integer', 'exists:categories,id'],
            'offers_certificate' => ['sometimes', 'boolean'],
        ], $this->deliveryRules());
    }

    public function messages(): array
    {
        return $this->deliveryMessages();
    }
}
