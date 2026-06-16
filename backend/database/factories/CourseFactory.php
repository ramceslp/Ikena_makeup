<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(4, false);

        return [
            'instructor_id'      => User::factory()->instructor(),
            'category_id'        => null,
            'title'              => $title,
            'slug'               => Str::slug($title),
            'description'        => fake()->paragraphs(3, true),
            'price'              => fake()->randomElement([0, 9.99, 29.99, 49.99, 79.99]),
            'thumbnail'          => 'https://picsum.photos/seed/' . Str::random(6) . '/640/360',
            'is_published'       => true,
            'offers_certificate' => false,
        ];
    }

    /**
     * State: course offers a certificate.
     */
    public function offersCertificate(): static
    {
        return $this->state(fn () => ['offers_certificate' => true]);
    }

    /**
     * State: course is assigned to a new Category.
     */
    public function withCategory(): static
    {
        return $this->state(fn () => ['category_id' => Category::factory()]);
    }
}
