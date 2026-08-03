<?php

namespace Tests\Unit\Analytics;

use App\Models\User;
use App\Models\VisitorEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * VisitorEventReportableScopeTest
 *
 * Covers `VisitorEvent::scopeReportable()` (design D7: sdd/visitor-analytics/design).
 *
 * This is the single most dangerous line in the whole visitor-analytics
 * change. SQL's `NOT IN` evaluates to NULL for a NULL row, and SQL discards
 * NULL in a WHERE clause — so a bare `whereNotIn('user_id', $staffIds)`
 * would silently drop EVERY anonymous event, which is most of this site's
 * traffic. Every admin figure would render empty with no error and green
 * tests. The scope must be a NULL-safe `whereNull OR whereNotIn`.
 */
class VisitorEventReportableScopeTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(array $overrides = []): VisitorEvent
    {
        return VisitorEvent::create(array_merge([
            'event_type' => 'page_view',
            'path' => '/',
            'route_name' => 'Home',
            'entity_type' => null,
            'entity_id' => null,
            'referrer_group' => 'direct',
            'is_bot' => false,
            'user_id' => null,
            'occurred_at' => now(),
        ], $overrides));
    }

    /**
     * THE TRAP TEST — written first, deliberately.
     *
     * An admin user must exist in the database (making staffIds() non-empty)
     * for this test to genuinely exercise the NULL-safety trap: a naive
     * `whereNotIn('user_id', $staffIds)` evaluates to NULL for the anonymous
     * event below and would silently exclude it. Only the nested
     * `whereNull OR whereNotIn` construction keeps it.
     */
    public function test_anonymous_events_survive_the_staff_exclusion(): void
    {
        // Staff user exists so staffIds() is non-empty — required for the
        // NULL-safety trap to actually bite a naive implementation.
        User::factory()->admin()->create();

        $anonymous = $this->makeEvent(['user_id' => null]);

        $ids = VisitorEvent::query()->reportable()->pluck('id');

        $this->assertTrue(
            $ids->contains($anonymous->id),
            'Anonymous events (user_id IS NULL) must survive the staff exclusion. '
            . 'A bare whereNotIn evaluates to NULL for NULL rows and SQL discards '
            . 'NULL in a WHERE clause, silently dropping every anonymous event.'
        );
    }

    public function test_bot_flagged_rows_are_excluded(): void
    {
        $bot = $this->makeEvent(['is_bot' => true, 'user_id' => null]);
        $human = $this->makeEvent(['is_bot' => false, 'user_id' => null]);

        $ids = VisitorEvent::query()->reportable()->pluck('id');

        $this->assertFalse($ids->contains($bot->id));
        $this->assertTrue($ids->contains($human->id));
    }

    public function test_admin_and_instructor_rows_are_excluded(): void
    {
        $admin = User::factory()->admin()->create();
        $instructor = User::factory()->instructor()->create();

        $adminEvent = $this->makeEvent(['user_id' => $admin->id]);
        $instructorEvent = $this->makeEvent(['user_id' => $instructor->id]);

        $ids = VisitorEvent::query()->reportable()->pluck('id');

        $this->assertFalse($ids->contains($adminEvent->id));
        $this->assertFalse($ids->contains($instructorEvent->id));
    }

    public function test_ordinary_logged_in_customer_rows_are_kept(): void
    {
        $customer = User::factory()->create(); // default role: student

        $customerEvent = $this->makeEvent(['user_id' => $customer->id]);

        $ids = VisitorEvent::query()->reportable()->pluck('id');

        $this->assertTrue($ids->contains($customerEvent->id));
    }
}
