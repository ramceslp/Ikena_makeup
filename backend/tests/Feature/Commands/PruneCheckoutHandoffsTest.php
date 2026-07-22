<?php

namespace Tests\Feature\Commands;

use App\Models\CheckoutHandoff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PruneCheckoutHandoffsTest
 *
 * Tests the checkout-handoffs:prune artisan command (mobile-capacitor-setup
 * PR2, task 2.12; design's "A scheduled prune removes expired/consumed
 * checkout_handoffs" note).
 */
class PruneCheckoutHandoffsTest extends TestCase
{
    use RefreshDatabase;

    private function makeHandoff(array $overrides = []): CheckoutHandoff
    {
        $user = User::factory()->create();

        return CheckoutHandoff::create(array_merge([
            'user_id' => $user->id,
            'type' => 'product_cart',
            'token_hash' => hash('sha256', 'token-'.Str::random(16)),
            'payload' => ['items' => []],
            'expires_at' => now()->addMinutes(10),
        ], $overrides));
    }

    public function test_prunes_expired_unconsumed_row(): void
    {
        $expired = $this->makeHandoff(['expires_at' => now()->subMinutes(5)]);

        $this->artisan('checkout-handoffs:prune')->assertExitCode(0);

        $this->assertDatabaseMissing('checkout_handoffs', ['id' => $expired->id]);
    }

    public function test_prunes_consumed_row_even_if_not_yet_expired(): void
    {
        $consumed = $this->makeHandoff([
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => now(),
        ]);

        $this->artisan('checkout-handoffs:prune')->assertExitCode(0);

        $this->assertDatabaseMissing('checkout_handoffs', ['id' => $consumed->id]);
    }

    public function test_leaves_unconsumed_unexpired_row_alone(): void
    {
        $active = $this->makeHandoff(['expires_at' => now()->addMinutes(10)]);

        $this->artisan('checkout-handoffs:prune')->assertExitCode(0);

        $this->assertDatabaseHas('checkout_handoffs', ['id' => $active->id]);
    }

    public function test_prunes_multiple_rows_in_one_run(): void
    {
        $expiredA = $this->makeHandoff(['expires_at' => now()->subMinutes(20)]);
        $expiredB = $this->makeHandoff(['expires_at' => now()->subMinutes(30)]);
        $active = $this->makeHandoff(['expires_at' => now()->addMinutes(5)]);

        $this->artisan('checkout-handoffs:prune')->assertExitCode(0);

        $this->assertDatabaseMissing('checkout_handoffs', ['id' => $expiredA->id]);
        $this->assertDatabaseMissing('checkout_handoffs', ['id' => $expiredB->id]);
        $this->assertDatabaseHas('checkout_handoffs', ['id' => $active->id]);
    }
}
