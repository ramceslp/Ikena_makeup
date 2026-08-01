<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Release expired product_cart stock reservations every minute.
// Safe to run frequently: idempotent + uses conditional UPDATE for race safety.
Schedule::command('stock:release-expired')->everyMinute();

// Prune expired/consumed checkout_handoffs rows (mobile-capacitor-setup PR2).
// Low frequency is fine -- these rows are inert once expired or consumed.
Schedule::command('checkout-handoffs:prune')->hourly();

// Delete personal_access_tokens rows that expired over 24h ago.
// sanctum.expiration stops an expired token from authenticating, but the row
// itself would otherwise accumulate forever — every token any user ever held,
// hash included, sitting in the database.
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Send the "Appointment reminder" v1 push trigger for appointments ~24h out
// (mobile-capacitor-setup PR3). Hourly cadence matches the command's
// one-hour-wide window so consecutive runs tile with no gap or overlap.
Schedule::command('appointments:send-reminders')->hourly()->withoutOverlapping();
