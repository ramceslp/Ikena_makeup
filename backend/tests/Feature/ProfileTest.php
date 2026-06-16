<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // 1. Unauthenticated → 401 on all three profile endpoints
    // -------------------------------------------------------------------------

    public function test_unauthenticated_cannot_update_profile(): void
    {
        $this->postJson('/api/profile', ['name' => 'New Name'])
            ->assertStatus(401);
    }

    public function test_unauthenticated_cannot_change_password(): void
    {
        $this->putJson('/api/profile/password', [
            'current_password'      => 'password',
            'password'              => 'newpassword',
            'password_confirmation' => 'newpassword',
        ])->assertStatus(401);
    }

    public function test_unauthenticated_cannot_view_orders(): void
    {
        $this->getJson('/api/profile/orders')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // 1b. has_password flag exposed on the user resource
    // -------------------------------------------------------------------------

    public function test_user_resource_exposes_has_password_true_for_password_account(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        Sanctum::actingAs($user);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.has_password', true);
    }

    public function test_user_resource_exposes_has_password_false_for_google_account(): void
    {
        $user = User::factory()->create(['password' => null, 'google_id' => 'google-uid-xyz']);
        Sanctum::actingAs($user);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.has_password', false);
    }

    // -------------------------------------------------------------------------
    // 2. Update name → 200, DB updated, response returns new name
    // -------------------------------------------------------------------------

    public function test_update_name_returns_200_and_persists(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/profile', ['name' => 'New Name']);

        $response->assertStatus(200)
                 ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    // -------------------------------------------------------------------------
    // 3. Update email: own email keeps 200, another user's email → 422
    // -------------------------------------------------------------------------

    public function test_update_email_returns_200_and_persists(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/profile', ['email' => 'new@example.com']);

        $response->assertStatus(200)
                 ->assertJsonPath('data.email', 'new@example.com');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'new@example.com']);
    }

    public function test_update_email_to_another_users_email_returns_422(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'mine@example.com']);
        Sanctum::actingAs($user);

        $this->postJson('/api/profile', ['email' => 'taken@example.com'])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['email']);
    }

    public function test_update_with_own_email_does_not_fail(): void
    {
        $user = User::factory()->create(['email' => 'mine@example.com']);
        Sanctum::actingAs($user);

        $this->postJson('/api/profile', ['email' => 'mine@example.com'])
             ->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // 4. Upload avatar → 200, file exists on public disk, stored path in DB,
    //    response data.avatar is an absolute URL
    // -------------------------------------------------------------------------

    public function test_upload_avatar_stores_file_and_returns_absolute_url(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['avatar' => null]);
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->postJson('/api/profile', ['avatar' => $file]);

        $response->assertStatus(200);

        // avatar in DB is the stored relative path (not null, not http)
        $user->refresh();
        $this->assertNotNull($user->avatar);
        $this->assertStringStartsNotWith('http', $user->avatar);
        $this->assertStringContainsString('avatars/', $user->avatar);

        // File exists on the public disk
        Storage::disk('public')->assertExists($user->avatar);

        // Response data.avatar is an absolute URL
        $avatarUrl = $response->json('data.avatar');
        $this->assertNotNull($avatarUrl);
        $this->assertStringContainsString('avatars/', $avatarUrl);
        // Must be a full URL (starts with http or /)
        $this->assertTrue(
            str_starts_with($avatarUrl, 'http') || str_starts_with($avatarUrl, '/'),
            "Expected absolute URL, got: {$avatarUrl}"
        );
    }

    // -------------------------------------------------------------------------
    // 5a. Re-upload avatar: old stored file is deleted, new file takes over
    // -------------------------------------------------------------------------

    public function test_reuploading_avatar_deletes_old_stored_file(): void
    {
        Storage::fake('public');

        // Create user with an existing stored avatar
        $user = User::factory()->create(['avatar' => null]);
        Sanctum::actingAs($user);

        // First upload
        $firstFile = UploadedFile::fake()->image('first.jpg');
        $this->postJson('/api/profile', ['avatar' => $firstFile])->assertStatus(200);

        $user->refresh();
        $oldPath = $user->avatar;
        Storage::disk('public')->assertExists($oldPath);

        // Second upload
        $secondFile = UploadedFile::fake()->image('second.jpg');
        $this->postJson('/api/profile', ['avatar' => $secondFile])->assertStatus(200);

        // Old file must be gone
        Storage::disk('public')->assertMissing($oldPath);

        // New file must exist
        $user->refresh();
        Storage::disk('public')->assertExists($user->avatar);
    }

    // -------------------------------------------------------------------------
    // 5b. User with Google avatar (http URL): upload does NOT delete the URL,
    //     avatar becomes the new stored path after upload
    // -------------------------------------------------------------------------

    public function test_google_avatar_url_is_not_deleted_on_new_upload(): void
    {
        Storage::fake('public');

        $googleAvatarUrl = 'https://lh3.googleusercontent.com/a/fake-avatar';
        $user = User::factory()->create(['avatar' => $googleAvatarUrl]);
        Sanctum::actingAs($user);

        // Upload a real file — should NOT attempt to delete the http URL
        $file = UploadedFile::fake()->image('new.jpg');
        $response = $this->postJson('/api/profile', ['avatar' => $file]);

        $response->assertStatus(200);

        // Avatar is now the new stored path (not the Google URL)
        $user->refresh();
        $this->assertStringStartsNotWith('http', $user->avatar);
        $this->assertStringContainsString('avatars/', $user->avatar);

        Storage::disk('public')->assertExists($user->avatar);
    }

    // -------------------------------------------------------------------------
    // 6. Avatar validation: non-image file → 422
    // -------------------------------------------------------------------------

    public function test_non_image_avatar_returns_422(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/profile', [
            'avatar' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['avatar']);
    }

    // -------------------------------------------------------------------------
    // 7. updatePassword: correct flow → 200; wrong current → 422; mismatch → 422
    // -------------------------------------------------------------------------

    public function test_update_password_with_correct_data_returns_200(): void
    {
        $user = User::factory()->create(['password' => Hash::make('oldpassword')]);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/profile/password', [
            'current_password'      => 'oldpassword',
            'password'              => 'newpassword8',
            'password_confirmation' => 'newpassword8',
        ]);

        $response->assertStatus(200);

        // The new password works
        $user->refresh();
        $this->assertTrue(Hash::check('newpassword8', $user->password));
    }

    public function test_wrong_current_password_returns_422(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correctpassword')]);
        Sanctum::actingAs($user);

        $this->putJson('/api/profile/password', [
            'current_password'      => 'wrongpassword',
            'password'              => 'newpassword8',
            'password_confirmation' => 'newpassword8',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['current_password']);
    }

    public function test_password_confirmation_mismatch_returns_422(): void
    {
        $user = User::factory()->create(['password' => Hash::make('oldpassword')]);
        Sanctum::actingAs($user);

        $this->putJson('/api/profile/password', [
            'current_password'      => 'oldpassword',
            'password'              => 'newpassword8',
            'password_confirmation' => 'differentpassword',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['password']);
    }

    // -------------------------------------------------------------------------
    // 8. updatePassword for Google user (password = null) → 422 + Spanish message
    // -------------------------------------------------------------------------

    public function test_google_user_cannot_change_password_and_gets_422_spanish(): void
    {
        // Google user: password is null
        $user = User::factory()->create([
            'password'  => null,
            'google_id' => 'google-uid-123',
        ]);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/profile/password', [
            'current_password'      => 'anything',
            'password'              => 'newpassword8',
            'password_confirmation' => 'newpassword8',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('message', 'Tu cuenta inicia sesión con Google y no tiene contraseña.');
    }

    // -------------------------------------------------------------------------
    // 9. orders: returns user's orders latest-first with course data + pagination;
    //    does NOT include another user's orders
    // -------------------------------------------------------------------------

    public function test_orders_returns_all_own_orders_latest_first_with_course_and_pagination(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $course = Course::factory()->create();

        // Create orders for the authenticated user with different statuses
        $paid = Order::factory()->paid()->create([
            'user_id'    => $user->id,
            'course_id'  => $course->id,
            'created_at' => now()->subDays(2),
        ]);

        $pending = Order::factory()->pending()->create([
            'user_id'    => $user->id,
            'course_id'  => $course->id,
            'created_at' => now()->subDay(),
        ]);

        $failed = Order::factory()->create([
            'user_id'    => $user->id,
            'course_id'  => $course->id,
            'status'     => 'failed',
            'created_at' => now(),
        ]);

        // Another user's order — must NOT appear
        Order::factory()->create(['user_id' => $other->id]);

        $response = $this->getJson('/api/profile/orders');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => [
                             'id',
                             'status',
                             'amount_cents',
                             'currency',
                             'paid_at',
                             'created_at',
                             'course' => ['id', 'title', 'slug', 'thumbnail'],
                         ],
                     ],
                     'meta',
                     'links',
                 ]);

        $data = $response->json('data');

        // Only own orders (3 total)
        $this->assertCount(3, $data);

        // Latest first: failed (now) → pending (subDay) → paid (subDays(2))
        $this->assertEquals($failed->id, $data[0]['id']);
        $this->assertEquals($pending->id, $data[1]['id']);
        $this->assertEquals($paid->id, $data[2]['id']);

        // Statuses present
        $statuses = collect($data)->pluck('status')->toArray();
        $this->assertContains('paid', $statuses);
        $this->assertContains('pending', $statuses);
        $this->assertContains('failed', $statuses);

        // Course data present on each order
        foreach ($data as $order) {
            $this->assertArrayHasKey('course', $order);
            $this->assertEquals($course->id, $order['course']['id']);
            $this->assertEquals($course->title, $order['course']['title']);
        }
    }

    public function test_orders_does_not_include_other_users_orders(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        // Only the other user has orders
        Order::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->getJson('/api/profile/orders');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    // -------------------------------------------------------------------------
    // 10. UserResource avatar: stored path → absolute URL; http URL → unchanged; null → null
    // -------------------------------------------------------------------------

    public function test_avatar_null_returns_null_in_response(): void
    {
        $user = User::factory()->create(['avatar' => null]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me');

        $response->assertStatus(200)
                 ->assertJsonPath('data.avatar', null);
    }

    public function test_avatar_http_url_is_returned_unchanged_in_response(): void
    {
        $googleUrl = 'https://lh3.googleusercontent.com/a/some-google-avatar';
        $user = User::factory()->create(['avatar' => $googleUrl]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me');

        $response->assertStatus(200)
                 ->assertJsonPath('data.avatar', $googleUrl);
    }

    public function test_avatar_stored_path_returns_absolute_url_in_response(): void
    {
        Storage::fake('public');

        // Simulate a stored relative path (as if previously uploaded)
        $storedPath = 'avatars/some-uuid.jpg';
        Storage::disk('public')->put($storedPath, 'fake-image-content');

        $user = User::factory()->create(['avatar' => $storedPath]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me');

        $response->assertStatus(200);

        $avatarUrl = $response->json('data.avatar');
        $this->assertNotNull($avatarUrl);
        // Must be an absolute URL (starts with http or /)
        $this->assertTrue(
            str_starts_with($avatarUrl, 'http') || str_starts_with($avatarUrl, '/'),
            "Expected absolute URL for stored path, got: {$avatarUrl}"
        );
        // Must contain the stored path fragment
        $this->assertStringContainsString('avatars/some-uuid.jpg', $avatarUrl);
    }
}
