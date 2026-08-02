<?php

namespace Tests\Feature\Notifications;

use App\Models\Appointment;
use App\Models\DeviceToken;
use App\Models\Service;
use App\Models\User;
use App\Notifications\BookingConfirmed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Tests\TestCase;

/**
 * FcmChannelResolutionTest — mobile-capacitor-setup PR3, Judgment Day Round 2
 * regression coverage.
 *
 * Every other push-notification test (BookingConfirmedNotificationTest,
 * SendAppointmentRemindersTest) uses Notification::fake(), which replaces
 * Laravel's ChannelManager entirely and never resolves a real notification
 * channel/driver. That means two Round 1 CRITICAL fixes had zero regression
 * coverage:
 *
 *  - BookingConfirmed::via() must return
 *    [\NotificationChannels\Fcm\FcmChannel::class] (a real, resolvable
 *    channel class) — NOT the bare string 'fcm', which Laravel's
 *    ChannelManager cannot resolve to a driver ("Driver [fcm] not
 *    supported.").
 *  - User::routeNotificationForFcm() must correctly return the user's
 *    registered device_tokens.
 *
 * This test exercises the REAL channel-resolution path (no
 * Notification::fake()) by binding a fake Kreait\Firebase\Contract\Messaging
 * implementation in the container, so no real network call to Firebase
 * happens, while every other layer (ChannelManager -> FcmChannel ->
 * routeNotificationForFcm()) runs for real.
 */
class FcmChannelResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_confirmed_resolves_the_real_fcm_channel_and_routes_registered_tokens(): void
    {
        $user = User::factory()->create();

        $tokenOne = DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'device-token-one',
            'platform' => 'android',
        ]);

        $tokenTwo = DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'device-token-two',
            'platform' => 'ios',
        ]);

        $service = Service::factory()->create([
            'availability_type' => 'by_appointment',
            'is_published' => true,
            'price' => 100.00,
            'deposit_percentage' => 30,
            'duration_hours' => 1,
        ]);

        $appointment = Appointment::create([
            'service_id' => $service->id,
            'user_id' => $user->id,
            'order_id' => null,
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00',
            'scheduled_end_time' => '11:00',
            'slot_key' => null,
            'whatsapp' => '+593999999999',
            'payment_mode' => 'gateway',
            'deposit_amount_cents' => 3000,
            'service_price_cents' => (int) round((float) $service->price * 100),
            'status' => 'paid',
        ]);

        $expectedTokens = [$tokenOne->token, $tokenTwo->token];

        // Fake the Firebase Messaging client so no real network call happens,
        // while letting Laravel's real ChannelManager -> FcmChannel ->
        // routeNotificationForFcm() path run unmodified.
        $messaging = $this->createMock(Messaging::class);
        $messaging->expects($this->once())
            ->method('sendMulticast')
            ->with(
                $this->anything(),
                $this->callback(fn (array $tokens): bool => $tokens === $expectedTokens),
            )
            ->willReturn(MulticastSendReport::withItems([]));

        $this->app->instance(Messaging::class, $messaging);

        // Real send — Notification::fake() is intentionally NOT used here.
        // If BookingConfirmed::via() ever regresses to the bare string 'fcm'
        // this throws InvalidArgumentException: Driver [fcm] not supported.
        Notification::send($user, new BookingConfirmed($appointment));
    }
}
