<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
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
 */
class StoreVisitorEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public endpoint (auth.optional middleware) — every visitor,
        // logged in or not, may submit an analytics event.
        return true;
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
