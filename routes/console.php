<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Check for stale driver tracking every 5 minutes and alert vendor + admin
Schedule::command('tracking:check-stale')->everyFiveMinutes();
