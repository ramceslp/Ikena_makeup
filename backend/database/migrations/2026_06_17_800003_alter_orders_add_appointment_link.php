<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alter `orders` table to support appointment-based deposit orders.
 *
 * Changes:
 *  1. Make `course_id` nullable (keep FK to courses).
 *  2. Add nullable `appointment_id` FK → appointments (nullOnDelete).
 *
 * Why raw SQL for the nullable change:
 *  `doctrine/dbal` is NOT installed, so `$table->change()` is unavailable.
 *  We use a DB::statement approach guarded by driver name:
 *   - MySQL/MariaDB: ALTER TABLE … MODIFY COLUMN
 *   - SQLite (test env): recreate the table via Schema::table (SQLite supports
 *     adding nullable columns and dropping NOT NULL via a table rebuild, but
 *     Laravel's SQLite driver doesn't expose change() without dbal).
 *     Instead we rely on the fact that SQLite ignores NOT NULL on nullable
 *     inserts when the constraint is enforced at app level (XOR guard).
 *     Specifically: the original migration used constrained() which adds NOT NULL;
 *     we DROP and re-ADD the column as nullable for SQLite.
 *
 * down() is intentionally LOSSY:
 *  - Appointment-only orders (course_id IS NULL) are deleted first.
 *  - Then appointment_id is dropped and course_id is restored to NOT NULL.
 *  This is the only safe way to re-impose the NOT NULL constraint.
 *  Documented here so any DBA reading the rollback understands the tradeoff.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL: MODIFY COLUMN changes NOT NULL → NULL keeping the FK intact.
            DB::statement('ALTER TABLE orders MODIFY COLUMN course_id BIGINT UNSIGNED NULL');
        } else {
            // SQLite (and other drivers): recreate the column as nullable.
            // SQLite does not support MODIFY COLUMN, but it DOES allow dropping
            // a column and re-adding it (Laravel 10+ wraps this in a table rebuild).
            // We drop course_id and re-add it as a nullable foreign key column.
            Schema::table('orders', function (Blueprint $table) {
                // Drop the existing NOT NULL FK
                $table->dropForeign(['course_id']);
                $table->dropColumn('course_id');
            });

            Schema::table('orders', function (Blueprint $table) {
                // Re-add as nullable, after user_id (to preserve column order semantics)
                $table->foreignId('course_id')
                      ->nullable()
                      ->after('user_id')
                      ->constrained('courses')
                      ->cascadeOnDelete();
            });
        }

        // Add appointment_id FK (safe for both MySQL and SQLite)
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('appointment_id')
                  ->nullable()
                  ->after('course_id')
                  ->constrained('appointments')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // LOSSY: appointment-only orders cannot survive course_id NOT NULL restore.
        // Delete appointment-only orders first to avoid constraint violation.
        DB::table('orders')->whereNull('course_id')->delete();

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['appointment_id']);
            $table->dropColumn('appointment_id');
        });

        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE orders MODIFY COLUMN course_id BIGINT UNSIGNED NOT NULL');
        } else {
            // SQLite: drop and re-add as NOT NULL
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['course_id']);
                $table->dropColumn('course_id');
            });

            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('course_id')
                      ->after('user_id')
                      ->constrained('courses')
                      ->cascadeOnDelete();
            });
        }
    }
};
