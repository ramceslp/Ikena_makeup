<?php

namespace Tests\Feature\Analytics;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * VisitorEventsMigrationTest
 *
 * Pins the `visitor_events` schema to design D2/D3 (sdd/visitor-analytics/design):
 * an append-only, identity-free event log. No `visitor_hash`, no `timestamps()`,
 * no foreign key on `entity_id` (deleted entities must keep their view history).
 *
 * Indexes: `(occurred_at)` serves every query's [from, to) bound and the
 * retention prune; `(event_type, entity_type, entity_id, occurred_at)` serves
 * the entity legs (rankings + funnel). `is_bot` is deliberately absent from
 * both — it will be ~99% one value, so as an index prefix it eliminates
 * nothing and only widens the index.
 */
class VisitorEventsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_events_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('visitor_events'));

        $this->assertTrue(Schema::hasColumn('visitor_events', 'id'));
        $this->assertTrue(Schema::hasColumn('visitor_events', 'event_type'));
        $this->assertTrue(Schema::hasColumn('visitor_events', 'path'));
        $this->assertTrue(Schema::hasColumn('visitor_events', 'route_name'));
        $this->assertTrue(Schema::hasColumn('visitor_events', 'entity_type'));
        $this->assertTrue(Schema::hasColumn('visitor_events', 'entity_id'));
        $this->assertTrue(Schema::hasColumn('visitor_events', 'referrer_group'));
        $this->assertTrue(Schema::hasColumn('visitor_events', 'is_bot'));
        $this->assertTrue(Schema::hasColumn('visitor_events', 'user_id'));
        $this->assertTrue(Schema::hasColumn('visitor_events', 'occurred_at'));
    }

    public function test_visitor_events_table_has_no_identity_or_timestamp_columns(): void
    {
        // D1: no visitor identity of any kind survives beyond the optional
        // authenticated user_id. D2: no timestamps() — created_at/updated_at
        // would be redundant with occurred_at on an append-only, never-updated row.
        $this->assertFalse(Schema::hasColumn('visitor_events', 'visitor_hash'));
        $this->assertFalse(Schema::hasColumn('visitor_events', 'ip_hash'));
        $this->assertFalse(Schema::hasColumn('visitor_events', 'session_id'));
        $this->assertFalse(Schema::hasColumn('visitor_events', 'created_at'));
        $this->assertFalse(Schema::hasColumn('visitor_events', 'updated_at'));
    }

    public function test_entity_id_has_no_foreign_key_constraint(): void
    {
        // Deleted entities must keep their view history for the full 13-month
        // retention window — a constrained/cascading FK would erase it the
        // instant a product/service/course/post is deleted.
        $foreignKeys = Schema::getForeignKeys('visitor_events');

        $entityIdForeignKeys = array_filter(
            $foreignKeys,
            fn (array $fk) => in_array('entity_id', $fk['columns'], true)
        );

        $this->assertEmpty($entityIdForeignKeys, 'entity_id must not carry a foreign key constraint.');
    }

    public function test_occurred_at_index_exists(): void
    {
        $indexes = Schema::getIndexes('visitor_events');

        $occurredAtIndex = collect($indexes)->first(
            fn (array $index) => $index['columns'] === ['occurred_at']
        );

        $this->assertNotNull($occurredAtIndex, 'Expected a single-column index on occurred_at.');
    }

    public function test_entity_leg_composite_index_exists(): void
    {
        $indexes = Schema::getIndexes('visitor_events');

        $compositeIndex = collect($indexes)->first(
            fn (array $index) => $index['columns'] === ['event_type', 'entity_type', 'entity_id', 'occurred_at']
        );

        $this->assertNotNull(
            $compositeIndex,
            'Expected a composite index on (event_type, entity_type, entity_id, occurred_at).'
        );
    }

    public function test_is_bot_is_not_part_of_any_index(): void
    {
        // is_bot will be ~99% `false` — as an index prefix or member it
        // eliminates nothing and only widens every index it touches.
        $indexes = Schema::getIndexes('visitor_events');

        foreach ($indexes as $index) {
            $this->assertNotContains(
                'is_bot',
                $index['columns'],
                'is_bot must never be part of a visitor_events index.'
            );
        }
    }
}
