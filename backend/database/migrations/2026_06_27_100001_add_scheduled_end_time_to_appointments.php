<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->time('scheduled_end_time')->nullable()->after('scheduled_time');
        });

        // Backfill scheduled_end_time for existing appointments.
        // Formula: scheduled_end_time = scheduled_time + duration_hours (integer hours).
        // SQLite and MySQL use different date/time arithmetic functions, so we branch.
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite: time() modifier '+N hours' works with integer duration_hours.
            DB::statement(<<<'SQL'
                UPDATE appointments
                SET scheduled_end_time = time(
                    scheduled_time,
                    '+' || (SELECT duration_hours FROM services WHERE id = appointments.service_id) || ' hours'
                )
                WHERE service_id IS NOT NULL
            SQL);
        } else {
            // MySQL: JOIN-based UPDATE with ADDTIME + SEC_TO_TIME.
            DB::statement(<<<'SQL'
                UPDATE appointments
                INNER JOIN services ON services.id = appointments.service_id
                SET appointments.scheduled_end_time = ADDTIME(
                    appointments.scheduled_time,
                    SEC_TO_TIME(services.duration_hours * 3600)
                )
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('scheduled_end_time');
        });
    }
};
