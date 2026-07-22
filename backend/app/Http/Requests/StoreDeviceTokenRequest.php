<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreDeviceTokenRequest — validates POST /api/device-tokens
 * (mobile-capacitor-setup PR3, design Decision 2).
 */
class StoreDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        // auth:sanctum middleware ensures the user is authenticated;
        // any authenticated user may register their own device token.
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'platform' => ['required', Rule::in(['ios', 'android'])],
        ];
    }
}
