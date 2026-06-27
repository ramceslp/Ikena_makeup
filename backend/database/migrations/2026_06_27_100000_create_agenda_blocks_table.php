<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_blocks', function (Blueprint $table) {
            $table->id();

            // Recurrence: exactly one of day_of_week or specific_date must be non-null.
            // XOR invariant is enforced at the request validation layer (VAGA-002).
            $table->unsignedTinyInteger('day_of_week')->nullable();  // 0=Sun … 6=Sat
            $table->date('specific_date')->nullable();

            $table->time('open_time');
            $table->time('close_time');

            // Concurrency limits: null falls back to config('booking.venue.default_*')
            $table->unsignedSmallInteger('concurrency_limit')->nullable();
            $table->unsignedSmallInteger('soft_threshold')->nullable();

            $table->boolean('is_blocked')->default(false);

            // Reserved for future per-staff routing (v1: null — venue-aggregate)
            $table->unsignedBigInteger('staff_id')->nullable();

            $table->timestamps();

            // Indexes for efficient resolver lookups by date
            $table->index('day_of_week');
            $table->index('specific_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_blocks');
    }
};
