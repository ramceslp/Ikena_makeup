<?php

namespace App\Http\Requests;

use App\Models\AgendaBlock;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FIX 8 — Timezone contract:
 *
 * `scheduled_date` and `scheduled_time` MUST be expressed as America/Guayaquil
 * local wall-clock time (UTC-5, no DST). This matches the timezone used when
 * defining AgendaBlocks and when computing venue availability windows in
 * VenueAvailabilityResolver. Sending UTC or any other timezone offset will
 * result in mismatched agenda lookups and a 422 "slot not available" response.
 *
 * The server performs NO timezone conversion on these fields — it stores exactly
 * what is sent and compares it directly against agenda block definitions.
 */
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
            // Deliberately NO 'after_or_equal:today' here. That rule resolves
            // "today" through app.timezone (UTC), which contradicts this
            // class's own timezone contract documented above: the field is
            // America/Guayaquil (UTC-5) wall-clock time. Between 19:00
            // Guayaquil and midnight UTC the two disagree on the date, so the
            // rule rejected same-day bookings as being in the past for five
            // hours of every evening — prime booking time. Field rules also run
            // BEFORE withValidator(), so its 422 pre-empted the correct check.
            // The past-date boundary is owned by withValidator() below, which
            // evaluates it in booking.timezone (same logic as
            // AvailableSlotsRequest) and is the single source of truth.
            'scheduled_date' => ['required', 'date'],
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
     *  3. The requested date/time is covered by an active (non-blocked) venue
     *     AgendaBlock (recurring day_of_week or specific_date).
     *  4. BOOK-004 — the requested time + service.duration_hours does not
     *     exceed the matching AgendaBlock's close_time.
     *
     *     NOTE: We do NOT reject on current capacity here — cap enforcement
     *     (overlap recount against concurrency_limit) happens inside
     *     BookingController::store()'s DB transaction (BOOK-002/BOOK-005),
     *     which returns 409 cap_exceeded when the venue is full.
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

            $requestedDate = $this->input('scheduled_date');
            $requestedTime = substr($this->input('scheduled_time'), 0, 5);
            $requestedDay  = Carbon::parse($requestedDate)->dayOfWeek;

            $tz      = config('booking.timezone');
            $today   = Carbon::now($tz)->startOfDay();
            $horizon = $today->copy()->addDays((int) config('booking.venue.look_ahead_days'));
            $reqDate = Carbon::parse($requestedDate, $tz)->startOfDay();

            // Within window?
            if ($reqDate->lt($today) || $reqDate->gte($horizon)) {
                $v->errors()->add('scheduled_date', 'The requested date is outside the booking window.');

                return;
            }

            // Find the active AgendaBlock whose [open_time, close_time) window
            // covers the requested time, matching either the recurring
            // day_of_week or the specific_date.
            $block = AgendaBlock::where('is_blocked', false)
                ->where(function ($q) use ($requestedDay, $requestedDate) {
                    $q->where('day_of_week', $requestedDay)
                      ->orWhereDate('specific_date', $requestedDate);
                })
                ->get()
                ->first(function (AgendaBlock $candidate) use ($requestedTime) {
                    $open  = substr($candidate->open_time, 0, 5);
                    $close = substr($candidate->close_time, 0, 5);

                    return $requestedTime >= $open && $requestedTime < $close;
                });

            if (! $block) {
                $v->errors()->add('scheduled_date', 'The requested slot is not available for this service.');

                return;
            }

            // BOOK-004 — reject when scheduled_time + duration_hours overflows close_time.
            // Minute-based integer arithmetic avoids any Carbon midnight-wrap ambiguity.
            [$startHour, $startMinute] = array_map('intval', explode(':', $requestedTime));
            $startMinutes = $startHour * 60 + $startMinute;
            $endMinutes   = $startMinutes + ((int) $service->duration_hours * 60);

            [$closeHour, $closeMinute] = array_map('intval', explode(':', substr($block->close_time, 0, 5)));
            $closeMinutes = $closeHour * 60 + $closeMinute;

            if ($endMinutes > $closeMinutes) {
                $v->errors()->add('scheduled_time', 'The requested duration exceeds the venue availability window.');
            }
        });
    }
}
