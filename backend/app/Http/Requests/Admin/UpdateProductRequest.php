<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var \App\Models\Product|null $product */
        $product = $this->route('product');

        return [
            'title'        => ['sometimes', 'string', 'max:255'],
            'description'  => ['sometimes', 'nullable', 'string'],
            'price'        => ['sometimes', 'numeric', 'min:0'],
            'stock_qty'    => ['sometimes', 'integer', 'min:0'],
            'category_id'  => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'is_published' => ['sometimes', 'boolean'],
            'slug'         => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'slug')->ignore($product?->id),
            ],
        ];
    }
}
