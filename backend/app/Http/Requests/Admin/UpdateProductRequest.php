<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'        => ['sometimes', 'string', 'max:255'],
            'description'  => ['sometimes', 'nullable', 'string'],
            'price'        => ['sometimes', 'numeric', 'min:0'],
            'stock_qty'    => ['sometimes', 'integer', 'min:0'],
            'category_id'  => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
