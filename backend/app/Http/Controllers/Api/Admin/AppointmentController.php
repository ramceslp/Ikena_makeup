<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    /**
     * GET /api/admin/appointments
     *
     * List all appointments (any status), paginated, with eager-loaded
     * service, user, and order. Filterable by status, service_id, and date range.
     *
     * Filter params are validated before querying: invalid input returns 422.
     * Allowed status values: pending, confirmed, paid, cancelled.
     */
    public function index(Request $request): JsonResponse
    {
        // FIX 6 — validate optional filter params to prevent DB errors on malformed input
        $request->validate([
            'date_from'  => ['nullable', 'date'],
            'date_to'    => ['nullable', 'date'],
            'status'     => ['nullable', 'string', 'in:pending,confirmed,paid,cancelled'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
        ]);

        $query = Appointment::with('service', 'user', 'order')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('service_id')) {
            $query->where('service_id', $request->input('service_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('scheduled_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('scheduled_date', '<=', $request->input('date_to'));
        }

        $appointments = $query->paginate(15);

        return response()->json(
            AppointmentResource::collection($appointments)->response()->getData(true)
        );
    }

    /**
     * PATCH /api/admin/appointments/{appointment}/mark-paid
     *
     * Manually mark an appointment as paid (cash/transfer collected in person).
     * Transitions status from pending|confirmed → paid.
     * Sets order.status=paid, order.paid_at=now(), appointment.payment_mode=manual.
     * Returns 422 if already paid or cancelled.
     *
     * FIX 1 — both writes are wrapped in a DB::transaction so a partial failure
     * (appointment updated but order update fails) cannot leave them in inconsistent states.
     */
    public function markPaid(Appointment $appointment): JsonResponse
    {
        if (! in_array($appointment->status, ['pending', 'confirmed'], true)) {
            return response()->json([
                'message' => 'Only pending or confirmed appointments can be marked as paid.',
            ], 422);
        }

        DB::transaction(function () use ($appointment) {
            $appointment->update([
                'status'       => 'paid',
                'payment_mode' => 'manual',
            ]);

            if ($appointment->order) {
                $appointment->order->update([
                    'status'  => 'paid',
                    'paid_at' => now(),
                ]);
            }
        });

        $appointment->loadMissing('service', 'user', 'order');

        return response()->json([
            'data' => new AppointmentResource($appointment),
        ]);
    }

    /**
     * PATCH /api/admin/appointments/{appointment}/cancel
     *
     * Cancel an appointment. Sets status=cancelled, records cancelled_by_id,
     * nulls out slot_key (freeing the slot for rebooking), and cancels the
     * linked order (if any).
     * Returns 422 if already cancelled.
     *
     * FIX 4 — all writes (appointment + order) are inside DB::transaction.
     * No refund logic — that is out of scope.
     */
    public function cancel(Request $request, Appointment $appointment): JsonResponse
    {
        if ($appointment->status === 'cancelled') {
            return response()->json([
                'message' => 'This appointment is already cancelled.',
            ], 422);
        }

        DB::transaction(function () use ($request, $appointment) {
            $appointment->update([
                'status'          => 'cancelled',
                'slot_key'        => null,
                'cancelled_by_id' => $request->user()->id,
                'cancelled_at'    => now(),
            ]);

            // FIX 4 — transition linked order to 'cancelled' so order status
            // stays consistent with the appointment lifecycle.
            if ($appointment->order) {
                $appointment->order->update(['status' => 'cancelled']);
            }
        });

        $appointment->loadMissing('service', 'user', 'order');

        return response()->json([
            'data' => new AppointmentResource($appointment),
        ]);
    }
}
