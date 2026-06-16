<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    public function definition(): array
    {
        return [
            'user_id'   => User::factory(),
            'course_id' => Course::factory(),
            'code'      => 'IKENA-' . strtoupper(fake()->bothify('??########')),
            'issued_at' => now(),
        ];
    }
}
