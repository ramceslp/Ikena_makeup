<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateBookingAction;
use App\Exceptions\BookingCapacityExceededException;
use App\Exceptions\BookingSlotUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AvailableSlotsRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\SlotResource;
use App\Models\Service;
use App\Services\Booking\VenueAvailabilityResolver;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    public function __construct(
        private readonly VenueAvailabilityResolver $resolver,
        private readonly CreateBookingAction $createBooking,
    ) {}

    /**
     * GET /api/services/{serviceId}/available-slots
     * GET /api/services/{serviceId}/available-slots?date=YYYY-MM-DD
     *
     * Returns available slot occurrences for a published by_appointment service.
     * This endpoint is PUBLIC — no authentication required.
     *
     * Without `date`: returns the full look-ahead window as a flat array
     * (unchanged, backward-compatible behavior).
     * With `date`: returns only that day's occurrences (calendar picker's
     * time-selection step). AvailableSlotsRequest validates `date` is a
     * well-formed 'Y-m-d' string within the bookable window — malformed or
     * out-of-range values short-circuit with a 422 before this method runs.
     */
    public function availableSlots(AvailableSlotsRequest $request, int $serviceId): JsonResponse
    {
        $service = Service::where('id', $serviceId)
            ->where('is_published', true)
            ->firstOrFail();

        if ($service->availability_type !== 'by_appointment') {
            return response()->json(['data' => []]);
        }

        $date = $request->validated('date');

        $occurrences = $date !== null
            ? $this->resolver->resolveDay($service, $date)
            : $this->resolver->resolve($service);

        // Wrap each occurrence array in SlotResource (array path in toArray)
        $data = collect($occurrences)
            ->map(fn ($occ) => new SlotResource($occ))
            ->values();

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/services/{serviceId}/available-days
     *
     * Returns one lightweight { date, available_count } row per calendar day
     * in the look-ahead window that has an active AgendaBlock — the calendar
     * picker's day-selection step. Days the venue is closed on (no matching
     * AgendaBlock) are omitted; fully booked days are included with
     * available_count: 0. See VenueAvailabilityResolver::resolveDaySummary()
     * for the full contract. This endpoint is PUBLIC — no authentication
     * required.
     */
    public function availableDays(int $serviceId): JsonResponse
    {
        $service = Service::where('id', $serviceId)
            ->where('is_published', true)
            ->firstOrFail();

        if ($service->availability_type !== 'by_appointment') {
            return response()->json(['data' => []]);
        }

        $summary = $this->resolver->resolveDaySummary($service);

        return response()->json(['data' => $summary]);
    }

    /**
     * POST /api/bookings
     *
     * Create a booking (appointment + deposit order) for the authenticated
     * user. Delegates the capacity recount + atomic Appointment/Order
     * creation to CreateBookingAction (see App\Actions\CreateBookingAction),
     * which is shared with the checkout-handoff redeem endpoint so
     * concurrency-cap rules stay single-sourced (BOOK-001/002/003/005). This
     * controller only translates the action's result / thrown exceptions
     * into HTTP responses.
     *
     * NOTE: The slot_key UNIQUE constraint has been dropped (DM-002) — multiple
     * appointments MAY share the same service/date/time up to concurrency_limit.
     * slot_key is retained only as an audit field.
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $serviceId = $request->validated()['service_id'];
        $user = $request->user();

        $scheduledDate = $request->input('scheduled_date');
        $scheduledTime = $request->input('scheduled_time');
        $whatsapp = $request->input('whatsapp');

        try {
            $result = ($this->createBooking)($user, $serviceId, $scheduledDate, $scheduledTime, $whatsapp);
        } catch (BookingSlotUnavailableException $e) {
            // Defensive — should be unreachable since StoreBookingRequest validated this.
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        } catch (BookingCapacityExceededException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'cap_exceeded',
            ], 409);
        }

        return response()->json([
            'data' => [
                'order_id' => $result['order']->id,
                'provider' => $result['session']->provider,
                'config' => $result['session']->config,
                'is_near_capacity' => $result['is_near_capacity'],
                'warning_message' => $result['is_near_capacity']
                    ? config('booking.venue.warning_message')
                    : null,
            ],
        ], 201);
    }
}
