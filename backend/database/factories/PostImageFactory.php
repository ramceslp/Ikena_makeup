<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostImage>
 */
class PostImageFactory extends Factory
{
    protected $model = PostImage::class;

    public function definition(): array
    {
        return [
            'post_id'    => Post::factory(),
            'path'       => 'posts/images/' . fake()->uuid() . '.jpg',
            'sort_order' => 0,
        ];
    }
}
