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

// hotels from booking systems: descriptions / photos of the hotels (prices are always live)
//   php artisan hotels:import 3 --countries=GR,BG        destinations of the countries
//   php artisan hotels:import 3 --destinations=SKG,HER --min-stars=3
//   php artisan hotels:import 3 --refresh                hotels of every destination offered on the website
Artisan::command('hotels:import {connection} {--countries=} {--destinations=} {--min-stars=0} {--refresh}', function () {
    $c = \App\Models\SupplierConnection::withoutGlobalScopes()->findOrFail($this->argument('connection'));
    $svc = app(\App\Services\Hotels\HotelService::class);
    if ($this->option('countries')) {
        $this->info('Destinations: '.$svc->importDestinations($c, explode(',', $this->option('countries'))));
    }
    $codes = $this->option('destinations') ? explode(',', $this->option('destinations')) : [];
    if ($this->option('refresh')) {
        $codes = array_merge($codes, \App\Models\SupplierDestination::withoutGlobalScopes()->where('supplier_connection_id', $c->id)->where('is_active', true)->pluck('code')->all());
    }
    foreach (array_unique(array_filter(array_map('trim', $codes))) as $code) {
        $n = $svc->importHotels($c, $code, (int) $this->option('min-stars'), fn ($done, $total) => $this->output->write("\r$code: $done / $total   "));
        \App\Models\SupplierDestination::withoutGlobalScopes()->where('supplier_connection_id', $c->id)->where('code', $code)->get()->each->update(['is_active' => true]);
        $this->newLine();
        $this->info("$code: $n hotels");
    }
})->purpose('Import destinations / hotel descriptions from a hotel booking system (Hotelbeds)');
