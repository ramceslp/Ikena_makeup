<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature tests for GET /api/products and GET /api/products/{slug}.
 *
 * Covers PC-2 (catalog list + filters + pagination) and PC-3 (product detail).
 * All endpoints are public — no auth required.
 */
class PublicProductControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // GET /api/products — Paginated list
    // -------------------------------------------------------------------------

    public function test_list_returns_200_with_paginated_structure(): void
    {
        Product::factory()->count(3)->published()->create();

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_list_paginates_at_12_per_page(): void
    {
        Product::factory()->count(15)->published()->create();

        $page1 = $this->getJson('/api/products?page=1');
        $page2 = $this->getJson('/api/products?page=2');

        $page1->assertStatus(200);
        $this->assertCount(12, $page1->json('data'));
        $this->assertEquals(15, $page1->json('meta.total'));

        $page2->assertStatus(200);
        $this->assertCount(3, $page2->json('data'));
    }

    public function test_list_returns_only_published_products(): void
    {
        $published   = Product::factory()->published()->create();
        $unpublished = Product::factory()->unpublished()->create();

        $response = $this->getJson('/api/products');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($published->id, $ids);
        $this->assertNotContains($unpublished->id, $ids);
    }

    public function test_unpublished_product_is_excluded_from_list(): void
    {
        Product::factory()->count(2)->published()->create();
        Product::factory()->unpublished()->create();

        $response = $this->getJson('/api/products');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Category filter
    // -------------------------------------------------------------------------

    public function test_category_filter_narrows_results(): void
    {
        $brushes  = Category::factory()->create(['name' => 'Brushes', 'slug' => 'brushes']);
        $palettes = Category::factory()->create(['name' => 'Palettes', 'slug' => 'palettes']);

        Product::factory()->count(3)->published()->create(['category_id' => $brushes->id]);
        Product::factory()->count(2)->published()->create(['category_id' => $palettes->id]);

        $response = $this->getJson('/api/products?category=brushes');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(3, $data);
        foreach ($data as $item) {
            $this->assertEquals('brushes', $item['category']['slug']);
        }
    }

    // -------------------------------------------------------------------------
    // Stock state filter (PC-2: in_stock / out_of_stock param — separate from label)
    // -------------------------------------------------------------------------

    public function test_in_stock_filter_excludes_zero_stock_products(): void
    {
        Product::factory()->published()->create(['stock_qty' => 10, 'slug' => 'p-in-stock']);
        Product::factory()->published()->create(['stock_qty' => 0, 'slug' => 'p-out-stock']);

        $response = $this->getJson('/api/products?stock_state=in_stock');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertGreaterThan(0, $data[0]['stock_qty']);
    }

    public function test_out_of_stock_filter_returns_only_zero_stock_products(): void
    {
        Product::factory()->published()->create(['stock_qty' => 10, 'slug' => 'prod-in1']);
        Product::factory()->published()->create(['stock_qty' => 0, 'slug' => 'prod-out1']);
        Product::factory()->published()->create(['stock_qty' => 0, 'slug' => 'prod-out2']);
        Product::factory()->published()->create(['stock_qty' => 0, 'slug' => 'prod-out3']);

        $response = $this->getJson('/api/products?stock_state=out_of_stock');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(3, $data);
        foreach ($data as $item) {
            $this->assertEquals(0, $item['stock_qty']);
        }
    }

    // -------------------------------------------------------------------------
    // Price filters
    // -------------------------------------------------------------------------

    public function test_min_price_filter_narrows_results(): void
    {
        Product::factory()->published()->create(['price' => 50.00, 'slug' => 'prd-50']);
        Product::factory()->published()->create(['price' => 120.00, 'slug' => 'prd-120']);
        Product::factory()->published()->create(['price' => 200.00, 'slug' => 'prd-200']);

        $response = $this->getJson('/api/products?min_price=100&max_price=150');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('120.00', $data[0]['price']);
    }

    public function test_max_price_filter_narrows_results(): void
    {
        Product::factory()->published()->create(['price' => 30.00, 'slug' => 'prd-low']);
        Product::factory()->published()->create(['price' => 90.00, 'slug' => 'prd-high']);

        $response = $this->getJson('/api/products?max_price=50');

        $response->assertStatus(200);
        $prices = collect($response->json('data'))->pluck('price')->toArray();
        foreach ($prices as $price) {
            $this->assertLessThanOrEqual(50, (float) $price);
        }
    }

    public function test_min_price_zero_is_not_silently_ignored(): void
    {
        Product::factory()->published()->create(['price' => 0.00, 'slug' => 'prd-free']);
        Product::factory()->published()->create(['price' => 50.00, 'slug' => 'prd-paid']);

        $response = $this->getJson('/api/products?min_price=0');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Search filter
    // -------------------------------------------------------------------------

    public function test_search_filter_matches_title(): void
    {
        Product::factory()->published()->create([
            'title' => 'Master Palette Pro',
            'slug'  => 'master-palette-pro',
        ]);
        Product::factory()->published()->create([
            'title' => 'Brush Set Deluxe',
            'slug'  => 'brush-set-deluxe',
        ]);

        $response = $this->getJson('/api/products?search=palette');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Master Palette Pro', $data[0]['title']);
    }

    public function test_search_does_not_return_unpublished_matching_product(): void
    {
        Product::factory()->published()->create([
            'title' => 'Contour Kit Published',
            'slug'  => 'contour-kit-pub',
        ]);
        Product::factory()->unpublished()->create([
            'title' => 'Contour Kit Draft',
            'slug'  => 'contour-kit-draft',
        ]);

        $response = $this->getJson('/api/products?search=contour');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Contour Kit Published', $response->json('data.0.title'));
    }

    // -------------------------------------------------------------------------
    // Sort
    // -------------------------------------------------------------------------

    public function test_sort_price_asc_orders_correctly(): void
    {
        Product::factory()->published()->create(['price' => 200.00, 'slug' => 'p-hi']);
        Product::factory()->published()->create(['price' => 50.00, 'slug' => 'p-lo']);
        Product::factory()->published()->create(['price' => 120.00, 'slug' => 'p-mid']);

        $response = $this->getJson('/api/products?sort=price_asc');

        $response->assertStatus(200);
        $prices = collect($response->json('data'))->pluck('price')->map(fn ($p) => (float) $p)->toArray();
        $sorted = $prices;
        sort($sorted);
        $this->assertEquals($sorted, $prices);
    }

    public function test_sort_price_desc_orders_correctly(): void
    {
        Product::factory()->published()->create(['price' => 50.00, 'slug' => 'p-lo2']);
        Product::factory()->published()->create(['price' => 200.00, 'slug' => 'p-hi2']);
        Product::factory()->published()->create(['price' => 120.00, 'slug' => 'p-mid2']);

        $response = $this->getJson('/api/products?sort=price_desc');

        $response->assertStatus(200);
        $prices = collect($response->json('data'))->pluck('price')->map(fn ($p) => (float) $p)->toArray();
        $sorted = $prices;
        rsort($sorted);
        $this->assertEquals($sorted, $prices);
    }

    public function test_default_sort_is_newest_first(): void
    {
        $older = Product::factory()->published()->create(['slug' => 'older-prod']);
        $older->forceFill(['created_at' => now()->subSeconds(5)])->save();

        $newer = Product::factory()->published()->create(['slug' => 'newer-prod']);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertLessThan(
            array_search($older->id, $ids),
            array_search($newer->id, $ids)
        );
    }

    public function test_sort_newest_explicit_orders_by_created_at_desc(): void
    {
        $older = Product::factory()->published()->create(['slug' => 'oldest-prod']);
        $older->forceFill(['created_at' => now()->subSeconds(10)])->save();

        $newer = Product::factory()->published()->create(['slug' => 'newest-prod']);

        $response = $this->getJson('/api/products?sort=newest');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertLessThan(
            array_search($older->id, $ids),
            array_search($newer->id, $ids)
        );
    }

    // -------------------------------------------------------------------------
    // Card resource structure
    // -------------------------------------------------------------------------

    public function test_list_response_includes_expected_card_fields(): void
    {
        Product::factory()->published()->create();

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => [
                             'id', 'title', 'slug', 'description',
                             'price', 'stock_qty', 'stock_state',
                             'thumbnail', 'images_count',
                         ],
                     ],
                 ]);

        // is_published must not be in the public response
        $item = $response->json('data.0');
        $this->assertArrayNotHasKey('is_published', $item);
    }

    public function test_thumbnail_is_first_image_by_sort_order(): void
    {
        Storage::fake('public');

        $product = Product::factory()->published()->create();
        ProductImage::factory()->create([
            'product_id' => $product->id,
            'path'       => 'products/second.jpg',
            'sort_order' => 2,
        ]);
        ProductImage::factory()->create([
            'product_id' => $product->id,
            'path'       => 'products/first.jpg',
            'sort_order' => 0,
        ]);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200);
        $item = collect($response->json('data'))->firstWhere('id', $product->id);
        $this->assertNotNull($item);
        $this->assertStringContainsString('products/first.jpg', $item['thumbnail']);
    }

    // -------------------------------------------------------------------------
    // GET /api/products/{slug} — Detail
    // -------------------------------------------------------------------------

    public function test_show_returns_200_for_published_product(): void
    {
        Storage::fake('public');

        $product = Product::factory()->published()->create(['slug' => 'master-palette']);
        ProductImage::factory()->create(['product_id' => $product->id, 'path' => 'products/img0.jpg', 'sort_order' => 0]);
        ProductImage::factory()->create(['product_id' => $product->id, 'path' => 'products/img1.jpg', 'sort_order' => 1]);

        $response = $this->getJson('/api/products/master-palette');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         'id', 'title', 'slug', 'description',
                         'price', 'stock_qty', 'stock_state',
                         'thumbnail', 'images_count', 'images',
                     ],
                 ]);
    }

    public function test_show_returns_full_description_not_truncated(): void
    {
        $longDesc = str_repeat('b', 300);
        $product  = Product::factory()->published()->create([
            'slug'        => 'full-desc-product',
            'description' => $longDesc,
        ]);

        $response = $this->getJson('/api/products/full-desc-product');

        $response->assertStatus(200);
        $this->assertEquals($longDesc, $response->json('data.description'));
    }

    public function test_show_returns_images_in_sort_order(): void
    {
        Storage::fake('public');

        $product = Product::factory()->published()->create(['slug' => 'gallery-sort']);
        ProductImage::factory()->create(['product_id' => $product->id, 'path' => 'products/c.jpg', 'sort_order' => 2]);
        ProductImage::factory()->create(['product_id' => $product->id, 'path' => 'products/a.jpg', 'sort_order' => 0]);
        ProductImage::factory()->create(['product_id' => $product->id, 'path' => 'products/b.jpg', 'sort_order' => 1]);

        $response = $this->getJson('/api/products/gallery-sort');

        $response->assertStatus(200);
        $orders = collect($response->json('data.images'))->pluck('sort_order')->toArray();
        $this->assertEquals([0, 1, 2], $orders);
    }

    public function test_show_image_urls_are_absolute(): void
    {
        $product = Product::factory()->published()->create(['slug' => 'abs-url-prod']);
        ProductImage::factory()->create([
            'product_id' => $product->id,
            'path'       => 'https://cdn.example.com/products/photo.jpg',
            'sort_order' => 0,
        ]);

        $response = $this->getJson('/api/products/abs-url-prod');

        $response->assertStatus(200);
        $url = $response->json('data.images.0.url');
        $this->assertNotNull($url);
        $this->assertEquals('https://cdn.example.com/products/photo.jpg', $url);
    }

    public function test_show_does_not_expose_is_published_to_public(): void
    {
        Product::factory()->published()->create(['slug' => 'no-pub-flag']);

        $response = $this->getJson('/api/products/no-pub-flag');

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('is_published', $response->json('data'));
    }

    public function test_show_returns_404_for_unpublished_product(): void
    {
        Product::factory()->unpublished()->create(['slug' => 'draft-product']);

        $response = $this->getJson('/api/products/draft-product');

        $response->assertStatus(404);
    }

    public function test_show_returns_404_for_missing_slug(): void
    {
        $response = $this->getJson('/api/products/does-not-exist');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Wildcard escape — FIX 1 regression guard
    // -------------------------------------------------------------------------

    public function test_percent_wildcard_search_returns_empty_when_no_literal_match(): void
    {
        // Seed 3 published products whose titles contain NO percent sign.
        // After escaping, ?search=% becomes LIKE "%\%%", which matches only
        // rows that literally contain %. Since none do, data must be empty.
        Product::factory()->published()->create(['title' => 'Brush Set Alpha',  'slug' => 'brush-alpha']);
        Product::factory()->published()->create(['title' => 'Lip Gloss Shine',  'slug' => 'lip-shine']);
        Product::factory()->published()->create(['title' => 'Foundation Matte', 'slug' => 'foundation-matte']);

        $response = $this->getJson('/api/products?search=' . urlencode('%'));

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'), 'Percent sign must be treated as a literal, not a wildcard.');
    }

    public function test_percent_literal_is_matched_when_present(): void
    {
        // Seed one product whose title CONTAINS a literal percent sign and one that does not.
        // Searching for "%" must return ONLY the product that literally has "%".
        // This proves the explicit ESCAPE clause causes "%" to be treated as a literal
        // on SQLite (:memory:) — structural coincidence would pass the negative test but
        // fail this positive one.
        $withPercent    = Product::factory()->published()->create([
            'title' => '50% OFF Brush Set',
            'slug'  => 'fifty-pct-brush',
        ]);
        $withoutPercent = Product::factory()->published()->create([
            'title' => 'Brush Set Alpha',
            'slug'  => 'brush-alpha-nopct',
        ]);

        $response = $this->getJson('/api/products?search=' . urlencode('%'));

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($withPercent->id, $ids, 'Product with literal "%" in title must be in results.');
        $this->assertNotContains($withoutPercent->id, $ids, 'Product without "%" must not appear in results.');
    }

    // -------------------------------------------------------------------------
    // Edge-case sort / filter contracts
    // -------------------------------------------------------------------------

    public function test_unknown_sort_value_falls_back_to_newest(): void
    {
        $older = Product::factory()->published()->create(['slug' => 'sort-fallback-old']);
        $older->forceFill(['created_at' => now()->subSeconds(10)])->save();

        $newer = Product::factory()->published()->create(['slug' => 'sort-fallback-new']);

        $response = $this->getJson('/api/products?sort=garbage');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        // Newer product must appear before the older one (desc created_at).
        $this->assertLessThan(
            array_search($older->id, $ids),
            array_search($newer->id, $ids)
        );
    }

    public function test_unknown_stock_state_value_is_ignored(): void
    {
        Product::factory()->published()->create(['slug' => 'bogus-stock-a', 'stock_qty' => 5]);
        Product::factory()->published()->create(['slug' => 'bogus-stock-b', 'stock_qty' => 0]);

        $response = $this->getJson('/api/products?stock_state=bogus');

        $response->assertStatus(200);
        // Both products must be returned — the unknown value must not filter anything.
        $this->assertCount(2, $response->json('data'));
    }

    public function test_inverted_price_range_returns_empty_result(): void
    {
        Product::factory()->published()->create(['price' => 150.00, 'slug' => 'inv-range-prod']);

        $response = $this->getJson('/api/products?min_price=500&max_price=100');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    public function test_search_matches_description_only(): void
    {
        Product::factory()->published()->create([
            'title'       => 'Plain Product Name',
            'description' => 'Contains the term highlighter in description only.',
            'slug'        => 'desc-only-match',
        ]);
        Product::factory()->published()->create([
            'title'       => 'Another Item',
            'description' => 'Totally unrelated text here.',
            'slug'        => 'no-match-prod',
        ]);

        $response = $this->getJson('/api/products?search=highlighter');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Plain Product Name', $data[0]['title']);
    }
}
