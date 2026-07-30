<?php

namespace Tests\Feature\Push;

use App\Jobs\BroadcastPushNotification;
use App\Models\Course;
use App\Models\DeviceToken;
use App\Models\PushNotificationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Tests\TestCase;

/**
 * AdminPushNotificationControllerTest — push-notifications Slice 3.
 */
class AdminPushNotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The real queue manager, captured before Queue::fake() replaces it, so
     * the synchronous-dispatch test below can put it back. Queue::fake()
     * intercepts dispatch entirely — with it in place a job never runs, not
     * even on the `sync` connection.
     */
    private mixed $realQueue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->realQueue = $this->app['queue'];

        Queue::fake();
        config(['push.enabled' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    // ---------------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------------

    public function test_a_guest_cannot_read_the_history(): void
    {
        $this->getJson('/api/admin/push-notifications')->assertUnauthorized();
    }

    public function test_a_non_admin_cannot_read_the_history(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'student']))
            ->getJson('/api/admin/push-notifications')
            ->assertForbidden();
    }

    public function test_a_non_admin_cannot_send_a_broadcast(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'instructor']))
            ->postJson('/api/admin/push-notifications', ['title' => 'Hola', 'body' => 'Mundo'])
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    // ---------------------------------------------------------------------
    // History
    // ---------------------------------------------------------------------

    public function test_the_history_returns_automatic_and_custom_sends_newest_first(): void
    {
        PushNotificationLog::factory()->ofType(PushNotificationLog::TYPE_POST_PUBLISHED)
            ->create(['title' => 'Vieja', 'created_at' => now()->subDays(2)]);
        PushNotificationLog::factory()->ofType(PushNotificationLog::TYPE_CUSTOM)
            ->create(['title' => 'Nueva', 'created_at' => now()]);

        $response = $this->actingAs($this->admin())
            ->getJson('/api/admin/push-notifications')
            ->assertOk();

        $this->assertSame('Nueva', $response->json('data.0.title'));
        $this->assertSame('Vieja', $response->json('data.1.title'));
    }

    public function test_the_history_can_be_filtered_by_type(): void
    {
        PushNotificationLog::factory()->ofType(PushNotificationLog::TYPE_POST_PUBLISHED)->create();
        PushNotificationLog::factory()->ofType(PushNotificationLog::TYPE_CUSTOM)->create();

        $response = $this->actingAs($this->admin())
            ->getJson('/api/admin/push-notifications?type=custom')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame(PushNotificationLog::TYPE_CUSTOM, $response->json('data.0.type'));
    }

    public function test_the_history_exposes_delivery_counts_and_the_deep_link(): void
    {
        PushNotificationLog::factory()->sent(successes: 7, failures: 2)->create([
            'data' => ['route' => '/cursos/bridal'],
        ]);

        $response = $this->actingAs($this->admin())
            ->getJson('/api/admin/push-notifications')
            ->assertOk();

        $this->assertSame(9, $response->json('data.0.recipients_count'));
        $this->assertSame(7, $response->json('data.0.success_count'));
        $this->assertSame(2, $response->json('data.0.failure_count'));
        $this->assertSame('/cursos/bridal', $response->json('data.0.route'));
        $this->assertSame(PushNotificationLog::STATUS_SENT, $response->json('data.0.status'));
    }

    public function test_it_is_paginated(): void
    {
        PushNotificationLog::factory()->count(25)->create();

        $response = $this->actingAs($this->admin())
            ->getJson('/api/admin/push-notifications')
            ->assertOk();

        $this->assertCount(20, $response->json('data'));
        $this->assertSame(25, $response->json('meta.total'));
    }

    // ---------------------------------------------------------------------
    // Sending
    // ---------------------------------------------------------------------

    public function test_an_admin_can_send_a_custom_broadcast(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->postJson('/api/admin/push-notifications', [
                'title' => 'Promo 2x1',
                'body'  => 'Solo por hoy en todos los cursos',
                'route' => '/cursos',
            ])
            ->assertCreated();

        $this->assertSame(PushNotificationLog::TYPE_CUSTOM, $response->json('data.type'));
        $this->assertSame('/cursos', $response->json('data.route'));
        $this->assertSame($admin->id, $response->json('data.sent_by.id'));

        $this->assertDatabaseHas('push_notification_logs', [
            'title'   => 'Promo 2x1',
            'sent_by' => $admin->id,
            'status'  => PushNotificationLog::STATUS_PENDING,
        ]);
        Queue::assertPushed(BroadcastPushNotification::class, 1);
    }

    public function test_the_route_is_optional(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/push-notifications', ['title' => 'Aviso', 'body' => 'Cerramos el lunes'])
            ->assertCreated()
            ->assertJsonPath('data.route', null);
    }

    public function test_title_and_body_are_required(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/push-notifications', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'body']);
    }

    /**
     * `route` is handed to vue-router inside the app. An absolute external URL
     * or a protocol-relative '//host' would navigate the user off the app, so
     * only internal paths are accepted.
     */
    public function test_it_rejects_an_external_or_protocol_relative_route(): void
    {
        foreach (['https://evil.com', '//evil.com', 'cursos', 'javascript:alert(1)'] as $route) {
            $this->actingAs($this->admin())
                ->postJson('/api/admin/push-notifications', [
                    'title' => 'X',
                    'body'  => 'Y',
                    'route' => $route,
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['route']);
        }

        Queue::assertNothingPushed();
    }

    // ---------------------------------------------------------------------
    // Deep-link destinations (the blank-screen fix)
    //
    // vue-router 4 resolves an unmatched path WITHOUT rejecting — the app then
    // renders its chrome around an empty <RouterView>, with no error on the
    // device, in the logs, or in the history. The server is therefore the last
    // place a dead deep link can be caught, which is what these pin.
    // ---------------------------------------------------------------------

    public function test_an_admin_can_send_to_a_destination_chosen_by_key(): void
    {
        $response = $this->actingAs($this->admin())
            ->postJson('/api/admin/push-notifications', [
                'title'       => 'Novedades',
                'body'        => 'Mirá lo nuevo',
                'destination' => 'news',
            ])
            ->assertCreated();

        $this->assertSame('/noticias', $response->json('data.route'));
    }

    public function test_a_destination_with_a_slug_is_expanded_into_the_app_path(): void
    {
        Course::factory()->create(['slug' => 'bridal', 'is_published' => true]);

        $response = $this->actingAs($this->admin())
            ->postJson('/api/admin/push-notifications', [
                'title'       => 'Nuevo curso',
                'body'        => 'Ya podés inscribirte',
                'destination' => 'course-detail',
                'slug'        => 'bridal',
            ])
            ->assertCreated();

        $this->assertSame('/cursos/bridal', $response->json('data.route'));
    }

    /**
     * The reported bug, verbatim: a course URL copied out of the WEB panel.
     * `/courses/{slug}` is the web's route; the app's is `/cursos/{slug}`. The
     * old `regex:/^\/(?!\/)/` accepted it happily — it is an internal path, it
     * just is not one the app has — and every device that tapped it got a blank
     * screen.
     */
    public function test_it_rejects_an_internal_path_the_app_has_no_route_for(): void
    {
        foreach (['/courses/bridal', '/admin/noticias', '/productos/labial', '/nope'] as $route) {
            $this->actingAs($this->admin())
                ->postJson('/api/admin/push-notifications', [
                    'title' => 'X',
                    'body'  => 'Y',
                    'route' => $route,
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['route']);
        }

        Queue::assertNothingPushed();
    }

    /**
     * A real route with a slug that matches nothing is not a blank screen — the
     * app's detail views render a "no encontrado" state — but it is still a
     * broadcast to every device advertising a page that is not there.
     */
    public function test_it_rejects_a_real_route_whose_slug_matches_no_published_record(): void
    {
        Course::factory()->create(['slug' => 'bridal', 'is_published' => false]);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/push-notifications', [
                'title'       => 'X',
                'body'        => 'Y',
                'destination' => 'course-detail',
                'slug'        => 'bridal',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['route']);

        Queue::assertNothingPushed();
    }

    public function test_a_destination_that_needs_a_slug_reports_a_missing_one(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/push-notifications', [
                'title'       => 'X',
                'body'        => 'Y',
                'destination' => 'course-detail',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);

        Queue::assertNothingPushed();
    }

    /**
     * Preferring a client-supplied `route` over an explicit `destination` would
     * reintroduce the free-text hole the picker exists to close.
     */
    public function test_the_destination_key_wins_over_a_route_sent_alongside_it(): void
    {
        $response = $this->actingAs($this->admin())
            ->postJson('/api/admin/push-notifications', [
                'title'       => 'X',
                'body'        => 'Y',
                'destination' => 'cart',
                'route'       => '/courses/bridal',
            ])
            ->assertCreated();

        $this->assertSame('/cart', $response->json('data.route'));
    }

    public function test_it_rejects_an_unknown_destination_key(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/push-notifications', [
                'title'       => 'X',
                'body'        => 'Y',
                'destination' => 'admin-panel',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['destination']);
    }

    public function test_it_lists_the_destinations_the_compose_form_offers(): void
    {
        $response = $this->actingAs($this->admin())
            ->getJson('/api/admin/push-notifications/destinations')
            ->assertOk();

        $keys = collect($response->json('data'));

        $this->assertTrue($keys->contains('key', 'news'));
        $this->assertTrue($keys->contains('key', 'course-detail'));

        $this->assertTrue($keys->firstWhere('key', 'course-detail')['requires_slug']);
        $this->assertFalse($keys->firstWhere('key', 'news')['requires_slug']);

        // The model class backing a destination is a server-side concern and
        // must not leak. Asserted by absence rather than by comparing the whole
        // key list, which would fail on an unrelated reordering.
        $this->assertArrayNotHasKey('model', $response->json('data.0'));
    }

    public function test_the_destinations_endpoint_is_admin_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'student']))
            ->getJson('/api/admin/push-notifications/destinations')
            ->assertForbidden();
    }

    public function test_it_rejects_an_overlong_title(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/push-notifications', [
                'title' => str_repeat('a', 101),
                'body'  => 'Y',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    /**
     * With Firebase not configured the send must be visibly recorded as
     * skipped rather than reported as sent — the admin has to be able to tell
     * that nobody was reached.
     */
    public function test_a_send_is_recorded_as_skipped_when_push_is_disabled(): void
    {
        config(['push.enabled' => false]);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/push-notifications', ['title' => 'X', 'body' => 'Y'])
            ->assertCreated()
            ->assertJsonPath('data.status', PushNotificationLog::STATUS_SKIPPED);

        Queue::assertNothingPushed();
    }

    /**
     * With QUEUE_CONNECTION=sync — this project's current setting — dispatch()
     * runs the broadcast inline before the controller returns. The job updates
     * its own model instance, so the response must re-read the row rather than
     * report the stale 'pending' it was created with. An API that contradicts
     * its own stored state is worse than a slow one.
     */
    public function test_the_response_reports_the_real_status_when_the_queue_runs_synchronously(): void
    {
        config(['queue.default' => 'sync']);
        Queue::swap($this->realQueue);

        $messaging = $this->createMock(Messaging::class);
        $messaging->method('sendMulticast')->willReturn(MulticastSendReport::withItems([]));
        $this->app->instance(Messaging::class, $messaging);

        $response = $this->actingAs($this->admin())
            ->postJson('/api/admin/push-notifications', ['title' => 'X', 'body' => 'Y'])
            ->assertCreated();

        $stored = PushNotificationLog::find($response->json('data.id'));

        $this->assertSame($stored->status, $response->json('data.status'));
        $this->assertSame(PushNotificationLog::STATUS_SENT, $response->json('data.status'));
    }

    // ---------------------------------------------------------------------
    // Stats
    // ---------------------------------------------------------------------

    public function test_stats_reports_the_reachable_device_count_and_flag(): void
    {
        $user = User::factory()->create();
        DeviceToken::create(['user_id' => $user->id, 'token' => 't1', 'platform' => 'android']);
        DeviceToken::create(['user_id' => $user->id, 'token' => 't2', 'platform' => 'ios']);

        $this->actingAs($this->admin())
            ->getJson('/api/admin/push-notifications/stats')
            ->assertOk()
            ->assertJsonPath('data.device_count', 2)
            ->assertJsonPath('data.push_enabled', true);
    }
}
