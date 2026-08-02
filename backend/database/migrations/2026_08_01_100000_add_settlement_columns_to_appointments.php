<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the two write-once money channels design D1 introduces on `appointments`:
 *
 *  - `service_price_cents`      price snapshot taken at booking time. NOT NULL —
 *    per the spec/design reconciliation (architecture/admin-reports-design-adjustments,
 *    adjustment #3), this is a real DB constraint, not the design's original
 *    nullable-plus-model-guard. There is no production data yet, so the stronger
 *    constraint is reachable, and it also covers raw UPDATEs/seeders that a
 *    model-level `saving` guard cannot see.
 *  - `deposit_collected_cents`  money the GATEWAY actually captured (never the
 *    quoted `deposit_amount_cents`, which stays a quote-only column — see the
 *    docblock added to `deposit_amount_cents` usage sites for the anti-double-
 *    count rationale).
 *  - `deposit_collected_at`     revenue anchor for the deposit stream.
 *  - `settled_amount_cents`     money collected IN PERSON at settlement.
 *  - `settled_at`               revenue anchor for the settlement stream.
 *
 * Bridging default on `service_price_cents`: this migration ships in PR1a
 * (schema + money invariants only). The write path that always supplies this
 * snapshot (`CreateBookingAction`) is out of scope for PR1a and lands in PR1b.
 * A DEFAULT of 0 lets `CreateBookingAction` keep creating appointments without
 * a schema-level failure in the interim — the value is corrected the moment
 * PR1b wires the snapshot write. This is a deliberate, temporary relaxation of
 * "NOT NULL, no default" recorded here (and in the PR1a apply-progress) so it
 * is not mistaken for an oversight; nothing reads `service_price_cents` for
 * money before the Reports layer exists (PR2a+), so a transient 0 is inert.
 *
 * No production backfill is needed for `service_price_cents` beyond the
 * migration itself: there is no production data yet (confirmed in design
 * "Migration / Rollout"). The `chunkById` backfill below is defensive — it
 * makes the column correct for any rows that exist in a given environment
 * (e.g. a shared staging DB) without depending on that assumption forever.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedInteger('service_price_cents')->default(0)->after('scheduled_end_time');
            $table->unsignedInteger('deposit_collected_cents')->default(0)->after('deposit_amount_cents');
            $table->timestamp('deposit_collected_at')->nullable()->after('deposit_collected_cents');
            $table->unsignedInteger('settled_amount_cents')->nullable()->after('deposit_collected_at');
            $table->timestamp('settled_at')->nullable()->after('settled_amount_cents');

            $table->index('deposit_collected_at');
            $table->index('settled_at');
        });

        // Backfill service_price_cents from services.price for any pre-existing
        // rows (defensive — see docblock above). chunkById keeps this portable
        // across SQLite/MySQL and bounded in memory regardless of table size.
        DB::table('appointments')->orderBy('id')->chunkById(200, function ($appointments) {
            foreach ($appointments as $appointment) {
                $price = DB::table('services')->where('id', $appointment->service_id)->value('price');

                DB::table('appointments')
                    ->where('id', $appointment->id)
                    ->update([
                        'service_price_cents' => $price !== null ? (int) round((float) $price * 100) : 0,
                    ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn([
                'service_price_cents',
                'deposit_collected_cents',
                'deposit_collected_at',
                'settled_amount_cents',
                'settled_at',
            ]);
        });
    }
};
