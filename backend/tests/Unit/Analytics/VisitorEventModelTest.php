<?php

namespace Tests\Unit\Analytics;

use App\Models\VisitorEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * VisitorEventModelTest
 *
 * Covers the model-level contract from design D2 (sdd/visitor-analytics/design):
 * `$timestamps = false` (the first model in this codebase to set this —
 * created_at would be provably identical to occurred_at on every row),
 * the `occurred_at` cast, and the fillable list.
 */
class VisitorEventModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_timestamps_are_disabled(): void
    {
        $event = new VisitorEvent();

        $this->assertFalse($event->timestamps);
    }

    public function test_occurred_at_is_cast_to_a_carbon_instance(): void
    {
        $event = VisitorEvent::create([
            'event_type' => 'page_view',
            'path' => '/productos/labial-mate',
            'route_name' => 'ProductDetail',
            'entity_type' => 'product',
            'entity_id' => 42,
            'referrer_group' => 'direct',
            'is_bot' => false,
            'user_id' => null,
            'occurred_at' => '2026-08-01 10:00:00',
        ]);

        $event->refresh();

        $this->assertInstanceOf(Carbon::class, $event->occurred_at);
    }

    public function test_fillable_attributes_can_be_mass_assigned(): void
    {
        $event = VisitorEvent::create([
            'event_type' => 'add_to_cart',
            'path' => '/productos/labial-mate',
            'route_name' => 'ProductDetail',
            'entity_type' => 'product',
            'entity_id' => 7,
            'referrer_group' => 'instagram',
            'is_bot' => true,
            'user_id' => null,
            'occurred_at' => now(),
        ]);

        $event->refresh();

        $this->assertSame('add_to_cart', $event->event_type);
        $this->assertSame('/productos/labial-mate', $event->path);
        $this->assertSame('ProductDetail', $event->route_name);
        $this->assertSame('product', $event->entity_type);
        $this->assertSame(7, $event->entity_id);
        $this->assertSame('instagram', $event->referrer_group);
        $this->assertTrue($event->is_bot);
        $this->assertNull($event->user_id);
    }

    public function test_row_does_not_persist_created_at_or_updated_at_columns(): void
    {
        $event = VisitorEvent::create([
            'event_type' => 'page_view',
            'path' => '/',
            'route_name' => 'Home',
            'entity_type' => null,
            'entity_id' => null,
            'referrer_group' => 'direct',
            'is_bot' => false,
            'user_id' => null,
            'occurred_at' => now(),
        ]);

        $this->assertArrayNotHasKey('created_at', $event->getAttributes());
        $this->assertArrayNotHasKey('updated_at', $event->getAttributes());
    }
}
