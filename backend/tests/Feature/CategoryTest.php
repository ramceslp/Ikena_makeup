<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function instructor(): User
    {
        return User::factory()->instructor()->create();
    }

    private function courseInCategory(Category $category, array $attrs = []): Course
    {
        return Course::factory()->create(array_merge([
            'instructor_id' => $this->instructor()->id,
            'is_published'  => true,
            'category_id'   => $category->id,
        ], $attrs));
    }

    // -------------------------------------------------------------------------
    // GET /api/categories
    // -------------------------------------------------------------------------

    public function test_categories_endpoint_returns_200(): void
    {
        Category::factory()->count(3)->create();

        $this->getJson('/api/categories')
             ->assertStatus(200);
    }

    public function test_categories_returns_all_categories_ordered_by_name(): void
    {
        Category::factory()->create(['name' => 'Zebra', 'slug' => 'zebra']);
        Category::factory()->create(['name' => 'Apple', 'slug' => 'apple']);
        Category::factory()->create(['name' => 'Mango', 'slug' => 'mango']);

        $response = $this->getJson('/api/categories')->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertEquals(['Apple', 'Mango', 'Zebra'], $names);
    }

    public function test_categories_response_has_expected_fields(): void
    {
        Category::factory()->create(['name' => 'Editorial', 'slug' => 'editorial']);

        $response = $this->getJson('/api/categories')->assertStatus(200);

        $item = $response->json('data.0');
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('name', $item);
        $this->assertArrayHasKey('slug', $item);
    }

    public function test_categories_returns_empty_array_when_no_categories(): void
    {
        $response = $this->getJson('/api/categories')->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Catalog filter: GET /api/courses?category={slug}
    // -------------------------------------------------------------------------

    public function test_catalog_category_filter_returns_only_matching_courses(): void
    {
        $editorial = Category::factory()->create(['name' => 'Editorial', 'slug' => 'editorial']);
        $novias    = Category::factory()->create(['name' => 'Novias', 'slug' => 'novias']);

        $courseA = $this->courseInCategory($editorial, ['slug' => 'course-editorial']);
        $courseB = $this->courseInCategory($novias, ['slug' => 'course-novias']);

        $response = $this->getJson('/api/courses?category=editorial')->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($courseA->id, $ids);
        $this->assertNotContains($courseB->id, $ids);
    }

    public function test_catalog_filter_by_nonexistent_category_returns_empty(): void
    {
        $this->courseInCategory(Category::factory()->create(['slug' => 'noche']), ['slug' => 'night-course']);

        $response = $this->getJson('/api/courses?category=does-not-exist')->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    public function test_catalog_without_category_filter_returns_all_published_courses(): void
    {
        $cat = Category::factory()->create(['slug' => 'editorial']);
        $this->courseInCategory($cat, ['slug' => 'c1']);
        Course::factory()->create([
            'instructor_id' => $this->instructor()->id,
            'is_published'  => true,
            'category_id'   => null,
            'slug'          => 'c2',
        ]);

        $response = $this->getJson('/api/courses')->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Category appears in course card payload
    // -------------------------------------------------------------------------

    public function test_course_card_includes_category_field(): void
    {
        $cat = Category::factory()->create(['name' => 'Novias', 'slug' => 'novias']);
        $this->courseInCategory($cat, ['slug' => 'wedding-course']);

        $response = $this->getJson('/api/courses')->assertStatus(200);
        $item = collect($response->json('data'))->first();

        $this->assertArrayHasKey('category', $item);
        $this->assertEquals('novias', $item['category']['slug']);
        $this->assertEquals('Novias', $item['category']['name']);
    }

    public function test_course_card_category_is_null_when_no_category(): void
    {
        Course::factory()->create([
            'instructor_id' => $this->instructor()->id,
            'is_published'  => true,
            'category_id'   => null,
            'slug'          => 'no-cat-course',
        ]);

        $response = $this->getJson('/api/courses')->assertStatus(200);
        $item = $response->json('data.0');

        $this->assertArrayHasKey('category', $item);
        $this->assertNull($item['category']);
    }

    public function test_course_detail_includes_category_field(): void
    {
        $cat = Category::factory()->create(['name' => 'Noche', 'slug' => 'noche']);
        $course = $this->courseInCategory($cat, ['slug' => 'night-look-course']);

        $response = $this->getJson("/api/courses/{$course->slug}")->assertStatus(200);

        $this->assertEquals('noche', $response->json('data.category.slug'));
    }
}
