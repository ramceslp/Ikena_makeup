<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('client_transaction_id', 50)->unique();
            $table->string('gateway')->default('payphone');
            $table->string('gateway_transaction_id')->nullable();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending'); // pending | paid | failed | canceled
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
