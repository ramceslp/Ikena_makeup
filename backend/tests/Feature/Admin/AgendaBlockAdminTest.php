<?php

namespace Tests\Feature\Admin;

use App\Models\AgendaBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgendaBlockAdminTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function student(): User
    {
        return User::factory()->create(['role' => 'student']);
    }

    // =========================================================================
    // Auth Matrix — 401 guest / 403 non-admin
    // =========================================================================

    public function test_guest_cannot_list_agenda_blocks_401(): void
    {
        $this->getJson('/api/admin/agenda')->assertStatus(401);
    }

    public function test_non_admin_cannot_list_agenda_blocks_403(): void
    {
        Sanctum::actingAs($this->student());
        $this->getJson('/api/admin/agenda')->assertStatus(403);
    }

    public function test_non_admin_cannot_create_agenda_block_403(): void
    {
        Sanctum::actingAs($this->student());
        $this->postJson('/api/admin/agenda', [
            'day_of_week'       => 1,
            'open_time'         => '09:00',
            'close_time'        => '18:00',
            'concurrency_limit' => 3,
        ])->assertStatus(403);
    }

    public function test_non_admin_cannot_update_agenda_block_403(): void
    {
        $block = AgendaBlock::factory()->create();
        Sanctum::actingAs($this->student());
        $this->patchJson("/api/admin/agenda/{$block->id}", [
            'close_time' => '17:00',
        ])->assertStatus(403);
    }

    public function test_non_admin_cannot_delete_agenda_block_403(): void
    {
        $block = AgendaBlock::factory()->create();
        Sanctum::actingAs($this->student());
        $this->deleteJson("/api/admin/agenda/{$block->id}")->assertStatus(403);
    }

    // =========================================================================
    // GET /api/admin/agenda — list (VAGA-001)
    // =========================================================================

    public function test_admin_can_list_agenda_blocks_200(): void
    {
        AgendaBlock::factory()->count(3)->create();

        Sanctum::actingAs($this->admin());
        $response = $this->getJson('/api/admin/agenda')->assertStatus(200);

        $this->assertCount(3, $response->json('data'));
    }

    // =========================================================================
    // GET /api/admin/agenda/{block} — show (VAGA-001)
    // =========================================================================

    public function test_admin_can_show_single_agenda_block_200(): void
    {
        $block = AgendaBlock::factory()->create();

        Sanctum::actingAs($this->admin());
        $response = $this->getJson("/api/admin/agenda/{$block->id}")->assertStatus(200);

        $response->assertJsonPath('data.id', $block->id);
    }

    // =========================================================================
    // POST /api/admin/agenda — create (VAGA-001)
    // =========================================================================

    public function test_admin_can_create_recurring_block_201(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/agenda', [
            'day_of_week'       => 1,
            'open_time'         => '09:00',
            'close_time'        => '18:00',
            'concurrency_limit' => 3,
        ])->assertStatus(201)
          ->assertJsonPath('data.day_of_week', 1)
          ->assertJsonPath('data.specific_date', null);

        $this->assertDatabaseHas('agenda_blocks', [
            'day_of_week'       => 1,
            'specific_date'     => null,
            'concurrency_limit' => 3,
        ]);
    }

    public function test_admin_can_create_specific_date_block_with_soft_threshold_201(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/agenda', [
            'specific_date'     => '2026-07-15',
            'open_time'         => '10:00',
            'close_time'        => '14:00',
            'concurrency_limit' => 2,
            'soft_threshold'    => 1,
        ])->assertStatus(201)
          ->assertJsonPath('data.day_of_week', null)
          ->assertJsonPath('data.soft_threshold', 1);

        $this->assertDatabaseHas('agenda_blocks', [
            'day_of_week'    => null,
            'soft_threshold' => 1,
        ]);
    }

    // =========================================================================
    // VAGA-002 — XOR recurrence invariant
    // =========================================================================

    public function test_both_day_of_week_and_specific_date_returns_422(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/agenda', [
            'day_of_week'       => 1,
            'specific_date'     => '2026-07-15',
            'open_time'         => '09:00',
            'close_time'        => '18:00',
            'concurrency_limit' => 1,
        ])->assertStatus(422);
    }

    public function test_neither_day_of_week_nor_specific_date_returns_422(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/agenda', [
            'open_time'         => '09:00',
            'close_time'        => '18:00',
            'concurrency_limit' => 1,
        ])->assertStatus(422);
    }

    // =========================================================================
    // VAGA-003 — time range invariant
    // =========================================================================

    public function test_open_time_equal_to_close_time_returns_422(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/agenda', [
            'day_of_week'       => 2,
            'open_time'         => '09:00',
            'close_time'        => '09:00',
            'concurrency_limit' => 1,
        ])->assertStatus(422);
    }

    public function test_open_time_after_close_time_returns_422(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/agenda', [
            'day_of_week'       => 2,
            'open_time'         => '18:00',
            'close_time'        => '09:00',
            'concurrency_limit' => 1,
        ])->assertStatus(422);
    }

    // =========================================================================
    // VAGA-004 — cap and threshold validation
    // =========================================================================

    public function test_concurrency_limit_zero_returns_422(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/agenda', [
            'day_of_week'       => 3,
            'open_time'         => '09:00',
            'close_time'        => '18:00',
            'concurrency_limit' => 0,
        ])->assertStatus(422);
    }

    public function test_soft_threshold_equal_to_concurrency_limit_returns_422(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/agenda', [
            'day_of_week'       => 3,
            'open_time'         => '09:00',
            'close_time'        => '18:00',
            'concurrency_limit' => 3,
            'soft_threshold'    => 3,
        ])->assertStatus(422);
    }

    public function test_soft_threshold_within_valid_range_passes(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/agenda', [
            'day_of_week'       => 3,
            'open_time'         => '09:00',
            'close_time'        => '18:00',
            'concurrency_limit' => 3,
            'soft_threshold'    => 2,
        ])->assertStatus(201);
    }

    // =========================================================================
    // VAGA-005 — non-overlapping blocks per day
    // =========================================================================

    public function test_new_block_overlapping_existing_on_same_weekday_returns_422(): void
    {
        AgendaBlock::factory()->create([
            'day_of_week' => 1,
            'open_time'   => '09:00',
            'close_time'  => '12:00',
        ]);

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/agenda', [
            'day_of_week'       => 1,
            'open_time'         => '11:00',
            'close_time'        => '15:00',
            'concurrency_limit' => 1,
        ])->assertStatus(422);
    }

    public function test_adjacent_blocks_touching_at_boundary_are_allowed_201(): void
    {
        AgendaBlock::factory()->create([
            'day_of_week' => 1,
            'open_time'   => '09:00',
            'close_time'  => '12:00',
        ]);

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/agenda', [
            'day_of_week'       => 1,
            'open_time'         => '12:00',
            'close_time'        => '18:00',
            'concurrency_limit' => 1,
        ])->assertStatus(201);
    }

    public function test_overlapping_on_specific_date_returns_422(): void
    {
        AgendaBlock::factory()->specificDate('2026-07-15')->create([
            'open_time'  => '09:00',
            'close_time' => '14:00',
        ]);

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/agenda', [
            'specific_date'     => '2026-07-15',
            'open_time'         => '13:00',
            'close_time'        => '18:00',
            'concurrency_limit' => 1,
        ])->assertStatus(422);
    }

    public function test_overlap_check_excludes_self_on_update_200(): void
    {
        $block = AgendaBlock::factory()->create([
            'day_of_week' => 1,
            'open_time'   => '09:00',
            'close_time'  => '12:00',
        ]);

        Sanctum::actingAs($this->admin());

        // Updating the block's own close_time must not collide with itself.
        $this->patchJson("/api/admin/agenda/{$block->id}", [
            'close_time' => '13:00',
        ])->assertStatus(200);

        $this->assertDatabaseHas('agenda_blocks', [
            'id'         => $block->id,
            'close_time' => '13:00',
        ]);
    }

    public function test_update_that_would_overlap_another_block_returns_422(): void
    {
        AgendaBlock::factory()->create([
            'day_of_week' => 1,
            'open_time'   => '09:00',
            'close_time'  => '12:00',
        ]);

        $other = AgendaBlock::factory()->create([
            'day_of_week' => 1,
            'open_time'   => '13:00',
            'close_time'  => '18:00',
        ]);

        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/agenda/{$other->id}", [
            'open_time' => '11:00',
        ])->assertStatus(422);
    }

    // =========================================================================
    // PATCH /api/admin/agenda/{block} — update (VAGA-001)
    // =========================================================================

    public function test_admin_can_update_close_time_only_200(): void
    {
        $block = AgendaBlock::factory()->create([
            'close_time' => '18:00',
        ]);

        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/agenda/{$block->id}", [
            'close_time' => '17:00',
        ])->assertStatus(200)
          ->assertJsonPath('data.close_time', '17:00');

        $this->assertDatabaseHas('agenda_blocks', [
            'id'         => $block->id,
            'close_time' => '17:00',
        ]);
    }

    // =========================================================================
    // DELETE /api/admin/agenda/{block} — delete (VAGA-001)
    // =========================================================================

    public function test_admin_can_delete_agenda_block_204(): void
    {
        $block = AgendaBlock::factory()->create();

        Sanctum::actingAs($this->admin());
        $this->deleteJson("/api/admin/agenda/{$block->id}")->assertStatus(204);

        $this->assertDatabaseMissing('agenda_blocks', ['id' => $block->id]);
    }
}
