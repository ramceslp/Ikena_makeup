<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id'               => User::factory(),
            'course_id'             => Course::factory(),
            'client_transaction_id' => 'ORD-' . Str::uuid(),
            'gateway'               => 'fake',
            'gateway_transaction_id'=> null,
            'amount_cents'          => fake()->numberBetween(100, 10000),
            'currency'              => 'USD',
            'status'                => 'pending',
            'paid_at'               => null,
            'meta'                  => null,
        ];
    }

    /**
     * Order in pending status.
     */
    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending', 'paid_at' => null]);
    }

    /**
     * Order in paid status.
     */
    public function paid(): static
    {
        return $this->state(fn () => [
            'status'                 => 'paid',
            'paid_at'                => now(),
            'gateway_transaction_id' => (string) fake()->numberBetween(1000, 9999),
        ]);
    }
}
