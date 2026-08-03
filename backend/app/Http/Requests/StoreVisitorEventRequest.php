<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * StoreVisitorEventRequest — validates POST /api/analytics/events
 * (visitor-analytics PR1b, design D4: sdd/visitor-analytics/design).
 *
 * This endpoint is PUBLIC and unauthenticated by design (D4) — every
 * rule here exists to make sure hostile or malformed input can never
 * reach the `visitor_events` insert and throw under MySQL strict mode.
 * `path` (string(255) NOT NULL) and `route_name` (string(64) nullable)
 * are the two client-supplied columns that could otherwise overflow;
 * both are REJECTED (422) rather than silently truncated when they
 * exceed the column width.
 *
 * Truncation was considered and deliberately rejected: it would let two
 * genuinely different long paths collapse into the same stored value
 * (corrupting the "most-viewed pages" ranking this data feeds), with no
 * visible sign to the caller that anything was lost. A 422 is loud,
 * costs nothing (the client already discards the response body — see
 * design D5's `.catch(() => {})`), and matches this codebase's existing
 * convention of rejecting rather than coercing invalid FormRequest input.
 *
 * Every other value this request accepts and the controller later
 * writes (`event_type`, `entity_type`) is constrained by an enumeration
 * far shorter than its column width (20 chars each), so it is bounded
 * by construction rather than by an explicit `max:` rule.
 * `referrer_group` is never client input at all — it is always one of
 * four short, hard-coded literals produced by ReferrerGrouper — so it
 * needs no validation here.
 *
 * `path` is normalized (query string / fragment stripped) in
 * `prepareForValidation()`, BEFORE the `max:255` rule runs — not after,
 * in the controller. Validating the raw value would reject a valid,
 * short, storable path whenever its raw form (e.g. a filtered catalogue
 * URL with several query parameters) happened to exceed 255 characters
 * before stripping, silently losing exactly the pageviews for the
 * longest, most-filtered URLs — plausibly the most interesting rows in
 * a "most-viewed pages" ranking, and invisible to everyone since the
 * client discards the 422 response body (design D5). Normalizing here
 * also keeps the stripping logic and its length rule in one place so
 * they cannot drift apart again.
 */
class StoreVisitorEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public endpoint (auth.optional middleware) — every visitor,
        // logged in or not, may submit an analytics event.
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('path'))) {
            $this->merge([
                'path' => Str::of($this->input('path'))->before('?')->before('#')->value(),
            ]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'event_type' => ['required', Rule::in(['page_view', 'add_to_cart'])],
            'path' => ['required', 'string', 'max:255'],
            'route_name' => ['nullable', 'string', 'max:64'],
            'entity_type' => ['nullable', Rule::in(['product', 'service', 'course', 'post'])],
            'entity_slug' => ['nullable', 'string', 'max:255', 'required_with:entity_type'],
            'referrer' => ['nullable', 'string', 'max:2048'],
        ];

        if ($this->input('event_type') === 'add_to_cart') {
            // The web SPA's cart is products-only by construction
            // (frontend/src/stores/cart.js) — an add_to_cart event for
            // any other entity type cannot originate from real use of
            // the UI and MUST be rejected, not silently accepted or
            // ignored (locked decision; spec "Add-to-cart event
            // recording is products-only").
            $rules['entity_type'] = ['required', 'in:product'];
            $rules['entity_slug'] = ['required', 'string', 'max:255'];
        }

        return $rules;
    }
}
