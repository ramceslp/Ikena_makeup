<?php

namespace Tests\Feature\Push;

use App\Jobs\BroadcastPushNotification;
use App\Models\Course;
use App\Models\Post;
use App\Models\PushNotificationLog;
use App\Models\User;
use App\Services\Push\PushDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * PushDispatcherTest — push-notifications Slice 2.
 *
 * Covers the decision rules (when a push is warranted), the history row that
 * gets written, and the idempotency guard. Delivery itself is covered by
 * PushBroadcasterTest and BroadcastPushNotificationJobTest.
 */
class PushDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private PushDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config(['push.enabled' => true]);

        $this->dispatcher = $this->app->make(PushDispatcher::class);
    }

    // ---------------------------------------------------------------------
    // Posts
    // ---------------------------------------------------------------------

    public function test_a_published_post_is_broadcast_with_a_deep_link_to_itself(): void
    {
        $post = Post::factory()->create([
            'title'        => 'Nueva colección de labiales',
            'excerpt'      => 'Ya está disponible en el catálogo.',
            'slug'         => 'nueva-coleccion-labiales',
            'is_published' => true,
        ]);

        $log = $this->dispatcher->forPost($post);

        $this->assertNotNull($log);
        $this->assertSame(PushNotificationLog::TYPE_POST_PUBLISHED, $log->type);
        $this->assertSame('Nueva colección de labiales', $log->title);
        $this->assertSame('Ya está disponible en el catálogo.', $log->body);
        $this->assertSame(['route' => '/noticias/nueva-coleccion-labiales'], $log->data);
        $this->assertSame(PushNotificationLog::STATUS_PENDING, $log->status);

        Queue::assertPushed(BroadcastPushNotification::class);
    }

    public function test_a_draft_post_is_not_broadcast(): void
    {
        $post = Post::factory()->create(['is_published' => false]);

        $this->assertNull($this->dispatcher->forPost($post));
        $this->assertDatabaseCount('push_notification_logs', 0);
        Queue::assertNothingPushed();
    }

    /**
     * The guard that matters most in practice: an admin fixing a typo on an
     * already-published post must not re-notify every device.
     */
    public function test_a_post_is_never_broadcast_twice(): void
    {
        $post = Post::factory()->create(['is_published' => true]);

        $this->assertNotNull($this->dispatcher->forPost($post));
        $this->assertNull($this->dispatcher->forPost($post->fresh()));

        $this->assertDatabaseCount('push_notification_logs', 1);
        Queue::assertPushed(BroadcastPushNotification::class, 1);
    }

    public function test_it_stamps_push_notified_at_on_the_post(): void
    {
        $post = Post::factory()->create(['is_published' => true]);

        $this->assertNull($post->push_notified_at);

        $this->dispatcher->forPost($post);

        $this->assertNotNull($post->fresh()->push_notified_at);
    }

    public function test_a_post_without_an_excerpt_falls_back_to_generic_copy(): void
    {
        $post = Post::factory()->create(['excerpt' => null, 'is_published' => true]);

        $log = $this->dispatcher->forPost($post);

        $this->assertSame('Nueva publicación en Ikena. Tocá para leerla.', $log->body);
    }

    public function test_a_long_excerpt_is_trimmed(): void
    {
        $post = Post::factory()->create([
            'excerpt'      => str_repeat('a', 400),
            'is_published' => true,
        ]);

        $log = $this->dispatcher->forPost($post);

        $this->assertLessThanOrEqual(163, mb_strlen($log->body)); // 160 + '...'
    }

    // ---------------------------------------------------------------------
    // Courses
    // ---------------------------------------------------------------------

    public function test_a_published_course_is_broadcast_with_a_deep_link_to_itself(): void
    {
        $course = Course::factory()->create([
            'title'        => 'Maquillaje de novias',
            'slug'         => 'maquillaje-de-novias',
            'is_published' => true,
        ]);

        $log = $this->dispatcher->forCourse($course);

        $this->assertNotNull($log);
        $this->assertSame(PushNotificationLog::TYPE_COURSE_PUBLISHED, $log->type);
        $this->assertSame('Nuevo curso disponible', $log->title);
        $this->assertSame('Maquillaje de novias', $log->body);
        $this->assertSame(['route' => '/cursos/maquillaje-de-novias'], $log->data);
    }

    public function test_an_unpublished_course_is_not_broadcast(): void
    {
        $course = Course::factory()->create(['is_published' => false]);

        $this->assertNull($this->dispatcher->forCourse($course));
        Queue::assertNothingPushed();
    }

    /**
     * Unpublishing and republishing a course is an editorial correction, not a
     * new launch, and must not re-notify.
     */
    public function test_a_course_is_never_broadcast_twice_across_a_republish(): void
    {
        $course = Course::factory()->create(['is_published' => true]);

        $this->assertNotNull($this->dispatcher->forCourse($course));

        $course->update(['is_published' => false]);
        $course->update(['is_published' => true]);

        $this->assertNull($this->dispatcher->forCourse($course->fresh()));
        $this->assertDatabaseCount('push_notification_logs', 1);
    }

    // ---------------------------------------------------------------------
    // Custom sends and the disabled flag
    // ---------------------------------------------------------------------

    public function test_a_custom_send_records_its_author_and_optional_route(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $log = $this->dispatcher->custom('Promo 2x1', 'Solo por hoy', '/cursos', $admin->id);

        $this->assertSame(PushNotificationLog::TYPE_CUSTOM, $log->type);
        $this->assertSame($admin->id, $log->sent_by);
        $this->assertSame(['route' => '/cursos'], $log->data);
        Queue::assertPushed(BroadcastPushNotification::class);
    }

    public function test_a_custom_send_without_a_route_stores_no_data_payload(): void
    {
        $log = $this->dispatcher->custom('Aviso', 'Cerramos el lunes', null, null);

        $this->assertNull($log->data);
    }

    /**
     * With push disabled the attempt must still be visible in the history —
     * "nobody was notified" and "nothing happened" have to be
     * distinguishable, which is the whole reason the log exists.
     */
    public function test_it_records_a_skipped_row_and_queues_nothing_when_push_is_disabled(): void
    {
        config(['push.enabled' => false]);

        $post = Post::factory()->create(['is_published' => true]);

        $log = $this->dispatcher->forPost($post);

        $this->assertSame(PushNotificationLog::STATUS_SKIPPED, $log->status);
        $this->assertNotNull($log->sent_at);
        Queue::assertNothingPushed();
    }

    /**
     * A skipped send still consumes the one-shot guard. Otherwise every post
     * created while push was off would fire retroactively the next time it was
     * touched after Firebase went live.
     */
    public function test_a_skipped_send_still_stamps_the_post_as_notified(): void
    {
        config(['push.enabled' => false]);

        $post = Post::factory()->create(['is_published' => true]);
        $this->dispatcher->forPost($post);

        $this->assertNotNull($post->fresh()->push_notified_at);
    }
}
