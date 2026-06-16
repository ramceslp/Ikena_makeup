<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('category_id')
                  ->nullable()
                  ->after('instructor_id')
                  ->constrained('categories')
                  ->nullOnDelete();

            $table->boolean('offers_certificate')
                  ->default(false)
                  ->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn(['category_id', 'offers_certificate']);
        });
    }
};
