<?php

namespace App\Reports\Money;

use App\Models\Appointment;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

/**
 * RevenueStreams — the ONLY class in `app/Reports/` permitted to touch the
 * `orders` table for money (design D4). Enforced by two guards:
 *
 *  - `RevenueSourceIsolationTest::
 *     test_only_revenue_streams_may_reference_the_orders_table_or_order_model`
 *    statically scans every OTHER file in `app/Reports/` for `Order::`/
 *    `'orders'` references.
 *  - This class's own tests (`RevenueStreamsTest`) behaviourally pin what
 *    each stream reads.
 *
 * Appointment orders (`orders.type = 'appointment'`) are structurally
 * invisible here: `course_sale`/`product_sale` filter on `type`, and the
 * two appointment streams read `appointments`'s own write-once money
 * columns directly — `orders` is never joined for appointment money at all.
 * This is exactly the anti-double-count guarantee design D1 exists for:
 * there is no path through this class that can count the same collected
 * cent twice.
 *
 * Every `query()` returns a Builder ALREADY scoped to the right rows for
 * that stream, unfiltered by date — callers (Summary/Timeline/Composition
 * query objects) apply their own `[from, to)` bound against
 * `anchorColumn()` on top. This keeps the date-window logic (design D3 —
 * `PeriodCalendar`-driven, zero driver-specific SQL) entirely outside this
 * class, whose only job is "which rows, which amount column, which anchor
 * column."
 */
final class RevenueStreams
{
    public function query(StreamKey $stream): Builder
    {
        return match ($stream) {
            StreamKey::CourseSale => $this->courseSaleQuery(),
            StreamKey::ProductSale => $this->productSaleQuery(),
            StreamKey::AppointmentDeposit => $this->appointmentDepositQuery(),
            StreamKey::AppointmentDepositRetained => $this->appointmentDepositRetainedQuery(),
            StreamKey::AppointmentSettlement => $this->appointmentSettlementQuery(),
        };
    }

    /**
     * The money column each stream sums (design D4's "Amount" column).
     */
    public function amountColumn(StreamKey $stream): string
    {
        return match ($stream) {
            StreamKey::CourseSale, StreamKey::ProductSale => 'amount_cents',
            StreamKey::AppointmentDeposit, StreamKey::AppointmentDepositRetained => 'deposit_collected_cents',
            StreamKey::AppointmentSettlement => 'settled_amount_cents',
        };
    }

    /**
     * The timestamp column a stream's timeline anchors on (design D4's
     * "Time anchor" column) — NEVER `created_at` (spec: "Timelines anchor on
     * the stream's own time anchor, never created_at").
     */
    public function anchorColumn(StreamKey $stream): string
    {
        return match ($stream) {
            StreamKey::CourseSale, StreamKey::ProductSale => 'paid_at',
            StreamKey::AppointmentDeposit, StreamKey::AppointmentDepositRetained => 'deposit_collected_at',
            StreamKey::AppointmentSettlement => 'settled_at',
        };
    }

    // -------------------------------------------------------------------------
    // Stream queries
    // -------------------------------------------------------------------------

    private function courseSaleQuery(): Builder
    {
        return Order::query()
            ->where('type', 'course')
            ->where('status', 'paid')
            ->whereNotNull('paid_at');
    }

    private function productSaleQuery(): Builder
    {
        return Order::query()
            ->where('type', 'product_cart')
            ->where('status', 'paid')
            ->whereNotNull('paid_at');
    }

    /**
     * Deposit vs retained is a single classification (design D4: "one CASE
     * on appointments.status ... so the classification lives in one
     * place") expressed here as the SAME base predicate — `status`
     * (`Appointment::STATUSES`) — split into two Builder scopes rather than
     * a raw SQL CASE, so it stays ANSI-portable with zero driver-specific
     * SQL (design D3) while still living in exactly one method pair.
     */
    private function appointmentDepositQuery(): Builder
    {
        return Appointment::query()
            ->whereNotNull('deposit_collected_at')
            ->where('status', '!=', 'cancelled');
    }

    private function appointmentDepositRetainedQuery(): Builder
    {
        return Appointment::query()
            ->whereNotNull('deposit_collected_at')
            ->where('status', 'cancelled');
    }

    private function appointmentSettlementQuery(): Builder
    {
        // Every status — settlement is recorded once, write-once (design
        // D2), and remains real income regardless of what happens to the
        // appointment afterward. Filtered to rows that actually HAVE a
        // settlement recorded (Appointment::booted() pairs settled_at with
        // settled_amount_cents, so this is equivalent to filtering on
        // either column).
        return Appointment::query()->whereNotNull('settled_at');
    }
}
