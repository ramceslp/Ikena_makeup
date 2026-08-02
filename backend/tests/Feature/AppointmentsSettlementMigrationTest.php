<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * AppointmentsSettlementMigrationTest
 *
 * Phase 1 (PR1a), task 1.1 — schema-only guard for the settlement columns
 * added to `appointments` (design D1), plus the sibling `cost`/`unit_cost_cents`
 * columns on `products`/`order_items` (needed so PR4a can snapshot margin
 * without reading the live, mutable price).
 *
 * `service_price_cents` is asserted NOT NULL per the reconciliation adjustment
 * #3 (architecture/admin-reports-design-adjustments): the design originally
 * specified nullable-plus-model-guard, but since there is no production data
 * yet, a real NOT NULL constraint is stronger — it also covers raw UPDATEs and
 * seeders that bypass Eloquent, which a model-only guard cannot.
 */
class AppointmentsSettlementMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_appointments_table_has_settlement_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('appointments', 'service_price_cents'));
        $this->assertTrue(Schema::hasColumn('appointments', 'deposit_collected_cents'));
        $this->assertTrue(Schema::hasColumn('appointments', 'deposit_collected_at'));
        $this->assertTrue(Schema::hasColumn('appointments', 'settled_amount_cents'));
        $this->assertTrue(Schema::hasColumn('appointments', 'settled_at'));
    }

    public function test_service_price_cents_is_not_nullable(): void
    {
        // Uses PRAGMA table_info because phpunit.xml forces the sqlite driver
        // (see phpunit.xml:26-27) — this suite does not run on MySQL until the
        // PR1b MySQL CI job lands (design D3). The NOT NULL constraint itself
        // is expressed with Laravel's portable schema builder in the migration
        // (no `->nullable()`), so it is enforced identically on both drivers;
        // this assertion just confirms it landed on the driver we can see today.
        $columns = DB::select('PRAGMA table_info(appointments)');
        $column = collect($columns)->firstWhere('name', 'service_price_cents');

        $this->assertNotNull($column, 'service_price_cents column must exist');
        $this->assertSame(1, $column->notnull, 'service_price_cents must be NOT NULL');
    }

    /**
     * PR1b, carried-debt item 1 (architecture/admin-reports-pr-budget): PR1a
     * shipped `service_price_cents` with a bridging DEFAULT 0 because
     * `CreateBookingAction` did not yet write the snapshot. Now that PR1b's
     * migration 2026_08_02_100000_drop_default_from_appointments_service_price_cents
     * removes it, the column must be NOT NULL with NO default — a raw insert
     * that omits the column must fail at the DB level, not silently store 0.
     */
    public function test_service_price_cents_has_no_bridging_default(): void
    {
        $columns = DB::select('PRAGMA table_info(appointments)');
        $column = collect($columns)->firstWhere('name', 'service_price_cents');

        $this->assertNotNull($column, 'service_price_cents column must exist');
        $this->assertNull(
            $column->dflt_value,
            'service_price_cents must not have a bridging default once CreateBookingAction ' .
            'supplies the snapshot on every write (PR1b) — see architecture/admin-reports-pr-budget.'
        );
    }

    public function test_products_table_has_cost_column(): void
    {
        $this->assertTrue(Schema::hasColumn('products', 'cost'));
    }

    public function test_order_items_table_has_unit_cost_cents_column(): void
    {
        $this->assertTrue(Schema::hasColumn('order_items', 'unit_cost_cents'));
    }
}
