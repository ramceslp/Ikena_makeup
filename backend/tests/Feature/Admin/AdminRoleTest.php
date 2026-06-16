<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsInstructor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminRoleTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function instructor(): User
    {
        return User::factory()->instructor()->create();
    }

    private function student(): User
    {
        return User::factory()->create(['role' => 'student']);
    }

    // =========================================================================
    // PHASE 1 — User model helper truth table
    // =========================================================================

    public function test_admin_user_isAdmin_returns_true(): void
    {
        $user = User::factory()->make(['role' => 'admin']);
        $this->assertTrue($user->isAdmin());
    }

    public function test_instructor_user_isAdmin_returns_false(): void
    {
        $user = User::factory()->make(['role' => 'instructor']);
        $this->assertFalse($user->isAdmin());
    }

    public function test_student_user_isAdmin_returns_false(): void
    {
        $user = User::factory()->make(['role' => 'student']);
        $this->assertFalse($user->isAdmin());
    }

    public function test_instructor_user_isInstructor_returns_true(): void
    {
        $user = User::factory()->make(['role' => 'instructor']);
        $this->assertTrue($user->isInstructor());
    }

    public function test_admin_user_isInstructor_returns_false(): void
    {
        $user = User::factory()->make(['role' => 'admin']);
        $this->assertFalse($user->isInstructor());
    }

    public function test_student_user_isInstructor_returns_false(): void
    {
        $user = User::factory()->make(['role' => 'student']);
        $this->assertFalse($user->isInstructor());
    }

    public function test_admin_user_canInstruct_returns_true(): void
    {
        $user = User::factory()->make(['role' => 'admin']);
        $this->assertTrue($user->canInstruct());
    }

    public function test_instructor_user_canInstruct_returns_true(): void
    {
        $user = User::factory()->make(['role' => 'instructor']);
        $this->assertTrue($user->canInstruct());
    }

    public function test_student_user_canInstruct_returns_false(): void
    {
        $user = User::factory()->make(['role' => 'student']);
        $this->assertFalse($user->canInstruct());
    }

    // =========================================================================
    // PHASE 2 — GET /api/admin/ping middleware + smoke route
    // =========================================================================

    public function test_unauthenticated_request_to_admin_ping_returns_401(): void
    {
        $this->getJson('/api/admin/ping')->assertStatus(401);
    }

    public function test_student_request_to_admin_ping_returns_403(): void
    {
        Sanctum::actingAs($this->student());

        $this->getJson('/api/admin/ping')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Admin role required.');
    }

    public function test_instructor_request_to_admin_ping_returns_403(): void
    {
        Sanctum::actingAs($this->instructor());

        $this->getJson('/api/admin/ping')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Admin role required.');
    }

    public function test_admin_request_to_admin_ping_returns_200(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/ping')
            ->assertStatus(200)
            ->assertJsonPath('message', 'pong');
    }

    // =========================================================================
    // PHASE 3 — EnsureUserIsInstructor widened: admin passes instructor routes
    // =========================================================================

    public function test_unauthenticated_request_to_instructor_dashboard_returns_401(): void
    {
        $this->getJson('/api/instructor/dashboard')->assertStatus(401);
    }

    public function test_student_request_to_instructor_dashboard_returns_403(): void
    {
        Sanctum::actingAs($this->student());

        $this->getJson('/api/instructor/dashboard')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Instructor role required.');
    }

    public function test_instructor_request_to_instructor_dashboard_returns_200(): void
    {
        Sanctum::actingAs($this->instructor());

        $this->getJson('/api/instructor/dashboard')->assertStatus(200);
    }

    public function test_admin_request_to_instructor_dashboard_returns_200(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/instructor/dashboard')->assertStatus(200);
    }

    // =========================================================================
    // PHASE 4 — Factory admin() state + DatabaseSeeder admin@ikena.test
    // =========================================================================

    public function test_factory_admin_state_sets_role_to_admin(): void
    {
        $user = User::factory()->admin()->make();
        $this->assertSame('admin', $user->role);
    }

    public function test_database_seeder_creates_admin_user(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@ikena.test',
            'role'  => 'admin',
        ]);
    }

    // =========================================================================
    // Hardening — middleware return 401 (not 403) when the user is unauthenticated,
    // so the guard is correct even if ever used outside an auth:sanctum group.
    // =========================================================================

    public function test_admin_middleware_returns_401_when_user_is_unauthenticated(): void
    {
        $middleware = new EnsureUserIsAdmin();
        $request    = Request::create('/api/admin/ping', 'GET');

        $response = $middleware->handle($request, fn () => response('next'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_instructor_middleware_returns_401_when_user_is_unauthenticated(): void
    {
        $middleware = new EnsureUserIsInstructor();
        $request    = Request::create('/api/instructor/dashboard', 'GET');

        $response = $middleware->handle($request, fn () => response('next'));

        $this->assertSame(401, $response->getStatusCode());
    }
}
