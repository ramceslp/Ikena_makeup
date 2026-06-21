<?php

namespace Tests\Unit\Models;

use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Fillable
    // =========================================================================

    public function test_post_has_expected_fillable_fields(): void
    {
        $post     = new Post();
        $fillable = $post->getFillable();

        $expected = [
            'title', 'slug', 'excerpt', 'cover_image_path', 'body',
            'type', 'is_featured', 'cta_label', 'cta_url',
            'is_published', 'published_at', 'author_id',
        ];

        foreach ($expected as $field) {
            $this->assertContains($field, $fillable, "Post must have [{$field}] in fillable");
        }
    }

    // =========================================================================
    // Casts
    // =========================================================================

    public function test_post_is_published_cast_to_boolean(): void
    {
        $post = Post::factory()->create(['is_published' => 1]);

        $this->assertIsBool($post->is_published);
        $this->assertTrue($post->is_published);
    }

    public function test_post_is_featured_cast_to_boolean(): void
    {
        $post = Post::factory()->create(['is_featured' => 1]);

        $this->assertIsBool($post->is_featured);
        $this->assertTrue($post->is_featured);
    }

    public function test_post_published_at_cast_to_datetime(): void
    {
        $post = Post::factory()->create(['published_at' => '2026-01-15 10:00:00']);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $post->published_at);
    }

    // =========================================================================
    // scopePublished
    // =========================================================================

    public function test_scope_published_returns_only_published_posts(): void
    {
        Post::factory()->create(['is_published' => true]);
        Post::factory()->create(['is_published' => true]);
        Post::factory()->create(['is_published' => false]);

        $results = Post::published()->get();

        $this->assertCount(2, $results);
        $results->each(fn ($p) => $this->assertTrue($p->is_published));
    }

    // =========================================================================
    // getCoverImageUrlAttribute
    // =========================================================================

    public function test_cover_image_url_returns_null_when_path_is_null(): void
    {
        $post = Post::factory()->create(['cover_image_path' => null]);

        $this->assertNull($post->cover_image_url);
    }

    public function test_cover_image_url_returns_url_when_path_is_set(): void
    {
        $post = Post::factory()->create(['cover_image_path' => 'posts/covers/test.jpg']);

        $this->assertNotNull($post->cover_image_url);
        $this->assertStringContainsString('posts/covers/test.jpg', $post->cover_image_url);
    }

    public function test_cover_image_url_returns_absolute_url_as_is(): void
    {
        $post = Post::factory()->create(['cover_image_path' => 'https://cdn.example.com/cover.jpg']);

        $this->assertEquals('https://cdn.example.com/cover.jpg', $post->cover_image_url);
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function test_post_belongs_to_author_user(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['author_id' => $user->id]);

        $this->assertInstanceOf(User::class, $post->author);
        $this->assertEquals($user->id, $post->author->id);
    }

    public function test_post_has_many_post_images_ordered_by_sort_order(): void
    {
        $post = Post::factory()->create();

        PostImage::factory()->create(['post_id' => $post->id, 'sort_order' => 2]);
        PostImage::factory()->create(['post_id' => $post->id, 'sort_order' => 0]);
        PostImage::factory()->create(['post_id' => $post->id, 'sort_order' => 1]);

        $images = $post->images;

        $this->assertCount(3, $images);
        $this->assertEquals(0, $images->first()->sort_order);
        $this->assertEquals(2, $images->last()->sort_order);
    }
}
