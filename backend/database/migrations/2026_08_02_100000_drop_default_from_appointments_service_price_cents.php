<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the bridging DEFAULT 0 that
 * 2026_08_01_100000_add_settlement_columns_to_appointments.php put on
 * `appointments.service_price_cents` (PR1a).
 *
 * That default existed for exactly one reason: `CreateBookingAction` did not
 * yet write the price snapshot, so a real "NOT NULL, no default" column would
 * have made every `/api/bookings` POST fail with a 500 the moment PR1a
 * shipped. PR1b wires that write (see `CreateBookingAction::__invoke()`), so
 * the reason for the bridge is gone — carrying it forward would leave the
 * column "required but silently creatable at zero", which is worse than
 * either being genuinely required or genuinely optional. Recorded as
 * carried-debt item 1 in architecture/admin-reports-pr-budget.
 *
 * A separate migration rather than editing PR1a's: that migration already
 * merged to the tracker branch, and edited migrations that already ran in
 * other environments are a correctness hazard (Laravel tracks migrations by
 * file name + batch, not by content — editing an already-run migration does
 * not get re-applied anywhere it already ran).
 *
 * `->change()` needs no data migration here: `CreateBookingAction` (this same
 * PR) now supplies `service_price_cents` on every new row, and the PR1a
 * migration already backfilled every pre-existing row from `services.price`.
 * Dropping the default does not touch already-stored values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Redefining without ->default(...) removes the DEFAULT 0 clause.
            // Omitting ->nullable() keeps the column NOT NULL, matching PR1a's
            // original constraint — only the default is being dropped here.
            $table->unsignedInteger('service_price_cents')->change();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Restores the PR1a bridging default, for symmetry with rollback.
            $table->unsignedInteger('service_price_cents')->default(0)->change();
        });
    }
};
