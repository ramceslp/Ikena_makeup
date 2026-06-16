<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    /** Sample royalty-free video URLs for seeding */
    protected array $sampleVideos = [
        'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
        'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4',
        'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
        'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerEscapes.mp4',
        'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/SubaruOutbackOnStreetAndDirt.mp4',
    ];

    public function definition(): array
    {
        return [
            'section_id'  => Section::factory(),
            'title'       => fake()->sentence(5, false),
            'description' => fake()->paragraph(),
            'video_url'   => fake()->randomElement($this->sampleVideos),
            'duration'    => fake()->numberBetween(120, 1800),
            'position'    => 0,
            'is_free'     => false,
            'is_practice' => false,
        ];
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_free' => true,
        ]);
    }
}
