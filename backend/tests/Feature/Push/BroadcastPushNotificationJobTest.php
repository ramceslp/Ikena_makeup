<?php

namespace Tests\Feature\Push;

use App\Jobs\BroadcastPushNotification;
use App\Models\DeviceToken;
use App\Models\PushNotificationLog;
use App\Models\User;
use App\Services\Push\DTOs\BroadcastResult;
use App\Services\Push\PushBroadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * BroadcastPushNotificationJobTest — push-notifications Slice 1.
 *
 * Exercises the job's own contract (audience resolution, log completion, the
 * disabled-flag short circuit, failure recording). The channel/FCM layer below
 * it is covered separately by PushBroadcasterTest, so PushBroadcaster is a
 * mock here.
 */
class BroadcastPushNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    private function log(array $attributes = []): PushNotificationLog
    {
        return PushNotificationLog::factory()->create($attributes);
    }

    private function registerTokens(string ...$tokens): void
    {
        $user = User::factory()->create();

        foreach ($tokens as $token) {
            DeviceToken::create(['user_id' => $user->id, 'token' => $token, 'platform' => 'android']);
        }
    }

    public function test_it_marks_the_log_sent_with_the_reported_counts(): void
    {
        config(['push.enabled' => true]);
        $this->registerTokens('token-a', 'token-b', 'token-c');

        $log = $this->log(['title' => 'Nueva noticia', 'body' => 'Mirá esto']);

        $broadcaster = $this->createMock(PushBroadcaster::class);
        $broadcaster->expects($this->once())
            ->method('broadcast')
            ->willReturn(new BroadcastResult(successes: 2, failures: 1));

        (new BroadcastPushNotification($log->id))->handle($broadcaster);

        $log->refresh();
        $this->assertSame(PushNotificationLog::STATUS_SENT, $log->status);
        $this->assertSame(3, $log->recipients_count);
        $this->assertSame(2, $log->success_count);
        $this->assertSame(1, $log->failure_count);
        $this->assertNotNull($log->sent_at);
    }

    public function test_it_broadcasts_the_message_stored_on_the_log_row(): void
    {
        config(['push.enabled' => true]);
        $this->registerTokens('token-a');

        $log = $this->log([
            'title' => 'Curso nuevo',
            'body'  => 'Ya está disponible',
            'data'  => ['route' => '/cursos/bridal'],
        ]);

        $broadcaster = $this->createMock(PushBroadcaster::class);
        $broadcaster->expects($this->once())
            ->method('broadcast')
            ->with(
                $this->callback(fn ($notification): bool => $notification->title === 'Curso nuevo'
                    && $notification->body === 'Ya está disponible'
                    && $notification->data === ['route' => '/cursos/bridal']),
                $this->identicalTo(['token-a']),
            )
            ->willReturn(BroadcastResult::empty());

        (new BroadcastPushNotification($log->id))->handle($broadcaster);
    }

    /**
     * Defence in depth: the flag can be switched off while jobs are already
     * queued. Resolving the Firebase client would then throw for missing
     * credentials, so the job must never reach the broadcaster.
     */
    public function test_it_skips_without_broadcasting_when_push_is_disabled(): void
    {
        config(['push.enabled' => false]);
        $this->registerTokens('token-a');

        $log = $this->log();

        $broadcaster = $this->createMock(PushBroadcaster::class);
        $broadcaster->expects($this->never())->method('broadcast');

        (new BroadcastPushNotification($log->id))->handle($broadcaster);

        $log->refresh();
        $this->assertSame(PushNotificationLog::STATUS_SKIPPED, $log->status);
        $this->assertSame(0, $log->success_count);
    }

    public function test_it_records_failure_and_rethrows_when_the_broadcast_throws(): void
    {
        config(['push.enabled' => true]);
        $this->registerTokens('token-a', 'token-b');

        $log = $this->log();

        $broadcaster = $this->createMock(PushBroadcaster::class);
        $broadcaster->method('broadcast')->willThrowException(new RuntimeException('credentials missing'));

        // Re-thrown so the queue's retry and failed_jobs handling still apply —
        // the log update must not swallow the failure.
        $this->expectException(RuntimeException::class);

        try {
            (new BroadcastPushNotification($log->id))->handle($broadcaster);
        } finally {
            $log->refresh();
            $this->assertSame(PushNotificationLog::STATUS_FAILED, $log->status);
            $this->assertSame(2, $log->recipients_count);
            $this->assertNotNull($log->sent_at);
        }
    }

    public function test_it_is_a_no_op_when_the_log_row_no_longer_exists(): void
    {
        config(['push.enabled' => true]);

        $broadcaster = $this->createMock(PushBroadcaster::class);
        $broadcaster->expects($this->never())->method('broadcast');

        (new BroadcastPushNotification(999_999))->handle($broadcaster);
    }

    public function test_it_sends_to_every_registered_device_across_all_users(): void
    {
        config(['push.enabled' => true]);
        $this->registerTokens('user-one-phone');
        $this->registerTokens('user-two-phone', 'user-two-tablet');

        $log = $this->log();

        $captured = null;
        $broadcaster = $this->createMock(PushBroadcaster::class);
        $broadcaster->method('broadcast')
            ->willReturnCallback(function ($notification, array $tokens) use (&$captured): BroadcastResult {
                $captured = $tokens;

                return BroadcastResult::empty();
            });

        (new BroadcastPushNotification($log->id))->handle($broadcaster);

        sort($captured);
        $this->assertSame(['user-one-phone', 'user-two-phone', 'user-two-tablet'], $captured);
    }
}
