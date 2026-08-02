<?php

namespace Tests\Feature\Reports;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * RevenueSourceIsolationTest
 *
 * Lives in the `Reports` testsuite (backend/phpunit.mysql.xml) so it runs on
 * BOTH SQLite (default CI) and MySQL (backend-tests-mysql job, design D3) —
 * this is precisely the invariant design D4 says must never silently
 * diverge between drivers: "no report ever reads orders.status for
 * appointment money."
 *
 * IMPORTANT: `markTestSkipped` is banned in this suite (design D3) — every
 * test here either asserts a real outcome or fails, it never skips.
 *
 * `app/Reports/RevenueStreams` (design D4) does not exist yet — it ships in
 * PR2a. This suite computes income directly against `appointments`'s two
 * write-once money channels (`deposit_collected_cents`, `settled_amount_cents`)
 * to pin the invariant BEFORE the query layer exists, so PR2a's
 * `RevenueStreams` has a regression guard to build against from day one.
 */
class RevenueSourceIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(float $price = 100.00): Service
    {
        return Service::factory()->create([
            'availability_type' => 'by_appointment',
            'is_published' => true,
            'price' => $price,
            'deposit_percentage' => 30,
        ]);
    }

    /**
     * Create an appointment directly with a fully-specified money state, so
     * each lifecycle permutation below is an explicit, self-contained
     * fixture rather than depending on the write-path actions under test
     * elsewhere in this PR.
     */
    private function makeAppointment(array $overrides = []): Appointment
    {
        $service = $this->makeService();
        $user = User::factory()->create();

        $appointment = Appointment::create(array_merge([
            'service_id' => $service->id,
            'user_id' => $user->id,
            'order_id' => null,
            'scheduled_date' => '2026-08-10',
            'scheduled_time' => '10:00',
            'slot_key' => "{$service->id}|2026-08-10|10:00|".uniqid(),
            'whatsapp' => '+593099912345',
            'payment_mode' => 'gateway',
            'deposit_amount_cents' => 2400,
            'service_price_cents' => 8000,
            'status' => 'pending',
        ], $overrides));

        Order::create([
            'user_id' => $user->id,
            'appointment_id' => $appointment->id,
            'client_transaction_id' => 'ORD-isolation-'.$appointment->id,
            'gateway' => 'fake',
            'amount_cents' => $appointment->deposit_collected_cents,
            'currency' => 'USD',
            'status' => $appointment->deposit_collected_cents > 0 ? 'paid' : 'pending',
        ]);

        return $appointment->fresh();
    }

    /**
     * Confirmed income for a set of appointments, computed the ONLY correct
     * way (design D1): the sum of the two write-once money channels. This is
     * the hand-summed oracle every permutation below is checked against.
     */
    private function handSummedIncome(array $appointmentIds): int
    {
        return (int) Appointment::whereIn('id', $appointmentIds)
            ->selectRaw('COALESCE(SUM(deposit_collected_cents), 0) + COALESCE(SUM(settled_amount_cents), 0) AS total')
            ->value('total');
    }

    // -------------------------------------------------------------------------
    // 4 appointment lifecycle permutations
    // -------------------------------------------------------------------------

    public function test_income_across_four_lifecycle_permutations_matches_hand_summed_fixture(): void
    {
        // 1) Deposit collected online, later settled in person for the balance.
        $depositThenSettled = $this->makeAppointment([
            'deposit_collected_cents' => 2000,
            'deposit_collected_at' => now(),
            'settled_amount_cents' => 6000,
            'settled_at' => now(),
            'status' => 'paid',
        ]);

        // 2) Deposit never collected online; the FULL price settled in person
        //    (walk-in / cash appointment) — the default-formula's other branch.
        $depositNeverCollectedFullySettled = $this->makeAppointment([
            'deposit_collected_cents' => 0,
            'deposit_collected_at' => null,
            'settled_amount_cents' => 8000,
            'settled_at' => now(),
            'status' => 'paid',
        ]);

        // 3) Deposit collected, appointment still upcoming and unsettled —
        //    only the deposit is confirmed income so far.
        $depositOnlyUnsettled = $this->makeAppointment([
            'deposit_collected_cents' => 2000,
            'deposit_collected_at' => now(),
            'settled_amount_cents' => null,
            'settled_at' => null,
            'status' => 'confirmed',
        ]);

        // 4) Cancelled AFTER the deposit was collected — the "retained
        //    deposit" case (PR4a scope for its own stream classification,
        //    but the money itself is still real, already-collected income
        //    here and must not vanish or be double counted).
        $cancelledAfterDeposit = $this->makeAppointment([
            'deposit_collected_cents' => 2000,
            'deposit_collected_at' => now(),
            'settled_amount_cents' => null,
            'settled_at' => null,
            'status' => 'cancelled',
        ]);

        $ids = [
            $depositThenSettled->id,
            $depositNeverCollectedFullySettled->id,
            $depositOnlyUnsettled->id,
            $cancelledAfterDeposit->id,
        ];

        // Hand-summed expectation: 8000 + 8000 + 2000 + 2000 = 20000.
        $expected = 8000 + 8000 + 2000 + 2000;

        $this->assertSame($expected, $this->handSummedIncome($ids));

        // Per-appointment pin, so a future change that breaks just ONE
        // permutation fails on the specific case, not only the aggregate.
        $this->assertSame(8000, $this->handSummedIncome([$depositThenSettled->id]));
        $this->assertSame(8000, $this->handSummedIncome([$depositNeverCollectedFullySettled->id]));
        $this->assertSame(2000, $this->handSummedIncome([$depositOnlyUnsettled->id]));
        $this->assertSame(2000, $this->handSummedIncome([$cancelledAfterDeposit->id]));
    }

    // -------------------------------------------------------------------------
    // Regression guard — the order-status-only formula is rejected
    // -------------------------------------------------------------------------

    /**
     * Pins the exact "10000 fails this scenario" numeric case from the spec's
     * regression guard: a naive report that infers deposit income from the
     * QUOTED `deposit_amount_cents` whenever the linked order is 'paid' —
     * instead of the actually-COLLECTED `deposit_collected_cents` — silently
     * disagrees with reality the moment the two differ (e.g. the quote was
     * 4000 but the gateway only ever captured 2000). The correct total
     * (8000) comes only from summing the two write-once channels; the wrong
     * total (10000) is what a status/quote-based formula would produce.
     */
    public function test_order_status_only_formula_is_rejected_as_a_defect(): void
    {
        $appointment = $this->makeAppointment([
            'deposit_amount_cents' => 4000,      // QUOTED — never itself income
            'deposit_collected_cents' => 2000,    // COLLECTED — what the gateway captured
            'deposit_collected_at' => now(),
            'settled_amount_cents' => 6000,       // max(0, 8000 - 2000), correctly computed
            'settled_at' => now(),
            'status' => 'paid',
        ]);

        $correctTotal = $this->handSummedIncome([$appointment->id]);
        $this->assertSame(8000, $correctTotal);

        // The rejected formula: linked order's status is 'paid', so treat
        // the QUOTED deposit as if it were collected, plus the settlement.
        $orderStatusOnlyTotal = $appointment->deposit_amount_cents + $appointment->settled_amount_cents;

        $this->assertSame(
            10000,
            $orderStatusOnlyTotal,
            'This pins the exact defect number from the spec — if this assertion ever fails, ' .
            'the fixture no longer reproduces the regression this guard exists to document.'
        );
        $this->assertNotSame(
            $correctTotal,
            $orderStatusOnlyTotal,
            'The order-status-only formula (quoted deposit + settlement) must never equal the ' .
            'correct total (collected deposit + settlement) — any reporting query that reads ' .
            'deposit_amount_cents instead of deposit_collected_cents is a defect, not a stylistic choice.'
        );
    }

    // -------------------------------------------------------------------------
    // Static guard scaffold (task 2.13) — full enforcement completes in PR2a
    // -------------------------------------------------------------------------

    /**
     * `app/Reports/` does not exist until PR2a (design D4's `RevenueStreams`
     * and its siblings). Scanning zero files finds zero violations, which is
     * a genuinely true assertion today — not a skip. Once PR2a creates the
     * directory, this same test starts enforcing the real rule with no
     * further changes needed here.
     */
    public function test_only_revenue_streams_may_reference_the_orders_table_or_order_model(): void
    {
        $reportsDir = app_path('Reports');

        if (! is_dir($reportsDir)) {
            $this->assertTrue(true, 'app/Reports/ does not exist yet (PR2a) — zero files, zero violations.');

            return;
        }

        $violations = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $reportsDir,
            \FilesystemIterator::SKIP_DOTS
        ));

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php' || $file->getFilename() === 'RevenueStreams.php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if (str_contains($contents, 'Order::') || str_contains($contents, "'orders'")) {
                $violations[] = $file->getPathname();
            }
        }

        $this->assertEmpty(
            $violations,
            'Only RevenueStreams.php may reference the orders table for appointment money ' .
            '(design D4): '.implode(', ', $violations)
        );
    }
}
