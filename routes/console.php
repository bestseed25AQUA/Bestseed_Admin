<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Check for stale driver tracking every 5 minutes and alert vendor + admin
Schedule::command('tracking:check-stale')->everyFiveMinutes();

// Warn farmers before their Farm Management subscription lapses.
//
// Once a day, mid-morning: the reminder is a prompt to ring the helpline, so
// it should arrive when someone is there to answer. The command is idempotent
// — it records each reminder before sending — so a double run is harmless.
Schedule::command('subscriptions:notify-expiry')->dailyAt('10:00');
