<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(4, false);

        return [
            'category_id'  => null,
            'title'        => $title,
            'slug'         => Str::slug($title),
            'description'  => fake()->paragraphs(2, true),
            'price'        => fake()->randomElement([50.00, 80.00, 120.00, 150.00, 200.00]),
            'stock_qty'    => fake()->numberBetween(0, 50),
            'is_published' => true,
        ];
    }

    /**
     * State: product is published.
     */
    public function published(): static
    {
        return $this->state(fn () => ['is_published' => true]);
    }

    /**
     * State: product is unpublished (draft).
     */
    public function unpublished(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }

    /**
     * State: product is out of stock.
     */
    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock_qty' => 0]);
    }

    /**
     * State: product is in stock.
     */
    public function inStock(): static
    {
        return $this->state(fn () => ['stock_qty' => 10]);
    }

    /**
     * State: product is assigned to a new Category.
     */
    public function withCategory(): static
    {
        return $this->state(fn () => ['category_id' => Category::factory()]);
    }
}
