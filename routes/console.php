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

/* ---------------- old Aries system (MCS eTours) while both systems are used ---------------- */

Artisan::command('aries:catalog', function () {
    foreach (\App\Models\SupplierConnection::withoutGlobalScopes()->where('provider', 'mcs')->where('is_active', true)->get() as $c) {
        try {
            $r = \App\Services\Mcs\McsService::importCatalog($c);
            $this->info($c->name.': '.json_encode($r));
        } catch (\Throwable $e) {
            report($e);
            $this->error($c->name.': '.$e->getMessage());
        }
    }
})->purpose('Hotels/apartments and packages of the old Aries system (for linking)');

Artisan::command('aries:prices {--days=} {--requested : only connections where "Sync prices now" was clicked}', function () {
    foreach (\App\Models\SupplierConnection::withoutGlobalScopes()->where('provider', 'mcs')->where('is_active', true)->get() as $c) {
        $days = $this->option('days') ? (int) $this->option('days') : null;
        if ($this->option('requested')) {
            $req = $c->settings['sync_request'] ?? null;
            if (! $req) {
                continue;
            }
            $days = (int) ($req['days'] ?? 0) ?: null;
            $c->forceFill(['settings' => array_diff_key((array) $c->settings, ['sync_request' => 1])])->saveQuietly();
        }
        $r = \App\Services\Mcs\McsService::runLocked($c, $days);
        $this->info($c->name.': '.($r === null ? 'already running or failed' : json_encode($r, JSON_UNESCAPED_UNICODE)));
    }
})->purpose('Dates and prices of linked tours and apartments from the old Aries system');

Artisan::command('aries:retry', function () {
    $list = \App\Models\Booking::withoutGlobalScopes()->where('legacy_status', 'failed')->where('created_at', '>=', now()->subDays(3))->get();
    foreach ($list as $b) {
        $r = \App\Services\Mcs\McsService::push($b);
        $this->line($b->reference.': '.$r['message']);
    }
})->purpose('Send again the bookings that did not reach the old Aries system');

\Illuminate\Support\Facades\Schedule::command('aries:catalog')->dailyAt('02:40')->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('aries:prices')->dailyAt('03:20')->runInBackground();
\Illuminate\Support\Facades\Schedule::command('aries:prices --requested')->everyMinute()->runInBackground();
\Illuminate\Support\Facades\Schedule::command('aries:retry')->everyThirtyMinutes()->withoutOverlapping();
