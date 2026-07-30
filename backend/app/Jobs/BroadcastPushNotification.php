<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Models\PushNotificationLog;
use App\Notifications\PushBroadcast;
use App\Services\Push\PushBroadcaster;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * BroadcastPushNotification — delivers one already-recorded
 * `push_notification_logs` row to every registered device
 * (push-notifications Slice 1: docs/push-notifications/HANDOFF.md).
 *
 * Queued (QUEUE_CONNECTION defaults to `database` in config/queue.php) so that
 * publishing a post never blocks on an FCM round trip — a broadcast to every
 * device is several sequential 500-token HTTP requests, which has no business
 * being inside an admin's save request.
 *
 * Carries only the log ID. The message text is read back from the log row, so
 * what the history shows is by construction what was sent, and the job payload
 * holds no serialized Eloquent model.
 */
class BroadcastPushNotification implements ShouldQueue
{
    use Queueable;

    /**
     * One retry. FCM failures are dominated by two classes: transient
     * transport errors, which a single retry clears, and bad credentials,
     * which no amount of retrying fixes. A long backoff chain would only
     * delay the 'failed' status the admin needs to see.
     */
    public int $tries = 2;

    public function __construct(public readonly int $logId) {}

    public function handle(PushBroadcaster $broadcaster): void
    {
        $log = PushNotificationLog::find($this->logId);

        if ($log === null) {
            // The row was deleted between dispatch and execution. Nothing to
            // send and nothing to report against — not an error.
            return;
        }

        if (! config('push.enabled')) {
            // Defence in depth. PushDispatcher already refuses to dispatch this
            // job when push is disabled, but the flag can also be turned off
            // while jobs are sitting in the queue — resolving the Firebase
            // Messaging client here would then throw for missing credentials.
            $log->update([
                'status' => PushNotificationLog::STATUS_SKIPPED,
                'sent_at' => now(),
            ]);

            return;
        }

        $tokens = $this->resolveTokens();

        try {
            $result = $broadcaster->broadcast(
                new PushBroadcast($log->title, $log->body, $log->data ?? []),
                $tokens,
            );
        } catch (Throwable $e) {
            // A transport/credentials failure, not a per-token rejection.
            // Recorded on the row so the admin history shows the failure
            // instead of a send that appears stuck on 'pending' forever, then
            // re-thrown so the queue's own retry and failed_jobs handling
            // still apply.
            $log->update([
                'status' => PushNotificationLog::STATUS_FAILED,
                'recipients_count' => count($tokens),
                'sent_at' => now(),
            ]);

            Log::error('Push broadcast failed', [
                'log_id' => $log->id,
                'type' => $log->type,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }

        $log->update([
            'status' => PushNotificationLog::STATUS_SENT,
            'recipients_count' => count($tokens),
            'success_count' => $result->successes,
            'failure_count' => $result->failures,
            'sent_at' => now(),
        ]);
    }

    /**
     * Resolves the audience to a token list. Only 'all' exists today; the
     * method is the seam where a segmented audience (enrolled students, past
     * buyers) would branch without touching PushBroadcaster.
     *
     * @return list<string>
     */
    private function resolveTokens(): array
    {
        return DeviceToken::query()->pluck('token')->all();
    }
}
