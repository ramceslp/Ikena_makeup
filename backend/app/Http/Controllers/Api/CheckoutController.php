<?php

namespace App\Http\Controllers\Api;

use App\Actions\CourseCheckoutAction;
use App\Exceptions\AlreadyEnrolledException;
use App\Exceptions\CourseUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Notifications\BookingConfirmed;
use App\Services\Commerce\StockReservation;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly StockReservation        $stockReservation,
        private readonly CourseCheckoutAction    $courseCheckout,
    ) {}

    /**
     * POST /api/courses/{course:slug}/checkout
     * Creates a pending Order and returns the provider checkout config.
     *
     * The eligibility/pricing logic lives in CourseCheckoutAction so the
     * checkout-handoff redeem endpoint can reuse it verbatim — same split
     * CartCheckoutController and BookingController already use.
     */
    public function checkout(Request $request, Course $course): JsonResponse
    {
        try {
            $result = ($this->courseCheckout)($request->user(), $course);
        } catch (CourseUnavailableException $e) {
            // 422 — unpublished, or free (free courses must use /enroll).
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (AlreadyEnrolledException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'data' => [
                'order_id' => $result['order']->id,
                'provider' => $result['session']->provider,
                'config'   => $result['session']->config,
            ],
        ], 201);
    }

    /**
     * POST /api/payments/confirm
     * Verifies the payment with the gateway and, if approved, performs
     * type-specific post-payment side effects:
     *  - course:        create Enrollment
     *  - appointment:   transition appointment to paid
     *  - product_cart:  mark paid (stock already decremented at checkout)
     *
     * On gateway-declined for product_cart: restore stock.
     * All paid transitions use a conditional UPDATE (WHERE status='pending')
     * for idempotency and race-safety (confirm-vs-release arbitration).
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

        // Route by order type (replaces FK-sniffing with authoritative type column)
        $orderType = $order->type;

        // Validate that the type is a known type we can confirm
        if (! in_array($orderType, ['course', 'appointment', 'product_cart'], true)) {
            return response()->json(['message' => 'Invalid order state.'], 422);
        }

        // -------------------------------------------------------------------------
        // product_cart branch
        // -------------------------------------------------------------------------

        if ($orderType === 'product_cart') {
            return $this->confirmProductCart($order, $gatewayId);
        }

        // -------------------------------------------------------------------------
        // course / appointment branches (unchanged from pre-PR2b)
        // -------------------------------------------------------------------------

        $isCourseOrder      = ($orderType === 'course');
        $isAppointmentOrder = ($orderType === 'appointment');

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

        // Confirm with the payment gateway. The Order is passed so the gateway
        // can verify the captured amount and currency against it — the widget
        // is client-side, so "approved" alone does not mean the right money
        // moved (see PayPhoneGateway::confirm).
        $result = $this->gateway->confirm($order, $gatewayId);

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

                    // Push notification trigger (mobile-capacitor-setup PR3,
                    // design Decision 2's "Booking confirmed" primary v1
                    // trigger). Fans out to every device_tokens row the user
                    // has registered; a no-op if they have none.
                    Notification::send($order->appointment->user, new BookingConfirmed($order->appointment));
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

    // -------------------------------------------------------------------------
    // product_cart confirmation logic (extracted for clarity)
    // -------------------------------------------------------------------------

    private function confirmProductCart(Order $order, string $gatewayId): JsonResponse
    {
        // If already canceled (release command won the race) → 409
        if ($order->status === 'canceled') {
            return response()->json([
                'data' => [
                    'status'  => 'canceled',
                    'message' => 'Reservation expired. Please start a new checkout.',
                ],
            ], 409);
        }

        // Idempotency: already paid → return terminal payload
        if ($order->status === 'paid') {
            return response()->json([
                'data' => [
                    'status'      => 'paid',
                    'order_id'    => $order->id,
                    'items_count' => $order->items()->count(),
                ],
            ]);
        }

        // Confirm with the payment gateway — see the note on the course branch:
        // the Order is what lets the gateway verify the captured amount.
        $result = $this->gateway->confirm($order, $gatewayId);

        if ($result->approved) {
            // Guard the paid transition atomically (confirm-vs-release arbitration).
            // If the release command already canceled this order, affected rows = 0.
            // meta is json_encoded explicitly here because raw DB::update bypasses
            // Eloquent's 'meta' => 'array' cast (the Eloquent update paths pass the raw array).
            $claimed = DB::update(
                "UPDATE orders SET status = 'paid', paid_at = ?, gateway_transaction_id = ?, meta = ? WHERE id = ? AND status = 'pending'",
                [now(), $result->gatewayId, json_encode($result->raw), $order->id]
            );

            if ($claimed === 0) {
                // Release command won the race — re-read current status and return terminal payload.
                $order->refresh();

                if ($order->status === 'canceled') {
                    return response()->json([
                        'data' => [
                            'status'  => 'canceled',
                            'message' => 'Reservation expired during payment confirmation.',
                        ],
                    ], 409);
                }

                // Some other terminal state (already paid by duplicate confirm?)
                return response()->json([
                    'data' => ['status' => $order->status],
                ]);
            }

            // Payment confirmed and we claimed the transition.
            // Stock is already decremented at checkout — NO further stock mutation.
            return response()->json([
                'data' => [
                    'status'      => 'paid',
                    'order_id'    => $order->id,
                    'items_count' => $order->items()->count(),
                ],
            ]);
        }

        // Payment declined — guard the failed transition atomically (mirrors the paid path above).
        // If the release command already canceled this order, affected rows = 0 → do NOT
        // restore stock a second time (release command already restored it).
        // meta is json_encoded explicitly here because raw DB::update bypasses
        // Eloquent's 'meta' => 'array' cast (the Eloquent update paths pass the raw array).
        $claimed = DB::update(
            "UPDATE orders SET status = 'failed', meta = ? WHERE id = ? AND status = 'pending'",
            [json_encode($result->raw), $order->id]
        );

        if ($claimed === 0) {
            // Release command won the race — re-read current status and return terminal payload.
            $order->refresh();

            if ($order->status === 'canceled') {
                return response()->json([
                    'data' => [
                        'status'  => 'canceled',
                        'message' => 'Reservation expired during payment confirmation.',
                    ],
                ], 409);
            }

            // Some other terminal state (already failed by duplicate decline?)
            return response()->json([
                'data' => ['status' => $order->status, 'order_id' => $order->id],
            ]);
        }

        // We claimed the failed transition — restore stock (don't wait for sweep).
        $this->stockReservation->release($order);

        return response()->json([
            'data' => [
                'status'   => 'failed',
                'order_id' => $order->id,
            ],
        ]);
    }
}
