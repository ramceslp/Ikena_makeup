<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RedeemCheckoutHandoffRequest — validates POST /api/checkout/handoff/redeem.
 *
 * Public route (no auth:sanctum) — the redeeming browser session is
 * anonymous. `token` is the opaque plaintext handed to the app in the
 * handoff URL fragment.
 */
class RedeemCheckoutHandoffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
        ];
    }
}
