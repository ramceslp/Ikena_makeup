<?php

namespace Tests\Feature\DeviceTokens;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * DeviceTokenControllerTest — mobile-capacitor-setup PR3, tasks 3.4-3.6.
 *
 * Tests POST /api/device-tokens (auth, idempotent upsert) and
 * DELETE /api/device-tokens (auth, removes matching row) — design Decision 2
 * (sdd/mobile-capacitor-setup/design.md), spec "Push Notifications"
 * (sdd/mobile-capacitor-setup/spec.md).
 */
class DeviceTokenControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // POST /api/device-tokens
    // -------------------------------------------------------------------------

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/device-tokens', [
            'token' => 'fcm-token-abc',
            'platform' => 'android',
        ])->assertStatus(401);
    }

    public function test_store_registers_a_new_device_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/device-tokens', [
            'token' => 'fcm-token-abc',
            'platform' => 'android',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-token-abc',
            'platform' => 'android',
        ]);
        $this->assertDatabaseCount('device_tokens', 1);
    }

    public function test_store_is_idempotent_on_token_and_updates_last_used_at(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/device-tokens', [
            'token' => 'fcm-token-abc',
            'platform' => 'android',
        ])->assertStatus(200);

        $this->postJson('/api/device-tokens', [
            'token' => 'fcm-token-abc',
            'platform' => 'android',
        ])->assertStatus(200);

        // Only one row for this token — the second call updated, not duplicated.
        $this->assertDatabaseCount('device_tokens', 1);

        $token = DeviceToken::first();
        $this->assertNotNull($token->last_used_at);
    }

    public function test_store_reassigns_token_to_the_current_user_if_previously_registered_by_another(): void
    {
        $previousOwner = User::factory()->create();
        DeviceToken::create([
            'user_id' => $previousOwner->id,
            'token' => 'shared-device-token',
            'platform' => 'ios',
        ]);

        $newOwner = User::factory()->create();
        Sanctum::actingAs($newOwner);

        $this->postJson('/api/device-tokens', [
            'token' => 'shared-device-token',
            'platform' => 'ios',
        ])->assertStatus(200);

        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', [
            'token' => 'shared-device-token',
            'user_id' => $newOwner->id,
        ]);
    }

    public function test_store_requires_token_and_valid_platform(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/device-tokens', ['platform' => 'android'])
            ->assertStatus(422);

        $this->postJson('/api/device-tokens', ['token' => 'abc', 'platform' => 'windows'])
            ->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/device-tokens
    // -------------------------------------------------------------------------

    public function test_destroy_requires_authentication(): void
    {
        $this->deleteJson('/api/device-tokens', ['token' => 'fcm-token-abc'])
            ->assertStatus(401);
    }

    public function test_destroy_removes_matching_row_for_the_current_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'fcm-token-abc',
            'platform' => 'android',
        ]);

        $this->deleteJson('/api/device-tokens', ['token' => 'fcm-token-abc'])
            ->assertStatus(200);

        $this->assertDatabaseMissing('device_tokens', ['token' => 'fcm-token-abc']);
    }

    public function test_destroy_does_not_remove_another_users_token(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        DeviceToken::create([
            'user_id' => $owner->id,
            'token' => 'owners-token',
            'platform' => 'android',
        ]);

        Sanctum::actingAs($attacker);

        $this->deleteJson('/api/device-tokens', ['token' => 'owners-token'])
            ->assertStatus(200);

        // Still there — the delete only scopes to the authenticated user's own rows.
        $this->assertDatabaseHas('device_tokens', ['token' => 'owners-token', 'user_id' => $owner->id]);
    }

    public function test_destroy_is_a_no_op_when_token_does_not_exist(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->deleteJson('/api/device-tokens', ['token' => 'does-not-exist'])
            ->assertStatus(200);
    }
}
