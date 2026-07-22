<?php

namespace App\Console\Commands;

use App\Models\CheckoutHandoff;
use Illuminate\Console\Command;

/**
 * checkout-handoffs:prune — delete expired-and-unconsumed rows immediately,
 * and consumed rows after a 24-hour grace period (mobile-capacitor-setup
 * PR2, design's "A scheduled prune removes expired/consumed
 * checkout_handoffs" note).
 *
 * An expired-but-never-consumed row can never be redeemed, so it is pure
 * snapshot leftover and safe to delete right away. A consumed row is
 * different: fix #3 (CheckoutHandoffController::releaseClaim()) resets
 * consumed_at back to null when the post-claim Action call fails, so a
 * "consumed" row at any given instant may actually represent a customer's
 * still-live retry path, not necessarily a finished checkout. Even for a
 * genuinely finished checkout, deleting the row on the very next hourly run
 * destroys the only audit trail support/ops has for that redemption. The
 * 24-hour grace period keeps a short support/ops window without retaining
 * rows indefinitely. Nothing downstream references a checkout_handoffs row
 * by id once it is gone.
 */
class PruneCheckoutHandoffs extends Command
{
    protected $signature = 'checkout-handoffs:prune';

    protected $description = 'Delete expired-and-unconsumed rows immediately, and consumed rows after a 24-hour grace period';

    public function handle(): int
    {
        $count = CheckoutHandoff::query()
            ->where(function ($query) {
                $query->whereNull('consumed_at')
                    ->where('expires_at', '<', now());
            })
            ->orWhere(function ($query) {
                $query->whereNotNull('consumed_at')
                    ->where('consumed_at', '<', now()->subHours(24));
            })
            ->delete();

        $this->info("Pruned {$count} checkout handoff row(s).");

        return self::SUCCESS;
    }
}
