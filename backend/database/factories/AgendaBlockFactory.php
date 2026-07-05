<?php

namespace Database\Factories;

use App\Models\AgendaBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgendaBlock>
 */
class AgendaBlockFactory extends Factory
{
    protected $model = AgendaBlock::class;

    public function definition(): array
    {
        // Default state: recurring weekly block (day_of_week set, specific_date null).
        return [
            'day_of_week'       => 1, // Monday
            'specific_date'     => null,
            'open_time'         => '09:00',
            'close_time'        => '18:00',
            'concurrency_limit' => 2,
            'soft_threshold'    => null,
            'is_blocked'        => false,
            'staff_id'          => null,
        ];
    }

    /**
     * State: specific one-off date block.
     */
    public function specificDate(string $date): static
    {
        return $this->state(fn () => [
            'day_of_week'   => null,
            'specific_date' => $date,
        ]);
    }

    /**
     * State: blocked (generates no candidates in the resolver).
     */
    public function blocked(): static
    {
        return $this->state(fn () => ['is_blocked' => true]);
    }
}
