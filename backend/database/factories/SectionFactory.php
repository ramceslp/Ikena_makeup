<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    protected $model = Section::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title'     => 'Section: ' . fake()->sentence(3, false),
            'position'  => 0,
        ];
    }
}
