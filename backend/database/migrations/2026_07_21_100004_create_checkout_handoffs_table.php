<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create `checkout_handoffs` — single-use snapshot rows backing the
 * mobile-app -> web checkout-handoff flow (mobile-capacitor-setup PR2,
 * design Decision 1: sdd/mobile-capacitor-setup/design.md).
 *
 * `token_hash` stores only a SHA-256 hash of the opaque 40-char plaintext
 * token handed to the app — the plaintext is never persisted. `payload`
 * snapshots the cart items or booking selection at handoff time; redeem
 * re-validates it against live product/service state rather than trusting
 * it verbatim. `expires_at` + `consumed_at` enforce the short-lived,
 * single-use contract (10-minute expiry, atomic consume-once claim).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_handoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('type', 20); // 'product_cart' | 'appointment'
            $table->string('token_hash')->unique();
            $table->json('payload');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_handoffs');
    }
};
