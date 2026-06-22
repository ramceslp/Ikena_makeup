<?php

namespace Database\Seeders;

use App\Models\CertificateSetting;
use Illuminate\Database\Seeder;

class CertificateSettingSeeder extends Seeder
{
    /**
     * Idempotent: creates/reconciles the singleton certificate-branding row
     * (id=1) with the canonical defaults. Safe to run repeatedly.
     */
    public function run(): void
    {
        CertificateSetting::updateOrCreate(['id' => 1], CertificateSetting::defaults());
    }
}
