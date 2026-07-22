<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

/**
 * AppointmentReminder — v1 push trigger "Appointment reminder" (secondary),
 * design Decision 2 (sdd/mobile-capacitor-setup/design.md). Dispatched from
 * the `appointments:send-reminders` scheduled command for appointments
 * starting in ~24 hours.
 */
class AppointmentReminder extends Notification
{
    public function __construct(public readonly Appointment $appointment) {}

    public function via(mixed $notifiable): array
    {
        return ['fcm'];
    }

    public function toFcm(mixed $notifiable): FcmMessage
    {
        $time = substr((string) $this->appointment->scheduled_time, 0, 5);

        return FcmMessage::create(
            notification: FcmNotification::create(
                title: 'Appointment reminder',
                body: "Reminder: your appointment is tomorrow at {$time}.",
            ),
        );
    }
}
