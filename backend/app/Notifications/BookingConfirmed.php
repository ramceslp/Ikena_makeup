<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

/**
 * BookingConfirmed — v1 push trigger "Booking confirmed" (primary), design
 * Decision 2 (sdd/mobile-capacitor-setup/design.md). Dispatched from
 * CheckoutController::confirm()'s appointment -> paid success branch.
 *
 * Sent over a single FCM transport (not queued — the confirm() request is
 * already synchronous and fast; failures are handled via the
 * NotificationFailed event, see App\Listeners\InvalidateFcmDeviceToken).
 */
class BookingConfirmed extends Notification
{
    public function __construct(public readonly Appointment $appointment) {}

    public function via(mixed $notifiable): array
    {
        return [FcmChannel::class];
    }

    public function toFcm(mixed $notifiable): FcmMessage
    {
        $date = $this->appointment->scheduled_date->format('Y-m-d');
        $time = substr((string) $this->appointment->scheduled_time, 0, 5);

        return FcmMessage::create(
            notification: FcmNotification::create(
                title: 'Appointment confirmed',
                body: "Your appointment is confirmed for {$date} {$time}.",
            ),
        );
    }
}
