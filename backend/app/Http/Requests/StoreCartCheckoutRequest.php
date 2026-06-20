<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreCartCheckoutRequest — validates the cart checkout payload.
 *
 * Accepts an `items` array where each entry must have:
 *   - product_id: integer, must exist in products table AND be published
 *   - quantity: integer, minimum 1
 *
 * Mixed carts (courses + products) are rejected at this level because
 * the `product_id` must exist in the `products` table. The request
 * rejects non-product items implicitly (they won't pass exists:products).
 * Empty items arrays are also rejected by `min:1` array validation.
 */
class StoreCartCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        // auth:sanctum middleware ensures the user is authenticated;
        // all authenticated users may submit a cart checkout.
        return true;
    }

    public function rules(): array
    {
        return [
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                // Must exist in products table AND be published
                // (two-step: exists check + business rule in controller)
                'exists:products,id',
            ],
            'items.*.quantity'   => ['required', 'integer', 'min:1'],
        ];
    }
}
