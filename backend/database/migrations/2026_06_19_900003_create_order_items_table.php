<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create `order_items` table for product cart line items.
 *
 * Design notes:
 *  - product_id uses nullOnDelete (preserve order history if product is deleted)
 *  - product_title snapshot ensures readable history even after product deletion
 *  - unit_price_cents and line_total_cents are persisted for accounting immutability
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                  ->constrained('orders')
                  ->cascadeOnDelete();
            $table->foreignId('product_id')
                  ->nullable()
                  ->constrained('products')
                  ->nullOnDelete();
            $table->string('product_title');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedInteger('line_total_cents');
            $table->timestamps();
            // No explicit index('order_id') needed: foreignId()->constrained() already creates one.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
