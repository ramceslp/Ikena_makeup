<?php

namespace Tests\Feature\Admin;

use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPostControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function student(): User
    {
        return User::factory()->create(['role' => 'student']);
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'title'        => 'Mi Primer Post',
            'body'         => '<p>Contenido del post.</p>',
            'type'         => 'noticia',
            'is_published' => false,
        ], $overrides);
    }

    // =========================================================================
    // Auth — access control
    // =========================================================================

    public function test_guest_cannot_access_admin_posts_401(): void
    {
        $this->getJson('/api/admin/posts')->assertStatus(401);
    }

    public function test_student_cannot_access_admin_posts_403(): void
    {
        Sanctum::actingAs($this->student());
        $this->getJson('/api/admin/posts')->assertStatus(403);
    }

    // =========================================================================
    // CRUD — Store
    // =========================================================================

    public function test_store_creates_post_with_author_id_set_to_authenticated_admin(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/posts', $this->basePayload())->assertStatus(201);

        $this->assertDatabaseHas('posts', [
            'title'     => 'Mi Primer Post',
            'author_id' => $admin->id,
        ]);
    }

    public function test_store_returns_201_with_post_data(): void
    {
        Sanctum::actingAs($this->admin());

        $response = $this->postJson('/api/admin/posts', $this->basePayload());

        $response->assertStatus(201)
                 ->assertJsonStructure(['data' => ['id', 'title', 'slug']]);
    }

    // =========================================================================
    // Auto-slug generation
    // =========================================================================

    public function test_store_auto_generates_slug_from_title(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/posts', $this->basePayload([
            'title' => 'Nuevo Curso de Verano',
        ]))->assertStatus(201);

        $this->assertDatabaseHas('posts', ['slug' => 'nuevo-curso-de-verano']);
    }

    public function test_store_generates_unique_slug_on_collision(): void
    {
        Post::factory()->create(['title' => 'Mi Post', 'slug' => 'mi-post']);
        Sanctum::actingAs($this->admin());

        $response = $this->postJson('/api/admin/posts', $this->basePayload(['title' => 'Mi Post']))->assertStatus(201);

        $this->assertEquals('mi-post-2', $response->json('data.slug'));
    }

    // =========================================================================
    // Manual slug override
    // =========================================================================

    public function test_update_manual_slug_override_is_preserved(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $post = Post::factory()->create(['slug' => 'original-slug']);

        $this->postJson("/api/admin/posts/{$post->id}", [
            'slug'  => 'curso-verano-especial',
            'title' => 'Nuevo Título Cualquiera',
        ])->assertStatus(200);

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'slug' => 'curso-verano-especial']);
    }

    public function test_update_duplicate_slug_from_another_post_returns_422(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        Post::factory()->create(['slug' => 'taken-slug']);
        $post = Post::factory()->create(['slug' => 'my-post']);

        $this->postJson("/api/admin/posts/{$post->id}", [
            'slug' => 'taken-slug',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['slug']);
    }

    // =========================================================================
    // published_at auto-set on first publish
    // =========================================================================

    public function test_update_sets_published_at_on_first_publish(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $post = Post::factory()->create([
            'is_published' => false,
            'published_at' => null,
        ]);

        $this->postJson("/api/admin/posts/{$post->id}", [
            'is_published' => true,
        ])->assertStatus(200);

        $post->refresh();
        $this->assertTrue($post->is_published);
        $this->assertNotNull($post->published_at);
    }

    public function test_update_does_not_overwrite_published_at_on_re_publish(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $originalDate = now()->subDays(30);

        $post = Post::factory()->create([
            'is_published' => false,
            'published_at' => $originalDate,
        ]);

        // Toggle back to published without providing published_at
        $this->postJson("/api/admin/posts/{$post->id}", [
            'is_published' => true,
        ])->assertStatus(200);

        $post->refresh();
        $this->assertTrue((bool) $post->is_published);
        $this->assertTrue($post->published_at->timestamp === $originalDate->timestamp);
    }

    // =========================================================================
    // is_featured toggle
    // =========================================================================

    public function test_update_is_featured_toggle(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $post = Post::factory()->published()->create(['is_featured' => false]);

        $response = $this->postJson("/api/admin/posts/{$post->id}", [
            'is_featured' => true,
        ])->assertStatus(200);

        $this->assertTrue((bool) $response->json('data.is_featured'));
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'is_featured' => true]);
    }

    // =========================================================================
    // Destroy — cascade
    // =========================================================================

    public function test_destroy_removes_post_and_cascades_images(): void
    {
        Storage::fake('public');

        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $post = Post::factory()->create();

        $file  = UploadedFile::fake()->image('img.jpg');
        $path  = $file->store('posts/images', 'public');
        $image = PostImage::factory()->create(['post_id' => $post->id, 'path' => $path]);

        // Also set a cover
        $coverPath = UploadedFile::fake()->image('cover.jpg')->store('posts/covers', 'public');
        $post->update(['cover_image_path' => $coverPath]);

        $this->deleteJson("/api/admin/posts/{$post->id}")->assertStatus(204);

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
        $this->assertDatabaseMissing('post_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing($path);
        Storage::disk('public')->assertMissing($coverPath);
    }

    // =========================================================================
    // Cover image
    // =========================================================================

    public function test_store_cover_image_stores_file_and_sets_path(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $post = Post::factory()->create(['cover_image_path' => null]);
        $file = UploadedFile::fake()->image('cover.jpg', 400, 300);

        $response = $this->postJson("/api/admin/posts/{$post->id}/cover", [
            'cover_image' => $file,
        ])->assertStatus(200);

        $post->refresh();
        $this->assertNotNull($post->cover_image_path);
        $this->assertStringContainsString('posts/covers', $post->cover_image_path);
        Storage::disk('public')->assertExists($post->cover_image_path);
    }

    public function test_destroy_cover_image_removes_file_and_nulls_path(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $file      = UploadedFile::fake()->image('cover.jpg')->store('posts/covers', 'public');
        $post      = Post::factory()->create(['cover_image_path' => $file]);

        $this->deleteJson("/api/admin/posts/{$post->id}/cover")->assertStatus(200);

        $post->refresh();
        $this->assertNull($post->cover_image_path);
        Storage::disk('public')->assertMissing($file);
    }

    // =========================================================================
    // Body images
    // =========================================================================

    public function test_store_images_creates_post_image_records(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $post  = Post::factory()->create();
        $files = [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
        ];

        $response = $this->postJson("/api/admin/posts/{$post->id}/images", [
            'images' => $files,
        ])->assertStatus(200);

        $this->assertDatabaseCount('post_images', 2);
        $this->assertIsArray($response->json('data'));
        $this->assertCount(2, $response->json('data'));
    }

    public function test_reorder_images_updates_sort_order(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $post = Post::factory()->create();
        $img1 = PostImage::factory()->create(['post_id' => $post->id, 'sort_order' => 0, 'path' => 'posts/images/a.jpg']);
        $img2 = PostImage::factory()->create(['post_id' => $post->id, 'sort_order' => 1, 'path' => 'posts/images/b.jpg']);

        $this->postJson("/api/admin/posts/{$post->id}/images/reorder", [
            'order' => [$img2->id, $img1->id],
        ])->assertStatus(200);

        $this->assertDatabaseHas('post_images', ['id' => $img2->id, 'sort_order' => 0]);
        $this->assertDatabaseHas('post_images', ['id' => $img1->id, 'sort_order' => 1]);
    }

    public function test_destroy_image_removes_record_and_file(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $post  = Post::factory()->create();
        $file  = UploadedFile::fake()->image('photo.jpg');
        $path  = $file->store('posts/images', 'public');
        $image = PostImage::factory()->create(['post_id' => $post->id, 'path' => $path]);

        $this->deleteJson("/api/admin/posts/{$post->id}/images/{$image->id}")->assertStatus(204);

        $this->assertDatabaseMissing('post_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing($path);
    }
}
