<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create `device_tokens` — one row per registered FCM registration token
 * (mobile-capacitor-setup PR3, design Decision 2:
 * sdd/mobile-capacitor-setup/design.md).
 *
 * `token` is unique so `POST /api/device-tokens` can upsert idempotently on
 * it. `platform` is a plain string (not a native DB enum) constrained to
 * 'ios'|'android' at the validation layer — matching the project's existing
 * `checkout_handoffs.type` convention (see
 * 2026_07_21_100004_create_checkout_handoffs_table.php) for SQLite/MySQL
 * portability. `user_id` is required (not nullable) — v1 requires auth,
 * no pre-login tokens per the design.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('token')->unique();
            $table->string('platform', 10); // 'ios' | 'android'
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
