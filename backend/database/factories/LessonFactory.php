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
            'meeting_url' => null,
            'starts_at'   => null,
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

    /**
     * State: a scheduled live session. video_url stays null — the recording
     * only exists after the session has happened.
     *
     * @param  string|null  $startsAt  Any strtotime-parsable moment; defaults
     *                                 to a session a week out, which keeps the
     *                                 meeting window CLOSED unless a test
     *                                 travels to it on purpose.
     */
    public function live(?string $startsAt = null): static
    {
        return $this->state(fn () => [
            'video_url'   => null,
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
            'starts_at'   => $startsAt ? new \DateTimeImmutable($startsAt) : now()->addWeek(),
            'duration'    => 5400,
        ]);
    }
}
