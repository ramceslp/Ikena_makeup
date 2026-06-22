<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create `certificate_settings` — a global SINGLETON (id=1) holding the
 * configurable branding for completion certificates. Column defaults match the
 * previously hardcoded copy so even a bare row (or the firstOrNew fallback)
 * renders a correct certificate. Singleton-ness is enforced at the app layer
 * (updateOrCreate id=1), not by a DB constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_settings', function (Blueprint $table) {
            $table->id();
            $table->string('logo_path')->nullable();
            $table->string('business_name')->default('Ikena Makeup Academy');
            $table->string('title')->default('Certificado de Profesionalización');
            $table->string('award_line')->default('Se otorga el presente certificado a');
            $table->string('achievement_line')->default('por completar satisfactoriamente el curso');
            $table->string('signer_name')->default('Ikena Makeup Academy');
            $table->string('signer_role')->nullable();
            $table->unsignedTinyInteger('design_variant')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_settings');
    }
};
