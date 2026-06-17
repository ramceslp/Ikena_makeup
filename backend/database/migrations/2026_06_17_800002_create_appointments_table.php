<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')
                  ->constrained('services')
                  ->cascadeOnDelete();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->foreignId('order_id')
                  ->nullable()
                  ->constrained('orders')
                  ->nullOnDelete();
            $table->date('scheduled_date');
            $table->time('scheduled_time');
            // slot_key = "{service_id}|{date}|{time}"; NULL when cancelled (frees the unique slot)
            $table->string('slot_key')->nullable()->unique();
            $table->string('whatsapp', 20);
            $table->string('payment_mode')->default('gateway'); // gateway | manual
            $table->unsignedInteger('deposit_amount_cents');
            $table->string('status')->default('pending'); // pending | confirmed | paid | cancelled
            $table->foreignId('cancelled_by_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
