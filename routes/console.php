<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Drives the 3-day / 24-hour reminder emails (the scheduler container runs
// schedule:work). Hourly is frequent enough for both windows and is idempotent.
Schedule::command('events:send-reminders')->hourly()->withoutOverlapping();
