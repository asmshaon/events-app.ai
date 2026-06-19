<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Drives the reminder emails (the scheduler container runs schedule:work).
// Hourly is frequent enough and the command is idempotent. The --days=1825
// override widens the lookahead to ~5 years so reminders fire for the far-out
// seeded events — for demo/testing; drop the flag for real 3-day/24-hour windows.
Schedule::command('events:send-reminders', ['--days' => 1825])->hourly()->withoutOverlapping();
