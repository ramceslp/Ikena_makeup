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

    public function test_list_is_paginated_at_12_per_page(): void
    {
        Service::factory()->count(15)->published()->create();

        $response = $this->getJson('/api/services?page=1');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data', 'links', 'meta']);

        $this->assertLessThanOrEqual(12, count($response->json('data')));
        $this->assertEquals(15, $response->json('meta.total'));
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
                         'duration_hours', 'availability_type', 'is_published',
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

    public function test_show_image_urls_are_absolute(): void
    {
        Storage::fake('public');

        $service = Service::factory()->published()->create(['slug' => 'absolute-url-test']);
        ServiceImage::factory()->create(['service_id' => $service->id, 'path' => 'services/photo.jpg', 'sort_order' => 0]);

        $response = $this->getJson('/api/services/absolute-url-test');

        $response->assertStatus(200);
        $url = $response->json('data.images.0.url');
        $this->assertNotNull($url);
        $this->assertStringContainsString('services/photo.jpg', $url);
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

    public function test_list_response_has_expected_card_fields(): void
    {
        Service::factory()->published()->create();

        $response = $this->getJson('/api/services');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => [
                             'id', 'title', 'slug', 'description', 'price',
                             'duration_hours', 'availability_type', 'is_published',
                             'thumbnail', 'images_count',
                         ],
                     ],
                 ]);
    }
}
