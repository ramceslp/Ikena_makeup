<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceImage>
 */
class ServiceImageFactory extends Factory
{
    protected $model = ServiceImage::class;

    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'path'       => 'services/' . fake()->uuid() . '.jpg',
            'sort_order' => 0,
        ];
    }
}
