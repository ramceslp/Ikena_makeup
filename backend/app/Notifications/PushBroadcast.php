<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

/**
 * PushBroadcast — the single FCM message shape used by every broadcast in this
 * feature: a published news post, a newly available course, and a custom admin
 * send (push-notifications Slice 1: docs/push-notifications/HANDOFF.md).
 *
 * Deliberately ONE generic notification rather than a subclass per trigger.
 * The per-trigger wording lives in App\Services\Push\PushDispatcher, which
 * writes it to `push_notification_logs` and hands the stored row to
 * App\Jobs\BroadcastPushNotification. That makes the log row the single source
 * of truth for what was actually sent — the history cannot drift from the
 * message, because the history IS the message. It also keeps the queued job
 * serializable by ID alone, with no Eloquent model embedded in a notification
 * payload.
 *
 * Unlike AppointmentReminder (a per-user notification routed through
 * User::routeNotificationForFcm()), this one is always sent to an
 * AnonymousNotifiable carrying an explicit token list — see PushBroadcaster.
 */
class PushBroadcast extends Notification
{
    /**
     * @param  array<string, string>  $data  deep-link payload, e.g. ['route' => '/noticias/slug']
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
    ) {}

    /**
     * Must be the channel's FQCN, not the bare string 'fcm' — Laravel's
     * ChannelManager cannot resolve 'fcm' to a driver and throws
     * "Driver [fcm] not supported." (regression pinned by
     * tests/Feature/Notifications/FcmChannelResolutionTest.php).
     */
    public function via(mixed $notifiable): array
    {
        return [FcmChannel::class];
    }

    public function toFcm(mixed $notifiable): FcmMessage
    {
        return FcmMessage::create(
            data: $this->stringifyData(),
            notification: FcmNotification::create(
                title: $this->title,
                body: $this->body,
            ),
        );
    }

    /**
     * FCM rejects a data payload whose values are not all strings, and the
     * failure surfaces as an opaque API error rather than a validation
     * message. Casting here rather than trusting callers keeps that failure
     * mode impossible regardless of what a future trigger puts in `data`.
     *
     * @return array<string, string>
     */
    private function stringifyData(): array
    {
        return array_map(static fn (mixed $value): string => (string) $value, $this->data);
    }
}
