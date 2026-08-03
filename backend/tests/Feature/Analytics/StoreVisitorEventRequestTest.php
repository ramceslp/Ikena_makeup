<?php

namespace Tests\Feature\Analytics;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * StoreVisitorEventRequestTest — validates POST /api/analytics/events
 * (visitor-analytics PR1b, design D4: sdd/visitor-analytics/design).
 *
 * This endpoint is PUBLIC and unauthenticated. Two of the client-supplied
 * columns it writes into (`path` string(255) NOT NULL, `route_name`
 * string(64)) have no other guard between a hostile caller and a MySQL
 * strict-mode insert failure, so over-length input MUST be rejected with
 * 422, never allowed through to throw a 500 from a public endpoint.
 */
class StoreVisitorEventRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_page_view_is_accepted(): void
    {
        $this->postJson('/api/analytics/events', [
            'event_type' => 'page_view',
            'path' => '/productos/labial-mate',
            'route_name' => 'ProductDetail',
            'entity_type' => 'product',
            'entity_slug' => 'labial-mate',
        ])->assertStatus(204);
    }

    public function test_a_valid_add_to_cart_for_a_product_is_accepted(): void
    {
        $this->postJson('/api/analytics/events', [
            'event_type' => 'add_to_cart',
            'path' => '/productos/labial-mate',
            'route_name' => 'ProductDetail',
            'entity_type' => 'product',
            'entity_slug' => 'labial-mate',
        ])->assertStatus(204);
    }

    public function test_add_to_cart_referencing_a_service_is_rejected(): void
    {
        $this->postJson('/api/analytics/events', [
            'event_type' => 'add_to_cart',
            'path' => '/servicios/manicure',
            'entity_type' => 'service',
            'entity_slug' => 'manicure',
        ])->assertStatus(422);
    }

    public function test_add_to_cart_referencing_a_course_is_rejected(): void
    {
        $this->postJson('/api/analytics/events', [
            'event_type' => 'add_to_cart',
            'path' => '/cursos/maquillaje-basico',
            'entity_type' => 'course',
            'entity_slug' => 'maquillaje-basico',
        ])->assertStatus(422);
    }

    public function test_add_to_cart_with_no_entity_at_all_is_rejected(): void
    {
        $this->postJson('/api/analytics/events', [
            'event_type' => 'add_to_cart',
            'path' => '/carrito',
        ])->assertStatus(422);
    }

    public function test_an_unknown_event_type_is_rejected(): void
    {
        $this->postJson('/api/analytics/events', [
            'event_type' => 'purchase',
            'path' => '/',
        ])->assertStatus(422);
    }

    public function test_a_missing_path_is_rejected(): void
    {
        $this->postJson('/api/analytics/events', [
            'event_type' => 'page_view',
        ])->assertStatus(422);
    }

    // -------------------------------------------------------------------
    // Mandatory bounded-string guards — visitor_events.path is
    // string(255) NOT NULL and route_name is string(64), with no
    // truncation anywhere downstream. This endpoint is public, so
    // hostile over-length input must 422, never reach the insert and
    // 500 under MySQL strict mode.
    // -------------------------------------------------------------------

    public function test_a_path_over_255_characters_is_rejected(): void
    {
        $this->postJson('/api/analytics/events', [
            'event_type' => 'page_view',
            'path' => '/'.str_repeat('a', 255),
        ])->assertStatus(422)
            ->assertJsonValidationErrors('path');
    }

    public function test_a_route_name_over_64_characters_is_rejected(): void
    {
        $this->postJson('/api/analytics/events', [
            'event_type' => 'page_view',
            'path' => '/',
            'route_name' => str_repeat('a', 65),
        ])->assertStatus(422)
            ->assertJsonValidationErrors('route_name');
    }

    public function test_a_path_at_exactly_255_characters_is_accepted(): void
    {
        $this->postJson('/api/analytics/events', [
            'event_type' => 'page_view',
            'path' => str_repeat('a', 255),
        ])->assertStatus(204);
    }
}
