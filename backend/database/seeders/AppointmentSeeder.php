<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AppointmentSeeder extends Seeder
{
    /**
     * Idempotent seeder: appointments are keyed on the natural tuple
     * (user_id, service_id, scheduled_date, scheduled_time) — stable across
     * re-runs regardless of status, so cancelled rows (which null their
     * slot_key) still reconcile instead of duplicating.
     *
     * Seeds one appointment in EACH of the four states so the admin
     * management view shows the full lifecycle: pending, confirmed, paid,
     * cancelled.
     */
    public function run(): void
    {
        $student = $this->resolveUser('student@ikena.test', 'Carlos López', 'student');
        $admin   = $this->resolveUser('admin@ikena.test', 'Admin Ikena', 'admin');

        $bride     = Service::where('slug', 'maquillaje-de-novia')->first();
        $night     = Service::where('slug', 'maquillaje-social-y-de-noche')->first();
        $editorial = Service::where('slug', 'maquillaje-editorial-y-books')->first();

        // Services must exist (ServiceSeeder runs first). Bail out gracefully
        // if this seeder is run standalone before services are seeded.
        if (! $bride || ! $night || ! $editorial) {
            $this->command?->warn('AppointmentSeeder skipped: run ServiceSeeder first.');
            return;
        }

        $rows = [
            // status, service, days from now, time, payment_mode
            ['pending',   $bride,     7,  '10:00', 'gateway', null],
            ['confirmed', $night,     10, '14:00', 'manual',  null],
            ['paid',      $editorial, 14, '10:00', 'gateway', null],
            ['cancelled', $bride,     5,  '14:00', 'gateway', $admin->id],
        ];

        foreach ($rows as [$status, $service, $days, $time, $paymentMode, $cancelledById]) {
            $date       = now()->addDays($days)->format('Y-m-d');
            $isCancelled = $status === 'cancelled';

            Appointment::updateOrCreate(
                [
                    'user_id'        => $student->id,
                    'service_id'     => $service->id,
                    'scheduled_date' => $date,
                    'scheduled_time' => $time,
                ],
                [
                    // Cancelled appointments null their slot_key to free the slot.
                    'slot_key'             => $isCancelled
                        ? null
                        : Appointment::makeSlotKey($service->id, $date, $time),
                    'whatsapp'             => '+593099900' . $days,
                    'payment_mode'         => $paymentMode,
                    'deposit_amount_cents' => (int) round($service->price * $service->depositPercentage()),
                    'status'               => $status,
                    'cancelled_by_id'      => $cancelledById,
                    'cancelled_at'         => $isCancelled ? now() : null,
                ]
            );
        }
    }

    /**
     * Fetch a seeded user by email, or create a minimal one so this seeder
     * also works when run standalone (before DatabaseSeeder creates users).
     */
    private function resolveUser(string $email, string $name, string $role): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            [
                'name'              => $name,
                'password'          => Hash::make('password'),
                'role'              => $role,
                'email_verified_at' => now(),
            ]
        );
    }
}
