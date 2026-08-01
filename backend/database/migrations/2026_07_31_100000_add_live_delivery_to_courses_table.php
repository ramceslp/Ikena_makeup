<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // on_demand | live — string rather than enum to match the
            // convention used by users.role and orders.status; the closed set
            // is enforced by the form requests, not by the column type.
            $table->string('delivery_mode', 20)
                  ->default('on_demand')
                  ->after('is_published');

            // Required only when delivery_mode is 'live'. On-demand courses
            // have no calendar, so both stay null there.
            $table->date('starts_on')->nullable()->after('delivery_mode');
            $table->date('ends_on')->nullable()->after('starts_on');

            // Advertised workload, entered by the author. Deliberately NOT
            // derived from sum(lessons.duration): for a live course the
            // lesson duration is the scheduled slot, and the author still
            // wants to advertise a round number ("20 horas").
            $table->decimal('total_hours', 5, 1)->nullable()->after('ends_on');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['delivery_mode', 'starts_on', 'ends_on', 'total_hours']);
        });
    }
};
