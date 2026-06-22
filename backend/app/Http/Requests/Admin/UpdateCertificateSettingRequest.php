<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCertificateSettingRequest extends FormRequest
{
    /** Route is already gated by the `admin` middleware. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_name'    => ['sometimes', 'string', 'max:255'],
            'title'            => ['sometimes', 'string', 'max:255'],
            'award_line'       => ['sometimes', 'string', 'max:500'],
            'achievement_line' => ['sometimes', 'string', 'max:500'],
            'signer_name'      => ['sometimes', 'string', 'max:255'],
            'signer_role'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'design_variant'   => ['sometimes', 'integer', 'between:1,5'],
            'logo'             => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
