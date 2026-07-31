<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCourseControllerTest extends TestCase
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

    /** A course with one section and one lesson — publishable. */
    private function courseWithLesson(array $attributes = []): Course
    {
        $course  = Course::factory()->create($attributes + ['is_published' => false]);
        $section = Section::factory()->create(['course_id' => $course->id]);
        Lesson::factory()->create(['section_id' => $section->id]);

        return $course;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title'         => 'Maquillaje Editorial Avanzado',
            'description'   => 'Un curso completo de maquillaje editorial.',
            'price'         => 79.99,
            'instructor_id' => $this->instructor()->id,
        ], $overrides);
    }

    // =========================================================================
    // Auth — non-admin access rejected
    // =========================================================================

    public function test_guest_cannot_access_admin_courses_index_401(): void
    {
        $this->getJson('/api/admin/courses')->assertStatus(401);
    }

    public function test_student_cannot_access_admin_courses_403(): void
    {
        Sanctum::actingAs($this->student());
        $this->getJson('/api/admin/courses')->assertStatus(403);
    }

    /**
     * The key boundary of this feature: an instructor is NOT a catalog admin.
     * Instructors keep their ownership-scoped endpoints and nothing more.
     */
    public function test_instructor_cannot_access_admin_courses_403(): void
    {
        Sanctum::actingAs($this->instructor());
        $this->getJson('/api/admin/courses')->assertStatus(403);
    }

    public function test_instructor_cannot_store_course_via_admin_endpoint_403(): void
    {
        Sanctum::actingAs($this->instructor());
        $this->postJson('/api/admin/courses', [])->assertStatus(403);
    }

    public function test_instructor_cannot_delete_course_via_admin_endpoint_403(): void
    {
        $course = Course::factory()->create();
        Sanctum::actingAs($this->instructor());
        $this->deleteJson("/api/admin/courses/{$course->id}")->assertStatus(403);
    }

    // =========================================================================
    // Index — catalog-wide listing
    // =========================================================================

    public function test_admin_sees_courses_from_every_instructor(): void
    {
        $first  = $this->instructor();
        $second = $this->instructor();

        Course::factory()->create(['instructor_id' => $first->id]);
        Course::factory()->create(['instructor_id' => $second->id]);

        Sanctum::actingAs($this->admin());

        $response = $this->getJson('/api/admin/courses')->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    /**
     * Regression guard for the gap this feature closes: before it, the only
     * course listing available scoped to the caller's own id, so an admin who
     * owned nothing saw an empty catalog.
     */
    public function test_admin_who_owns_no_courses_still_sees_the_catalog(): void
    {
        Course::factory()->count(3)->create();

        Sanctum::actingAs($this->admin());

        $response = $this->getJson('/api/admin/courses')->assertOk();

        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_includes_drafts_and_published(): void
    {
        Course::factory()->create(['is_published' => true]);
        Course::factory()->create(['is_published' => false]);

        Sanctum::actingAs($this->admin());

        $response = $this->getJson('/api/admin/courses')->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_row_carries_owning_instructor_and_counts(): void
    {
        $owner  = $this->instructor();
        $course = $this->courseWithLesson(['instructor_id' => $owner->id]);

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/courses')
            ->assertOk()
            ->assertJsonPath('data.0.id', $course->id)
            ->assertJsonPath('data.0.instructor.id', $owner->id)
            ->assertJsonPath('data.0.instructor.name', $owner->name)
            ->assertJsonPath('data.0.sections_count', 1)
            ->assertJsonPath('data.0.lessons_count', 1)
            ->assertJsonPath('data.0.students_count', 0);
    }

    public function test_index_is_paginated(): void
    {
        Course::factory()->count(25)->create();

        Sanctum::actingAs($this->admin());

        $response = $this->getJson('/api/admin/courses')->assertOk();

        $this->assertCount(20, $response->json('data'));
        $this->assertSame(25, $response->json('meta.total'));
    }

    public function test_index_filters_by_instructor(): void
    {
        $target = $this->instructor();
        Course::factory()->create(['instructor_id' => $target->id]);
        Course::factory()->count(2)->create();

        Sanctum::actingAs($this->admin());

        $response = $this->getJson("/api/admin/courses?instructor_id={$target->id}")->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($target->id, $response->json('data.0.instructor.id'));
    }

    public function test_index_filters_by_title_search(): void
    {
        Course::factory()->create(['title' => 'Cejas perfectas', 'slug' => 'cejas-perfectas']);
        Course::factory()->create(['title' => 'Novias premium', 'slug' => 'novias-premium']);

        Sanctum::actingAs($this->admin());

        $response = $this->getJson('/api/admin/courses?search=Cejas')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Cejas perfectas', $response->json('data.0.title'));
    }

    /**
     * is_published=0 must survive the filter pipeline. A `filled()` check would
     * silently drop it and return the whole catalog instead of drafts only.
     */
    public function test_index_filters_drafts_with_falsy_published_flag(): void
    {
        Course::factory()->create(['is_published' => true]);
        Course::factory()->create(['is_published' => false]);

        Sanctum::actingAs($this->admin());

        $response = $this->getJson('/api/admin/courses?is_published=0')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertFalse($response->json('data.0.is_published'));
    }

    // =========================================================================
    // Store
    // =========================================================================

    public function test_admin_creates_course_on_behalf_of_an_instructor(): void
    {
        $owner = $this->instructor();

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/courses', $this->validPayload([
            'instructor_id' => $owner->id,
        ]))
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'Maquillaje Editorial Avanzado')
            ->assertJsonPath('data.instructor_id', $owner->id);

        $this->assertDatabaseHas('courses', [
            'title'         => 'Maquillaje Editorial Avanzado',
            'instructor_id' => $owner->id,
        ]);
    }

    /**
     * The admin is not silently made the owner — that was the trap of reusing
     * the instructor endpoint, where instructor_id came from the session.
     */
    public function test_created_course_is_not_owned_by_the_acting_admin(): void
    {
        $owner = $this->instructor();
        $admin = $this->admin();

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/courses', $this->validPayload(['instructor_id' => $owner->id]))
            ->assertStatus(201);

        $this->assertDatabaseMissing('courses', ['instructor_id' => $admin->id]);
    }

    public function test_created_course_starts_as_draft(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/courses', $this->validPayload())
            ->assertStatus(201)
            ->assertJsonPath('data.is_published', false);
    }

    public function test_store_ignores_a_submitted_published_flag(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/courses', $this->validPayload(['is_published' => true]))
            ->assertStatus(201)
            ->assertJsonPath('data.is_published', false);
    }

    public function test_store_generates_a_unique_slug_on_collision(): void
    {
        Course::factory()->create(['title' => 'Curso Base', 'slug' => 'curso-base']);

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/courses', $this->validPayload(['title' => 'Curso Base']))
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'curso-base-2');
    }

    public function test_store_requires_an_instructor(): void
    {
        Sanctum::actingAs($this->admin());

        $payload = $this->validPayload();
        unset($payload['instructor_id']);

        $this->postJson('/api/admin/courses', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('instructor_id');
    }

    public function test_store_rejects_a_student_as_instructor(): void
    {
        $student = $this->student();

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/courses', $this->validPayload(['instructor_id' => $student->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('instructor_id');
    }

    public function test_store_validates_required_fields(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/courses', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'description', 'instructor_id']);
    }

    // =========================================================================
    // Show / Update / Destroy
    // =========================================================================

    public function test_admin_shows_any_course_including_drafts(): void
    {
        $course = Course::factory()->create(['is_published' => false]);

        Sanctum::actingAs($this->admin());

        $this->getJson("/api/admin/courses/{$course->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $course->id)
            ->assertJsonPath('data.is_published', false);
    }

    public function test_show_returns_404_for_unknown_course(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/courses/999999')->assertStatus(404);
    }

    public function test_admin_updates_a_course_owned_by_someone_else(): void
    {
        $course = Course::factory()->create(['instructor_id' => $this->instructor()->id]);

        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/courses/{$course->id}", ['price' => 149.5])
            ->assertOk()
            ->assertJsonPath('data.price', '149.50');
    }

    public function test_update_regenerates_slug_when_title_changes(): void
    {
        $course = Course::factory()->create(['title' => 'Viejo', 'slug' => 'viejo']);

        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/courses/{$course->id}", ['title' => 'Nuevo Titulo'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'nuevo-titulo');
    }

    public function test_update_keeps_slug_when_title_is_unchanged(): void
    {
        $course = Course::factory()->create(['title' => 'Estable', 'slug' => 'estable-original']);

        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/courses/{$course->id}", ['title' => 'Estable'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'estable-original');
    }

    public function test_admin_reassigns_a_course_to_another_instructor(): void
    {
        $original = $this->instructor();
        $next     = $this->instructor();
        $course   = Course::factory()->create(['instructor_id' => $original->id]);

        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/courses/{$course->id}", ['instructor_id' => $next->id])
            ->assertOk()
            ->assertJsonPath('data.instructor_id', $next->id)
            ->assertJsonPath('data.instructor.id', $next->id);

        $this->assertDatabaseHas('courses', [
            'id'            => $course->id,
            'instructor_id' => $next->id,
        ]);
    }

    public function test_reassignment_rejects_a_student(): void
    {
        $course  = Course::factory()->create();
        $student = $this->student();

        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/courses/{$course->id}", ['instructor_id' => $student->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('instructor_id');
    }

    public function test_update_accepts_a_category(): void
    {
        $course   = Course::factory()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/courses/{$course->id}", ['category_id' => $category->id])
            ->assertOk()
            ->assertJsonPath('data.category_id', $category->id);
    }

    public function test_admin_deletes_a_course_owned_by_someone_else(): void
    {
        $course = Course::factory()->create(['instructor_id' => $this->instructor()->id]);

        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/admin/courses/{$course->id}")->assertStatus(204);

        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }

    // =========================================================================
    // Publish / Unpublish
    // =========================================================================

    public function test_admin_publishes_a_course_with_lessons(): void
    {
        $course = $this->courseWithLesson();

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/admin/courses/{$course->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.is_published', true);

        $this->assertDatabaseHas('courses', [
            'id'           => $course->id,
            'is_published' => true,
        ]);
    }

    /**
     * Admin authority does not override the product invariant. Publishing an
     * empty course would ship a broken detail page, so the guard is enforced
     * identically on both surfaces.
     */
    public function test_admin_cannot_publish_a_course_with_no_lessons(): void
    {
        $course = Course::factory()->create(['is_published' => false]);

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/admin/courses/{$course->id}/publish")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot publish a course with no lessons.');

        $this->assertDatabaseHas('courses', [
            'id'           => $course->id,
            'is_published' => false,
        ]);
    }

    public function test_admin_unpublishes_a_course(): void
    {
        $course = Course::factory()->create(['is_published' => true]);

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/admin/courses/{$course->id}/unpublish")
            ->assertOk()
            ->assertJsonPath('data.is_published', false);
    }

    // =========================================================================
    // Instructor picker
    // =========================================================================

    public function test_admin_lists_instructors_for_the_picker(): void
    {
        $instructor = $this->instructor();
        $this->student();

        Sanctum::actingAs($this->admin());

        $response = $this->getJson('/api/admin/instructors')->assertOk();

        $ids = array_column($response->json('data'), 'id');

        $this->assertContains($instructor->id, $ids);
    }

    public function test_instructor_picker_excludes_students(): void
    {
        $student = $this->student();

        Sanctum::actingAs($this->admin());

        $response = $this->getJson('/api/admin/instructors')->assertOk();

        $ids = array_column($response->json('data'), 'id');

        $this->assertNotContains($student->id, $ids);
    }

    public function test_instructor_picker_does_not_leak_emails(): void
    {
        $this->instructor();

        Sanctum::actingAs($this->admin());

        $response = $this->getJson('/api/admin/instructors')->assertOk();

        $this->assertArrayNotHasKey('email', $response->json('data.0'));
    }

    public function test_student_cannot_list_instructors_403(): void
    {
        Sanctum::actingAs($this->student());

        $this->getJson('/api/admin/instructors')->assertStatus(403);
    }
}
