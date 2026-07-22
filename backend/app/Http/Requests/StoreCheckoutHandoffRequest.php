<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreCheckoutHandoffRequest — validates POST /api/checkout/handoff.
 *
 * Accepts one of two shapes, discriminated by `type`:
 *   - product_cart: same shape/rules as StoreCartCheckoutRequest (`items`).
 *   - appointment:  same shape/rules as StoreBookingRequest (`service_id`,
 *     `scheduled_date`, `scheduled_time`, `whatsapp`).
 *
 * Rules are reused verbatim from the sibling FormRequest classes so the
 * two validation boundaries (direct checkout vs. handoff snapshot) never
 * drift apart. Business-rule checks specific to booking (agenda-block
 * coverage, BOOK-004 duration overflow — see StoreBookingRequest::withValidator)
 * are intentionally NOT re-run here: this endpoint only writes a snapshot,
 * it does not reserve stock or create an Order. CreateBookingAction re-runs
 * the authoritative capacity/coverage checks at redeem time, when the
 * snapshot may already be stale.
 */
class StoreCheckoutHandoffRequest extends FormRequest
{
    public function authorize(): bool
    {
        // auth:sanctum middleware ensures the user is authenticated;
        // all authenticated users may request a checkout handoff.
        return true;
    }

    public function rules(): array
    {
        $typeRule = ['type' => ['required', 'in:product_cart,appointment']];

        return $this->input('type') === 'appointment'
            ? $typeRule + (new StoreBookingRequest)->rules()
            : $typeRule + (new StoreCartCheckoutRequest)->rules();
    }
}
