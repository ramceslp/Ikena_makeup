<?php

namespace App\Http\Requests;

use App\Models\Service;
use App\Models\ServiceSlot;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'service_id'     => ['required', 'integer', 'exists:services,id'],
            'scheduled_date' => ['required', 'date', 'after_or_equal:today'],
            'scheduled_time' => ['required', 'date_format:H:i'],
            'whatsapp'       => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * Additional business-rule validation after the standard rules pass.
     *
     * Checks:
     *  1. The service is published.
     *  2. The service has availability_type = by_appointment.
     *  3. The requested date/time maps to an active (non-blocked) slot definition.
     *     NOTE: We do NOT exclude already-booked slots here — the DB unique index
     *     on slot_key handles collision detection (UniqueConstraintViolationException → 409).
     *     This means a second booking attempt will pass validation and hit the DB,
     *     which returns the 409 conflict response from the controller.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return; // stop early — field rules already failed
            }

            $serviceId = $this->input('service_id');
            $service   = Service::find($serviceId);

            if (! $service) {
                return; // exists rule already catches this
            }

            if (! $service->is_published) {
                $v->errors()->add('service_id', 'The service is not published.');

                return;
            }

            if ($service->availability_type !== 'by_appointment') {
                $v->errors()->add('service_id', 'This service does not accept appointments.');

                return;
            }

            // Verify the requested date/time matches an active (non-blocked) slot definition.
            // We check against the slot DEFINITIONS only (recurring day or specific date),
            // not against current availability — slot collision is handled by the DB unique index.
            $requestedDate = $this->input('scheduled_date');
            $requestedTime = substr($this->input('scheduled_time'), 0, 5);
            $requestedDay  = Carbon::parse($requestedDate)->dayOfWeek;

            $tz      = config('booking.timezone');
            $today   = Carbon::now($tz)->startOfDay();
            $horizon = $today->copy()->addDays(60);
            $reqDate = Carbon::parse($requestedDate, $tz)->startOfDay();

            // Within window?
            if ($reqDate->lt($today) || $reqDate->gte($horizon)) {
                $v->errors()->add('scheduled_date', 'The requested date is outside the booking window.');

                return;
            }

            // Does an active slot definition cover this date/time?
            $slotExists = ServiceSlot::where('service_id', $service->id)
                ->where('is_blocked', false)
                ->where('start_time', 'LIKE', $requestedTime . '%')
                ->where(function ($q) use ($requestedDay, $requestedDate) {
                    $q->where('day_of_week', $requestedDay)
                      ->orWhere('specific_date', $requestedDate);
                })
                ->exists();

            if (! $slotExists) {
                $v->errors()->add('scheduled_date', 'The requested slot is not available for this service.');
            }
        });
    }
}
