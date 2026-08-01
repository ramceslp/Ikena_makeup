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
 * Authoring side of live courses: the delivery mode, its calendar, and the
 * mode-dependent shape of a lesson.
 *
 * The student-facing half — when the join link is actually served — lives in
 * LessonAccessTest, because it is an access-control question, not an
 * authoring one.
 */
class LiveCourseAuthoringTest extends TestCase
{
    use RefreshDatabase;

    private function instructor(): User
    {
        return User::factory()->instructor()->create();
    }

    private function sectionFor(Course $course): Section
    {
        return Section::factory()->create(['course_id' => $course->id]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title'       => 'Maquillaje social en vivo',
            'description' => 'Cinco sesiones en vivo por Google Meet.',
            'price'       => 120,
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Course creation — delivery mode and calendar
    // -------------------------------------------------------------------------

    public function test_course_defaults_to_on_demand_when_mode_is_omitted(): void
    {
        Sanctum::actingAs($this->instructor());

        $this->postJson('/api/instructor/courses', $this->validPayload())
            ->assertStatus(201)
            ->assertJsonPath('data.delivery_mode', Course::DELIVERY_ON_DEMAND)
            ->assertJsonPath('data.starts_on', null)
            ->assertJsonPath('data.ends_on', null);
    }

    public function test_instructor_creates_live_course_with_calendar_and_total_hours(): void
    {
        Sanctum::actingAs($this->instructor());

        $this->postJson('/api/instructor/courses', $this->validPayload([
            'delivery_mode' => 'live',
            'starts_on'     => '2026-09-01',
            'ends_on'       => '2026-09-30',
            'total_hours'   => 20.5,
        ]))
            ->assertStatus(201)
            ->assertJsonPath('data.delivery_mode', 'live')
            ->assertJsonPath('data.starts_on', '2026-09-01')
            ->assertJsonPath('data.ends_on', '2026-09-30')
            ->assertJsonPath('data.total_hours', '20.5');
    }

    public function test_live_course_without_dates_is_rejected(): void
    {
        Sanctum::actingAs($this->instructor());

        $this->postJson('/api/instructor/courses', $this->validPayload([
            'delivery_mode' => 'live',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['starts_on', 'ends_on']);
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        Sanctum::actingAs($this->instructor());

        $this->postJson('/api/instructor/courses', $this->validPayload([
            'delivery_mode' => 'live',
            'starts_on'     => '2026-09-30',
            'ends_on'       => '2026-09-01',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ends_on']);
    }

    public function test_unknown_delivery_mode_is_rejected(): void
    {
        Sanctum::actingAs($this->instructor());

        $this->postJson('/api/instructor/courses', $this->validPayload([
            'delivery_mode' => 'webinar',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['delivery_mode']);
    }

    /**
     * The partial-update trap: a PATCH that omits delivery_mode must still be
     * judged against the mode the course ALREADY has, or a course stays live
     * with no calendar.
     */
    public function test_partial_update_cannot_clear_the_dates_of_a_live_course(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->live()->create([
            'instructor_id' => $instructor->id,
            'is_published'  => false,
        ]);

        Sanctum::actingAs($instructor);

        $this->patchJson("/api/instructor/courses/{$course->slug}", [
            'starts_on' => null,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['starts_on']);

        $this->assertNotNull($course->fresh()->starts_on);
    }

    public function test_updating_unrelated_field_on_live_course_still_succeeds(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->live()->create([
            'instructor_id' => $instructor->id,
            'is_published'  => false,
        ]);

        Sanctum::actingAs($instructor);

        $this->patchJson("/api/instructor/courses/{$course->slug}", [
            'price' => 999,
        ])->assertStatus(200);
    }

    /**
     * With starts_on absent from the payload there is no field to compare
     * against, so the stored start date has to be the reference — otherwise
     * the check silently passes and the course ends before it begins.
     */
    public function test_end_date_is_compared_against_the_stored_start_date(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->live()->create([
            'instructor_id' => $instructor->id,
            'is_published'  => false,
            'starts_on'     => '2026-09-01',
            'ends_on'       => '2026-09-30',
        ]);

        Sanctum::actingAs($instructor);

        $this->patchJson("/api/instructor/courses/{$course->slug}", [
            'ends_on' => '2026-08-01',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ends_on']);
    }

    public function test_switching_an_on_demand_course_to_live_requires_dates(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'is_published'  => false,
        ]);

        Sanctum::actingAs($instructor);

        $this->patchJson("/api/instructor/courses/{$course->slug}", [
            'delivery_mode' => 'live',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['starts_on', 'ends_on']);
    }

    // -------------------------------------------------------------------------
    // Lesson authoring — the URL field depends on the course mode
    // -------------------------------------------------------------------------

    public function test_live_lesson_accepts_a_meeting_link_and_a_schedule(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->live()->create(['instructor_id' => $instructor->id]);
        $section = $this->sectionFor($course);

        Sanctum::actingAs($instructor);

        $this->postJson("/api/instructor/sections/{$section->id}/lessons", [
            'title'       => 'Sesión 1 — teoría del color',
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
            'starts_at'   => '2026-09-01 19:00:00',
            'duration'    => 5400,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.meeting_url', 'https://meet.google.com/abc-defg-hij');
    }

    public function test_live_lesson_accepts_a_zoom_link(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->live()->create(['instructor_id' => $instructor->id]);
        $section = $this->sectionFor($course);

        Sanctum::actingAs($instructor);

        $this->postJson("/api/instructor/sections/{$section->id}/lessons", [
            'title'       => 'Sesión 2',
            'meeting_url' => 'https://us02web.zoom.us/j/89012345678',
        ])->assertStatus(201);
    }

    /**
     * The whitelist is the point: students are told to trust and click this
     * link at a scheduled time, so an arbitrary https URL must not pass.
     */
    public function test_arbitrary_url_is_rejected_as_a_meeting_link(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->live()->create(['instructor_id' => $instructor->id]);
        $section = $this->sectionFor($course);

        Sanctum::actingAs($instructor);

        $this->postJson("/api/instructor/sections/{$section->id}/lessons", [
            'title'       => 'Sesión 3',
            'meeting_url' => 'https://meet-google.example.com/phishing',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['meeting_url']);
    }

    public function test_on_demand_lesson_rejects_a_meeting_link(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $section = $this->sectionFor($course);

        Sanctum::actingAs($instructor);

        $this->postJson("/api/instructor/sections/{$section->id}/lessons", [
            'title'       => 'Lección grabada',
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['meeting_url']);
    }

    public function test_live_lesson_still_accepts_a_recording_url(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->live()->create(['instructor_id' => $instructor->id]);
        $section = $this->sectionFor($course);

        Sanctum::actingAs($instructor);

        $this->postJson("/api/instructor/sections/{$section->id}/lessons", [
            'title'       => 'Sesión 1 (grabada)',
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
            'video_url'   => 'https://youtu.be/dQw4w9WgXcQ',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.video_url', 'https://youtu.be/dQw4w9WgXcQ');
    }

    // -------------------------------------------------------------------------
    // Publish guard
    // -------------------------------------------------------------------------

    public function test_live_course_cannot_be_published_with_an_unscheduled_lesson(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->live()->create([
            'instructor_id' => $instructor->id,
            'is_published'  => false,
        ]);
        $section = $this->sectionFor($course);

        Lesson::factory()->live()->create(['section_id' => $section->id]);
        Lesson::factory()->create([
            'section_id'  => $section->id,
            'meeting_url' => null,
            'starts_at'   => null,
        ]);

        Sanctum::actingAs($instructor);

        $this->postJson("/api/instructor/courses/{$course->slug}/publish")
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Cannot publish a live course while some lessons have no meeting link or no scheduled date.'
            );

        $this->assertFalse($course->fresh()->is_published);
    }

    public function test_fully_scheduled_live_course_publishes(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->live()->create([
            'instructor_id' => $instructor->id,
            'is_published'  => false,
        ]);
        $section = $this->sectionFor($course);

        Lesson::factory()->live()->count(3)->create(['section_id' => $section->id]);

        Sanctum::actingAs($instructor);

        $this->postJson("/api/instructor/courses/{$course->slug}/publish")
            ->assertStatus(200);

        $this->assertTrue($course->fresh()->is_published);
    }

    public function test_on_demand_course_publish_is_unaffected_by_the_live_rules(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'is_published'  => false,
        ]);
        $section = $this->sectionFor($course);

        Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($instructor);

        $this->postJson("/api/instructor/courses/{$course->slug}/publish")
            ->assertStatus(200);
    }
}
