<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a custom push notification composed by an admin
 * (push-notifications Slice 3).
 */
class StorePushNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // admin middleware already guards the route
    }

    public function rules(): array
    {
        return [
            // Android collapses a long title to a single ellipsised line in the
            // tray, so a hard cap here is friendlier than silent truncation on
            // the device.
            'title' => ['required', 'string', 'max:100'],
            'body'  => ['required', 'string', 'max:500'],

            // Optional deep link. Constrained to an INTERNAL absolute path:
            // this value is delivered to the mobile app as FCM `data.route` and
            // handed to vue-router. `regex:/^\/(?!\/)/` requires a single
            // leading slash and rejects a protocol-relative '//evil.com', which
            // a browser-like router would treat as an external origin. The app
            // re-validates on receipt (Slice 5) — this is defence in depth, not
            // the only check.
            'route' => ['nullable', 'string', 'max:255', 'regex:/^\/(?!\/)/'],
        ];
    }

    public function messages(): array
    {
        return [
            'route.regex' => 'The route must be an internal path starting with a single "/".',
        ];
    }
}
