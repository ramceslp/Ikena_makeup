<?php

namespace App\Listeners;

use App\Models\DeviceToken;
use Illuminate\Notifications\Events\NotificationFailed;
use Kreait\Firebase\Messaging\MulticastSendReport;

/**
 * InvalidateFcmDeviceToken — mobile-capacitor-setup PR3, design Decision 2:
 * "Invalidation: when FCM returns NotRegistered/InvalidToken, delete the
 * row." (sdd/mobile-capacitor-setup/design.md).
 *
 * NotificationChannels\Fcm\FcmChannel dispatches the framework's
 * Illuminate\Notifications\Events\NotificationFailed event for every failed
 * item in the multicast send report. Auto-discovered by Laravel (no
 * explicit registration needed — this app has no EventServiceProvider,
 * relying on Laravel 11+'s default listener discovery for app/Listeners).
 */
class InvalidateFcmDeviceToken
{
    public function handle(NotificationFailed $event): void
    {
        if ($event->channel !== 'fcm') {
            return;
        }

        $report = $event->data['report'] ?? null;

        if (! $report instanceof MulticastSendReport) {
            return;
        }

        $deadTokens = [...$report->unknownTokens(), ...$report->invalidTokens()];

        if (empty($deadTokens)) {
            return;
        }

        DeviceToken::whereIn('token', $deadTokens)->delete();
    }
}
