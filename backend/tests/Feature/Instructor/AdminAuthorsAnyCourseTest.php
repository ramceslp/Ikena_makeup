<?php

namespace Tests\Feature\Instructor;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers CoursePolicy::manage — the single definition of who may author a
 * course — as exercised through the instructor authoring endpoints.
 *
 * The admin catalog delegates deep authoring (sections/lessons) to this editor
 * rather than cloning it, so these tests are what keep that delegation honest:
 * if the policy regresses to plain ownership, the admin "Editar contenido"
 * hand-off silently starts returning 403.
 *
 * The complementary boundary — that an instructor still cannot touch another
 * instructor's course — is asserted here too, because widening the rule for
 * admins must not widen it for peers.
 */
class AdminAuthorsAnyCourseTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function instructor(): User
    {
        return User::factory()->instructor()->create();
    }

    /** A course owned by someone other than the acting user. */
    private function foreignCourse(): Course
    {
        return Course::factory()->create([
            'instructor_id' => $this->instructor()->id,
            'is_published'  => false,
        ]);
    }

    private function sectionOf(Course $course): Section
    {
        return Section::factory()->create(['course_id' => $course->id]);
    }

    // =========================================================================
    // Course-level authoring
    // =========================================================================

    public function test_admin_can_show_a_course_owned_by_another_instructor(): void
    {
        $course = $this->foreignCourse();

        Sanctum::actingAs($this->admin());

        $this->getJson("/api/instructor/courses/{$course->slug}")
            ->assertOk()
            ->assertJsonPath('data.id', $course->id);
    }

    public function test_admin_can_update_a_course_owned_by_another_instructor(): void
    {
        $course = $this->foreignCourse();

        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/instructor/courses/{$course->slug}", ['price' => 55])
            ->assertOk()
            ->assertJsonPath('data.price', '55.00');
    }

    public function test_admin_can_publish_a_course_owned_by_another_instructor(): void
    {
        $course  = $this->foreignCourse();
        $section = $this->sectionOf($course);
        Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/instructor/courses/{$course->slug}/publish")
            ->assertOk()
            ->assertJsonPath('data.is_published', true);
    }

    public function test_admin_can_delete_a_course_owned_by_another_instructor(): void
    {
        $course = $this->foreignCourse();

        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/instructor/courses/{$course->slug}")->assertStatus(204);

        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }

    /**
     * The instructor index stays ownership-scoped even for an admin. "Panel
     * instructor" must keep meaning "my courses"; the catalog-wide view is
     * /api/admin/courses.
     */
    public function test_instructor_index_stays_ownership_scoped_for_admins(): void
    {
        $this->foreignCourse();
        $admin = $this->admin();
        Course::factory()->create(['instructor_id' => $admin->id]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/instructor/courses')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($admin->id, Course::find($response->json('data.0.id'))->instructor_id);
    }

    // =========================================================================
    // Section and lesson authoring — the reason the delegation exists
    // =========================================================================

    public function test_admin_can_create_a_section_on_a_foreign_course(): void
    {
        $course = $this->foreignCourse();

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/instructor/courses/{$course->slug}/sections", [
            'title' => 'Modulo 1',
        ])->assertStatus(201);

        $this->assertDatabaseHas('sections', [
            'course_id' => $course->id,
            'title'     => 'Modulo 1',
        ]);
    }

    public function test_admin_can_update_a_section_on_a_foreign_course(): void
    {
        $section = $this->sectionOf($this->foreignCourse());

        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/instructor/sections/{$section->id}", ['title' => 'Renombrado'])
            ->assertOk();

        $this->assertDatabaseHas('sections', [
            'id'    => $section->id,
            'title' => 'Renombrado',
        ]);
    }

    public function test_admin_can_delete_a_section_on_a_foreign_course(): void
    {
        $section = $this->sectionOf($this->foreignCourse());

        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/instructor/sections/{$section->id}")->assertStatus(204);

        $this->assertDatabaseMissing('sections', ['id' => $section->id]);
    }

    public function test_admin_can_create_a_lesson_on_a_foreign_course(): void
    {
        $section = $this->sectionOf($this->foreignCourse());

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/instructor/sections/{$section->id}/lessons", [
            'title'     => 'Leccion 1',
            'video_url' => 'https://example.com/video.mp4',
        ])->assertStatus(201);

        $this->assertDatabaseHas('lessons', [
            'section_id' => $section->id,
            'title'      => 'Leccion 1',
        ]);
    }

    public function test_admin_can_delete_a_lesson_on_a_foreign_course(): void
    {
        $section = $this->sectionOf($this->foreignCourse());
        $lesson  = Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/instructor/lessons/{$lesson->id}")->assertStatus(204);

        $this->assertDatabaseMissing('lessons', ['id' => $lesson->id]);
    }

    // =========================================================================
    // The rule widened for admins only — peers stay locked out
    // =========================================================================

    public function test_instructor_still_cannot_update_a_peers_course(): void
    {
        $course = $this->foreignCourse();

        Sanctum::actingAs($this->instructor());

        $this->patchJson("/api/instructor/courses/{$course->slug}", ['price' => 10])
            ->assertStatus(403);
    }

    public function test_instructor_still_cannot_delete_a_peers_course(): void
    {
        $course = $this->foreignCourse();

        Sanctum::actingAs($this->instructor());

        $this->deleteJson("/api/instructor/courses/{$course->slug}")->assertStatus(403);
    }

    public function test_instructor_still_cannot_author_sections_on_a_peers_course(): void
    {
        $section = $this->sectionOf($this->foreignCourse());

        Sanctum::actingAs($this->instructor());

        $this->patchJson("/api/instructor/sections/{$section->id}", ['title' => 'Hijack'])
            ->assertStatus(403);
    }

    public function test_instructor_still_cannot_author_lessons_on_a_peers_course(): void
    {
        $section = $this->sectionOf($this->foreignCourse());
        $lesson  = Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($this->instructor());

        $this->deleteJson("/api/instructor/lessons/{$lesson->id}")->assertStatus(403);
    }
}
