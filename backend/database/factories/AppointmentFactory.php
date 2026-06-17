<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $service = Service::factory()->create(['availability_type' => 'by_appointment']);
        $date    = fake()->dateTimeBetween('now', '+60 days')->format('Y-m-d');
        $time    = fake()->randomElement(['09:00', '10:00', '11:00', '14:00', '15:00']);

        return [
            'service_id'          => $service->id,
            'user_id'             => User::factory(),
            'order_id'            => null,
            'scheduled_date'      => $date,
            'scheduled_time'      => $time,
            'slot_key'            => "{$service->id}|{$date}|{$time}",
            'whatsapp'            => '+5930999' . fake()->numerify('####'),
            'payment_mode'        => 'gateway',
            'deposit_amount_cents' => fake()->numberBetween(1000, 10000),
            'status'              => 'pending',
            'cancelled_by_id'     => null,
            'cancelled_at'        => null,
        ];
    }

    /**
     * State: appointment is paid.
     */
    public function paid(): static
    {
        return $this->state(fn () => [
            'status'       => 'paid',
            'payment_mode' => 'gateway',
        ]);
    }

    /**
     * State: appointment is cancelled (slot_key is null to free the slot).
     */
    public function cancelled(int $cancelledById): static
    {
        return $this->state(fn () => [
            'status'          => 'cancelled',
            'slot_key'        => null,
            'cancelled_by_id' => $cancelledById,
            'cancelled_at'    => now(),
        ]);
    }
}
