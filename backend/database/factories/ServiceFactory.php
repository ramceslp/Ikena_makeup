<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(4, false);

        return [
            'category_id'       => null,
            'title'             => $title,
            'slug'              => Str::slug($title),
            'description'       => fake()->paragraphs(2, true),
            'price'             => fake()->randomElement([50.00, 80.00, 120.00, 150.00, 200.00]),
            'duration_hours'    => fake()->randomElement([1, 2, 3, 4]),
            'availability_type' => fake()->randomElement(['immediate', 'by_appointment']),
            'is_published'      => true,
        ];
    }

    /**
     * State: service is published.
     */
    public function published(): static
    {
        return $this->state(fn () => ['is_published' => true]);
    }

    /**
     * State: service is unpublished (draft).
     */
    public function unpublished(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }

    /**
     * State: service is assigned to a new Category.
     */
    public function withCategory(): static
    {
        return $this->state(fn () => ['category_id' => Category::factory()]);
    }
}
