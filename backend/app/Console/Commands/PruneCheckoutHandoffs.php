<?php

namespace App\Console\Commands;

use App\Models\CheckoutHandoff;
use Illuminate\Console\Command;

/**
 * checkout-handoffs:prune — delete expired or already-consumed
 * `checkout_handoffs` rows (mobile-capacitor-setup PR2, design's
 * "A scheduled prune removes expired/consumed checkout_handoffs" note).
 *
 * Rows are pure snapshot leftovers once expired or redeemed: a consumed
 * row's real work (Order/Appointment creation) already happened inside
 * CheckoutHandoffController::redeem(), and an expired-but-unconsumed row
 * can never be redeemed. Deleting both is safe — nothing downstream
 * references a checkout_handoffs row by id.
 */
class PruneCheckoutHandoffs extends Command
{
    protected $signature = 'checkout-handoffs:prune';

    protected $description = 'Delete expired or already-consumed checkout_handoffs rows';

    public function handle(): int
    {
        $count = CheckoutHandoff::query()
            ->where(function ($query) {
                $query->where('expires_at', '<', now())
                    ->orWhereNotNull('consumed_at');
            })
            ->delete();

        $this->info("Pruned {$count} checkout handoff row(s).");

        return self::SUCCESS;
    }
}
