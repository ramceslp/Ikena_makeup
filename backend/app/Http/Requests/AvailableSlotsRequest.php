<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the optional `date` query parameter for
 * GET /api/services/{serviceId}/available-slots.
 *
 * When present, `date` must:
 *  - be a well-formed 'Y-m-d' calendar date;
 *  - fall within [today, today + look_ahead_days) in the booking.timezone
 *    timezone — the exact window VenueAvailabilityResolver resolves against.
 *
 * When absent, the controller falls back to the full-window resolve() call
 * (backward-compatible behavior — see BookingController::availableSlots()).
 */
class AvailableSlotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public endpoint — no auth required
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $v) {
            if ($v->errors()->isNotEmpty()) {
                return; // stop early — field rules already failed
            }

            $date = $this->input('date');

            if ($date === null) {
                return; // no date param — full-window behavior, nothing to range-check
            }

            $tz = config('booking.timezone');
            $today = Carbon::now($tz)->startOfDay();
            $horizon = $today->copy()->addDays((int) config('booking.venue.look_ahead_days'));
            $reqDate = Carbon::createFromFormat('Y-m-d', $date, $tz)->startOfDay();

            if ($reqDate->lt($today) || $reqDate->gte($horizon)) {
                $v->errors()->add('date', 'The requested date is outside the booking window.');
            }
        });
    }
}
