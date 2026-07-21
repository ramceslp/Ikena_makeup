<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AvailableSlotsRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\SlotResource;
use App\Models\AgendaBlock;
use App\Models\Appointment;
use App\Models\Order;
use App\Models\Service;
use App\Services\Booking\DepositCalculator;
use App\Services\Booking\VenueAvailabilityResolver;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function __construct(
        private readonly VenueAvailabilityResolver $resolver,
        private readonly DepositCalculator $calculator,
        private readonly PaymentGatewayInterface $gateway,
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
     * Create a booking (appointment + deposit order) for the authenticated user.
     *
     * Flow (all inside a DB transaction, BOOK-001/002/003/005):
     *  1. Resolve the matching AgendaBlock (already validated to exist by
     *     StoreBookingRequest) to get the effective concurrency_limit/soft_threshold.
     *  2. Recount venue-wide overlap for [scheduled_time, scheduled_end_time).
     *     On MySQL, the recount query is executed with lockForUpdate() to
     *     serialize concurrent writers; SQLite (tests / current prod) relies on
     *     the transaction's optimistic recount, matching the documented tradeoff
     *     in the design (see VenueAvailabilityResolver).
     *  3. If overlap_count >= effective_limit → return 409 cap_exceeded (BOOK-002/BOOK-005).
     *  4. Otherwise insert the Appointment with scheduled_end_time (DM-001),
     *     create the deposit Order, and kick off gateway checkout.
     *  5. Response includes is_near_capacity/warning_message when the soft
     *     threshold has been reached (BOOK-003).
     *
     * NOTE: The slot_key UNIQUE constraint has been dropped (DM-002) — multiple
     * appointments MAY share the same service/date/time up to concurrency_limit.
     * slot_key is retained only as an audit field.
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $service = Service::findOrFail($request->validated()['service_id']);
        $user = $request->user();

        $scheduledDate = $request->input('scheduled_date');
        $scheduledTime = substr($request->input('scheduled_time'), 0, 5);
        $whatsapp = $request->input('whatsapp');

        $depositCents = $this->calculator->cents($service);
        $durationMinutes = (int) $service->duration_hours * 60;
        $scheduledEndTime = $this->addMinutes($scheduledTime, $durationMinutes);

        $slotKey = Appointment::makeSlotKey($service->id, $scheduledDate, $scheduledTime);

        // Resolve the AgendaBlock covering this date/time to read its effective caps.
        // StoreBookingRequest already validated a matching, non-overflowing block exists.
        $requestedDay = Carbon::parse($scheduledDate)->dayOfWeek;
        $block = AgendaBlock::where('is_blocked', false)
            ->where(function ($q) use ($requestedDay, $scheduledDate) {
                $q->where('day_of_week', $requestedDay)
                    ->orWhereDate('specific_date', $scheduledDate);
            })
            ->get()
            ->first(function (AgendaBlock $candidate) use ($scheduledTime) {
                $open = substr($candidate->open_time, 0, 5);
                $close = substr($candidate->close_time, 0, 5);

                return $scheduledTime >= $open && $scheduledTime < $close;
            });

        if (! $block) {
            // Defensive — should be unreachable since StoreBookingRequest validated this.
            return response()->json([
                'message' => 'This slot is no longer available. Please choose another time.',
            ], 409);
        }

        $effectiveLimit = $block->concurrency_limit
            ?? (int) config('booking.venue.default_concurrency_limit');
        $softThreshold = $block->soft_threshold
            ?? config('booking.venue.default_soft_threshold');

        $result = DB::transaction(function () use (
            $service, $user, $scheduledDate, $scheduledTime, $scheduledEndTime,
            $whatsapp, $depositCents, $slotKey, $effectiveLimit, $softThreshold
        ) {
            // BOOK-002/BOOK-005 — recount venue-wide overlap inside the transaction.
            $overlapQuery = DB::table('appointments')
                ->where('status', '!=', 'cancelled')
                ->whereDate('scheduled_date', $scheduledDate)
                ->where('scheduled_time', '<', $scheduledEndTime)
                ->where('scheduled_end_time', '>', $scheduledTime);

            // lockForUpdate() serializes concurrent writers on MySQL (InnoDB row locks).
            // SQLite (tests / current prod default) does not support row-level locks the
            // same way, so this branch is a no-op there — optimistic recount is the
            // documented tradeoff for that driver (see design "Architecture Decisions" #4).
            if (DB::connection()->getDriverName() === 'mysql') {
                $overlapQuery->lockForUpdate();
            }

            $overlapCount = $overlapQuery->count();

            if ($overlapCount >= $effectiveLimit) {
                return null; // signals cap_exceeded to the caller
            }

            $appointment = Appointment::create([
                'service_id' => $service->id,
                'user_id' => $user->id,
                'order_id' => null, // updated after order is created
                'scheduled_date' => $scheduledDate,
                'scheduled_time' => $scheduledTime,
                'scheduled_end_time' => $scheduledEndTime,
                'slot_key' => $slotKey,
                'whatsapp' => $whatsapp,
                'payment_mode' => 'gateway',
                'deposit_amount_cents' => $depositCents,
                'status' => 'pending',
            ]);

            $order = Order::create([
                'user_id' => $user->id,
                'course_id' => null,
                'appointment_id' => $appointment->id,
                'client_transaction_id' => 'ORD-'.Str::uuid(),
                'gateway' => $this->gateway->name(),
                'amount_cents' => $depositCents,
                'currency' => 'USD',
                'status' => 'pending',
            ]);

            $appointment->update(['order_id' => $order->id]);

            $order->setRelation('appointment', $appointment);
            $session = $this->gateway->createCheckout($order);

            return [
                'order' => $order,
                'session' => $session,
                'is_near_capacity' => $softThreshold !== null && $overlapCount >= $softThreshold,
            ];
        });

        if ($result === null) {
            return response()->json([
                'message' => 'This time slot has reached capacity. Please choose another time.',
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

    /**
     * Add $minutes to a 'HH:MM' time string, returning a zero-padded 'HH:MM' result.
     * Minute-based integer arithmetic avoids midnight-wrap ambiguity from Carbon
     * formatting (see StoreBookingRequest for the matching validation-side logic).
     */
    private function addMinutes(string $time, int $minutes): string
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));
        $total = $hour * 60 + $minute + $minutes;

        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }
}
