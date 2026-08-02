<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `cost` to `products` — the admin-entered unit cost used by the
 * TopProducts margin report (PR4a). Matches the sibling `price` column's
 * `decimal(10,2)` shape (dollars, not cents — consistent with `price`).
 *
 * Defaults to 0 so existing products remain valid rows; the margin report
 * (PR4a) surfaces a coverage indicator ("margin computed over N of M
 * published products with cost recorded") rather than assuming every product
 * has a real cost entered — see adjustment #2 in
 * architecture/admin-reports-design-adjustments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost', 10, 2)->default(0)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('cost');
        });
    }
};
