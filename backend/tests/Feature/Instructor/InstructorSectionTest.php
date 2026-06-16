<?php

namespace Tests\Feature\Instructor;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InstructorSectionTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function instructor(): User
    {
        return User::factory()->instructor()->create();
    }

    private function courseFor(User $instructor): Course
    {
        return Course::factory()->create([
            'instructor_id' => $instructor->id,
            'is_published'  => false,
        ]);
    }

    // -------------------------------------------------------------------------
    // Create section
    // -------------------------------------------------------------------------

    public function test_create_section_auto_assigns_position(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        Sanctum::actingAs($instructor);

        // First section → position 0
        $r1 = $this->postJson("/api/instructor/courses/{$course->slug}/sections", [
            'title' => 'Section One',
        ])->assertStatus(201);

        $this->assertEquals(0, $r1->json('data.position'));

        // Second section → position 1
        $r2 = $this->postJson("/api/instructor/courses/{$course->slug}/sections", [
            'title' => 'Section Two',
        ])->assertStatus(201);

        $this->assertEquals(1, $r2->json('data.position'));
    }

    public function test_create_section_validates_title_required(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        Sanctum::actingAs($instructor);

        $this->postJson("/api/instructor/courses/{$course->slug}/sections", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_create_section_returns_403_for_foreign_course(): void
    {
        $instructor1 = $this->instructor();
        $instructor2 = $this->instructor();
        $course = $this->courseFor($instructor2);

        Sanctum::actingAs($instructor1);

        $this->postJson("/api/instructor/courses/{$course->slug}/sections", [
            'title' => 'Sneaky Section',
        ])->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Update section
    // -------------------------------------------------------------------------

    public function test_update_section_changes_title(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $section = Section::factory()->create(['course_id' => $course->id]);

        Sanctum::actingAs($instructor);

        $this->patchJson("/api/instructor/sections/{$section->id}", [
            'title' => 'Updated Section Title',
        ])->assertStatus(200)
          ->assertJsonPath('data.title', 'Updated Section Title');
    }

    public function test_update_section_returns_403_for_foreign_course_section(): void
    {
        $instructor1 = $this->instructor();
        $instructor2 = $this->instructor();
        $course = $this->courseFor($instructor2);
        $section = Section::factory()->create(['course_id' => $course->id]);

        Sanctum::actingAs($instructor1);

        $this->patchJson("/api/instructor/sections/{$section->id}", [
            'title' => 'Hack',
        ])->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Delete section
    // -------------------------------------------------------------------------

    public function test_delete_section_cascades_lessons(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $section = Section::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($instructor);

        $this->deleteJson("/api/instructor/sections/{$section->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('sections', ['id' => $section->id]);
        $this->assertDatabaseMissing('lessons', ['id' => $lesson->id]);
    }

    public function test_delete_section_returns_403_for_foreign_course(): void
    {
        $instructor1 = $this->instructor();
        $instructor2 = $this->instructor();
        $course = $this->courseFor($instructor2);
        $section = Section::factory()->create(['course_id' => $course->id]);

        Sanctum::actingAs($instructor1);

        $this->deleteJson("/api/instructor/sections/{$section->id}")
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Reorder sections
    // -------------------------------------------------------------------------

    public function test_reorder_sections_happy_path(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $s1 = Section::factory()->create(['course_id' => $course->id, 'position' => 0]);
        $s2 = Section::factory()->create(['course_id' => $course->id, 'position' => 1]);
        $s3 = Section::factory()->create(['course_id' => $course->id, 'position' => 2]);

        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/instructor/courses/{$course->slug}/sections/reorder", [
            'ordered_ids' => [$s3->id, $s1->id, $s2->id],
        ])->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals($s3->id, $data[0]['id']);
        $this->assertEquals(0, $data[0]['position']);
        $this->assertEquals($s1->id, $data[1]['id']);
        $this->assertEquals(1, $data[1]['position']);
    }

    public function test_reorder_sections_422_when_ids_dont_match(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $s1 = Section::factory()->create(['course_id' => $course->id]);
        $s2 = Section::factory()->create(['course_id' => $course->id]);

        Sanctum::actingAs($instructor);

        // Missing s2, includes a fake id
        $this->patchJson("/api/instructor/courses/{$course->slug}/sections/reorder", [
            'ordered_ids' => [$s1->id, 99999],
        ])->assertStatus(422);
    }

    public function test_reorder_sections_returns_403_for_foreign_course(): void
    {
        $instructor1 = $this->instructor();
        $instructor2 = $this->instructor();
        $course = $this->courseFor($instructor2);
        $section = Section::factory()->create(['course_id' => $course->id]);

        Sanctum::actingAs($instructor1);

        $this->patchJson("/api/instructor/courses/{$course->slug}/sections/reorder", [
            'ordered_ids' => [$section->id],
        ])->assertStatus(403);
    }
}
