<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the UNIQUE constraint on appointments.slot_key.
 *
 * The original create_appointments migration added the column as:
 *   $table->string('slot_key')->nullable()->unique();
 *
 * Laravel's naming convention creates the index as "appointments_slot_key_unique".
 *
 * Dropping UNIQUE allows multiple appointments to share the same service/date/time
 * (up to concurrency_limit), which is required for the venue concurrency model.
 * The slot_key column is retained for audit purposes and will be removed in Slice 5.
 *
 * SQLite note: ->dropUnique() compiles to DROP INDEX, which works for named indexes
 * created via the column-level ->unique() call (Laravel creates them as named indexes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('appointments_slot_key_unique');
        });
    }

    public function down(): void
    {
        // Restore the UNIQUE constraint. This will fail if duplicate slot_keys
        // were inserted while the constraint was absent — acceptable for rollback.
        Schema::table('appointments', function (Blueprint $table) {
            $table->unique('slot_key', 'appointments_slot_key_unique');
        });
    }
};
