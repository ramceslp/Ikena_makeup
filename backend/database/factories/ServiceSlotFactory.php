<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceSlot>
 */
class ServiceSlotFactory extends Factory
{
    protected $model = ServiceSlot::class;

    public function definition(): array
    {
        // Default: recurring weekly slot (day_of_week set, specific_date null)
        return [
            'service_id'    => Service::factory(),
            'day_of_week'   => fake()->numberBetween(0, 6),
            'specific_date' => null,
            'start_time'    => fake()->randomElement(['09:00', '10:00', '11:00', '14:00', '15:00', '16:00']),
            'capacity'      => 1,
            'is_blocked'    => false,
        ];
    }

    /**
     * State: specific one-off date slot.
     */
    public function specificDate(string $date): static
    {
        return $this->state(fn () => [
            'day_of_week'   => null,
            'specific_date' => $date,
        ]);
    }

    /**
     * State: blocked slot.
     */
    public function blocked(): static
    {
        return $this->state(fn () => ['is_blocked' => true]);
    }
}
