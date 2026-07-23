<?php

namespace App\Listeners;

use App\Models\DeviceToken;
use Illuminate\Notifications\Events\NotificationFailed;
use Kreait\Firebase\Messaging\SendReport;
use NotificationChannels\Fcm\FcmChannel;

/**
 * InvalidateFcmDeviceToken — mobile-capacitor-setup PR3, design Decision 2:
 * "Invalidation: when FCM returns NotRegistered/InvalidToken, delete the
 * row." (sdd/mobile-capacitor-setup/design.md).
 *
 * NotificationChannels\Fcm\FcmChannel dispatches the framework's
 * Illuminate\Notifications\Events\NotificationFailed event once per failed
 * item in the multicast send report, with `$channel` set to
 * `NotificationChannels\Fcm\FcmChannel::class` (its own FQCN, not the
 * string 'fcm') and `data['report']` set to a single
 * Kreait\Firebase\Messaging\SendReport (not a MulticastSendReport) — see
 * vendor/laravel-notification-channels/fcm/src/FcmChannel.php
 * ::dispatchFailedNotification(). Auto-discovered by Laravel (no explicit
 * registration needed — this app has no EventServiceProvider, relying on
 * Laravel 11+'s default listener discovery for app/Listeners).
 */
class InvalidateFcmDeviceToken
{
    public function handle(NotificationFailed $event): void
    {
        if ($event->channel !== FcmChannel::class) {
            return;
        }

        $report = $event->data['report'] ?? null;

        if (! $report instanceof SendReport) {
            return;
        }

        if (! $report->messageWasSentToUnknownToken() && ! $report->messageTargetWasInvalid()) {
            return;
        }

        DeviceToken::where('token', $report->target()->value())->delete();
    }
}
