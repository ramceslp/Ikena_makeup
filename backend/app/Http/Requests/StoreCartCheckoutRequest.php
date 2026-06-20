<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreCartCheckoutRequest — validates the cart checkout payload.
 *
 * Accepts an `items` array where each entry must have:
 *   - product_id: integer, must exist in products table AND have is_published = true
 *     (enforced here at the validation boundary; the controller also guards as defense in depth)
 *   - quantity: integer, minimum 1, maximum 100 (prevents integer overflow / negative totals)
 *
 * Mixed carts (courses + products) are rejected at this level because
 * the `product_id` must exist as a published product. The request
 * rejects non-product items implicitly (they won't pass the Rule::exists check).
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
                // Enforces existence AND published status at the validation boundary.
                // The controller also checks is_published as defense in depth.
                Rule::exists('products', 'id')->where('is_published', true),
            ],
            'items.*.quantity'   => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
