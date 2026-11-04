<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// every morning: payment / invoice / option / trip reminders to clients + summary for the agency
\Illuminate\Support\Facades\Schedule::command('reminders:send')->dailyAt('09:00')->withoutOverlapping();

// promotions that change by date (early booking steps, last minute end): prices on the website follow every night
Artisan::command('promotions:refresh', function () {
    $n = 0;
    foreach ([\App\Models\Tour::class, \App\Models\Accommodation::class, \App\Models\Event::class] as $class) {
        $class::withoutGlobalScopes()->where('is_active', true)
            ->where(fn ($q) => $q->whereNotNull('early_booking_tiers')->orWhereDate('first_minute_until', today()->subDay())->orWhereDate('last_minute_until', today()->subDay()))
            ->each(function ($m) use (&$n) {
                $m->touch();
                $n++;
            });
    }
    $this->info("Refreshed: $n");
})->purpose('Recalculate prices of offers whose promotion changed today');
\Illuminate\Support\Facades\Schedule::command('promotions:refresh')->dailyAt('00:15')->withoutOverlapping();
