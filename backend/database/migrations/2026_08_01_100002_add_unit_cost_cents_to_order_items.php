<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `unit_cost_cents` to `order_items` — a snapshot of the product's cost
 * at sale time, following the same accounting-immutability precedent as the
 * sibling `unit_price_cents`/`line_total_cents` columns (see the docblock on
 * `2026_06_19_900003_create_order_items_table.php`). The TopProducts margin
 * report (PR4a) reads this snapshot, never the live `products.cost`, so a
 * later cost change never rewrites historical margin.
 *
 * unsignedInteger default 0 matches the ledger cents convention used by every
 * other money column on this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('unit_cost_cents')->default(0)->after('unit_price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost_cents');
        });
    }
};
