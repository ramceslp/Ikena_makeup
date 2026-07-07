<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the legacy `service_slots` table.
 *
 * Superseded by `agenda_blocks` (Slice 1) + VenueAvailabilityResolver
 * (Slice 1/2) — the per-service slot model has had no consumer in the live
 * code path since Slice 2 switched BookingController/StoreBookingRequest to
 * the venue-wide agenda resolver. Slice 3/4 built the AgendaBlock admin CRUD
 * (backend + frontend) that replaces the legacy per-service slot admin UI.
 *
 * This is a NEW migration (not an edit of the original create_service_slots
 * migration) per Laravel convention — schema history is append-only.
 *
 * Down migration recreates the original table shape so a rollback restores
 * the exact structure that Slice 5 removed (data is NOT restored).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('service_slots');
    }

    public function down(): void
    {
        Schema::create('service_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')
                  ->constrained('services')
                  ->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week')->nullable(); // 0=Sunday … 6=Saturday
            $table->date('specific_date')->nullable();
            $table->time('start_time');
            $table->unsignedTinyInteger('capacity')->default(1);
            $table->boolean('is_blocked')->default(false);
            $table->timestamps();
        });
    }
};
