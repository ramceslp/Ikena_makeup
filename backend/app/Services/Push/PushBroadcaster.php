<?php

namespace App\Services\Push;

use App\Services\Push\DTOs\BroadcastResult;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Kreait\Firebase\Messaging\MulticastSendReport;
use NotificationChannels\Fcm\FcmChannel;

/**
 * PushBroadcaster — sends one notification to an explicit list of device
 * tokens and reports how many landed (push-notifications Slice 1, decision D1:
 * docs/push-notifications/HANDOFF.md).
 *
 * Calls NotificationChannels\Fcm\FcmChannel DIRECTLY rather than going through
 * the Notification facade, and that is the whole point of this class. Three
 * things fall out of the channel's own implementation
 * (vendor/laravel-notification-channels/fcm/src/FcmChannel.php):
 *
 *  1. It already chunks at TOKENS_PER_REQUEST = 500 and calls sendMulticast()
 *     per chunk — so this feature writes no batching code of its own.
 *  2. It RETURNS Collection<MulticastSendReport>. Notification::send() discards
 *     that return value, and it is exactly the per-token success/failure detail
 *     the admin history has to display. Hence the direct call.
 *  3. It dispatches Illuminate\Notifications\Events\NotificationFailed per
 *     failed token, which the pre-existing App\Listeners\InvalidateFcmDeviceToken
 *     already consumes to delete NotRegistered/InvalidToken rows. Broadcast
 *     token invalidation therefore works with zero new code.
 *
 * Takes tokens as an argument rather than querying for them, so the audience
 * decision stays in one place (BroadcastPushNotification) and a future
 * segmented audience — only enrolled students, only past buyers — needs no
 * change here.
 *
 * Does not catch exceptions: a credentials or transport failure is not a
 * per-token outcome, and the caller records it as a failed send.
 */
class PushBroadcaster
{
    public function __construct(private readonly FcmChannel $channel) {}

    /**
     * @param  list<string>  $tokens
     */
    public function broadcast(Notification $notification, array $tokens): BroadcastResult
    {
        if ($tokens === []) {
            // No registered devices. Short-circuited rather than handed to the
            // channel, which would return null and force null-handling below.
            return BroadcastResult::empty();
        }

        $notifiable = (new AnonymousNotifiable)->route('fcm', $tokens);

        $reports = $this->channel->send($notifiable, $notification);

        if ($reports === null) {
            return BroadcastResult::empty();
        }

        return new BroadcastResult(
            successes: $reports->sum(static fn (MulticastSendReport $report): int => $report->successes()->count()),
            failures: $reports->sum(static fn (MulticastSendReport $report): int => $report->failures()->count()),
        );
    }
}
