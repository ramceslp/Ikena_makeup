<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseCatalogTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create a fully-formed published course with one section and one lesson.
     */
    private function createCourseWithLesson(array $courseAttributes = [], bool $lessonFree = false): Course
    {
        $instructor = User::factory()->instructor()->create();
        $course = Course::factory()->create(array_merge([
            'instructor_id' => $instructor->id,
            'is_published'  => true,
        ], $courseAttributes));

        $section = Section::factory()->create(['course_id' => $course->id]);
        Lesson::factory()->create([
            'section_id' => $section->id,
            'is_free'    => $lessonFree,
        ]);

        return $course;
    }

    // -------------------------------------------------------------------------
    // GET /api/courses — Catalog
    // -------------------------------------------------------------------------

    public function test_catalog_returns_only_published_courses(): void
    {
        $published   = $this->createCourseWithLesson(['is_published' => true]);
        $unpublished = $this->createCourseWithLesson(['is_published' => false]);

        $response = $this->getJson('/api/courses');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($published->id, $ids);
        $this->assertNotContains($unpublished->id, $ids);
    }

    public function test_catalog_returns_lessons_count_and_sections_count(): void
    {
        $instructor = User::factory()->instructor()->create();
        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'is_published'  => true,
        ]);
        $section1 = Section::factory()->create(['course_id' => $course->id]);
        $section2 = Section::factory()->create(['course_id' => $course->id]);
        Lesson::factory()->create(['section_id' => $section1->id]);
        Lesson::factory()->create(['section_id' => $section1->id]);
        Lesson::factory()->create(['section_id' => $section2->id]);

        $response = $this->getJson('/api/courses');
        $response->assertStatus(200);

        $item = collect($response->json('data'))->firstWhere('id', $course->id);
        $this->assertNotNull($item);
        $this->assertEquals(3, $item['lessons_count']);
        $this->assertEquals(2, $item['sections_count']);
    }

    public function test_catalog_search_filter_works_by_title(): void
    {
        $this->createCourseWithLesson(['title' => 'PHP Mastery Course', 'slug' => 'php-mastery-course']);
        $this->createCourseWithLesson(['title' => 'Vue JS Basics', 'slug' => 'vue-js-basics']);

        $response = $this->getJson('/api/courses?search=PHP');
        $response->assertStatus(200);

        $titles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertContains('PHP Mastery Course', $titles);
        $this->assertNotContains('Vue JS Basics', $titles);
    }

    public function test_catalog_min_price_filter_works(): void
    {
        $this->createCourseWithLesson(['price' => 10.00, 'slug' => 'cheap-course']);
        $this->createCourseWithLesson(['price' => 50.00, 'slug' => 'expensive-course']);

        $response = $this->getJson('/api/courses?min_price=30');
        $response->assertStatus(200);

        $prices = collect($response->json('data'))->pluck('price')->toArray();
        foreach ($prices as $price) {
            $this->assertGreaterThanOrEqual(30, (float) $price);
        }
    }

    public function test_catalog_max_price_filter_works(): void
    {
        $this->createCourseWithLesson(['price' => 10.00, 'slug' => 'cheap-course-2']);
        $this->createCourseWithLesson(['price' => 80.00, 'slug' => 'pricey-course-2']);

        $response = $this->getJson('/api/courses?max_price=20');
        $response->assertStatus(200);

        $prices = collect($response->json('data'))->pluck('price')->toArray();
        foreach ($prices as $price) {
            $this->assertLessThanOrEqual(20, (float) $price);
        }
    }

    public function test_catalog_sort_price_asc_works(): void
    {
        $this->createCourseWithLesson(['price' => 99.00, 'slug' => 'costly']);
        $this->createCourseWithLesson(['price' => 9.00, 'slug' => 'budget']);
        $this->createCourseWithLesson(['price' => 49.00, 'slug' => 'mid']);

        $response = $this->getJson('/api/courses?sort=price_asc');
        $response->assertStatus(200);

        $prices = collect($response->json('data'))->pluck('price')->map(fn ($p) => (float) $p)->toArray();
        $sorted = $prices;
        sort($sorted);
        $this->assertEquals($sorted, $prices);
    }

    public function test_catalog_sort_price_desc_works(): void
    {
        $this->createCourseWithLesson(['price' => 5.00, 'slug' => 'c-low']);
        $this->createCourseWithLesson(['price' => 75.00, 'slug' => 'c-high']);
        $this->createCourseWithLesson(['price' => 35.00, 'slug' => 'c-mid']);

        $response = $this->getJson('/api/courses?sort=price_desc');
        $response->assertStatus(200);

        $prices = collect($response->json('data'))->pluck('price')->map(fn ($p) => (float) $p)->toArray();
        $sorted = $prices;
        rsort($sorted);
        $this->assertEquals($sorted, $prices);
    }

    public function test_catalog_is_paginated(): void
    {
        // Create 15 courses (default page size is 12)
        for ($i = 0; $i < 15; $i++) {
            $instructor = User::factory()->instructor()->create();
            $slug = 'course-paginate-' . $i;
            Course::factory()->create([
                'instructor_id' => $instructor->id,
                'slug'          => $slug,
                'is_published'  => true,
            ]);
        }

        $response = $this->getJson('/api/courses');
        $response->assertStatus(200)
                 ->assertJsonStructure(['data', 'links', 'meta']);

        // Page 1 has at most 12
        $this->assertLessThanOrEqual(12, count($response->json('data')));
    }

    public function test_catalog_does_not_expose_is_enrolled_when_unauthenticated(): void
    {
        $this->createCourseWithLesson();

        $response = $this->getJson('/api/courses');
        $response->assertStatus(200);

        $item = $response->json('data.0');
        $this->assertArrayNotHasKey('is_enrolled', $item);
    }

    public function test_catalog_exposes_is_enrolled_when_authenticated(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->createCourseWithLesson();

        $response = $this->getJson('/api/courses');
        $response->assertStatus(200);

        $item = $response->json('data.0');
        $this->assertArrayHasKey('is_enrolled', $item);
        $this->assertFalse($item['is_enrolled']);
    }

    // -------------------------------------------------------------------------
    // GET /api/courses — checkout-confirm-scoped token must be ignored
    // (OptionalSanctum leak fix — a checkout-confirm-scoped token must not
    // authenticate the requester on optional-auth routes; it must behave
    // exactly like an anonymous request, never like the bound user).
    // -------------------------------------------------------------------------

    public function test_catalog_ignores_checkout_confirm_scoped_token_and_returns_anonymous_shape(): void
    {
        $course = $this->createCourseWithLesson();

        $student = User::factory()->create();
        Enrollment::create([
            'user_id'    => $student->id,
            'course_id'  => $course->id,
            'price_paid' => $course->price,
        ]);

        $scopedToken = $student->createToken(
            'checkout-confirm',
            ['checkout-confirm'],
            now()->addMinutes(15),
        )->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$scopedToken}")
            ->getJson('/api/courses');

        $response->assertStatus(200);

        // Same shape as a fully anonymous request — is_enrolled absent, even
        // though the token's bound user IS enrolled in this course.
        $item = $response->json('data.0');
        $this->assertArrayNotHasKey('is_enrolled', $item);
    }

    public function test_catalog_still_personalizes_with_a_normal_full_access_token(): void
    {
        $course = $this->createCourseWithLesson();

        $student = User::factory()->create();
        Enrollment::create([
            'user_id'    => $student->id,
            'course_id'  => $course->id,
            'price_paid' => $course->price,
        ]);

        $normalToken = $student->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$normalToken}")
            ->getJson('/api/courses');

        $response->assertStatus(200);

        $item = $response->json('data.0');
        $this->assertArrayHasKey('is_enrolled', $item);
        $this->assertTrue($item['is_enrolled']);
    }

    // -------------------------------------------------------------------------
    // GET /api/courses/{slug} — Course Detail
    // -------------------------------------------------------------------------

    public function test_course_detail_returns_sections_and_lessons(): void
    {
        $instructor = User::factory()->instructor()->create();
        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'slug'          => 'detail-test-course',
            'is_published'  => true,
        ]);
        $section = Section::factory()->create(['course_id' => $course->id, 'position' => 0]);
        Lesson::factory()->free()->create(['section_id' => $section->id, 'position' => 0]);
        Lesson::factory()->create(['section_id' => $section->id, 'position' => 1]);

        $response = $this->getJson("/api/courses/detail-test-course");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         'id', 'title', 'slug', 'description', 'price',
                         'instructor', 'total_lessons', 'is_enrolled',
                         'sections' => [
                             '*' => [
                                 'id', 'title', 'position',
                                 'lessons' => [
                                     '*' => ['id', 'title', 'position', 'is_free', 'duration'],
                                 ],
                             ],
                         ],
                     ],
                 ]);
    }

    public function test_course_detail_never_leaks_video_url_for_paid_lessons(): void
    {
        $instructor = User::factory()->instructor()->create();
        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'slug'          => 'leak-test-course',
            'is_published'  => true,
        ]);
        $section = Section::factory()->create(['course_id' => $course->id]);
        Lesson::factory()->create([
            'section_id' => $section->id,
            'is_free'    => false,
            'video_url'  => 'https://secret.example.com/paid-video.mp4',
        ]);

        $response = $this->getJson('/api/courses/leak-test-course');
        $response->assertStatus(200);

        $lessons = $response->json('data.sections.0.lessons');
        foreach ($lessons as $lesson) {
            $this->assertArrayNotHasKey('video_url', $lesson);
        }
    }

    public function test_course_detail_returns_404_for_unpublished_course(): void
    {
        $instructor = User::factory()->instructor()->create();
        Course::factory()->create([
            'instructor_id' => $instructor->id,
            'slug'          => 'hidden-course',
            'is_published'  => false,
        ]);

        $response = $this->getJson('/api/courses/hidden-course');
        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // GET /api/courses/{slug} — checkout-confirm-scoped token must be ignored
    // (OptionalSanctum leak fix).
    // -------------------------------------------------------------------------

    public function test_course_detail_ignores_checkout_confirm_scoped_token_and_returns_anonymous_shape(): void
    {
        $course = $this->createCourseWithLesson(['slug' => 'scoped-token-course']);

        $student = User::factory()->create();
        Enrollment::create([
            'user_id'    => $student->id,
            'course_id'  => $course->id,
            'price_paid' => $course->price,
        ]);

        $scopedToken = $student->createToken(
            'checkout-confirm',
            ['checkout-confirm'],
            now()->addMinutes(15),
        )->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$scopedToken}")
            ->getJson('/api/courses/scoped-token-course');

        $response->assertStatus(200);

        // Same shape as a fully anonymous request — is_enrolled false and
        // my_review null, even though the token's bound user IS enrolled.
        $this->assertFalse($response->json('data.is_enrolled'));
        $this->assertNull($response->json('data.my_review'));
    }

    public function test_course_detail_still_personalizes_with_a_normal_full_access_token(): void
    {
        $course = $this->createCourseWithLesson(['slug' => 'full-token-course']);

        $student = User::factory()->create();
        Enrollment::create([
            'user_id'    => $student->id,
            'course_id'  => $course->id,
            'price_paid' => $course->price,
        ]);

        $normalToken = $student->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$normalToken}")
            ->getJson('/api/courses/full-token-course');

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.is_enrolled'));
    }
}
