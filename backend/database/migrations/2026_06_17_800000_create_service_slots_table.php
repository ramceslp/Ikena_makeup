<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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

    public function down(): void
    {
        Schema::dropIfExists('service_slots');
    }
};
