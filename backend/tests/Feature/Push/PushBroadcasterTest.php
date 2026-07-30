<?php

namespace Tests\Feature\Push;

use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\PushBroadcast;
use App\Services\Push\PushBroadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport;
use NotificationChannels\Fcm\FcmMessage;
use Tests\TestCase;

/**
 * PushBroadcasterTest — push-notifications Slice 1.
 *
 * Binds a mock Kreait\Firebase\Contract\Messaging in the container so the real
 * FcmChannel runs unmodified (chunking, report handling, NotificationFailed
 * dispatch) with no network call — the same technique as the pre-existing
 * tests/Feature/Notifications/FcmChannelResolutionTest.php.
 *
 * Notification::fake() is deliberately NOT used anywhere in this file: it
 * replaces the ChannelManager wholesale, which would skip the very channel
 * behaviour this class depends on.
 */
class PushBroadcasterTest extends TestCase
{
    use RefreshDatabase;

    private function target(string $token): MessageTarget
    {
        return MessageTarget::with(MessageTarget::TOKEN, $token);
    }

    public function test_it_reports_success_and_failure_counts_from_the_fcm_report(): void
    {
        $messaging = $this->createMock(Messaging::class);
        $messaging->expects($this->once())
            ->method('sendMulticast')
            ->willReturn(MulticastSendReport::withItems([
                SendReport::success($this->target('token-a'), []),
                SendReport::success($this->target('token-b'), []),
                SendReport::failure($this->target('token-c'), new NotFound('gone')),
            ]));

        $this->app->instance(Messaging::class, $messaging);

        $result = $this->app->make(PushBroadcaster::class)->broadcast(
            new PushBroadcast('Title', 'Body'),
            ['token-a', 'token-b', 'token-c'],
        );

        $this->assertSame(2, $result->successes);
        $this->assertSame(1, $result->failures);
    }

    public function test_it_sends_nothing_and_reports_zero_when_there_are_no_tokens(): void
    {
        $messaging = $this->createMock(Messaging::class);
        $messaging->expects($this->never())->method('sendMulticast');

        $this->app->instance(Messaging::class, $messaging);

        $result = $this->app->make(PushBroadcaster::class)->broadcast(
            new PushBroadcast('Title', 'Body'),
            [],
        );

        $this->assertSame(0, $result->successes);
        $this->assertSame(0, $result->failures);
    }

    /**
     * The channel chunks at TOKENS_PER_REQUEST = 500, so 501 tokens must
     * produce exactly two multicast requests and the counts must aggregate
     * across both. This pins the assumption the whole design rests on: that
     * this feature never needs its own batching code.
     */
    public function test_it_aggregates_counts_across_the_channels_500_token_chunks(): void
    {
        $tokens = array_map(static fn (int $i): string => "token-{$i}", range(1, 501));

        $messaging = $this->createMock(Messaging::class);
        $messaging->expects($this->exactly(2))
            ->method('sendMulticast')
            ->willReturnCallback(function (mixed $message, array $chunk): MulticastSendReport {
                return MulticastSendReport::withItems(
                    array_map(
                        fn (string $token): SendReport => SendReport::success($this->target($token), []),
                        $chunk,
                    ),
                );
            });

        $this->app->instance(Messaging::class, $messaging);

        $result = $this->app->make(PushBroadcaster::class)->broadcast(
            new PushBroadcast('Title', 'Body'),
            $tokens,
        );

        $this->assertSame(501, $result->successes);
        $this->assertSame(0, $result->failures);
    }

    /**
     * Broadcasts must keep the pre-existing dead-token cleanup working. The
     * channel dispatches NotificationFailed per failed token and
     * App\Listeners\InvalidateFcmDeviceToken deletes the row — with no code in
     * this feature doing so. If that ever stops holding, the token table grows
     * unbounded with uninstalled devices.
     */
    public function test_a_token_fcm_rejects_as_unknown_is_deleted_by_the_existing_listener(): void
    {
        $user = User::factory()->create();

        DeviceToken::create(['user_id' => $user->id, 'token' => 'live-token', 'platform' => 'android']);
        DeviceToken::create(['user_id' => $user->id, 'token' => 'dead-token', 'platform' => 'android']);

        $messaging = $this->createMock(Messaging::class);
        $messaging->method('sendMulticast')->willReturn(MulticastSendReport::withItems([
            SendReport::success($this->target('live-token'), []),
            // NotFound is what FCM returns for an unregistered token; it is
            // what SendReport::messageWasSentToUnknownToken() keys on.
            SendReport::failure($this->target('dead-token'), new NotFound('Requested entity was not found.')),
        ]));

        $this->app->instance(Messaging::class, $messaging);

        $this->app->make(PushBroadcaster::class)->broadcast(
            new PushBroadcast('Title', 'Body'),
            ['live-token', 'dead-token'],
        );

        $this->assertDatabaseHas('device_tokens', ['token' => 'live-token']);
        $this->assertDatabaseMissing('device_tokens', ['token' => 'dead-token']);
    }

    /**
     * FCM rejects a data payload containing non-string values with an opaque
     * API error, so PushBroadcast casts every value on the way out.
     */
    public function test_it_stringifies_every_data_payload_value(): void
    {
        $captured = null;

        $messaging = $this->createMock(Messaging::class);
        $messaging->method('sendMulticast')
            ->willReturnCallback(function (FcmMessage $message) use (&$captured): MulticastSendReport {
                $captured = $message->data;

                return MulticastSendReport::withItems([]);
            });

        $this->app->instance(Messaging::class, $messaging);

        $this->app->make(PushBroadcaster::class)->broadcast(
            new PushBroadcast('Title', 'Body', ['route' => '/noticias/x', 'post_id' => 42]),
            ['token-a'],
        );

        $this->assertSame(['route' => '/noticias/x', 'post_id' => '42'], $captured);
    }
}
