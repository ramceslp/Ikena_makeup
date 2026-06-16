<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseReview>
 */
class CourseReviewFactory extends Factory
{
    protected $model = CourseReview::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'user_id'   => User::factory(),
            'rating'    => fake()->numberBetween(1, 5),
            'body'      => fake()->optional(0.7)->paragraph(),
        ];
    }
}
