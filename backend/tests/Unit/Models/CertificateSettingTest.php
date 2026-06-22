<?php

namespace Tests\Unit\Models;

use App\Models\CertificateSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_returns_the_canonical_hardcoded_copy(): void
    {
        $d = CertificateSetting::defaults();

        $this->assertSame('Ikena Makeup Academy', $d['business_name']);
        $this->assertSame('Certificado de Profesionalización', $d['title']);
        $this->assertSame('Se otorga el presente certificado a', $d['award_line']);
        $this->assertSame('por completar satisfactoriamente el curso', $d['achievement_line']);
        $this->assertSame('Ikena Makeup Academy', $d['signer_name']);
        $this->assertNull($d['signer_role']);
        $this->assertSame(1, $d['design_variant']);
        $this->assertNull($d['logo_path']);
    }

    public function test_current_on_empty_table_returns_unpersisted_defaults_filled_instance(): void
    {
        $this->assertSame(0, CertificateSetting::count());

        $setting = CertificateSetting::current();

        // It must be a defaults-filled instance that is NOT persisted (read path never writes).
        $this->assertFalse($setting->exists);
        $this->assertSame(1, $setting->design_variant);
        $this->assertSame('Ikena Makeup Academy', $setting->business_name);
        $this->assertNull($setting->logo_path);
        $this->assertSame(0, CertificateSetting::count());
    }

    public function test_current_returns_the_persisted_row_when_it_exists(): void
    {
        CertificateSetting::updateOrCreate(['id' => 1], array_merge(
            CertificateSetting::defaults(),
            ['business_name' => 'Custom Academy', 'design_variant' => 4],
        ));

        $setting = CertificateSetting::current();

        $this->assertTrue($setting->exists);
        $this->assertSame('Custom Academy', $setting->business_name);
        $this->assertSame(4, $setting->design_variant);
    }

    public function test_resolve_image_url_is_null_safe_and_passes_through_http(): void
    {
        $setting = new CertificateSetting();

        $this->assertNull($setting->resolveImageUrl(null));
        $this->assertSame('https://cdn.example/logo.png', $setting->resolveImageUrl('https://cdn.example/logo.png'));
        $this->assertStringContainsString('certificate/logo.png', $setting->resolveImageUrl('certificate/logo.png'));
    }
}
