<?php

namespace Tests\Feature\Instructor;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InstructorLessonTest extends TestCase
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

    private function sectionFor(Course $course, int $position = 0): Section
    {
        return Section::factory()->create(['course_id' => $course->id, 'position' => $position]);
    }

    // -------------------------------------------------------------------------
    // Create lesson
    // -------------------------------------------------------------------------

    public function test_create_lesson_auto_assigns_position(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $section = $this->sectionFor($course);

        Sanctum::actingAs($instructor);

        // First lesson → position 0
        $r1 = $this->postJson("/api/instructor/sections/{$section->id}/lessons", [
            'title' => 'Lesson One',
        ])->assertStatus(201);

        $this->assertEquals(0, $r1->json('data.position'));

        // Second lesson → position 1
        $r2 = $this->postJson("/api/instructor/sections/{$section->id}/lessons", [
            'title' => 'Lesson Two',
        ])->assertStatus(201);

        $this->assertEquals(1, $r2->json('data.position'));
    }

    public function test_create_lesson_with_valid_video_url(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $section = $this->sectionFor($course);

        Sanctum::actingAs($instructor);

        $this->postJson("/api/instructor/sections/{$section->id}/lessons", [
            'title'     => 'Video Lesson',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'duration'  => 300,
            'is_free'   => true,
        ])->assertStatus(201)
          ->assertJsonPath('data.video_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
          ->assertJsonPath('data.is_free', true);
    }

    public function test_create_lesson_with_invalid_video_url_returns_422(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $section = $this->sectionFor($course);

        Sanctum::actingAs($instructor);

        $this->postJson("/api/instructor/sections/{$section->id}/lessons", [
            'title'     => 'Bad Lesson',
            'video_url' => 'https://evil.com/not-a-video',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['video_url']);
    }

    public function test_create_lesson_validates_title_required(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $section = $this->sectionFor($course);

        Sanctum::actingAs($instructor);

        $this->postJson("/api/instructor/sections/{$section->id}/lessons", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_create_lesson_returns_403_for_foreign_section(): void
    {
        $instructor1 = $this->instructor();
        $instructor2 = $this->instructor();
        $course = $this->courseFor($instructor2);
        $section = $this->sectionFor($course);

        Sanctum::actingAs($instructor1);

        $this->postJson("/api/instructor/sections/{$section->id}/lessons", [
            'title' => 'Sneaky Lesson',
        ])->assertStatus(403);
    }

    public function test_create_lesson_response_has_expected_structure(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $section = $this->sectionFor($course);

        Sanctum::actingAs($instructor);

        $this->postJson("/api/instructor/sections/{$section->id}/lessons", [
            'title' => 'Structure Test Lesson',
        ])->assertStatus(201)
          ->assertJsonStructure([
              'data' => [
                  'id', 'section_id', 'title', 'description',
                  'video_url', 'duration', 'position', 'is_free',
              ],
          ]);
    }

    // -------------------------------------------------------------------------
    // Update lesson
    // -------------------------------------------------------------------------

    public function test_update_lesson_changes_fields(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $section = $this->sectionFor($course);
        $lesson = Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($instructor);

        $this->patchJson("/api/instructor/lessons/{$lesson->id}", [
            'title'     => 'Updated Lesson Title',
            'video_url' => 'https://vimeo.com/123456789',
            'is_free'   => true,
        ])->assertStatus(200)
          ->assertJsonPath('data.title', 'Updated Lesson Title')
          ->assertJsonPath('data.video_url', 'https://vimeo.com/123456789')
          ->assertJsonPath('data.is_free', true);
    }

    public function test_update_lesson_returns_403_for_foreign_course(): void
    {
        $instructor1 = $this->instructor();
        $instructor2 = $this->instructor();
        $course = $this->courseFor($instructor2);
        $section = $this->sectionFor($course);
        $lesson = Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($instructor1);

        $this->patchJson("/api/instructor/lessons/{$lesson->id}", [
            'title' => 'Hack',
        ])->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Delete lesson
    // -------------------------------------------------------------------------

    public function test_delete_lesson_removes_it(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $section = $this->sectionFor($course);
        $lesson = Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($instructor);

        $this->deleteJson("/api/instructor/lessons/{$lesson->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('lessons', ['id' => $lesson->id]);
    }

    public function test_delete_lesson_returns_403_for_foreign_course(): void
    {
        $instructor1 = $this->instructor();
        $instructor2 = $this->instructor();
        $course = $this->courseFor($instructor2);
        $section = $this->sectionFor($course);
        $lesson = Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($instructor1);

        $this->deleteJson("/api/instructor/lessons/{$lesson->id}")
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Reorder lessons
    // -------------------------------------------------------------------------

    public function test_reorder_lessons_happy_path(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $section = $this->sectionFor($course);
        $l1 = Lesson::factory()->create(['section_id' => $section->id, 'position' => 0]);
        $l2 = Lesson::factory()->create(['section_id' => $section->id, 'position' => 1]);
        $l3 = Lesson::factory()->create(['section_id' => $section->id, 'position' => 2]);

        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/instructor/sections/{$section->id}/lessons/reorder", [
            'ordered_ids' => [$l3->id, $l1->id, $l2->id],
        ])->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals($l3->id, $data[0]['id']);
        $this->assertEquals(0, $data[0]['position']);
        $this->assertEquals($l1->id, $data[1]['id']);
        $this->assertEquals(1, $data[1]['position']);
    }

    public function test_reorder_lessons_422_when_ids_dont_match(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $section = $this->sectionFor($course);
        $l1 = Lesson::factory()->create(['section_id' => $section->id]);
        $l2 = Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($instructor);

        // Missing l2, includes a fake id
        $this->patchJson("/api/instructor/sections/{$section->id}/lessons/reorder", [
            'ordered_ids' => [$l1->id, 99999],
        ])->assertStatus(422);
    }

    public function test_reorder_lessons_returns_403_for_foreign_section(): void
    {
        $instructor1 = $this->instructor();
        $instructor2 = $this->instructor();
        $course = $this->courseFor($instructor2);
        $section = $this->sectionFor($course);
        $lesson = Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($instructor1);

        $this->patchJson("/api/instructor/sections/{$section->id}/lessons/reorder", [
            'ordered_ids' => [$lesson->id],
        ])->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // is_practice field (new — practice submissions feature)
    // -------------------------------------------------------------------------

    public function test_create_lesson_with_is_practice_true_persists_and_returns_it(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $section = $this->sectionFor($course);

        Sanctum::actingAs($instructor);

        $response = $this->postJson("/api/instructor/sections/{$section->id}/lessons", [
            'title'       => 'Practice Lesson',
            'is_practice' => true,
        ])->assertStatus(201)
          ->assertJsonPath('data.is_practice', true);

        $this->assertDatabaseHas('lessons', [
            'id'          => $response->json('data.id'),
            'is_practice' => true,
        ]);
    }

    public function test_update_lesson_is_practice_field(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $section = $this->sectionFor($course);
        $lesson = Lesson::factory()->create(['section_id' => $section->id, 'is_practice' => false]);

        Sanctum::actingAs($instructor);

        $this->patchJson("/api/instructor/lessons/{$lesson->id}", [
            'is_practice' => true,
        ])->assertStatus(200)
          ->assertJsonPath('data.is_practice', true);

        $this->assertDatabaseHas('lessons', [
            'id'          => $lesson->id,
            'is_practice' => true,
        ]);
    }
}
