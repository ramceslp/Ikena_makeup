<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // POST /api/register
    // -------------------------------------------------------------------------

    public function test_register_creates_user_and_returns_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['user', 'token'])
                 ->assertJsonPath('user.email', 'test@example.com');

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_register_returns_422_when_fields_are_missing(): void
    {
        $response = $this->postJson('/api/register', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_register_returns_422_on_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dupe@example.com']);

        $response = $this->postJson('/api/register', [
            'name'                  => 'Another User',
            'email'                 => 'dupe@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email']);
    }

    public function test_register_returns_422_when_passwords_do_not_match(): void
    {
        $response = $this->postJson('/api/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'different',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['password']);
    }

    // -------------------------------------------------------------------------
    // POST /api/login
    // -------------------------------------------------------------------------

    public function test_login_returns_token_with_correct_credentials(): void
    {
        User::factory()->create([
            'email'    => 'login@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'login@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['user', 'token']);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_returns_401_with_wrong_password(): void
    {
        User::factory()->create([
            'email'    => 'user@example.com',
            'password' => bcrypt('correctpassword'),
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'user@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
                 ->assertJsonPath('message', 'The provided credentials are incorrect.');
    }

    public function test_login_returns_422_when_email_is_missing(): void
    {
        $response = $this->postJson('/api/login', [
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email']);
    }

    // -------------------------------------------------------------------------
    // POST /api/logout
    // -------------------------------------------------------------------------

    public function test_logout_revokes_token_and_returns_204(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/logout');

        $response->assertStatus(204);

        // After logout the token is gone from personal_access_tokens
        $this->assertDatabaseEmpty('personal_access_tokens');
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // GET /api/me
    // -------------------------------------------------------------------------

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => ['id', 'name', 'email', 'role']])
                 ->assertJsonPath('data.email', $user->email);
    }

    public function test_me_returns_401_without_token(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }
}
