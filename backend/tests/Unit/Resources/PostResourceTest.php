<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\PostCardResource;
use App\Http\Resources\PostDetailResource;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PostResourceTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(bool $asAdmin = false): Request
    {
        $request = Request::create('/');

        if ($asAdmin) {
            $admin = User::factory()->admin()->create();
            $request->setUserResolver(fn () => $admin);
        }

        return $request;
    }

    // =========================================================================
    // PostCardResource
    // =========================================================================

    public function test_post_card_resource_contains_required_fields(): void
    {
        $post = Post::factory()->create([
            'title'        => 'Nuevo Curso',
            'slug'         => 'nuevo-curso',
            'excerpt'      => 'Un curso increíble.',
            'type'         => 'nuevo_curso',
            'is_featured'  => true,
            'cta_label'    => 'Ver curso',
            'cta_url'      => 'https://example.com',
            'published_at' => now(),
            'is_published' => true,
        ]);

        $resource = (new PostCardResource($post))->toArray($this->makeRequest());

        $this->assertArrayHasKey('id', $resource);
        $this->assertArrayHasKey('title', $resource);
        $this->assertArrayHasKey('slug', $resource);
        $this->assertArrayHasKey('excerpt', $resource);
        $this->assertArrayHasKey('type', $resource);
        $this->assertArrayHasKey('is_featured', $resource);
        $this->assertArrayHasKey('cta_label', $resource);
        $this->assertArrayHasKey('cta_url', $resource);
        $this->assertArrayHasKey('published_at', $resource);
        $this->assertArrayHasKey('cover_image_url', $resource);
    }

    public function test_post_card_resource_cover_image_url_is_null_without_cover(): void
    {
        $post     = Post::factory()->create(['cover_image_path' => null]);
        $resource = (new PostCardResource($post))->toArray($this->makeRequest());

        $this->assertNull($resource['cover_image_url']);
    }

    // =========================================================================
    // PostDetailResource
    // =========================================================================

    public function test_post_detail_resource_contains_body_and_author(): void
    {
        $author = User::factory()->create(['name' => 'Ana Torres']);
        $post   = Post::factory()->create([
            'author_id' => $author->id,
            'body'      => '<p>Contenido completo del post.</p>',
        ]);
        $post->load('author', 'images');

        $resource = (new PostDetailResource($post))->toArray($this->makeRequest());

        $this->assertArrayHasKey('body', $resource);
        $this->assertEquals('<p>Contenido completo del post.</p>', $resource['body']);
        $this->assertArrayHasKey('author', $resource);
        $this->assertEquals('Ana Torres', $resource['author']['name']);
    }

    public function test_post_detail_resource_contains_images_array(): void
    {
        $post = Post::factory()->create();
        PostImage::factory()->create(['post_id' => $post->id, 'path' => 'posts/images/a.jpg', 'sort_order' => 0]);
        PostImage::factory()->create(['post_id' => $post->id, 'path' => 'posts/images/b.jpg', 'sort_order' => 1]);
        $post->load('author', 'images');

        $resource = (new PostDetailResource($post))->toArray($this->makeRequest());

        $this->assertArrayHasKey('images', $resource);
        $this->assertCount(2, $resource['images']);
        $this->assertArrayHasKey('url', $resource['images'][0]);
        $this->assertArrayHasKey('sort_order', $resource['images'][0]);
    }
}
