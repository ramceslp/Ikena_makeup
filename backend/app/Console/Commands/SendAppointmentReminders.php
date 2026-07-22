<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Notifications\AppointmentReminder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * appointments:send-reminders — mobile-capacitor-setup PR3, task 3.10.
 *
 * v1 "Appointment reminder" push trigger (secondary), design Decision 2
 * (sdd/mobile-capacitor-setup/design.md). Intended to run hourly (see
 * routes/console.php): each run covers a one-hour-wide window
 * [now+24h, now+25h) evaluated in `booking.timezone` — the same timezone
 * appointments are scheduled in — so consecutive hourly runs tile exactly
 * with no gap or overlap.
 *
 * Only `status = 'paid'` (confirmed/paid) appointments qualify — pending or
 * cancelled appointments are not reminded. `reminder_sent_at` is set after a
 * successful send so a later run never notifies the same appointment twice.
 */
class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Send a reminder notification for appointments starting in ~24 hours';

    public function handle(): int
    {
        $timezone = config('booking.timezone');
        $now = Carbon::now($timezone);
        $windowStart = $now->copy()->addHours(24);
        $windowEnd = $now->copy()->addHours(25);

        // Narrow at the SQL level by calendar date first (cheap, index-friendly),
        // then apply the precise date+time window check in PHP below — this
        // keeps the exact boundary semantics (start inclusive, end exclusive)
        // independent of DB-specific date/time arithmetic.
        //
        // whereDate() (not whereBetween() on the raw column) is required here:
        // SQLite's `date` cast persists the column as a full 'Y-m-d 00:00:00'
        // string, not a bare 'Y-m-d' one, so a plain string range comparison
        // against toDateString() silently excludes every row (the extra
        // ' 00:00:00' suffix sorts after the bare-date upper bound).
        // whereDate() wraps the column in a DATE() SQL function so the
        // comparison is driver-consistent.
        $candidates = Appointment::query()
            ->where('status', 'paid')
            ->whereNull('reminder_sent_at')
            ->whereDate('scheduled_date', '>=', $windowStart->toDateString())
            ->whereDate('scheduled_date', '<=', $windowEnd->toDateString())
            ->get();

        $sent = 0;

        foreach ($candidates as $appointment) {
            $scheduledAt = Carbon::parse(
                $appointment->scheduled_date->format('Y-m-d').' '.substr((string) $appointment->scheduled_time, 0, 5),
                $timezone,
            );

            if ($scheduledAt->greaterThanOrEqualTo($windowStart) && $scheduledAt->lessThan($windowEnd)) {
                Notification::send($appointment->user, new AppointmentReminder($appointment));
                $appointment->update(['reminder_sent_at' => now()]);
                $sent++;
            }
        }

        $this->info("Sent {$sent} appointment reminder(s).");

        return self::SUCCESS;
    }
}
