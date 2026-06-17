<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function __construct(private readonly PaymentGatewayInterface $gateway) {}

    /**
     * POST /api/courses/{course:slug}/checkout
     * Creates a pending Order and returns the provider checkout config.
     */
    public function checkout(Request $request, Course $course): JsonResponse
    {
        $user = $request->user();

        // 422 — free courses must use /enroll instead
        if ((float) $course->price === 0.0) {
            return response()->json([
                'message' => 'This course is free. Use POST /courses/{slug}/enroll instead.',
            ], 422);
        }

        // 409 — already enrolled
        $alreadyEnrolled = $user->enrolledCourses()
            ->where('courses.id', $course->id)
            ->exists();

        if ($alreadyEnrolled) {
            return response()->json([
                'message' => 'You are already enrolled in this course.',
            ], 409);
        }

        // Create a pending order with a unique client_transaction_id (max 50 chars).
        // 'ORD-' (4) + 36 UUID chars = 40 chars — within the 50-char limit.
        $order = Order::create([
            'user_id'               => $user->id,
            'course_id'             => $course->id,
            'client_transaction_id' => 'ORD-' . Str::uuid(),
            'gateway'               => $this->gateway->name(),
            'amount_cents'          => (int) round((float) $course->price * 100),
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);

        // Eager-load course so the gateway can access it without an extra query.
        $order->setRelation('course', $course);

        $session = $this->gateway->createCheckout($order);

        return response()->json([
            'data' => [
                'order_id' => $order->id,
                'provider' => $session->provider,
                'config'   => $session->config,
            ],
        ], 201);
    }

    /**
     * POST /api/payments/confirm
     * Verifies the payment with the gateway and, if approved:
     *  - For course orders: creates the Enrollment.
     *  - For appointment orders: transitions the linked appointment to paid/confirmed.
     */
    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id'                   => ['required'],
            'clientTransactionId'  => ['required', 'string'],
        ]);

        $gatewayId           = (string) $validated['id'];
        $clientTransactionId = $validated['clientTransactionId'];

        // Find the order by client_transaction_id — must belong to the authenticated user.
        $order = Order::where('client_transaction_id', $clientTransactionId)->first();

        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'This order does not belong to you.'], 403);
        }

        // Determine order type
        $isCourseOrder      = ! is_null($order->course_id);
        $isAppointmentOrder = ! is_null($order->appointment_id);

        // FIX 2 — guard: if the order is neither a course nor an appointment order
        // (data inconsistency; XOR guard on Order model should prevent this in practice),
        // return a clear error BEFORE any paid mutation to avoid a 500 and partial state.
        if (! $isCourseOrder && ! $isAppointmentOrder) {
            return response()->json(['message' => 'Invalid order state.'], 422);
        }

        if ($isCourseOrder) {
            $order->loadMissing('course');
            $courseSlug = $order->course->slug;
        }

        // Idempotency — if already paid, return the appropriate success payload without re-charging.
        if ($order->status === 'paid') {
            if ($isCourseOrder) {
                return response()->json([
                    'data' => [
                        'status'      => 'paid',
                        'enrolled'    => true,
                        'course_slug' => $courseSlug,
                    ],
                ]);
            }

            // Appointment order already paid
            return response()->json([
                'data' => [
                    'status'          => 'paid',
                    'appointment_id'  => $order->appointment_id,
                ],
            ]);
        }

        // Confirm with the payment gateway.
        $result = $this->gateway->confirm($gatewayId, $clientTransactionId);

        if ($result->approved) {
            // Mark order as paid.
            $order->update([
                'status'                 => 'paid',
                'paid_at'                => now(),
                'gateway_transaction_id' => $result->gatewayId,
                'meta'                   => $result->raw,
            ]);

            if ($isCourseOrder) {
                // Create enrollment (idempotent — firstOrCreate prevents duplicates).
                Enrollment::firstOrCreate(
                    ['user_id' => $order->user_id, 'course_id' => $order->course_id],
                    ['price_paid' => $order->amount_cents / 100]
                );

                return response()->json([
                    'data' => [
                        'status'      => 'paid',
                        'enrolled'    => true,
                        'course_slug' => $courseSlug,
                    ],
                ]);
            }

            if ($isAppointmentOrder) {
                // Transition the linked appointment to paid.
                $order->loadMissing('appointment');

                if ($order->appointment) {
                    $order->appointment->update([
                        'status' => 'paid',
                    ]);
                }

                return response()->json([
                    'data' => [
                        'status'         => 'paid',
                        'appointment_id' => $order->appointment_id,
                    ],
                ]);
            }
        }

        // Payment was not approved — mark order failed.
        $order->update([
            'status' => 'failed',
            'meta'   => $result->raw,
        ]);

        if ($isCourseOrder) {
            return response()->json([
                'data' => [
                    'status'      => 'failed',
                    'enrolled'    => false,
                    'course_slug' => $courseSlug,
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'status'         => 'failed',
                'appointment_id' => $order->appointment_id,
            ],
        ]);
    }
}
