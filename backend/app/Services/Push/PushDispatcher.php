<?php

namespace App\Services\Push;

use App\Jobs\BroadcastPushNotification;
use App\Models\Course;
use App\Models\Post;
use App\Models\PushNotificationLog;
use Illuminate\Support\Str;

/**
 * PushDispatcher — decides WHETHER a push should go out, writes the history
 * row, and queues the broadcast (push-notifications Slice 2:
 * sdd/push-notifications/HANDOFF.md §5.3).
 *
 * The single place that owns per-trigger wording. App\Notifications\PushBroadcast
 * is deliberately generic, so the copy a user actually reads lives here and is
 * persisted to `push_notification_logs` before anything is sent — which is what
 * makes the admin history an exact record of what went out rather than a
 * reconstruction of it.
 *
 * Called explicitly from the controllers that own each business event, not from
 * a model observer. Observers would also fire from seeders, factories and
 * tinker, and the project's precedent is explicit dispatch (BookingConfirmed is
 * sent from CheckoutController::confirm()). Tradeoff: a future publish path
 * added elsewhere must remember to call this. There is exactly one such path
 * per entity today — see the class docblocks on the two callers.
 */
class PushDispatcher
{
    /** Fallback body when a post has no excerpt (the column is nullable). */
    private const POST_BODY_FALLBACK = 'Nueva publicación en Ikena. Tocá para leerla.';

    /** FCM truncates long bodies in the tray anyway; trimming keeps it predictable. */
    private const BODY_MAX_LENGTH = 160;

    /**
     * "A news post was published." Returns null when no push is warranted, so
     * callers can hook unconditionally and let this method own the rules.
     *
     * Guarded on `push_notified_at` so correcting a typo in an already-published
     * post, or toggling it unpublished and published again, never re-notifies.
     */
    public function forPost(Post $post): ?PushNotificationLog
    {
        if (! $post->is_published || $post->push_notified_at !== null) {
            return null;
        }

        $log = $this->dispatch(
            type: PushNotificationLog::TYPE_POST_PUBLISHED,
            title: $post->title,
            body: $this->postBody($post),
            data: ['route' => "/noticias/{$post->slug}"],
        );

        $this->markNotified($post);

        return $log;
    }

    /**
     * "A course became available." Reached only through
     * Instructor\CourseController::publish(), which is the sole publish path —
     * store() hard-codes is_published to false.
     */
    public function forCourse(Course $course): ?PushNotificationLog
    {
        if (! $course->is_published || $course->push_notified_at !== null) {
            return null;
        }

        $log = $this->dispatch(
            type: PushNotificationLog::TYPE_COURSE_PUBLISHED,
            title: 'Nuevo curso disponible',
            body: $course->title,
            data: ['route' => "/cursos/{$course->slug}"],
        );

        $this->markNotified($course);

        return $log;
    }

    /**
     * A custom broadcast composed by an admin. `$route` is optional — a
     * promotional message does not have to link anywhere.
     */
    public function custom(string $title, string $body, ?string $route, ?int $sentBy): PushNotificationLog
    {
        return $this->dispatch(
            type: PushNotificationLog::TYPE_CUSTOM,
            title: $title,
            body: $body,
            data: $route !== null ? ['route' => $route] : [],
            sentBy: $sentBy,
        );
    }

    /**
     * Records the send, then queues it — in that order, and always.
     *
     * When push is disabled the row is still written, with status 'skipped',
     * and no job is queued. A disabled feature that silently discards the
     * event would leave the admin with no way to tell "nobody was notified"
     * apart from "nothing happened", which is the exact confusion the history
     * exists to prevent.
     */
    private function dispatch(string $type, string $title, string $body, array $data, ?int $sentBy = null): PushNotificationLog
    {
        $enabled = (bool) config('push.enabled');

        $log = PushNotificationLog::create([
            'type'     => $type,
            'title'    => $title,
            'body'     => $body,
            'data'     => $data === [] ? null : $data,
            'audience' => 'all',
            'sent_by'  => $sentBy,
            'status'   => $enabled
                ? PushNotificationLog::STATUS_PENDING
                : PushNotificationLog::STATUS_SKIPPED,
            'sent_at'  => $enabled ? null : now(),
        ]);

        if ($enabled) {
            BroadcastPushNotification::dispatch($log->id);
        }

        return $log;
    }

    private function postBody(Post $post): string
    {
        $excerpt = trim((string) $post->excerpt);

        if ($excerpt === '') {
            return self::POST_BODY_FALLBACK;
        }

        return Str::limit($excerpt, self::BODY_MAX_LENGTH);
    }

    /**
     * forceFill rather than update(): `push_notified_at` is deliberately absent
     * from both models' $fillable so no request payload can clear the
     * idempotency guard and cause a re-notify.
     */
    private function markNotified(Post|Course $model): void
    {
        $model->forceFill(['push_notified_at' => now()])->save();
    }
}
