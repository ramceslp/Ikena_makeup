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
