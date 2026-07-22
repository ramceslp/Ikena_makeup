<?php

namespace Tests\Unit\Listeners;

use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\BookingConfirmed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationFailed;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport;
use Tests\TestCase;

/**
 * InvalidateFcmDeviceTokenTest — mobile-capacitor-setup PR3, task 3.9.
 *
 * Verifies App\Listeners\InvalidateFcmDeviceToken deletes the corresponding
 * device_tokens row when FCM reports a token as NotRegistered (unknown) or
 * InvalidToken (design Decision 2: sdd/mobile-capacitor-setup/design.md,
 * "Invalidation: when FCM returns NotRegistered/InvalidToken, delete the
 * row.").
 *
 * Dispatches the REAL Illuminate\Notifications\Events\NotificationFailed
 * event (via the `event()` helper, not calling the listener class directly)
 * so this also proves Laravel's auto-discovery actually wires
 * InvalidateFcmDeviceToken up without any manual registration.
 */
class InvalidateFcmDeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    private function makeDeviceToken(string $token, string $platform = 'android'): DeviceToken
    {
        $user = User::factory()->create();

        return DeviceToken::create([
            'user_id' => $user->id,
            'token' => $token,
            'platform' => $platform,
        ]);
    }

    private function tokenTarget(string $token): MessageTarget
    {
        return MessageTarget::with(MessageTarget::TOKEN, $token);
    }

    public function test_deletes_device_token_when_fcm_reports_not_registered(): void
    {
        $deviceToken = $this->makeDeviceToken('unregistered-token');

        $report = MulticastSendReport::withItems([
            SendReport::failure(
                $this->tokenTarget('unregistered-token'),
                NotFound::becauseTokenNotFound('unregistered-token'),
            ),
        ]);

        event(new NotificationFailed(
            $deviceToken->user,
            new BookingConfirmed($this->makeUnrelatedAppointment()),
            'fcm',
            ['report' => $report],
        ));

        $this->assertDatabaseMissing('device_tokens', ['id' => $deviceToken->id]);
    }

    public function test_deletes_device_token_when_fcm_reports_invalid_token(): void
    {
        $deviceToken = $this->makeDeviceToken('malformed-token');

        $report = MulticastSendReport::withItems([
            SendReport::failure(
                $this->tokenTarget('malformed-token'),
                new InvalidArgument('The registration token is not a valid FCM registration token.'),
            ),
        ]);

        event(new NotificationFailed(
            $deviceToken->user,
            new BookingConfirmed($this->makeUnrelatedAppointment()),
            'fcm',
            ['report' => $report],
        ));

        $this->assertDatabaseMissing('device_tokens', ['id' => $deviceToken->id]);
    }

    public function test_leaves_other_tokens_untouched_on_partial_multicast_failure(): void
    {
        $failing = $this->makeDeviceToken('failing-token');
        $succeeding = $this->makeDeviceToken('succeeding-token');

        $report = MulticastSendReport::withItems([
            SendReport::success($this->tokenTarget('succeeding-token'), ['name' => 'projects/x/messages/1']),
            SendReport::failure(
                $this->tokenTarget('failing-token'),
                NotFound::becauseTokenNotFound('failing-token'),
            ),
        ]);

        event(new NotificationFailed(
            $failing->user,
            new BookingConfirmed($this->makeUnrelatedAppointment()),
            'fcm',
            ['report' => $report],
        ));

        $this->assertDatabaseMissing('device_tokens', ['id' => $failing->id]);
        $this->assertDatabaseHas('device_tokens', ['id' => $succeeding->id]);
    }

    public function test_ignores_failures_on_channels_other_than_fcm(): void
    {
        $deviceToken = $this->makeDeviceToken('some-token');

        $report = MulticastSendReport::withItems([
            SendReport::failure(
                $this->tokenTarget('some-token'),
                NotFound::becauseTokenNotFound('some-token'),
            ),
        ]);

        event(new NotificationFailed(
            $deviceToken->user,
            new BookingConfirmed($this->makeUnrelatedAppointment()),
            'mail',
            ['report' => $report],
        ));

        $this->assertDatabaseHas('device_tokens', ['id' => $deviceToken->id]);
    }

    /**
     * BookingConfirmed requires an Appointment instance in its constructor —
     * these tests only exercise the failure-report/token side, so a plain
     * in-memory (never persisted) Appointment is enough.
     */
    private function makeUnrelatedAppointment(): \App\Models\Appointment
    {
        return new \App\Models\Appointment([
            'scheduled_date' => now()->toDateString(),
            'scheduled_time' => '10:00',
        ]);
    }
}
