<?php

namespace Tests\Feature\Analytics;

use App\Models\VisitorEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * VisitorEventSyncWriteTest — pins the ingestion write as SYNCHRONOUS,
 * never queued (visitor-analytics PR1b, design D4: sdd/visitor-analytics/design).
 *
 * config/queue.php defaults to the 'database' driver and no worker runs
 * anywhere in this project (docs/push-notifications/HANDOFF.md:396-401).
 * Both phpunit.xml and phpunit.mysql.xml force QUEUE_CONNECTION=sync, so a
 * queued write would pass every other test in this suite while silently
 * losing every event in production. Queue::fake() removes that masking
 * sync override and proves nothing was ever pushed, on top of asserting
 * the row exists immediately after the response returns.
 */
class VisitorEventSyncWriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_ingestion_write_is_synchronous_not_queued(): void
    {
        Queue::fake();

        $this->postJson('/api/analytics/events', [
            'event_type' => 'page_view',
            'path' => '/',
            'route_name' => 'Home',
        ])->assertStatus(204);

        Queue::assertNothingPushed();

        $this->assertSame(1, VisitorEvent::query()->count());
    }
}
