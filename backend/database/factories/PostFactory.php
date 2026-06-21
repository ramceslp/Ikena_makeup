<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(5, false);

        return [
            'author_id'        => null,
            'title'            => $title,
            'slug'             => Str::slug($title),
            'excerpt'          => fake()->optional()->sentence(15),
            'cover_image_path' => null,
            'body'             => '<p>' . fake()->paragraphs(2, true) . '</p>',
            'type'             => fake()->randomElement([
                'noticia', 'nuevo_curso', 'oferta', 'evento',
                'lanzamiento', 'certificacion', 'contenido',
            ]),
            'is_featured'  => false,
            'cta_label'    => null,
            'cta_url'      => null,
            'is_published' => true,
            'published_at' => now(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn () => [
            'is_featured'  => true,
            'is_published' => true,
            'published_at' => now(),
        ]);
    }
}
