<?php

namespace App\Actions;

use App\Exceptions\BookingCapacityExceededException;
use App\Exceptions\BookingSlotUnavailableException;
use App\Exceptions\ServiceUnavailableException;
use App\Models\AgendaBlock;
use App\Models\Appointment;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\Booking\DepositCalculator;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\DTOs\CheckoutSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CreateBookingAction — resolves venue capacity for a requested slot and
 * atomically creates the Appointment + deposit Order + gateway checkout
 * session.
 *
 * Extracted from BookingController::store() so the checkout-handoff redeem
 * endpoint (mobile-capacitor-setup PR2) can recount capacity and create the
 * booking from a snapshot as the bound user, reusing the exact same
 * concurrency-cap rules (BOOK-001/002/003/005) instead of a parallel
 * implementation.
 */
class CreateBookingAction
{
    public function __construct(
        private readonly DepositCalculator $calculator,
        private readonly PaymentGatewayInterface $gateway,
    ) {}

    /**
     * @return array{order: Order, session: CheckoutSession, is_near_capacity: bool}
     *
     * @throws ServiceUnavailableException the service is unpublished, is not by_appointment, or the requested duration overflows the covering AgendaBlock's close_time (BOOK-004) — re-validated here because the checkout-handoff redeem endpoint calls this Action directly against a snapshot that may be up to 10 minutes stale, bypassing StoreBookingRequest::withValidator()
     * @throws BookingSlotUnavailableException no AgendaBlock covers the requested time — genuinely reachable via the checkout-handoff redeem endpoint's stale snapshot, not just a defensive guard against /api/bookings
     * @throws BookingCapacityExceededException venue-wide concurrency cap reached for the requested window
     */
    public function __invoke(
        User $user,
        int $serviceId,
        string $scheduledDate,
        string $scheduledTime,
        ?string $whatsapp,
    ): array {
        $service = Service::findOrFail($serviceId);
        $scheduledTime = substr($scheduledTime, 0, 5);

        // Re-validate the same business rules StoreBookingRequest::withValidator()
        // enforces at the direct /api/bookings HTTP boundary. This Action is also
        // invoked by the checkout-handoff redeem endpoint (mobile-capacitor-setup
        // PR2) against a snapshot that may be up to 10 minutes stale — the service
        // could have been unpublished or had its availability_type changed since.
        if (! $service->is_published) {
            throw new ServiceUnavailableException('The service is not published.');
        }

        if ($service->availability_type !== 'by_appointment') {
            throw new ServiceUnavailableException('This service does not accept appointments.');
        }

        $depositCents = $this->calculator->cents($service);
        $durationMinutes = (int) $service->duration_hours * 60;
        $scheduledEndTime = $this->addMinutes($scheduledTime, $durationMinutes);

        $slotKey = Appointment::makeSlotKey($service->id, $scheduledDate, $scheduledTime);

        // Resolve the AgendaBlock covering this date/time to read its effective caps.
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
            // Genuinely reachable — not just defensive. StoreBookingRequest
            // validates this for the direct /api/bookings path, but the
            // checkout-handoff redeem endpoint calls this Action against a
            // snapshot that may be up to 10 minutes stale (the covering
            // AgendaBlock may have been removed/blocked since the handoff
            // was created).
            throw new BookingSlotUnavailableException;
        }

        // BOOK-004 — reject when scheduledTime + duration overflows the
        // covering AgendaBlock's close_time. Same rule StoreBookingRequest
        // enforces at the HTTP boundary, re-validated here for the same
        // stale-snapshot reason as above.
        $closeTime = substr($block->close_time, 0, 5);
        if ($scheduledEndTime > $closeTime) {
            throw new ServiceUnavailableException('The requested duration exceeds the venue availability window.');
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
            throw new BookingCapacityExceededException;
        }

        return $result;
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
