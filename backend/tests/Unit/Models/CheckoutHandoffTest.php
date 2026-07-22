<?php

namespace Tests\Unit\Models;

use App\Models\CheckoutHandoff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CheckoutHandoffTest
 *
 * Covers the model's hashed-token lookup scope and expiry/consumed helpers
 * (mobile-capacitor-setup PR2, tasks 2.1-2.2).
 */
class CheckoutHandoffTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    public function test_by_token_scope_finds_row_by_hashed_plaintext_token(): void
    {
        $user = $this->makeUser();
        $plaintext = 'a-known-plaintext-token-value-0123456789';

        $handoff = CheckoutHandoff::create([
            'user_id' => $user->id,
            'type' => 'product_cart',
            'token_hash' => hash('sha256', $plaintext),
            'payload' => ['items' => []],
            'expires_at' => now()->addMinutes(10),
        ]);

        $found = CheckoutHandoff::query()->byToken($plaintext)->first();

        $this->assertNotNull($found);
        $this->assertSame($handoff->id, $found->id);

        // Plaintext must never be persisted anywhere queryable.
        $this->assertNotSame($plaintext, $found->token_hash);
    }

    public function test_by_token_scope_returns_null_for_wrong_token(): void
    {
        $user = $this->makeUser();

        CheckoutHandoff::create([
            'user_id' => $user->id,
            'type' => 'product_cart',
            'token_hash' => hash('sha256', 'correct-token'),
            'payload' => ['items' => []],
            'expires_at' => now()->addMinutes(10),
        ]);

        $found = CheckoutHandoff::query()->byToken('wrong-token')->first();

        $this->assertNull($found);
    }

    public function test_is_expired_and_is_consumed_helpers(): void
    {
        $user = $this->makeUser();

        $expired = CheckoutHandoff::create([
            'user_id' => $user->id,
            'type' => 'product_cart',
            'token_hash' => hash('sha256', 'token-a'),
            'payload' => ['items' => []],
            'expires_at' => now()->subMinute(),
        ]);

        $consumed = CheckoutHandoff::create([
            'user_id' => $user->id,
            'type' => 'product_cart',
            'token_hash' => hash('sha256', 'token-b'),
            'payload' => ['items' => []],
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => now(),
        ]);

        $this->assertTrue($expired->isExpired());
        $this->assertFalse($expired->isConsumed());

        $this->assertFalse($consumed->isExpired());
        $this->assertTrue($consumed->isConsumed());
    }

    public function test_payload_is_cast_to_array_and_type_is_persisted(): void
    {
        $user = $this->makeUser();

        $handoff = CheckoutHandoff::create([
            'user_id' => $user->id,
            'type' => 'appointment',
            'token_hash' => hash('sha256', 'token-c'),
            'payload' => [
                'service_id' => 5,
                'scheduled_date' => '2026-08-01',
                'scheduled_time' => '14:00',
                'whatsapp' => '+593999999999',
            ],
            'expires_at' => now()->addMinutes(10),
        ]);

        $handoff->refresh();

        $this->assertIsArray($handoff->payload);
        $this->assertSame(5, $handoff->payload['service_id']);
        $this->assertSame('appointment', $handoff->type);
    }
}
