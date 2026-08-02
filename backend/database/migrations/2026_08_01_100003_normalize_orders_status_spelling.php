<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalizes `orders.status` to the single documented spelling `canceled`
 * (design D5). `CheckoutController` and `ReleaseExpiredReservations` already
 * write `canceled`; only `Admin\AppointmentController::cancel()` wrote the
 * two-L `cancelled` spelling. That write site is corrected in this same PR1a
 * batch (see apply-progress for the "ONE exception" rationale) alongside the
 * `Order::STATUSES` write-time guard, which rejects `cancelled` on an order
 * going forward.
 *
 * A single portable UPDATE — no driver-specific SQL needed for a literal
 * string comparison.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')->where('status', 'cancelled')->update(['status' => 'canceled']);
    }

    public function down(): void
    {
        // Intentionally a no-op: the two spellings meant the same business
        // state, so there is nothing meaningful to reverse. Re-introducing
        // the 'cancelled' spelling would just reintroduce the bug this
        // migration exists to fix.
    }
};
