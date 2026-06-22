<?php

namespace Tests\Feature\Admin;

use App\Models\CertificateSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CertificateSettingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    // -------------------------------------------------------------------------
    // Admin gate
    // -------------------------------------------------------------------------

    public function test_guest_cannot_access_admin_settings(): void
    {
        $this->getJson('/api/admin/certificate-settings')->assertStatus(401);
    }

    public function test_student_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'student']));
        $this->getJson('/api/admin/certificate-settings')->assertStatus(403);
    }

    public function test_instructor_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->instructor()->create());
        $this->getJson('/api/admin/certificate-settings')->assertStatus(403);
    }

    public function test_admin_can_fetch_settings_with_defaults(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/certificate-settings')
            ->assertStatus(200)
            ->assertJsonPath('data.business_name', 'Ikena Makeup Academy')
            ->assertJsonPath('data.design_variant', 1);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_admin_updates_text_fields_and_variant(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/certificate-settings', [
            'business_name'    => 'Studio Bella',
            'title'            => 'Diploma de Honor',
            'award_line'       => 'Otorgado con distinción a',
            'achievement_line' => 'por finalizar el programa',
            'signer_name'      => 'María Pérez',
            'signer_role'      => 'Directora Académica',
            'design_variant'   => 4,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.business_name', 'Studio Bella')
            ->assertJsonPath('data.signer_role', 'Directora Académica')
            ->assertJsonPath('data.design_variant', 4);

        $this->assertDatabaseHas('certificate_settings', [
            'id'            => 1,
            'business_name' => 'Studio Bella',
            'design_variant' => 4,
        ]);
    }

    public function test_update_keeps_a_single_row_singleton_invariant(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/certificate-settings', ['business_name' => 'First'])->assertStatus(200);
        $this->postJson('/api/admin/certificate-settings', ['business_name' => 'Second'])->assertStatus(200);

        $this->assertSame(1, CertificateSetting::count());
        $this->assertSame('Second', CertificateSetting::current()->business_name);
    }

    // -------------------------------------------------------------------------
    // Logo upload + replace
    // -------------------------------------------------------------------------

    public function test_admin_uploads_logo_stored_on_public_disk(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->admin());

        $response = $this->postJson('/api/admin/certificate-settings', [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertStatus(200);

        $logoUrl = $response->json('data.logo_url');
        $this->assertNotNull($logoUrl);
        $this->assertStringContainsString('certificate/', $logoUrl);

        $path = CertificateSetting::current()->logo_path;
        Storage::disk('public')->assertExists($path);
    }

    public function test_replacing_logo_deletes_the_previous_file(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/certificate-settings', [
            'logo' => UploadedFile::fake()->image('first.png'),
        ])->assertStatus(200);

        $firstPath = CertificateSetting::current()->logo_path;

        $this->postJson('/api/admin/certificate-settings', [
            'logo' => UploadedFile::fake()->image('second.png'),
        ])->assertStatus(200);

        $secondPath = CertificateSetting::current()->logo_path;

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function test_design_variant_out_of_range_is_rejected(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/certificate-settings', ['design_variant' => 6])
            ->assertStatus(422)
            ->assertJsonValidationErrors('design_variant');

        $this->postJson('/api/admin/certificate-settings', ['design_variant' => 0])
            ->assertStatus(422);
    }

    public function test_logo_must_be_an_image_within_size_limit(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/certificate-settings', [
            'logo' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('logo');

        $this->postJson('/api/admin/certificate-settings', [
            'logo' => UploadedFile::fake()->image('big.png')->size(3000),
        ])->assertStatus(422)->assertJsonValidationErrors('logo');
    }

    public function test_signer_role_is_nullable(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/certificate-settings', [
            'signer_name' => 'Solo Nombre',
            'signer_role' => null,
        ])->assertStatus(200)->assertJsonPath('data.signer_role', null);
    }
}
