<?php

namespace Tests\Feature\Push;

use App\Jobs\BroadcastPushNotification;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Post;
use App\Models\PushNotificationLog;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * PushTriggerEndpointTest — push-notifications Slice 2.
 *
 * PushDispatcherTest covers the rules in isolation; this file pins that the
 * controllers are actually WIRED to them. Without it, deleting a dispatcher
 * call from a controller would leave the whole suite green.
 */
class PushTriggerEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config(['push.enabled' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    // ---------------------------------------------------------------------
    // POST /api/admin/posts  and  POST /api/admin/posts/{post}
    // ---------------------------------------------------------------------

    public function test_creating_a_published_post_broadcasts(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/posts', [
                'title'        => 'Noticia recién salida',
                'body'         => '<p>Contenido</p>',
                'type'         => 'noticia',
                'is_published' => true,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('push_notification_logs', [
            'type'  => PushNotificationLog::TYPE_POST_PUBLISHED,
            'title' => 'Noticia recién salida',
        ]);
        Queue::assertPushed(BroadcastPushNotification::class, 1);
    }

    public function test_creating_a_draft_post_does_not_broadcast(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/posts', [
                'title'        => 'Borrador',
                'body'         => '<p>Contenido</p>',
                'type'         => 'noticia',
                'is_published' => false,
            ])
            ->assertCreated();

        $this->assertDatabaseCount('push_notification_logs', 0);
        Queue::assertNothingPushed();
    }

    public function test_publishing_an_existing_draft_broadcasts_once(): void
    {
        $post = Post::factory()->create(['is_published' => false, 'published_at' => null]);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson("/api/admin/posts/{$post->id}", ['is_published' => true])
            ->assertOk();

        $this->assertDatabaseCount('push_notification_logs', 1);
        Queue::assertPushed(BroadcastPushNotification::class, 1);
    }

    /**
     * The regression that would hurt most in production: every subsequent edit
     * of a live post re-blasting every device.
     */
    public function test_editing_an_already_published_post_does_not_rebroadcast(): void
    {
        $post = Post::factory()->create(['is_published' => true]);
        $admin = $this->admin();

        // First save publishes and notifies.
        $this->actingAs($admin)
            ->postJson("/api/admin/posts/{$post->id}", ['title' => 'Título corregido'])
            ->assertOk();

        // Two more edits must add nothing.
        $this->actingAs($admin)
            ->postJson("/api/admin/posts/{$post->id}", ['title' => 'Otra corrección'])
            ->assertOk();

        $this->actingAs($admin)
            ->postJson("/api/admin/posts/{$post->id}", ['excerpt' => 'Nuevo resumen'])
            ->assertOk();

        $this->assertDatabaseCount('push_notification_logs', 1);
        Queue::assertPushed(BroadcastPushNotification::class, 1);
    }

    // ---------------------------------------------------------------------
    // POST /api/instructor/courses/{slug}/publish
    // ---------------------------------------------------------------------

    private function publishableCourse(User $instructor): Course
    {
        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'is_published'  => false,
        ]);

        $section = Section::factory()->create(['course_id' => $course->id]);
        Lesson::factory()->create(['section_id' => $section->id]);

        return $course;
    }

    public function test_publishing_a_course_broadcasts(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = $this->publishableCourse($instructor);

        $this->actingAs($instructor)
            ->postJson("/api/instructor/courses/{$course->slug}/publish")
            ->assertOk();

        $this->assertDatabaseHas('push_notification_logs', [
            'type'  => PushNotificationLog::TYPE_COURSE_PUBLISHED,
            'title' => 'Nuevo curso disponible',
            'body'  => $course->title,
        ]);
        Queue::assertPushed(BroadcastPushNotification::class, 1);
    }

    public function test_republishing_a_course_does_not_rebroadcast(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = $this->publishableCourse($instructor);

        $this->actingAs($instructor)->postJson("/api/instructor/courses/{$course->slug}/publish")->assertOk();
        $this->actingAs($instructor)->postJson("/api/instructor/courses/{$course->slug}/unpublish")->assertOk();
        $this->actingAs($instructor)->postJson("/api/instructor/courses/{$course->slug}/publish")->assertOk();

        $this->assertDatabaseCount('push_notification_logs', 1);
        Queue::assertPushed(BroadcastPushNotification::class, 1);
    }

    /**
     * publish() returns 422 before touching is_published when the course has no
     * lessons — no push may escape on that path.
     */
    public function test_a_rejected_publish_does_not_broadcast(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'is_published'  => false,
        ]);

        $this->actingAs($instructor)
            ->postJson("/api/instructor/courses/{$course->slug}/publish")
            ->assertStatus(422);

        $this->assertDatabaseCount('push_notification_logs', 0);
        Queue::assertNothingPushed();
    }
}
