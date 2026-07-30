<?php

namespace Database\Factories;

use App\Models\PushNotificationLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushNotificationLog>
 */
class PushNotificationLogFactory extends Factory
{
    protected $model = PushNotificationLog::class;

    public function definition(): array
    {
        return [
            'type'             => PushNotificationLog::TYPE_CUSTOM,
            'title'            => fake()->sentence(4),
            'body'             => fake()->sentence(12),
            'data'             => null,
            'audience'         => 'all',
            'sent_by'          => null,
            'recipients_count' => 0,
            'success_count'    => 0,
            'failure_count'    => 0,
            'status'           => PushNotificationLog::STATUS_PENDING,
            'sent_at'          => null,
        ];
    }

    public function sent(int $successes = 3, int $failures = 0): static
    {
        return $this->state(fn (): array => [
            'status'           => PushNotificationLog::STATUS_SENT,
            'recipients_count' => $successes + $failures,
            'success_count'    => $successes,
            'failure_count'    => $failures,
            'sent_at'          => now(),
        ]);
    }

    public function skipped(): static
    {
        return $this->state(fn (): array => [
            'status'  => PushNotificationLog::STATUS_SKIPPED,
            'sent_at' => now(),
        ]);
    }

    public function ofType(string $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }
}
