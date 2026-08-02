<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * ReportControllerTest
 *
 * `GET /api/admin/reports/{summary,timeline,composition}` live inside the
 * EXISTING `Route::middleware('admin')` group (routes/api.php:181), so they
 * inherit `auth:sanctum` (outer group) + `EnsureUserIsAdmin` for free — this
 * suite pins that they actually do, plus the adjustment #1 contract: a wide
 * range degrades instead of ever returning 422.
 */
class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeInstructor(): User
    {
        return User::factory()->instructor()->create();
    }

    // -------------------------------------------------------------------------
    // Auth gate
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/admin/reports/summary?from=2026-08-01&to=2026-08-05');

        $response->assertStatus(401);
    }

    public function test_non_admin_gets_403(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($student);

        $response = $this->getJson('/api/admin/reports/summary?from=2026-08-01&to=2026-08-05');

        $response->assertStatus(403);
    }

    public function test_admin_can_reach_all_three_endpoints(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $this->getJson('/api/admin/reports/summary?from=2026-08-01&to=2026-08-05')->assertStatus(200);
        $this->getJson('/api/admin/reports/timeline?from=2026-08-01&to=2026-08-05')->assertStatus(200);
        $this->getJson('/api/admin/reports/composition?from=2026-08-01&to=2026-08-05')->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // Response shape
    // -------------------------------------------------------------------------

    public function test_summary_reconciles_confirmed_revenue_for_a_paid_course_order(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $buyer = User::factory()->create();
        $course = Course::factory()->create(['instructor_id' => $this->makeInstructor()->id]);

        Order::create([
            'user_id' => $buyer->id,
            'course_id' => $course->id,
            'type' => 'course',
            'client_transaction_id' => 'ORD-controller-course',
            'gateway' => 'fake',
            'amount_cents' => 9900,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => '2026-08-02 10:00:00',
        ]);

        $response = $this->getJson('/api/admin/reports/summary?from=2026-08-01&to=2026-08-05');

        $response->assertStatus(200)
            ->assertJsonPath('data.confirmed_revenue_cents', 9900)
            ->assertJsonPath('data.requested_granularity', 'day')
            ->assertJsonPath('data.effective_granularity', 'day')
            ->assertJsonPath('data.degraded', false);
    }

    /**
     * Adjustment #1 — the non-negotiable behaviour: a range wide enough to
     * exceed the day cap (92) must NEVER 422. The response must carry the
     * EFFECTIVE granularity, distinct from what was requested, plus the
     * degraded flag — otherwise the frontend renders daily labels over
     * weekly data.
     */
    public function test_timeline_over_the_day_cap_degrades_instead_of_422(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $response = $this->getJson(
            '/api/admin/reports/timeline?from=2026-01-01&to=2026-06-01&granularity=day'
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.requested_granularity', 'day')
            ->assertJsonPath('data.effective_granularity', 'week')
            ->assertJsonPath('data.degraded', true);
    }

    public function test_missing_required_date_range_returns_422(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $response = $this->getJson('/api/admin/reports/summary');

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // PR3 — sales ledger + CSV export
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_to_the_ledger_is_rejected(): void
    {
        $response = $this->getJson('/api/admin/reports/ledger?from=2026-08-01&to=2026-08-05');

        $response->assertStatus(401);
    }

    public function test_non_admin_cannot_reach_the_ledger_or_its_export(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($student);

        $this->getJson('/api/admin/reports/ledger?from=2026-08-01&to=2026-08-05')->assertStatus(403);
        $this->getJson('/api/admin/reports/ledger/export?from=2026-08-01&to=2026-08-05')->assertStatus(403);
    }

    public function test_ledger_response_contains_paginated_rows_and_meta(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        Order::create([
            'user_id' => User::factory()->create()->id,
            'type' => 'product_cart',
            'client_transaction_id' => 'ORD-ledger-endpoint',
            'gateway' => 'fake',
            'amount_cents' => 4500,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => '2026-08-02 10:00:00',
        ]);

        $response = $this->getJson('/api/admin/reports/ledger?from=2026-08-01&to=2026-08-05');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('data.0.amount_cents', 4500)
            ->assertJsonPath('data.0.stream', 'product_sale');
    }

    public function test_ledger_export_requires_a_date_range(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $this->getJson('/api/admin/reports/ledger/export')->assertStatus(422);
    }

    public function test_ledger_export_streams_a_csv_with_the_correct_content_type(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $response = $this->get('/api/admin/reports/ledger/export?from=2026-08-01&to=2026-08-05');

        $response->assertStatus(200);
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));
    }
}
