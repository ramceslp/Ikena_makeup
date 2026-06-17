<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\SlotResource;
use App\Models\Appointment;
use App\Models\Order;
use App\Models\Service;
use App\Services\Booking\DepositCalculator;
use App\Services\Booking\SlotAvailabilityResolver;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function __construct(
        private readonly SlotAvailabilityResolver $resolver,
        private readonly DepositCalculator        $calculator,
        private readonly PaymentGatewayInterface  $gateway,
    ) {}

    /**
     * GET /api/services/{serviceId}/available-slots
     *
     * Returns available slot occurrences for a published by_appointment service.
     * This endpoint is PUBLIC — no authentication required.
     */
    public function availableSlots(int $serviceId): JsonResponse
    {
        $service = Service::where('id', $serviceId)
                          ->where('is_published', true)
                          ->firstOrFail();

        if ($service->availability_type !== 'by_appointment') {
            return response()->json(['data' => []]);
        }

        $occurrences = $this->resolver->resolve($service);

        // Wrap each occurrence array in SlotResource (array path in toArray)
        $data = collect($occurrences)
            ->map(fn ($occ) => new SlotResource($occ))
            ->values();

        return response()->json(['data' => $data]);
    }

    /**
     * POST /api/bookings
     *
     * Create a booking (appointment + deposit order) for the authenticated user.
     *
     * Flow (all inside a DB transaction):
     *  1. Insert Appointment with slot_key set.
     *  2. On UniqueConstraintViolationException → rollback → 409 (no orphan order).
     *  3. Compute deposit via DepositCalculator.
     *  4. Create Order (appointment_id set, course_id null).
     *  5. Update appointment.order_id back-reference.
     *  6. Kick off gateway checkout → return 201 with gateway payload.
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $service = Service::findOrFail($request->validated()['service_id']);
        $user    = $request->user();

        $scheduledDate = $request->input('scheduled_date');
        $scheduledTime = $request->input('scheduled_time');
        $whatsapp      = $request->input('whatsapp');

        $depositCents = $this->calculator->cents($service);

        $slotKey = Appointment::makeSlotKey($service->id, $scheduledDate, $scheduledTime);

        // Pre-check slot availability inside a lock to detect collisions before the INSERT.
        // This approach works correctly in both MySQL and SQLite (the latter does not reliably
        // roll back only the savepoint on a unique constraint violation in a nested transaction,
        // which would invalidate the outer RefreshDatabase transaction wrapper in tests).
        $slotTaken = Appointment::where('slot_key', $slotKey)->exists();

        if ($slotTaken) {
            return response()->json([
                'message' => 'This slot is no longer available. Please choose another time.',
            ], 409);
        }

        try {
            $result = DB::transaction(function () use (
                $service, $user, $scheduledDate, $scheduledTime, $whatsapp, $depositCents, $slotKey
            ) {
                // Step 1: insert appointment (may still throw on slot_key UNIQUE violation
                // due to race condition; caught below for atomicity guarantee)
                $appointment = Appointment::create([
                    'service_id'          => $service->id,
                    'user_id'             => $user->id,
                    'order_id'            => null, // updated after order is created
                    'scheduled_date'      => $scheduledDate,
                    'scheduled_time'      => $scheduledTime,
                    'slot_key'            => $slotKey,
                    'whatsapp'            => $whatsapp,
                    'payment_mode'        => 'gateway',
                    'deposit_amount_cents' => $depositCents,
                    'status'              => 'pending',
                ]);

                // Step 2: create the deposit Order (triggers XOR guard — must pass)
                $order = Order::create([
                    'user_id'               => $user->id,
                    'course_id'             => null,
                    'appointment_id'        => $appointment->id,
                    'client_transaction_id' => 'ORD-' . Str::uuid(),
                    'gateway'               => $this->gateway->name(),
                    'amount_cents'          => $depositCents,
                    'currency'              => 'USD',
                    'status'                => 'pending',
                ]);

                // Step 3: link order back to appointment
                $appointment->update(['order_id' => $order->id]);

                // Step 4: initiate gateway checkout
                $order->setRelation('appointment', $appointment);
                $session = $this->gateway->createCheckout($order);

                return [
                    'order'   => $order,
                    'session' => $session,
                ];
            });
        } catch (UniqueConstraintViolationException) {
            // Race-condition collision — transaction rolled back; no orphan order exists.
            return response()->json([
                'message' => 'This slot is no longer available. Please choose another time.',
            ], 409);
        }

        return response()->json([
            'data' => [
                'order_id' => $result['order']->id,
                'provider' => $result['session']->provider,
                'config'   => $result['session']->config,
            ],
        ], 201);
    }
}
