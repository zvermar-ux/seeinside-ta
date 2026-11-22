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

/*
 * Diagnosis of sending a booking to the old Aries system: shows every request exactly as sent (password hidden) and the whole answer.
 * Ways are tried one by one and it STOPS at the first answer that is not empty (so a booking is made at most once).
 *   php artisan aries:book-test R2611-KBI1S            only shows the fields, sends nothing
 *   php artisan aries:book-test R2611-KBI1S --send     sends
 */
Artisan::command('aries:book-test {reference} {--send : really send it} {--mode=guests : guests | room}', function () {
    $b = \App\Models\Booking::withoutGlobalScopes()->where('reference', strtoupper($this->argument('reference')))->first();
    if (! $b) {
        $this->error('No booking '.$this->argument('reference'));

        return 1;
    }
    $c = \App\Services\Mcs\McsService::connectionFor($b);
    if (! $c) {
        $this->error('The offer of this booking is not linked to the old Aries system.');

        return 1;
    }
    $api = \App\Services\Mcs\McsService::client($c);
    try {
        $params = \App\Services\Mcs\McsService::debugBookParams($b, $c, (string) $this->option('mode'));
    } catch (\Throwable $e) {
        $this->error('Search before booking: '.$e->getMessage());

        return 1;
    }
    $this->line('Address: '.$api->baseUrl());
    $this->line('Fields (+ username, password):');
    foreach ($params as $k => $v) {
        $this->line('  '.$k.' = '.$v);
    }
    $show = function (array $r) {
        $this->line(str_repeat('-', 70));
        $this->info(strtoupper($r['how']).' '.$r['url']);
        if (isset($r['error'])) {
            $this->error('  ERROR: '.$r['error']);

            return;
        }
        $this->line('  HTTP '.$r['status'].' · '.$r['ms'].' ms · '.strlen($r['body']).' bytes');
        foreach (['Content-Type', 'Content-Length', 'Server', 'X-Powered-By', 'Location'] as $h) {
            if (isset($r['headers'][$h])) {
                $this->line('  '.$h.': '.$r['headers'][$h]);
            }
        }
        $this->line('  Body: '.json_encode(mb_substr($r['body'], 0, 1500), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    };
    if (! $this->option('send')) {
        $this->warn('Nothing sent. Add --send to send it (it stops at the first non-empty answer).');

        return 0;
    }
    $base = $api->baseUrl();
    $plain = preg_replace('#\.php$#', '', $base);
    $ways = [['BookRequest', 'post-form', $base], ['Book', 'post-form', $base], ['BookRequest', 'post-form', $plain], ['BookRequest', 'get', $base], ['BookRequest', 'post-json', $base]];
    foreach ($ways as [$op, $how, $url]) {
        $r = $api->raw($op, $params, $how, $url);
        $show($r);
        if (! isset($r['error']) && trim($r['body']) !== '' && ($r['status'] ?? 0) < 400) {
            $this->info('>>> Non-empty answer – stopped here (no more tries).');

            return 0;
        }
    }
    $this->warn('Every way gave an empty answer or an error.');
    $this->line('For MCS (password hidden):');
    $this->line('curl -i -X POST '.escapeshellarg($base.'/BookRequest').' '.collect(['username' => '***', 'password' => '***'] + $params)->map(fn ($v, $k) => '--data-urlencode '.escapeshellarg($k.'='.$v))->implode(' '));

    return 0;
})->purpose('Diagnosis: send a booking to the old Aries system and show the raw answer');

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

Artisan::command('aries:availability', function () {
    foreach (\App\Models\SupplierConnection::withoutGlobalScopes()->where('provider', 'mcs')->where('is_active', true)->get() as $c) {
        if (($c->settings['sync_prices'] ?? true) === false) {
            continue;
        }
        $lock = \Illuminate\Support\Facades\Cache::lock('mcs-sync-'.$c->id, 3600);   // not while the big sync runs
        if (! $lock->get()) {
            $this->line($c->name.': sync running');

            continue;
        }
        try {
            $this->info($c->name.': '.json_encode(\App\Services\Mcs\McsService::quickAvailability($c)));
        } catch (\Throwable $e) {
            report($e);
        } finally {
            $lock->release();
        }
    }
})->purpose('What was booked offline in the old Aries system: free / stop sale for the terms and departures we have');
// offline bookings of the old system reach the website within ~2 hours (7–23 h)
\Illuminate\Support\Facades\Schedule::command('aries:availability')->cron('10 7-23/2 * * *')->runInBackground()->withoutOverlapping();
