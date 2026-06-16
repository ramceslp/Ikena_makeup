<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\PracticeSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PracticeSubmission>
 */
class PracticeSubmissionFactory extends Factory
{
    protected $model = PracticeSubmission::class;

    public function definition(): array
    {
        return [
            'lesson_id'   => Lesson::factory(),
            'user_id'     => User::factory(),
            'before_path' => 'submissions/1/' . fake()->uuid() . '.jpg',
            'after_path'  => 'submissions/1/' . fake()->uuid() . '.jpg',
            'status'      => 'pending',
            'feedback'    => null,
            'graded_by'   => null,
            'graded_at'   => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'     => 'approved',
            'feedback'   => fake()->sentence(),
            'graded_by'  => User::factory(),
            'graded_at'  => now(),
        ]);
    }

    public function needsWork(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'     => 'needs_work',
            'feedback'   => fake()->sentence(),
            'graded_by'  => User::factory(),
            'graded_at'  => now(),
        ]);
    }
}
