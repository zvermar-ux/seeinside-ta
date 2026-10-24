<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// every morning: payment / invoice / option / trip reminders to clients + summary for the agency
\Illuminate\Support\Facades\Schedule::command('reminders:send')->dailyAt('09:00')->withoutOverlapping();
