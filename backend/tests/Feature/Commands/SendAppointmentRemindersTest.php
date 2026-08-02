<?php

namespace Tests\Feature\Commands;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use App\Notifications\AppointmentReminder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * SendAppointmentRemindersTest — mobile-capacitor-setup PR3, task 3.10.
 *
 * Tests the `appointments:send-reminders` artisan command — design
 * Decision 2's "Appointment reminder (secondary)" v1 trigger
 * (sdd/mobile-capacitor-setup/design.md): finds appointments ~24h out and
 * sends App\Notifications\AppointmentReminder, once per appointment.
 *
 * The command runs hourly against a [now+24h, now+25h) window (see command
 * docblock) evaluated in `booking.timezone` (America/Guayaquil) — the same
 * timezone appointments are scheduled in.
 */
class SendAppointmentRemindersTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(): Service
    {
        return Service::factory()->create([
            'availability_type' => 'by_appointment',
            'is_published' => true,
            'price' => 100.00,
            'deposit_percentage' => 30,
            'duration_hours' => 1,
        ]);
    }

    /**
     * Create an appointment scheduled at $hoursFromNow hours from the
     * (frozen) current time, expressed in booking.timezone.
     */
    private function makeAppointmentInHours(float $hoursFromNow, array $overrides = []): Appointment
    {
        $timezone = config('booking.timezone');
        $target = Carbon::now($timezone)->addMinutes((int) round($hoursFromNow * 60));

        $user = User::factory()->create();
        $service = $this->makeService();

        return Appointment::create(array_merge([
            'service_id' => $service->id,
            'user_id' => $user->id,
            'order_id' => null,
            'scheduled_date' => $target->toDateString(),
            'scheduled_time' => $target->format('H:i'),
            'scheduled_end_time' => $target->copy()->addHour()->format('H:i'),
            'slot_key' => null,
            'whatsapp' => '+593999999999',
            'payment_mode' => 'gateway',
            'deposit_amount_cents' => 3000,
            'service_price_cents' => (int) round((float) $service->price * 100),
            'status' => 'paid',
        ], $overrides));
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Freeze "now" so the 24h-out window is deterministic across the test run.
        Carbon::setTestNow(Carbon::parse('2026-08-01 09:00:00', config('booking.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_sends_reminder_for_appointment_exactly_24_hours_out(): void
    {
        Notification::fake();

        $appointment = $this->makeAppointmentInHours(24.0);

        $this->artisan('appointments:send-reminders')->assertExitCode(0);

        Notification::assertSentTo($appointment->user, AppointmentReminder::class);
        $this->assertNotNull($appointment->fresh()->reminder_sent_at);
    }

    public function test_does_not_send_for_appointment_just_before_the_24_hour_window(): void
    {
        Notification::fake();

        // 23h59 out — belongs to the PREVIOUS hourly run's window, not this one.
        $appointment = $this->makeAppointmentInHours(23 + 59 / 60);

        $this->artisan('appointments:send-reminders')->assertExitCode(0);

        Notification::assertNotSentTo($appointment->user, AppointmentReminder::class);
        $this->assertNull($appointment->fresh()->reminder_sent_at);
    }

    public function test_does_not_send_for_appointment_at_or_after_the_25_hour_boundary(): void
    {
        Notification::fake();

        // Exactly 25h out — window end is exclusive, belongs to the NEXT run.
        $appointment = $this->makeAppointmentInHours(25.0);

        $this->artisan('appointments:send-reminders')->assertExitCode(0);

        Notification::assertNotSentTo($appointment->user, AppointmentReminder::class);
        $this->assertNull($appointment->fresh()->reminder_sent_at);
    }

    public function test_does_not_resend_to_an_already_reminded_appointment(): void
    {
        Notification::fake();

        $appointment = $this->makeAppointmentInHours(24.0, [
            'reminder_sent_at' => now()->subMinutes(5),
        ]);

        $this->artisan('appointments:send-reminders')->assertExitCode(0);

        Notification::assertNotSentTo($appointment->user, AppointmentReminder::class);
    }

    public function test_does_not_send_for_a_cancelled_appointment(): void
    {
        Notification::fake();

        $appointment = $this->makeAppointmentInHours(24.0, ['status' => 'cancelled']);

        $this->artisan('appointments:send-reminders')->assertExitCode(0);

        Notification::assertNotSentTo($appointment->user, AppointmentReminder::class);
    }

    public function test_does_not_send_for_a_pending_unpaid_appointment(): void
    {
        Notification::fake();

        $appointment = $this->makeAppointmentInHours(24.0, ['status' => 'pending']);

        $this->artisan('appointments:send-reminders')->assertExitCode(0);

        Notification::assertNotSentTo($appointment->user, AppointmentReminder::class);
    }

    public function test_sends_reminders_for_multiple_qualifying_appointments_in_one_run(): void
    {
        Notification::fake();

        $first = $this->makeAppointmentInHours(24.1);
        $second = $this->makeAppointmentInHours(24.5);
        $outOfWindow = $this->makeAppointmentInHours(48.0);

        $this->artisan('appointments:send-reminders')->assertExitCode(0);

        Notification::assertSentTo($first->user, AppointmentReminder::class);
        Notification::assertSentTo($second->user, AppointmentReminder::class);
        Notification::assertNotSentTo($outOfWindow->user, AppointmentReminder::class);
    }
}
