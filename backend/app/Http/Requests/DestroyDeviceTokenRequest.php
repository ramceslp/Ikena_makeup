<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DestroyDeviceTokenRequest — validates DELETE /api/device-tokens
 * (mobile-capacitor-setup PR3, design Decision 2).
 */
class DestroyDeviceTokenRequest extends FormRequest
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
