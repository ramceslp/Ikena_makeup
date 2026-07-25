<?php

namespace Tests\Unit\Models;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DeviceTokenTest — mobile-capacitor-setup PR3, task 3.2-3.3.
 *
 * Covers the `device_tokens` table/model (design Decision 2:
 * sdd/mobile-capacitor-setup/design.md): user_id FK, unique token,
 * platform, nullable last_used_at.
 */
class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_device_token_for_a_user(): void
    {
        $user = User::factory()->create();

        $token = DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'fcm-token-abc123',
            'platform' => 'android',
        ]);

        $this->assertDatabaseHas('device_tokens', [
            'id' => $token->id,
            'user_id' => $user->id,
            'token' => 'fcm-token-abc123',
            'platform' => 'android',
        ]);
        $this->assertNull($token->last_used_at);
    }

    public function test_token_column_is_unique(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        DeviceToken::create([
            'user_id' => $userA->id,
            'token' => 'duplicate-token',
            'platform' => 'ios',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DeviceToken::create([
            'user_id' => $userB->id,
            'token' => 'duplicate-token',
            'platform' => 'ios',
        ]);
    }

    public function test_last_used_at_is_cast_to_datetime(): void
    {
        $user = User::factory()->create();

        $token = DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'fcm-token-def456',
            'platform' => 'android',
            'last_used_at' => now(),
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $token->last_used_at);
    }

    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();

        $token = DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'fcm-token-ghi789',
            'platform' => 'ios',
        ]);

        $this->assertTrue($token->user->is($user));
    }

    public function test_user_has_many_device_tokens(): void
    {
        $user = User::factory()->create();

        DeviceToken::create(['user_id' => $user->id, 'token' => 'tok-1', 'platform' => 'android']);
        DeviceToken::create(['user_id' => $user->id, 'token' => 'tok-2', 'platform' => 'ios']);

        $this->assertCount(2, $user->deviceTokens);
    }

    public function test_route_notification_for_fcm_returns_all_token_strings(): void
    {
        $user = User::factory()->create();

        DeviceToken::create(['user_id' => $user->id, 'token' => 'tok-a', 'platform' => 'android']);
        DeviceToken::create(['user_id' => $user->id, 'token' => 'tok-b', 'platform' => 'ios']);

        $tokens = $user->routeNotificationForFcm();

        $this->assertEqualsCanonicalizing(['tok-a', 'tok-b'], $tokens);
    }
}
