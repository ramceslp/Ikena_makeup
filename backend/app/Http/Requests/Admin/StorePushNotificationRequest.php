<?php

namespace App\Http\Requests\Admin;

use App\Rules\AppDestination;
use App\Services\Push\AppDestinations;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates a custom push notification composed by an admin
 * (push-notifications Slice 3).
 *
 * The deep link is expressed as a `destination` key plus an optional `slug`,
 * both taken from the catalogue in config/push_destinations.php, and collapsed
 * here into the single `route` string the rest of the pipeline already speaks
 * (PushDispatcher::custom → the log row's `data.route` → FCM). One stored
 * representation, two ways to express it — nothing downstream changed.
 *
 * `route` is still accepted directly, for a caller that already holds a path
 * (and for the history rows written before the picker existed), but it is now
 * validated against the same catalogue rather than merely being checked for a
 * leading slash. See App\Rules\AppDestination for why a leading slash was never
 * enough.
 */
class StorePushNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // admin middleware already guards the route
    }

    /**
     * Collapses destination + slug into `route` before any rule runs, so the
     * catalogue check below is the single gate every deep link passes through
     * regardless of which shape the client sent.
     *
     * A `destination` key always WINS over a client-supplied `route`: accepting
     * both and preferring the raw path would reintroduce the free-text hole the
     * picker exists to close.
     */
    protected function prepareForValidation(): void
    {
        $key = $this->input('destination');

        if (! is_string($key) || $key === '') {
            return;
        }

        $route = app(AppDestinations::class)->resolve($key, $this->input('slug'));

        // A null here means an unknown key or a missing required slug. Both are
        // reported by the rules below against the field the admin actually
        // filled in, so `route` is left untouched rather than merged as null —
        // which would read as "no deep link wanted" and send a linkless
        // notification instead of failing.
        if ($route !== null) {
            $this->merge(['route' => $route]);
        }
    }

    public function rules(): array
    {
        $destinations = app(AppDestinations::class);

        return [
            // Android collapses a long title to a single ellipsised line in the
            // tray, so a hard cap here is friendlier than silent truncation on
            // the device.
            'title' => ['required', 'string', 'max:100'],
            'body'  => ['required', 'string', 'max:500'],

            'destination' => [
                'nullable',
                'string',
                'in:'.$destinations->all()->pluck('key')->implode(','),
            ],

            'slug' => ['nullable', 'string', 'max:200'],

            // Optional deep link — a promotional message does not have to link
            // anywhere. When present it must open a real screen in the app.
            'route' => ['nullable', 'string', 'max:255', new AppDestination($destinations)],
        ];
    }

    /**
     * Which destinations need a slug is data, not schema, so `required_if`
     * cannot express it — the answer lives in config/push_destinations.php.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $key = $this->input('destination');

            if (! is_string($key) || $key === '') {
                return;
            }

            $destinations = app(AppDestinations::class);
            $destination  = $destinations->find($key);

            // An unknown key is already reported by the `in:` rule above.
            if ($destination === null || ! $destinations->requiresSlug($destination)) {
                return;
            }

            if (trim((string) $this->input('slug')) === '') {
                $validator->errors()->add(
                    'slug',
                    'El destino "'.$destination['label'].'" necesita que indiques cuál.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'destination.in' => 'Ese destino no existe en la app.',
        ];
    }
}
