<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminServiceTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function instructor(): User
    {
        return User::factory()->instructor()->create();
    }

    private function student(): User
    {
        return User::factory()->create(['role' => 'student']);
    }

    private function validPayload(array $overrides = []): array
    {
        $category = Category::factory()->create();

        return array_merge([
            'title'             => 'Maquillaje Social',
            'description'       => 'Servicio de maquillaje para eventos sociales.',
            'price'             => 120.00,
            'duration_hours'    => 2,
            'availability_type' => 'immediate',
            'category_id'       => $category->id,
            'is_published'      => false,
        ], $overrides);
    }

    // =========================================================================
    // Auth Matrix — GET /api/admin/services
    // =========================================================================

    public function test_guest_cannot_access_admin_services_index_401(): void
    {
        $this->getJson('/api/admin/services')->assertStatus(401);
    }

    public function test_student_cannot_access_admin_services_index_403(): void
    {
        Sanctum::actingAs($this->student());
        $this->getJson('/api/admin/services')->assertStatus(403);
    }

    public function test_instructor_cannot_access_admin_services_index_403(): void
    {
        Sanctum::actingAs($this->instructor());
        $this->getJson('/api/admin/services')->assertStatus(403);
    }

    public function test_admin_can_access_admin_services_index_200(): void
    {
        Sanctum::actingAs($this->admin());
        $this->getJson('/api/admin/services')->assertStatus(200);
    }

    // Auth matrix on POST /api/admin/services

    public function test_guest_cannot_store_service_401(): void
    {
        $this->postJson('/api/admin/services', [])->assertStatus(401);
    }

    public function test_student_cannot_store_service_403(): void
    {
        Sanctum::actingAs($this->student());
        $this->postJson('/api/admin/services', [])->assertStatus(403);
    }

    public function test_instructor_cannot_store_service_403(): void
    {
        Sanctum::actingAs($this->instructor());
        $this->postJson('/api/admin/services', [])->assertStatus(403);
    }

    // Auth matrix on DELETE /api/admin/services/{id}

    public function test_guest_cannot_delete_service_401(): void
    {
        $service = Service::factory()->create();
        $this->deleteJson("/api/admin/services/{$service->id}")->assertStatus(401);
    }

    public function test_student_cannot_delete_service_403(): void
    {
        $service = Service::factory()->create();
        Sanctum::actingAs($this->student());
        $this->deleteJson("/api/admin/services/{$service->id}")->assertStatus(403);
    }

    public function test_instructor_cannot_delete_service_403(): void
    {
        $service = Service::factory()->create();
        Sanctum::actingAs($this->instructor());
        $this->deleteJson("/api/admin/services/{$service->id}")->assertStatus(403);
    }

    // =========================================================================
    // Index — returns published AND unpublished
    // =========================================================================

    public function test_admin_index_returns_all_services_including_unpublished(): void
    {
        Service::factory()->count(2)->published()->create();
        Service::factory()->unpublished()->create();

        Sanctum::actingAs($this->admin());

        $response = $this->getJson('/api/admin/services')->assertStatus(200);

        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    // =========================================================================
    // Store — create + slug auto-gen
    // =========================================================================

    public function test_store_creates_service_with_auto_generated_slug(): void
    {
        Sanctum::actingAs($this->admin());

        $category = Category::factory()->create();

        $response = $this->postJson('/api/admin/services', [
            'title'             => 'Maquillaje Social',
            'description'       => 'Servicio de maquillaje.',
            'price'             => 120,
            'duration_hours'    => 2,
            'availability_type' => 'immediate',
            'category_id'       => $category->id,
            'is_published'      => false,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('services', [
            'title' => 'Maquillaje Social',
            'slug'  => 'maquillaje-social',
        ]);
    }

    public function test_store_generates_unique_slug_on_collision(): void
    {
        Service::factory()->create(['title' => 'Maquillaje Social', 'slug' => 'maquillaje-social']);

        Sanctum::actingAs($this->admin());

        $category = Category::factory()->create();

        $response = $this->postJson('/api/admin/services', [
            'title'             => 'Maquillaje Social',
            'description'       => 'Otro servicio.',
            'price'             => 80,
            'duration_hours'    => 1,
            'availability_type' => 'by_appointment',
            'category_id'       => $category->id,
            'is_published'      => false,
        ]);

        $response->assertStatus(201);

        $slug = $response->json('data.slug');
        $this->assertNotEquals('maquillaje-social', $slug);
        $this->assertStringStartsWith('maquillaje-social', $slug);
    }

    // =========================================================================
    // Validation — 422 scenarios
    // =========================================================================

    public function test_store_validates_missing_title_returns_422(): void
    {
        Sanctum::actingAs($this->admin());

        $payload = $this->validPayload();
        unset($payload['title']);

        $this->postJson('/api/admin/services', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_store_validates_missing_description_returns_422(): void
    {
        Sanctum::actingAs($this->admin());

        $payload = $this->validPayload();
        unset($payload['description']);

        $this->postJson('/api/admin/services', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    public function test_store_validates_negative_price_returns_422(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/services', $this->validPayload(['price' => -10]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price']);
    }

    public function test_store_validates_invalid_availability_type_returns_422(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/services', $this->validPayload(['availability_type' => 'unknown']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['availability_type']);
    }

    public function test_store_validates_nonexistent_category_id_returns_422(): void
    {
        Sanctum::actingAs($this->admin());

        $payload = $this->validPayload();
        $payload['category_id'] = 99999;

        $this->postJson('/api/admin/services', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    // =========================================================================
    // Show
    // =========================================================================

    public function test_show_returns_unpublished_service_to_admin(): void
    {
        $service = Service::factory()->unpublished()->create();

        Sanctum::actingAs($this->admin());

        $this->getJson("/api/admin/services/{$service->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $service->id);
    }

    // =========================================================================
    // Update (POST multipart)
    // =========================================================================

    public function test_update_edits_service_fields(): void
    {
        $service = Service::factory()->create(['price' => 100.00]);

        Sanctum::actingAs($this->admin());

        // Admin update uses POST (multipart-compatible; PHP does not parse multipart on PATCH/PUT)
        $this->postJson("/api/admin/services/{$service->id}", [
            'price' => 200.00,
        ])->assertStatus(200);

        $this->assertDatabaseHas('services', [
            'id'    => $service->id,
            'price' => 200.00,
        ]);
    }

    public function test_update_regenerates_slug_when_title_changes(): void
    {
        $service = Service::factory()->create(['title' => 'Old Title', 'slug' => 'old-title']);

        Sanctum::actingAs($this->admin());

        // Admin update uses POST (multipart-compatible; PHP does not parse multipart on PATCH/PUT)
        $response = $this->postJson("/api/admin/services/{$service->id}", [
            'title' => 'Brand New Title',
        ])->assertStatus(200);

        $this->assertEquals('brand-new-title', $response->json('data.slug'));
    }

    // =========================================================================
    // Destroy — removes service, cascades image rows and files
    // =========================================================================

    public function test_destroy_removes_service_and_cascades_images(): void
    {
        Storage::fake('public');

        $service = Service::factory()->create();
        $file    = UploadedFile::fake()->image('photo.jpg');
        $path    = $file->store('services', 'public');

        $image = ServiceImage::factory()->create([
            'service_id' => $service->id,
            'path'       => $path,
            'sort_order' => 0,
        ]);

        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/admin/services/{$service->id}")->assertStatus(204);

        $this->assertDatabaseMissing('services', ['id' => $service->id]);
        $this->assertDatabaseMissing('service_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing($path);
    }

    // =========================================================================
    // Image Upload — POST /api/admin/services/{id}/images
    // =========================================================================

    public function test_image_upload_stores_files_and_creates_ordered_records(): void
    {
        Storage::fake('public');

        $service = Service::factory()->create();

        Sanctum::actingAs($this->admin());

        $files = [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
            UploadedFile::fake()->image('c.jpg'),
        ];

        $response = $this->postJson("/api/admin/services/{$service->id}/images", [
            'images' => $files,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseCount('service_images', 3);

        $images = ServiceImage::where('service_id', $service->id)
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(3, $images);
        $this->assertEquals(0, $images[0]->sort_order);
        $this->assertEquals(1, $images[1]->sort_order);
        $this->assertEquals(2, $images[2]->sort_order);

        foreach ($images as $img) {
            Storage::disk('public')->assertExists($img->path);
        }
    }

    public function test_image_upload_sort_order_continues_from_existing_max(): void
    {
        Storage::fake('public');

        $service = Service::factory()->create();
        ServiceImage::factory()->create(['service_id' => $service->id, 'sort_order' => 0]);
        ServiceImage::factory()->create(['service_id' => $service->id, 'sort_order' => 1]);

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/admin/services/{$service->id}/images", [
            'images' => [UploadedFile::fake()->image('new.jpg')],
        ])->assertStatus(200);

        $newImage = ServiceImage::where('service_id', $service->id)
            ->orderByDesc('sort_order')
            ->first();

        $this->assertEquals(2, $newImage->sort_order);
    }

    public function test_image_upload_invalid_mime_type_returns_422(): void
    {
        Storage::fake('public');

        $service = Service::factory()->create();

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/admin/services/{$service->id}/images", [
            'images' => [UploadedFile::fake()->create('document.pdf', 100, 'application/pdf')],
        ])->assertStatus(422);
    }

    public function test_image_upload_file_exceeds_5mb_returns_422(): void
    {
        Storage::fake('public');

        $service = Service::factory()->create();

        Sanctum::actingAs($this->admin());

        // 6 MB = 6144 KB
        $this->postJson("/api/admin/services/{$service->id}/images", [
            'images' => [UploadedFile::fake()->create('big.jpg', 6144, 'image/jpeg')],
        ])->assertStatus(422);
    }

    public function test_image_upload_more_than_10_files_returns_422(): void
    {
        Storage::fake('public');

        $service = Service::factory()->create();

        Sanctum::actingAs($this->admin());

        $files = [];
        for ($i = 0; $i < 11; $i++) {
            $files[] = UploadedFile::fake()->image("img{$i}.jpg");
        }

        $this->postJson("/api/admin/services/{$service->id}/images", [
            'images' => $files,
        ])->assertStatus(422);
    }

    // =========================================================================
    // Image Delete — DELETE /api/admin/services/{id}/images/{image}
    // =========================================================================

    public function test_destroy_image_removes_record_and_file(): void
    {
        Storage::fake('public');

        $service = Service::factory()->create();
        $file    = UploadedFile::fake()->image('photo.jpg');
        $path    = $file->store('services', 'public');

        $image = ServiceImage::factory()->create([
            'service_id' => $service->id,
            'path'       => $path,
            'sort_order' => 0,
        ]);

        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/admin/services/{$service->id}/images/{$image->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('service_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_destroy_image_returns_404_for_image_belonging_to_different_service(): void
    {
        $service1 = Service::factory()->create();
        $service2 = Service::factory()->create();
        $image    = ServiceImage::factory()->create(['service_id' => $service2->id]);

        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/admin/services/{$service1->id}/images/{$image->id}")
            ->assertStatus(404);
    }

    // =========================================================================
    // Image Reorder — PATCH /api/admin/services/{id}/images/reorder
    // =========================================================================

    public function test_reorder_updates_sort_order_values(): void
    {
        $service = Service::factory()->create();

        $img1 = ServiceImage::factory()->create(['service_id' => $service->id, 'sort_order' => 0]);
        $img2 = ServiceImage::factory()->create(['service_id' => $service->id, 'sort_order' => 1]);
        $img3 = ServiceImage::factory()->create(['service_id' => $service->id, 'sort_order' => 2]);

        Sanctum::actingAs($this->admin());

        // Reorder: put img3 first, img1 second, img2 third
        $this->patchJson("/api/admin/services/{$service->id}/images/reorder", [
            'order' => [$img3->id, $img1->id, $img2->id],
        ])->assertStatus(200);

        $this->assertDatabaseHas('service_images', ['id' => $img3->id, 'sort_order' => 0]);
        $this->assertDatabaseHas('service_images', ['id' => $img1->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('service_images', ['id' => $img2->id, 'sort_order' => 2]);
    }

    public function test_reorder_validates_order_must_be_array(): void
    {
        $service = Service::factory()->create();

        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/services/{$service->id}/images/reorder", [
            'order' => 'not-an-array',
        ])->assertStatus(422);
    }
}
