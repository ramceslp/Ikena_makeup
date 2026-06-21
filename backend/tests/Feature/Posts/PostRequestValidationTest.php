<?php

namespace Tests\Feature\Posts;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Validates StorePostRequest + UpdatePostRequest rules via HTTP.
 * Relies on Admin\PostController which must also be implemented before these pass.
 */
class PostRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'title'        => 'Mi Primer Post',
            'body'         => '<p>Contenido del post sin base64.</p>',
            'type'         => 'noticia',
            'is_published' => false,
        ], $overrides);
    }

    // =========================================================================
    // StorePostRequest — required fields
    // =========================================================================

    public function test_store_requires_title(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/posts', $this->basePayload(['title' => '']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['title']);
    }

    public function test_store_requires_body(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/posts', $this->basePayload(['body' => '']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['body']);
    }

    public function test_store_requires_valid_type_enum(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/posts', $this->basePayload(['type' => 'promo']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['type']);
    }

    public function test_store_accepts_all_valid_type_values(): void
    {
        Sanctum::actingAs($this->admin());

        $validTypes = ['noticia', 'nuevo_curso', 'oferta', 'evento', 'lanzamiento', 'certificacion', 'contenido'];

        foreach ($validTypes as $type) {
            $response = $this->postJson('/api/admin/posts', $this->basePayload([
                'title' => "Post {$type}",
                'type'  => $type,
            ]));

            $response->assertStatus(201, "Type [{$type}] should be valid");
        }
    }

    public function test_store_rejects_base64_in_body(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/posts', $this->basePayload([
            'body' => '<p>Hello</p><img src="data:image/png;base64,abc123">',
        ]))->assertStatus(422)
           ->assertJsonValidationErrors(['body']);
    }

    public function test_store_rejects_invalid_cta_url(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/posts', $this->basePayload([
            'cta_url' => 'not-a-url',
        ]))->assertStatus(422)
           ->assertJsonValidationErrors(['cta_url']);
    }

    public function test_store_accepts_null_cta_url(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/posts', $this->basePayload([
            'cta_url' => null,
        ]))->assertStatus(201);
    }

    public function test_store_accepts_valid_cta_url(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/posts', $this->basePayload([
            'cta_url' => 'https://example.com/promo',
        ]))->assertStatus(201);
    }

    public function test_store_rejects_cover_image_exceeding_2mb(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->admin());

        $bigFile = UploadedFile::fake()->create('cover.jpg', 3000, 'image/jpeg');

        $this->postJson('/api/admin/posts', array_merge($this->basePayload(), [
            'cover_image' => $bigFile,
        ]))->assertStatus(422)
           ->assertJsonValidationErrors(['cover_image']);
    }

    public function test_store_accepts_cover_image_under_2mb(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->admin());

        $file = UploadedFile::fake()->image('cover.jpg', 100, 100);

        $this->postJson('/api/admin/posts', array_merge($this->basePayload(), [
            'cover_image' => $file,
        ]))->assertStatus(201);
    }

    // =========================================================================
    // UpdatePostRequest — slug unique ignore self
    // =========================================================================

    public function test_update_slug_must_be_unique_ignoring_self(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        // Create two posts
        $post1 = Post::factory()->create(['slug' => 'slug-one']);
        $post2 = Post::factory()->create(['slug' => 'slug-two']);

        // Try to set post2's slug to post1's slug → should fail
        $this->postJson("/api/admin/posts/{$post2->id}", [
            'slug' => 'slug-one',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['slug']);
    }

    public function test_update_can_keep_same_slug(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $post = Post::factory()->create(['slug' => 'keep-slug']);

        // Sending same slug on self-update must not 422
        $this->postJson("/api/admin/posts/{$post->id}", [
            'slug' => 'keep-slug',
        ])->assertStatus(200);
    }
}
