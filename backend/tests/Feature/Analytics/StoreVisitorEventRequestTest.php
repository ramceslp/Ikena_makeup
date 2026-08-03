<?php

namespace Tests\Feature\Analytics;

use App\Models\VisitorEvent;
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

    // -------------------------------------------------------------------
    // Length validation MUST run against the NORMALIZED path (query
    // string and fragment stripped), not the raw submitted value.
    // Orchestrator-flagged defect: validating the raw value before
    // normalization silently rejected valid, short, storable pageviews
    // whose raw form (e.g. a filtered catalogue URL with several query
    // parameters) happened to exceed 255 characters before stripping.
    // -------------------------------------------------------------------

    public function test_a_raw_path_over_255_characters_that_normalizes_short_is_accepted(): void
    {
        // Raw length (with query string) is well over 255; the
        // normalized path ('/productos') is 10 characters — trivially
        // storable. This is the regression test for the defect: it must
        // be observed FAILING (422) against the pre-fix code before the
        // fix, since the pre-fix code validates the raw value.
        $rawPath = '/productos?'.str_repeat('categoria=maquillaje&marca=ikena&orden=precio&pagina=1&', 5);
        $this->assertGreaterThan(255, strlen($rawPath));

        $this->postJson('/api/analytics/events', [
            'event_type' => 'page_view',
            'path' => $rawPath,
        ])->assertStatus(204);

        $event = VisitorEvent::query()->firstOrFail();

        $this->assertSame('/productos', $event->path);
    }

    public function test_a_path_whose_normalized_form_still_exceeds_255_characters_is_rejected(): void
    {
        // The guard must not be weakened into uselessness: a path whose
        // NORMALIZED form is still over 255 characters must still 422.
        $rawPath = str_repeat('a', 300).'?short=1';

        $this->postJson('/api/analytics/events', [
            'event_type' => 'page_view',
            'path' => $rawPath,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('path');

        $this->assertSame(0, VisitorEvent::query()->count());
    }

    public function test_a_path_with_fragment_before_query_string_normalizes_correctly(): void
    {
        // Order of '?' and '#' in the raw string must not matter — both
        // must be stripped regardless of which comes first.
        $this->postJson('/api/analytics/events', [
            'event_type' => 'page_view',
            'path' => '/productos/labial-mate#reviews?ref=ig',
        ])->assertStatus(204);

        $event = VisitorEvent::query()->firstOrFail();

        $this->assertSame('/productos/labial-mate', $event->path);
    }

    public function test_a_path_with_query_string_before_fragment_normalizes_correctly(): void
    {
        $this->postJson('/api/analytics/events', [
            'event_type' => 'page_view',
            'path' => '/productos/labial-mate?ref=ig#reviews',
        ])->assertStatus(204);

        $event = VisitorEvent::query()->firstOrFail();

        $this->assertSame('/productos/labial-mate', $event->path);
    }
}
