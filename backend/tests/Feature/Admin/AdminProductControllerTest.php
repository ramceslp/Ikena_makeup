<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function student(): User
    {
        return User::factory()->create(['role' => 'student']);
    }

    private function validPayload(array $overrides = []): array
    {
        $category = Category::factory()->create();

        return array_merge([
            'title'        => 'Master Palette',
            'description'  => 'A professional makeup palette.',
            'price'        => 120.00,
            'stock_qty'    => 10,
            'category_id'  => $category->id,
            'is_published' => false,
        ], $overrides);
    }

    // =========================================================================
    // Auth — Non-admin access rejected (403)
    // =========================================================================

    public function test_guest_cannot_access_admin_products_index_401(): void
    {
        $this->getJson('/api/admin/products')->assertStatus(401);
    }

    public function test_student_cannot_access_admin_products_403(): void
    {
        Sanctum::actingAs($this->student());
        $this->getJson('/api/admin/products')->assertStatus(403);
    }

    public function test_student_cannot_store_product_403(): void
    {
        Sanctum::actingAs($this->student());
        $this->postJson('/api/admin/products', [])->assertStatus(403);
    }

    public function test_student_cannot_delete_product_403(): void
    {
        $product = Product::factory()->create();
        Sanctum::actingAs($this->student());
        $this->deleteJson("/api/admin/products/{$product->id}")->assertStatus(403);
    }

    public function test_student_cannot_upload_product_images_403(): void
    {
        $product = Product::factory()->create();
        Sanctum::actingAs($this->student());
        $this->postJson("/api/admin/products/{$product->id}/images", [])->assertStatus(403);
    }

    // =========================================================================
    // Index
    // =========================================================================

    public function test_admin_index_returns_all_products_including_unpublished(): void
    {
        Product::factory()->count(2)->published()->create();
        Product::factory()->unpublished()->create();

        Sanctum::actingAs($this->admin());

        $response = $this->getJson('/api/admin/products')->assertStatus(200);

        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    // =========================================================================
    // Store — create + slug auto-gen
    // =========================================================================

    public function test_store_creates_product_with_auto_generated_slug(): void
    {
        Sanctum::actingAs($this->admin());

        $category = Category::factory()->create();

        $response = $this->postJson('/api/admin/products', [
            'title'        => 'Master Palette',
            'description'  => 'A professional makeup palette.',
            'price'        => 120.00,
            'stock_qty'    => 10,
            'category_id'  => $category->id,
            'is_published' => false,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('products', [
            'title' => 'Master Palette',
            'slug'  => 'master-palette',
        ]);
    }

    public function test_store_generates_unique_slug_on_collision(): void
    {
        Product::factory()->create(['title' => 'Master Palette', 'slug' => 'master-palette']);

        Sanctum::actingAs($this->admin());

        $category = Category::factory()->create();

        $response = $this->postJson('/api/admin/products', [
            'title'        => 'Master Palette',
            'description'  => 'Another palette.',
            'price'        => 80.00,
            'stock_qty'    => 5,
            'category_id'  => $category->id,
            'is_published' => false,
        ]);

        $response->assertStatus(201);

        $slug = $response->json('data.slug');
        $this->assertNotEquals('master-palette', $slug);
        $this->assertStringStartsWith('master-palette', $slug);
        $this->assertEquals('master-palette-2', $slug);
    }

    // =========================================================================
    // Validation
    // =========================================================================

    public function test_store_validates_missing_title_returns_422(): void
    {
        Sanctum::actingAs($this->admin());

        $payload = $this->validPayload();
        unset($payload['title']);

        $this->postJson('/api/admin/products', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_store_validates_missing_price_returns_422(): void
    {
        Sanctum::actingAs($this->admin());

        $payload = $this->validPayload();
        unset($payload['price']);

        $this->postJson('/api/admin/products', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price']);
    }

    public function test_store_validates_negative_price_returns_422(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/products', $this->validPayload(['price' => -1]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price']);
    }

    public function test_store_validates_negative_stock_qty_returns_422(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/products', $this->validPayload(['stock_qty' => -1]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['stock_qty']);
    }

    // =========================================================================
    // Show
    // =========================================================================

    public function test_show_returns_unpublished_product_to_admin(): void
    {
        $product = Product::factory()->unpublished()->create();

        Sanctum::actingAs($this->admin());

        $this->getJson("/api/admin/products/{$product->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $product->id);
    }

    // =========================================================================
    // Update (POST multipart with _method=PATCH)
    // =========================================================================

    public function test_update_edits_product_fields(): void
    {
        $product = Product::factory()->create(['price' => 100.00]);

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/admin/products/{$product->id}", [
            'price' => 200.00,
        ])->assertStatus(200);

        $this->assertDatabaseHas('products', [
            'id'    => $product->id,
            'price' => 200.00,
        ]);
    }

    public function test_update_regenerates_slug_when_title_changes(): void
    {
        $product = Product::factory()->create(['title' => 'Old Title', 'slug' => 'old-title']);

        Sanctum::actingAs($this->admin());

        $response = $this->postJson("/api/admin/products/{$product->id}", [
            'title' => 'Brand New Title',
        ])->assertStatus(200);

        $this->assertEquals('brand-new-title', $response->json('data.slug'));
    }

    // =========================================================================
    // Destroy
    // =========================================================================

    public function test_destroy_removes_product_and_cascades_images(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        $file    = UploadedFile::fake()->image('photo.jpg');
        $path    = $file->store('products', 'public');

        $image = ProductImage::factory()->create([
            'product_id' => $product->id,
            'path'       => $path,
            'sort_order' => 0,
        ]);

        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/admin/products/{$product->id}")->assertStatus(204);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing($path);
    }

    // =========================================================================
    // Image Upload
    // =========================================================================

    public function test_image_upload_stores_files_and_creates_ordered_records(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();

        Sanctum::actingAs($this->admin());

        $files = [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
            UploadedFile::fake()->image('c.jpg'),
        ];

        $response = $this->postJson("/api/admin/products/{$product->id}/images", [
            'images' => $files,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseCount('product_images', 3);

        $images = ProductImage::where('product_id', $product->id)
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

    public function test_image_upload_total_exceeds_10_across_batches_returns_422(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        ProductImage::factory()->count(8)->create(['product_id' => $product->id]);

        Sanctum::actingAs($this->admin());

        $files = [
            UploadedFile::fake()->image('new1.jpg'),
            UploadedFile::fake()->image('new2.jpg'),
            UploadedFile::fake()->image('new3.jpg'),
        ];

        $this->postJson("/api/admin/products/{$product->id}/images", [
            'images' => $files,
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['images']);
    }

    // =========================================================================
    // Image Reorder
    // =========================================================================

    public function test_reorder_updates_sort_order_values(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();

        $img1 = ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 0, 'path' => 'products/a.jpg']);
        $img2 = ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 1, 'path' => 'products/b.jpg']);
        $img3 = ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 2, 'path' => 'products/c.jpg']);

        Sanctum::actingAs($this->admin());

        $response = $this->postJson("/api/admin/products/{$product->id}/images/reorder", [
            'order' => [$img3->id, $img1->id, $img2->id],
        ])->assertStatus(200);

        $this->assertDatabaseHas('product_images', ['id' => $img3->id, 'sort_order' => 0]);
        $this->assertDatabaseHas('product_images', ['id' => $img1->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('product_images', ['id' => $img2->id, 'sort_order' => 2]);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(3, $data);
        $this->assertEquals($img3->id, $data[0]['id']);
        $this->assertEquals(0, $data[0]['sort_order']);
    }

    public function test_reorder_rejects_image_id_belonging_to_different_product(): void
    {
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        $img1     = ProductImage::factory()->create(['product_id' => $product1->id, 'sort_order' => 0]);
        $imgOther = ProductImage::factory()->create(['product_id' => $product2->id, 'sort_order' => 0]);

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/admin/products/{$product1->id}/images/reorder", [
            'order' => [$img1->id, $imgOther->id],
        ])->assertStatus(422);
    }

    // =========================================================================
    // Image Delete
    // =========================================================================

    public function test_destroy_image_removes_record_and_file(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        $file    = UploadedFile::fake()->image('photo.jpg');
        $path    = $file->store('products', 'public');

        $image = ProductImage::factory()->create([
            'product_id' => $product->id,
            'path'       => $path,
            'sort_order' => 0,
        ]);

        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/admin/products/{$product->id}/images/{$image->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_destroy_image_returns_404_for_image_belonging_to_different_product(): void
    {
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();
        $image    = ProductImage::factory()->create(['product_id' => $product2->id]);

        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/admin/products/{$product1->id}/images/{$image->id}")
            ->assertStatus(404);
    }

    // =========================================================================
    // is_published visibility
    // =========================================================================

    public function test_admin_index_response_includes_is_published(): void
    {
        Product::factory()->published()->create();

        Sanctum::actingAs($this->admin());

        $response = $this->getJson('/api/admin/products')->assertStatus(200);

        $item = $response->json('data.0');
        $this->assertArrayHasKey('is_published', $item);
    }

    // =========================================================================
    // Slug override (AM-3) — RED first, then covered by FIX 2 implementation
    // =========================================================================

    public function test_update_with_explicit_unique_slug_persists_as_provided(): void
    {
        $product = Product::factory()->create(['title' => 'Original Title', 'slug' => 'original-title']);

        Sanctum::actingAs($this->admin());

        $response = $this->postJson("/api/admin/products/{$product->id}", [
            'slug' => 'my-custom-slug',
        ])->assertStatus(200);

        $this->assertEquals('my-custom-slug', $response->json('data.slug'));
        $this->assertDatabaseHas('products', ['id' => $product->id, 'slug' => 'my-custom-slug']);
    }

    public function test_update_with_slug_colliding_with_another_product_returns_422(): void
    {
        Product::factory()->create(['slug' => 'taken-slug']);
        $product = Product::factory()->create(['slug' => 'my-product']);

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/admin/products/{$product->id}", [
            'slug' => 'taken-slug',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['slug']);
    }

    // =========================================================================
    // Auth boundaries — non-admin gets 403 on update / reorder / destroy image
    // =========================================================================

    public function test_student_cannot_update_product_403(): void
    {
        $product = Product::factory()->create();
        Sanctum::actingAs($this->student());

        $this->postJson("/api/admin/products/{$product->id}", [
            'price' => 50.00,
        ])->assertStatus(403);
    }

    public function test_student_cannot_reorder_product_images_403(): void
    {
        $product = Product::factory()->create();
        Sanctum::actingAs($this->student());

        $this->postJson("/api/admin/products/{$product->id}/images/reorder", [
            'order' => [],
        ])->assertStatus(403);
    }

    public function test_student_cannot_destroy_product_image_403(): void
    {
        $product = Product::factory()->create();
        $image   = ProductImage::factory()->create(['product_id' => $product->id]);
        Sanctum::actingAs($this->student());

        $this->deleteJson("/api/admin/products/{$product->id}/images/{$image->id}")
            ->assertStatus(403);
    }

    // =========================================================================
    // Image cap boundary — 9 existing + 1 more = 10 (allowed, spec AM-1)
    // =========================================================================

    public function test_image_upload_exactly_10_total_is_accepted(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        ProductImage::factory()->count(9)->create(['product_id' => $product->id]);

        Sanctum::actingAs($this->admin());

        $response = $this->postJson("/api/admin/products/{$product->id}/images", [
            'images' => [UploadedFile::fake()->image('tenth.jpg')],
        ]);

        $response->assertStatus(200);
        $this->assertEquals(10, $product->images()->count());
    }
}
