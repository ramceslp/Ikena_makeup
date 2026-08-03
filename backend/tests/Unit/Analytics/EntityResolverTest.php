<?php

namespace Tests\Unit\Analytics;

use App\Analytics\EntityResolver;
use App\Models\Course;
use App\Models\Post;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EntityResolverTest — covers App\Analytics\EntityResolver
 * (visitor-analytics PR1b, design D6: sdd/visitor-analytics/design).
 *
 * The client sends entity_type + slug; the server resolves the numeric
 * primary key, never trusting a client-supplied id. An unknown slug or an
 * unknown entity type resolves to null — the pageview is still recorded
 * against its path/route_name, never discarded (see VisitorEventController).
 */
class EntityResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_slug_resolves_to_its_numeric_id(): void
    {
        $product = Product::factory()->create();

        $id = (new EntityResolver)->resolve('product', $product->slug);

        $this->assertSame($product->id, $id);
    }

    public function test_a_service_slug_resolves_to_its_numeric_id(): void
    {
        $service = Service::factory()->create();

        $id = (new EntityResolver)->resolve('service', $service->slug);

        $this->assertSame($service->id, $id);
    }

    public function test_a_course_slug_resolves_to_its_numeric_id(): void
    {
        $course = Course::factory()->create();

        $id = (new EntityResolver)->resolve('course', $course->slug);

        $this->assertSame($course->id, $id);
    }

    public function test_a_post_slug_resolves_to_its_numeric_id(): void
    {
        $post = Post::factory()->create();

        $id = (new EntityResolver)->resolve('post', $post->slug);

        $this->assertSame($post->id, $id);
    }

    public function test_an_unknown_slug_resolves_to_null(): void
    {
        Product::factory()->create(['slug' => 'a-real-product']);

        $id = (new EntityResolver)->resolve('product', 'does-not-exist');

        $this->assertNull($id);
    }

    public function test_an_unknown_entity_type_resolves_to_null(): void
    {
        $id = (new EntityResolver)->resolve('appointment', 'whatever');

        $this->assertNull($id);
    }

    public function test_a_null_entity_type_or_slug_resolves_to_null(): void
    {
        $resolver = new EntityResolver;

        $this->assertNull($resolver->resolve(null, 'some-slug'));
        $this->assertNull($resolver->resolve('product', null));
    }
}
