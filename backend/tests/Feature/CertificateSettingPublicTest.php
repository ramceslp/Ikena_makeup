<?php

namespace Tests\Feature;

use App\Models\CertificateSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateSettingPublicTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_endpoint_requires_no_auth_and_returns_defaults_when_unseeded(): void
    {
        $this->getJson('/api/certificate-settings')
            ->assertStatus(200)
            ->assertJsonPath('data.business_name', 'Ikena Makeup Academy')
            ->assertJsonPath('data.title', 'Certificado de Profesionalización')
            ->assertJsonPath('data.award_line', 'Se otorga el presente certificado a')
            ->assertJsonPath('data.achievement_line', 'por completar satisfactoriamente el curso')
            ->assertJsonPath('data.signer_name', 'Ikena Makeup Academy')
            ->assertJsonPath('data.signer_role', null)
            ->assertJsonPath('data.design_variant', 1)
            ->assertJsonPath('data.logo_url', null);
    }

    public function test_public_endpoint_returns_saved_values(): void
    {
        CertificateSetting::updateOrCreate(['id' => 1], array_merge(CertificateSetting::defaults(), [
            'business_name' => 'Custom Academy',
            'signer_name'   => 'Jane Director',
            'signer_role'   => 'Directora',
            'design_variant' => 3,
        ]));

        $this->getJson('/api/certificate-settings')
            ->assertStatus(200)
            ->assertJsonPath('data.business_name', 'Custom Academy')
            ->assertJsonPath('data.signer_name', 'Jane Director')
            ->assertJsonPath('data.signer_role', 'Directora')
            ->assertJsonPath('data.design_variant', 3);
    }

    public function test_logo_url_is_absolute_when_logo_path_is_set_and_null_otherwise(): void
    {
        CertificateSetting::updateOrCreate(['id' => 1], array_merge(CertificateSetting::defaults(), [
            'logo_path' => 'certificate/logo.png',
        ]));

        $logoUrl = $this->getJson('/api/certificate-settings')
            ->assertStatus(200)
            ->json('data.logo_url');

        $this->assertNotNull($logoUrl);
        $this->assertStringStartsWith('http', $logoUrl);
        $this->assertStringContainsString('certificate/logo.png', $logoUrl);
    }

    public function test_design_variant_is_returned_as_an_integer(): void
    {
        CertificateSetting::updateOrCreate(['id' => 1], array_merge(CertificateSetting::defaults(), [
            'design_variant' => 5,
        ]));

        $value = $this->getJson('/api/certificate-settings')
            ->assertStatus(200)
            ->json('data.design_variant');

        $this->assertIsInt($value);
        $this->assertSame(5, $value);
    }
}
