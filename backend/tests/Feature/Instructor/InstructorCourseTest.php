<?php

namespace Tests\Feature\Instructor;

use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InstructorCourseTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function instructor(): User
    {
        return User::factory()->instructor()->create();
    }

    private function student(): User
    {
        return User::factory()->create(['role' => 'student']);
    }

    private function courseFor(User $instructor, array $attrs = []): Course
    {
        return Course::factory()->create(array_merge(
            ['instructor_id' => $instructor->id, 'is_published' => false],
            $attrs
        ));
    }

    // -------------------------------------------------------------------------
    // Authentication / Role guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/instructor/courses')->assertStatus(401);
    }

    public function test_student_role_returns_403_on_instructor_routes(): void
    {
        Sanctum::actingAs($this->student());

        $this->getJson('/api/instructor/courses')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Instructor role required.');
    }

    // -------------------------------------------------------------------------
    // Index — only own courses including drafts
    // -------------------------------------------------------------------------

    public function test_index_returns_only_own_courses(): void
    {
        $instructor1 = $this->instructor();
        $instructor2 = $this->instructor();

        $own = $this->courseFor($instructor1, ['is_published' => true]);
        $other = $this->courseFor($instructor2, ['is_published' => true]);

        Sanctum::actingAs($instructor1);

        $response = $this->getJson('/api/instructor/courses')->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($own->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_index_includes_draft_courses(): void
    {
        $instructor = $this->instructor();
        $draft = $this->courseFor($instructor, ['is_published' => false]);

        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/instructor/courses')->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($draft->id, $ids);
    }

    public function test_index_card_has_expected_structure(): void
    {
        $instructor = $this->instructor();
        $this->courseFor($instructor);

        Sanctum::actingAs($instructor);

        $this->getJson('/api/instructor/courses')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'title', 'slug', 'price', 'thumbnail',
                        'is_published', 'sections_count', 'lessons_count',
                        'students_count', 'created_at',
                    ],
                ],
            ]);
    }

    // -------------------------------------------------------------------------
    // Store — creates draft course
    // -------------------------------------------------------------------------

    public function test_store_creates_course_as_draft(): void
    {
        $instructor = $this->instructor();
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/instructor/courses', [
            'title'       => 'My New Course',
            'description' => 'Course description text',
            'price'       => 49.99,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.is_published', false)
                 ->assertJsonPath('data.title', 'My New Course');

        $this->assertDatabaseHas('courses', [
            'title'        => 'My New Course',
            'instructor_id' => $instructor->id,
            'is_published' => false,
        ]);
    }

    public function test_store_auto_generates_slug_from_title(): void
    {
        $instructor = $this->instructor();
        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/instructor/courses', [
            'title'       => 'Advanced Makeup Techniques',
            'description' => 'Course description',
            'price'       => 0,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.slug', 'advanced-makeup-techniques');
    }

    public function test_store_generates_unique_slug_on_duplicate_title(): void
    {
        $instructor = $this->instructor();
        Sanctum::actingAs($instructor);

        // Create first course
        $this->postJson('/api/instructor/courses', [
            'title'       => 'Same Title',
            'description' => 'First',
            'price'       => 0,
        ])->assertStatus(201);

        // Create second course with same title
        $response = $this->postJson('/api/instructor/courses', [
            'title'       => 'Same Title',
            'description' => 'Second',
            'price'       => 0,
        ])->assertStatus(201);

        // Slug must be unique (appended suffix)
        $this->assertNotEquals('same-title', $response->json('data.slug'));
    }

    public function test_store_validates_required_fields(): void
    {
        $instructor = $this->instructor();
        Sanctum::actingAs($instructor);

        $this->postJson('/api/instructor/courses', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'description']);
    }

    public function test_store_validates_price_is_numeric_min_zero(): void
    {
        $instructor = $this->instructor();
        Sanctum::actingAs($instructor);

        $this->postJson('/api/instructor/courses', [
            'title'       => 'Test',
            'description' => 'Desc',
            'price'       => -5,
        ])->assertStatus(422)->assertJsonValidationErrors(['price']);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_course_detail_with_video_url(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $section = Section::factory()->create(['course_id' => $course->id]);
        Lesson::factory()->create([
            'section_id' => $section->id,
            'video_url'  => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/instructor/courses/{$course->slug}")
            ->assertStatus(200)
            ->assertJsonPath('data.is_published', false);

        $lessonVideoUrl = $response->json('data.sections.0.lessons.0.video_url');
        $this->assertEquals('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $lessonVideoUrl);
    }

    public function test_show_returns_403_for_foreign_course(): void
    {
        $instructor1 = $this->instructor();
        $instructor2 = $this->instructor();
        $course = $this->courseFor($instructor2);

        Sanctum::actingAs($instructor1);

        $this->getJson("/api/instructor/courses/{$course->slug}")
            ->assertStatus(403)
            ->assertJsonPath('message', 'You do not own this course.');
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_changes_course_fields(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        Sanctum::actingAs($instructor);

        $this->patchJson("/api/instructor/courses/{$course->slug}", [
            'title'       => 'Updated Title',
            'description' => 'Updated description',
            'price'       => 99.99,
        ])->assertStatus(200)
          ->assertJsonPath('data.title', 'Updated Title');

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'title' => 'Updated Title']);
    }

    public function test_update_regenerates_slug_on_title_change(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor, ['title' => 'Old Title', 'slug' => 'old-title']);

        Sanctum::actingAs($instructor);

        $response = $this->patchJson("/api/instructor/courses/{$course->slug}", [
            'title' => 'Brand New Title',
        ])->assertStatus(200);

        $this->assertEquals('brand-new-title', $response->json('data.slug'));
    }

    public function test_update_returns_403_for_foreign_course(): void
    {
        $instructor1 = $this->instructor();
        $instructor2 = $this->instructor();
        $course = $this->courseFor($instructor2);

        Sanctum::actingAs($instructor1);

        $this->patchJson("/api/instructor/courses/{$course->slug}", ['title' => 'Hack'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'You do not own this course.');
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    public function test_delete_removes_course_and_cascades(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $section = Section::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($instructor);

        $this->deleteJson("/api/instructor/courses/{$course->slug}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
        $this->assertDatabaseMissing('sections', ['id' => $section->id]);
        $this->assertDatabaseMissing('lessons', ['id' => $lesson->id]);
    }

    public function test_delete_returns_403_for_foreign_course(): void
    {
        $instructor1 = $this->instructor();
        $instructor2 = $this->instructor();
        $course = $this->courseFor($instructor2);

        Sanctum::actingAs($instructor1);

        $this->deleteJson("/api/instructor/courses/{$course->slug}")
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Publish
    // -------------------------------------------------------------------------

    public function test_publish_with_zero_lessons_returns_422(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        Sanctum::actingAs($instructor);

        $this->postJson("/api/instructor/courses/{$course->slug}/publish")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot publish a course with no lessons.');
    }

    public function test_publish_with_lessons_sets_is_published_true(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $section = Section::factory()->create(['course_id' => $course->id]);
        Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($instructor);

        $this->postJson("/api/instructor/courses/{$course->slug}/publish")
            ->assertStatus(200)
            ->assertJsonPath('data.is_published', true);

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'is_published' => true]);
    }

    public function test_unpublish_sets_is_published_false(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor, ['is_published' => true]);

        Sanctum::actingAs($instructor);

        $this->postJson("/api/instructor/courses/{$course->slug}/unpublish")
            ->assertStatus(200)
            ->assertJsonPath('data.is_published', false);

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'is_published' => false]);
    }

    public function test_publish_returns_403_for_foreign_course(): void
    {
        $instructor1 = $this->instructor();
        $instructor2 = $this->instructor();
        $course = $this->courseFor($instructor2);
        $section = Section::factory()->create(['course_id' => $course->id]);
        Lesson::factory()->create(['section_id' => $section->id]);

        Sanctum::actingAs($instructor1);

        $this->postJson("/api/instructor/courses/{$course->slug}/publish")
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // category_id + offers_certificate on store/update
    // -------------------------------------------------------------------------

    public function test_store_persists_category_id_and_offers_certificate(): void
    {
        $instructor = $this->instructor();
        $category   = Category::factory()->create(['slug' => 'editorial', 'name' => 'Editorial']);

        Sanctum::actingAs($instructor);

        $response = $this->postJson('/api/instructor/courses', [
            'title'               => 'Editorial Makeup Course',
            'description'         => 'Editorial techniques description',
            'price'               => 29.99,
            'category_id'         => $category->id,
            'offers_certificate'  => true,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.category_id', $category->id)
                 ->assertJsonPath('data.offers_certificate', true);

        $this->assertDatabaseHas('courses', [
            'title'              => 'Editorial Makeup Course',
            'category_id'        => $category->id,
            'offers_certificate' => true,
        ]);
    }

    public function test_update_persists_category_id_and_offers_certificate(): void
    {
        $instructor = $this->instructor();
        $category   = Category::factory()->create(['slug' => 'novias', 'name' => 'Novias']);
        $course     = $this->courseFor($instructor, ['category_id' => null, 'offers_certificate' => false]);

        Sanctum::actingAs($instructor);

        $this->patchJson("/api/instructor/courses/{$course->slug}", [
            'category_id'        => $category->id,
            'offers_certificate' => true,
        ])->assertStatus(200)
          ->assertJsonPath('data.category_id', $category->id)
          ->assertJsonPath('data.offers_certificate', true);

        $this->assertDatabaseHas('courses', [
            'id'                 => $course->id,
            'category_id'        => $category->id,
            'offers_certificate' => true,
        ]);
    }

    public function test_store_validates_nonexistent_category_id(): void
    {
        $instructor = $this->instructor();
        Sanctum::actingAs($instructor);

        $this->postJson('/api/instructor/courses', [
            'title'       => 'Test Course',
            'description' => 'Desc',
            'category_id' => 99999,
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['category_id']);
    }

    public function test_instructor_course_card_includes_category_id_and_offers_certificate(): void
    {
        $instructor = $this->instructor();
        $category   = Category::factory()->create(['slug' => 'noche', 'name' => 'Noche']);
        $this->courseFor($instructor, ['category_id' => $category->id, 'offers_certificate' => true]);

        Sanctum::actingAs($instructor);

        $response = $this->getJson('/api/instructor/courses')->assertStatus(200);

        $item = $response->json('data.0');
        $this->assertArrayHasKey('category_id', $item);
        $this->assertArrayHasKey('offers_certificate', $item);
        $this->assertEquals($category->id, $item['category_id']);
        $this->assertTrue($item['offers_certificate']);
    }

    // -------------------------------------------------------------------------
    // is_practice present in instructor course detail lesson payload
    // -------------------------------------------------------------------------

    public function test_instructor_course_detail_lessons_include_is_practice(): void
    {
        $instructor = $this->instructor();
        $course     = $this->courseFor($instructor);
        $section    = Section::factory()->create(['course_id' => $course->id]);

        // Create one practice and one regular lesson
        Lesson::factory()->create([
            'section_id'  => $section->id,
            'is_practice' => true,
        ]);
        Lesson::factory()->create([
            'section_id'  => $section->id,
            'is_practice' => false,
        ]);

        Sanctum::actingAs($instructor);

        $response = $this->getJson("/api/instructor/courses/{$course->slug}")->assertStatus(200);

        $lessons = $response->json('data.sections.0.lessons');
        $this->assertCount(2, $lessons);

        foreach ($lessons as $lesson) {
            $this->assertArrayHasKey('is_practice', $lesson);
        }

        $practiceLesson = collect($lessons)->first(fn ($l) => $l['is_practice'] === true);
        $this->assertNotNull($practiceLesson, 'At least one lesson should have is_practice=true');
    }
}
