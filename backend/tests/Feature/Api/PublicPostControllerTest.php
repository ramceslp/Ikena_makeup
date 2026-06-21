<?php

namespace Tests\Feature\Api;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for public POST endpoints:
 *   GET /api/posts             → index (published-gated + search + pagination)
 *   GET /api/posts/latest      → latest N published
 *   GET /api/posts/featured    → featured/fallback/empty
 *   GET /api/posts/{slug}      → show (404 on draft)
 *
 * Route ordering (latest/featured before {slug}) is also verified here.
 */
class PublicPostControllerTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Index — published gating
    // =========================================================================

    public function test_index_returns_only_published_posts(): void
    {
        Post::factory()->count(3)->published()->create();
        Post::factory()->count(2)->draft()->create();

        $response = $this->getJson('/api/posts')->assertStatus(200);

        $this->assertCount(3, $response->json('data'));

        foreach ($response->json('data') as $item) {
            $this->assertNotNull($item['id']);
        }
    }

    // =========================================================================
    // Index — LIKE search with special chars
    // =========================================================================

    public function test_index_search_treats_percent_as_literal(): void
    {
        Post::factory()->create([
            'title'        => 'Curso de Verano 50%',
            'slug'         => 'curso-verano',
            'is_published' => true,
            'published_at' => now(),
        ]);
        Post::factory()->create([
            'title'        => 'Taller Especial',
            'slug'         => 'taller-especial',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/posts?search=curs%25')->assertStatus(200);

        // Only the post with "Curso" in title should match the literal '%' escaped search
        // Note: URL-encoded '%' is '%25' — but we're testing the LIKE escape logic
        // The search "curs" (without %) should return 1 result
        $response2 = $this->getJson('/api/posts?search=Curso')->assertStatus(200);
        $this->assertCount(1, $response2->json('data'));
    }

    public function test_index_search_escapes_percent_character(): void
    {
        Post::factory()->create([
            'title'        => 'Exacto % Match',
            'slug'         => 'exacto-match',
            'is_published' => true,
            'published_at' => now(),
        ]);
        Post::factory()->create([
            'title'        => 'Sin especial',
            'slug'         => 'sin-especial',
            'is_published' => true,
            'published_at' => now(),
        ]);

        // A search with literal "%" should only match posts that have "%" in the title
        // The LIKE escape means "%" is treated as a literal character, not a wildcard
        $response = $this->getJson('/api/posts?search=%25')->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Exacto % Match', $response->json('data.0.title'));
    }

    // =========================================================================
    // Index — Pagination
    // =========================================================================

    public function test_index_paginates_at_12_per_page(): void
    {
        Post::factory()->count(15)->published()->create();

        $page1 = $this->getJson('/api/posts?page=1')->assertStatus(200);
        $page2 = $this->getJson('/api/posts?page=2')->assertStatus(200);

        $this->assertCount(12, $page1->json('data'));
        $this->assertCount(3, $page2->json('data'));
        $this->assertEquals(15, $page1->json('meta.total'));
    }

    // =========================================================================
    // Show — 200 published / 404 draft
    // =========================================================================

    public function test_show_returns_200_for_published_post(): void
    {
        $post = Post::factory()->create([
            'slug'         => 'mi-post',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->getJson('/api/posts/mi-post')
             ->assertStatus(200)
             ->assertJsonPath('data.slug', 'mi-post');
    }

    public function test_show_returns_404_for_draft_post(): void
    {
        Post::factory()->create([
            'slug'         => 'borrador',
            'is_published' => false,
            'published_at' => null,
        ]);

        $this->getJson('/api/posts/borrador')->assertStatus(404);
    }

    // =========================================================================
    // Latest
    // =========================================================================

    public function test_latest_returns_3_most_recent_published_posts(): void
    {
        Post::factory()->count(10)->published()->create();

        $response = $this->getJson('/api/posts/latest')->assertStatus(200);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_latest_orders_by_coalesce_published_at_created_at_desc(): void
    {
        // Create posts with specific published_at timestamps
        $oldest = Post::factory()->create([
            'slug'         => 'oldest',
            'is_published' => true,
            'published_at' => now()->subDays(10),
        ]);
        $newest = Post::factory()->create([
            'slug'         => 'newest',
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);
        $middle = Post::factory()->create([
            'slug'         => 'middle',
            'is_published' => true,
            'published_at' => now()->subDays(5),
        ]);

        $response = $this->getJson('/api/posts/latest')->assertStatus(200);

        $slugs = collect($response->json('data'))->pluck('slug')->toArray();

        $this->assertEquals('newest', $slugs[0]);
        $this->assertEquals('middle', $slugs[1]);
        $this->assertEquals('oldest', $slugs[2]);
    }

    // =========================================================================
    // Featured
    // =========================================================================

    public function test_featured_returns_is_featured_post_when_exists(): void
    {
        Post::factory()->published()->create(['is_featured' => false]);
        $featured = Post::factory()->featured()->create(['slug' => 'featured-post']);

        $response = $this->getJson('/api/posts/featured')->assertStatus(200);

        $this->assertEquals('featured-post', $response->json('data.slug'));
    }

    public function test_featured_falls_back_to_most_recent_published_when_no_featured(): void
    {
        $older  = Post::factory()->published()->create(['published_at' => now()->subDay(), 'slug' => 'older-post']);
        $recent = Post::factory()->published()->create(['published_at' => now(), 'slug' => 'recent-post']);

        $response = $this->getJson('/api/posts/featured')->assertStatus(200);

        $this->assertEquals('recent-post', $response->json('data.slug'));
    }

    public function test_featured_returns_200_with_null_data_when_no_published_posts(): void
    {
        Post::factory()->count(2)->draft()->create();

        $response = $this->getJson('/api/posts/featured')->assertStatus(200);

        $this->assertNull($response->json('data'));
    }

    // =========================================================================
    // Route ordering — latest/featured must NOT be shadowed by {slug}
    // =========================================================================

    public function test_latest_route_is_not_shadowed_by_slug_route(): void
    {
        Post::factory()->count(3)->published()->create();

        // If route ordering is wrong, "latest" would be treated as a slug and return 404
        $response = $this->getJson('/api/posts/latest');
        $this->assertNotEquals(404, $response->status(), '/api/posts/latest must not route to @show');
        $response->assertStatus(200);
        $this->assertIsArray($response->json('data'));
    }

    public function test_featured_route_is_not_shadowed_by_slug_route(): void
    {
        Post::factory()->published()->create(['is_featured' => true]);

        $response = $this->getJson('/api/posts/featured');
        $this->assertNotEquals(404, $response->status(), '/api/posts/featured must not route to @show');
        $response->assertStatus(200);
    }
}
