<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Evolve `orders` table to support typed orders and money breakdown.
 *
 * ADD COLUMN only — SQLite-safe, no MODIFY / no doctrine/dbal required.
 *
 * Columns added:
 *  - type           string(20) NOT NULL default 'course', index
 *  - subtotal_cents unsignedInteger nullable
 *  - tax_cents      unsignedInteger nullable
 *  - total_cents    unsignedInteger nullable
 *  - reserved_until timestamp nullable, index (used by release sweep)
 *
 * Backfill: existing appointment orders (appointment_id NOT NULL) are
 * updated to type='appointment'. All other rows keep the default 'course'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('type', 20)->default('course')->after('appointment_id');
            $table->unsignedInteger('subtotal_cents')->nullable()->after('amount_cents');
            $table->unsignedInteger('tax_cents')->nullable()->after('subtotal_cents');
            $table->unsignedInteger('total_cents')->nullable()->after('tax_cents');
            $table->timestamp('reserved_until')->nullable()->after('paid_at');
        });

        // Add indexes after column creation
        Schema::table('orders', function (Blueprint $table) {
            $table->index('type');
            $table->index('reserved_until');
        });

        // Backfill: appointment orders have appointment_id set → mark them
        DB::table('orders')
            ->whereNotNull('appointment_id')
            ->update(['type' => 'appointment']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['reserved_until']);
            $table->dropColumn(['type', 'subtotal_cents', 'tax_cents', 'total_cents', 'reserved_until']);
        });
    }
};
