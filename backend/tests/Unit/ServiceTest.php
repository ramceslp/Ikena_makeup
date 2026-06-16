<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Models\ServiceImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // scopePublished
    // -------------------------------------------------------------------------

    public function test_published_scope_returns_only_published_services(): void
    {
        Service::factory()->published()->create();
        Service::factory()->unpublished()->create();

        $this->assertEquals(1, Service::published()->count());
    }

    // -------------------------------------------------------------------------
    // images() — ordered by sort_order
    // -------------------------------------------------------------------------

    public function test_images_relationship_returns_ordered_by_sort_order(): void
    {
        $service = Service::factory()->create();
        ServiceImage::factory()->create(['service_id' => $service->id, 'sort_order' => 2]);
        ServiceImage::factory()->create(['service_id' => $service->id, 'sort_order' => 0]);
        ServiceImage::factory()->create(['service_id' => $service->id, 'sort_order' => 1]);

        $orders = $service->images->pluck('sort_order')->toArray();

        $this->assertEquals([0, 1, 2], $orders);
    }

    // -------------------------------------------------------------------------
    // thumbnailUrl accessor
    // -------------------------------------------------------------------------

    public function test_thumbnail_url_returns_absolute_url_for_first_image(): void
    {
        Storage::fake('public');

        $service = Service::factory()->create();
        ServiceImage::factory()->create(['service_id' => $service->id, 'path' => 'services/img2.jpg', 'sort_order' => 1]);
        ServiceImage::factory()->create(['service_id' => $service->id, 'path' => 'services/img1.jpg', 'sort_order' => 0]);

        $service->load('images');

        $url = $service->thumbnailUrl;

        $this->assertNotNull($url);
        $this->assertStringContainsString('services/img1.jpg', $url);
    }

    public function test_thumbnail_url_returns_null_when_no_images(): void
    {
        $service = Service::factory()->create();

        $this->assertNull($service->thumbnailUrl);
    }

    // -------------------------------------------------------------------------
    // imagesUrls accessor
    // -------------------------------------------------------------------------

    public function test_images_urls_returns_ordered_absolute_url_array(): void
    {
        Storage::fake('public');

        $service = Service::factory()->create();
        ServiceImage::factory()->create(['service_id' => $service->id, 'path' => 'services/b.jpg', 'sort_order' => 1]);
        ServiceImage::factory()->create(['service_id' => $service->id, 'path' => 'services/a.jpg', 'sort_order' => 0]);

        $service->load('images');

        $urls = $service->imagesUrls;

        $this->assertCount(2, $urls);
        $this->assertStringContainsString('services/a.jpg', $urls[0]);
        $this->assertStringContainsString('services/b.jpg', $urls[1]);
    }

    public function test_images_urls_returns_empty_array_when_no_images(): void
    {
        $service = Service::factory()->create();

        $this->assertEquals([], $service->imagesUrls);
    }
}
