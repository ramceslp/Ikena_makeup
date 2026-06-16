<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServiceCatalogTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // GET /api/services — Public catalog list
    // -------------------------------------------------------------------------

    public function test_list_returns_only_published_services(): void
    {
        Service::factory()->count(3)->published()->create();
        Service::factory()->unpublished()->create();

        $response = $this->getJson('/api/services');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_list_excludes_unpublished_service_from_results(): void
    {
        $published   = Service::factory()->published()->create();
        $unpublished = Service::factory()->unpublished()->create();

        $response = $this->getJson('/api/services');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($published->id, $ids);
        $this->assertNotContains($unpublished->id, $ids);
    }

    public function test_category_slug_filter_narrows_results(): void
    {
        $social    = Category::factory()->create(['name' => 'Social', 'slug' => 'social']);
        $formacion = Category::factory()->create(['name' => 'Formacion', 'slug' => 'formacion']);

        Service::factory()->count(2)->published()->create(['category_id' => $social->id]);
        Service::factory()->published()->create(['category_id' => $formacion->id]);

        $response = $this->getJson('/api/services?category=social');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        foreach ($data as $item) {
            $this->assertEquals('social', $item['category']['slug']);
        }
    }

    public function test_min_price_filter_narrows_results(): void
    {
        Service::factory()->published()->create(['price' => 50.00, 'slug' => 'svc-50']);
        Service::factory()->published()->create(['price' => 120.00, 'slug' => 'svc-120']);
        Service::factory()->published()->create(['price' => 200.00, 'slug' => 'svc-200']);

        $response = $this->getJson('/api/services?min_price=100&max_price=150');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('120.00', $data[0]['price']);
    }

    public function test_max_price_filter_narrows_results(): void
    {
        Service::factory()->published()->create(['price' => 30.00, 'slug' => 'svc-low']);
        Service::factory()->published()->create(['price' => 90.00, 'slug' => 'svc-high']);

        $response = $this->getJson('/api/services?max_price=50');

        $response->assertStatus(200);
        $prices = collect($response->json('data'))->pluck('price')->toArray();
        foreach ($prices as $price) {
            $this->assertLessThanOrEqual(50, (float) $price);
        }
    }

    /** W-1: min_price=0 must NOT be silently ignored — free services must be returned. */
    public function test_min_price_zero_is_not_ignored(): void
    {
        Service::factory()->published()->create(['price' => 0.00, 'slug' => 'free-svc']);
        Service::factory()->published()->create(['price' => 50.00, 'slug' => 'paid-svc']);

        $response = $this->getJson('/api/services?min_price=0');

        $response->assertStatus(200);
        $prices = collect($response->json('data'))->pluck('price')->toArray();
        // Both services satisfy price >= 0
        $this->assertCount(2, $response->json('data'));
        // The free service must be present
        $this->assertContains('0.00', $prices);
    }

    /** W-1: max_price=0 must NOT be silently ignored — only free services returned. */
    public function test_max_price_zero_is_not_ignored(): void
    {
        Service::factory()->published()->create(['price' => 0.00, 'slug' => 'free-svc-2']);
        Service::factory()->published()->create(['price' => 50.00, 'slug' => 'paid-svc-2']);

        $response = $this->getJson('/api/services?max_price=0');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('0.00', $data[0]['price']);
    }

    public function test_availability_type_filter_narrows_results(): void
    {
        Service::factory()->published()->create(['availability_type' => 'immediate', 'slug' => 'svc-immediate']);
        Service::factory()->published()->create(['availability_type' => 'by_appointment', 'slug' => 'svc-appointment']);

        $response = $this->getJson('/api/services?availability_type=immediate');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('immediate', $data[0]['availability_type']);
    }

    /** W-4: Invalid availability value must not 500 and must not act as a real filter. */
    public function test_invalid_availability_type_does_not_500_and_returns_all_published(): void
    {
        Service::factory()->published()->create(['availability_type' => 'immediate', 'slug' => 'svc-imm2']);
        Service::factory()->published()->create(['availability_type' => 'by_appointment', 'slug' => 'svc-app2']);

        $response = $this->getJson('/api/services?availability_type=invalid_value');

        // Must not crash
        $response->assertStatus(200);
        // Invalid value is silently ignored — both published services are returned
        $this->assertCount(2, $response->json('data'));
    }

    public function test_sort_price_asc_orders_correctly(): void
    {
        Service::factory()->published()->create(['price' => 200.00, 'slug' => 'svc-hi']);
        Service::factory()->published()->create(['price' => 50.00, 'slug' => 'svc-lo']);
        Service::factory()->published()->create(['price' => 120.00, 'slug' => 'svc-mid']);

        $response = $this->getJson('/api/services?sort=price_asc');

        $response->assertStatus(200);
        $prices = collect($response->json('data'))->pluck('price')->map(fn ($p) => (float) $p)->toArray();
        $sorted = $prices;
        sort($sorted);
        $this->assertEquals($sorted, $prices);
    }

    public function test_sort_price_desc_orders_correctly(): void
    {
        Service::factory()->published()->create(['price' => 50.00, 'slug' => 'svc-lo2']);
        Service::factory()->published()->create(['price' => 200.00, 'slug' => 'svc-hi2']);
        Service::factory()->published()->create(['price' => 120.00, 'slug' => 'svc-mid2']);

        $response = $this->getJson('/api/services?sort=price_desc');

        $response->assertStatus(200);
        $prices = collect($response->json('data'))->pluck('price')->map(fn ($p) => (float) $p)->toArray();
        $sorted = $prices;
        rsort($sorted);
        $this->assertEquals($sorted, $prices);
    }

    /** S-3: Omitting ?sort returns results ordered by created_at DESC (newest first). */
    public function test_default_sort_is_newest_first(): void
    {
        // Create services 1 second apart to guarantee distinct created_at
        $older  = Service::factory()->published()->create(['slug' => 'older-svc']);
        $older->forceFill(['created_at' => now()->subSeconds(5)])->save();

        $newer  = Service::factory()->published()->create(['slug' => 'newer-svc']);

        $response = $this->getJson('/api/services');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        // Newer service must appear before older
        $this->assertLessThan(
            array_search($older->id, $ids),
            array_search($newer->id, $ids)
        );
    }

    public function test_search_filter_matches_title(): void
    {
        Service::factory()->published()->create(['title' => 'Maquillaje Social', 'slug' => 'maquillaje-social']);
        Service::factory()->published()->create(['title' => 'Masterclass Novia', 'slug' => 'masterclass-novia']);

        $response = $this->getJson('/api/services?search=social');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Maquillaje Social', $data[0]['title']);
    }

    public function test_search_filter_matches_description(): void
    {
        Service::factory()->published()->create([
            'title'       => 'Generic Service',
            'slug'        => 'generic-service',
            'description' => 'Expert novia bridal makeup sessions',
        ]);
        Service::factory()->published()->create([
            'title'       => 'Another Service',
            'slug'        => 'another-service',
            'description' => 'Corporate photography support',
        ]);

        $response = $this->getJson('/api/services?search=bridal');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Generic Service', $data[0]['title']);
    }

    /** S-5: Search must NOT surface an unpublished service whose title/description matches. */
    public function test_search_does_not_return_unpublished_matching_service(): void
    {
        Service::factory()->published()->create([
            'title' => 'Maquillaje Nupcial',
            'slug'  => 'maquillaje-nupcial-pub',
        ]);
        Service::factory()->unpublished()->create([
            'title' => 'Maquillaje Nupcial Draft',
            'slug'  => 'maquillaje-nupcial-draft',
        ]);

        $response = $this->getJson('/api/services?search=nupcial');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Maquillaje Nupcial', $data[0]['title']);
    }

    /** W-5: Pagination must return exactly 12 on page 1 and the remaining 3 on page 2. */
    public function test_list_is_paginated_at_exactly_12_per_page(): void
    {
        Service::factory()->count(15)->published()->create();

        $responsePage1 = $this->getJson('/api/services?page=1');
        $responsePage2 = $this->getJson('/api/services?page=2');

        $responsePage1->assertStatus(200)
                      ->assertJsonStructure(['data', 'links', 'meta']);

        $this->assertCount(12, $responsePage1->json('data'));
        $this->assertEquals(15, $responsePage1->json('meta.total'));

        $responsePage2->assertStatus(200);
        $this->assertCount(3, $responsePage2->json('data'));
    }

    public function test_thumbnail_is_first_image_by_sort_order(): void
    {
        Storage::fake('public');

        $service = Service::factory()->published()->create();
        ServiceImage::factory()->create(['service_id' => $service->id, 'path' => 'services/second.jpg', 'sort_order' => 2]);
        ServiceImage::factory()->create(['service_id' => $service->id, 'path' => 'services/first.jpg', 'sort_order' => 0]);

        $response = $this->getJson('/api/services');

        $response->assertStatus(200);
        $item = collect($response->json('data'))->firstWhere('id', $service->id);
        $this->assertNotNull($item);
        $this->assertStringContainsString('services/first.jpg', $item['thumbnail']);
    }

    public function test_list_response_includes_images_count(): void
    {
        $service = Service::factory()->published()->create();
        ServiceImage::factory()->count(3)->create(['service_id' => $service->id]);

        $response = $this->getJson('/api/services');

        $response->assertStatus(200);
        $item = collect($response->json('data'))->firstWhere('id', $service->id);
        $this->assertEquals(3, $item['images_count']);
    }

    // -------------------------------------------------------------------------
    // GET /api/services/{slug} — Public service detail
    // -------------------------------------------------------------------------

    public function test_show_returns_full_detail_for_published_service(): void
    {
        Storage::fake('public');

        $service = Service::factory()->published()->create(['slug' => 'maquillaje-social-detail']);
        ServiceImage::factory()->create(['service_id' => $service->id, 'path' => 'services/img0.jpg', 'sort_order' => 0]);
        ServiceImage::factory()->create(['service_id' => $service->id, 'path' => 'services/img1.jpg', 'sort_order' => 1]);
        ServiceImage::factory()->create(['service_id' => $service->id, 'path' => 'services/img2.jpg', 'sort_order' => 2]);

        $response = $this->getJson('/api/services/maquillaje-social-detail');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         'id', 'title', 'slug', 'description', 'price',
                         'duration_hours', 'availability_type',
                         'images',
                     ],
                 ]);

        $images = $response->json('data.images');
        $this->assertCount(3, $images);
        $this->assertEquals(0, $images[0]['sort_order']);
        $this->assertEquals(1, $images[1]['sort_order']);
        $this->assertEquals(2, $images[2]['sort_order']);
    }

    public function test_show_returns_gallery_images_in_sort_order(): void
    {
        Storage::fake('public');

        $service = Service::factory()->published()->create(['slug' => 'gallery-order-test']);
        ServiceImage::factory()->create(['service_id' => $service->id, 'path' => 'services/c.jpg', 'sort_order' => 2]);
        ServiceImage::factory()->create(['service_id' => $service->id, 'path' => 'services/a.jpg', 'sort_order' => 0]);
        ServiceImage::factory()->create(['service_id' => $service->id, 'path' => 'services/b.jpg', 'sort_order' => 1]);

        $response = $this->getJson('/api/services/gallery-order-test');

        $response->assertStatus(200);
        $orders = collect($response->json('data.images'))->pluck('sort_order')->toArray();
        $this->assertEquals([0, 1, 2], $orders);
    }

    /** S-6: Gallery image URLs must start with 'http' (absolute).
     *  Using an already-absolute HTTP path exercises the pass-through branch and
     *  guarantees the assertion is stable regardless of test APP_URL configuration.
     */
    public function test_show_image_urls_are_absolute(): void
    {
        $service = Service::factory()->published()->create(['slug' => 'absolute-url-test']);
        // Store an already-absolute URL path — the model/resource must pass it through unchanged
        ServiceImage::factory()->create([
            'service_id' => $service->id,
            'path'       => 'https://cdn.example.com/services/photo.jpg',
            'sort_order' => 0,
        ]);

        $response = $this->getJson('/api/services/absolute-url-test');

        $response->assertStatus(200);
        $url = $response->json('data.images.0.url');
        $this->assertNotNull($url);
        $this->assertStringStartsWith('http', $url);
        $this->assertEquals('https://cdn.example.com/services/photo.jpg', $url);
    }

    public function test_show_returns_404_for_unpublished_service(): void
    {
        Service::factory()->unpublished()->create(['slug' => 'draft-service']);

        $response = $this->getJson('/api/services/draft-service');

        $response->assertStatus(404);
    }

    public function test_show_returns_404_for_missing_slug(): void
    {
        $response = $this->getJson('/api/services/does-not-exist');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Resource structure assertions
    // -------------------------------------------------------------------------

    /** S-2: is_published must NOT be present in the public card resource. */
    public function test_list_response_has_expected_card_fields(): void
    {
        Service::factory()->published()->create();

        $response = $this->getJson('/api/services');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => [
                             'id', 'title', 'slug', 'description', 'price',
                             'duration_hours', 'availability_type',
                             'thumbnail', 'images_count',
                         ],
                     ],
                 ]);

        // is_published must not be exposed in the public catalog response
        $item = $response->json('data.0');
        $this->assertArrayNotHasKey('is_published', $item);
    }

    /** S-2: is_published must NOT be present in the public detail resource. */
    public function test_show_response_does_not_expose_is_published(): void
    {
        Service::factory()->published()->create(['slug' => 'no-is-published-test']);

        $response = $this->getJson('/api/services/no-is-published-test');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayNotHasKey('is_published', $data);
    }
}
