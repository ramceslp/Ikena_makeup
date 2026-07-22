<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `reminder_sent_at` to `appointments` — tracks whether the
 * "Appointment reminder" v1 push trigger (mobile-capacitor-setup PR3,
 * design Decision 2) has already been sent for this appointment, so the
 * hourly `appointments:send-reminders` scheduled command never notifies the
 * same appointment twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
