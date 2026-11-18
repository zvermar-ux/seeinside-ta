<?php

namespace App\Services\Hotels;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\SupplierDestination;
use App\Models\SupplierHotel;
use App\Services\Hotels\Hotelbeds\HotelbedsDriver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Hotels from all booking systems of an agency: search with live prices (+ the agency's margin),
 * one hotel with all rooms and rates, booking request from the website, booking / cancelling at the supplier.
 */
class HotelService
{
    public const DRIVERS = [
        'hotelbeds' => HotelbedsDriver::class,
    ];

    public static function driver(SupplierConnection $c): HotelSupplier
    {
        $class = self::DRIVERS[$c->provider?->value ?? ''] ?? null;
        if (! $class) {
            throw new RuntimeException(__('This booking system has no hotel connection yet.'));
        }

        return app($class);
    }

    public static function supports(?SupplierConnection $c): bool
    {
        return $c && isset(self::DRIVERS[$c->provider?->value ?? '']);
    }

    /** Active hotel connections of an agency */
    public static function connections(int $agencyId): Collection
    {
        return SupplierConnection::withoutGlobalScopes()->where('agency_id', $agencyId)->where('is_active', true)
            ->whereIn('provider', array_keys(self::DRIVERS))->get();
    }

    public static function enabled(int $agencyId): bool
    {
        return self::connections($agencyId)->isNotEmpty();
    }

    /* ---------------- prices ---------------- */

    /** Price for the client: net + margin, rounded up to a whole amount; never below the hotel's own price (rate parity) */
    public static function sell(array $rate, SupplierConnection $c, ?SupplierHotel $h = null): float
    {
        $markup = $h?->markup_percent !== null ? (float) $h->markup_percent : (float) ($c->settings['markup_percent'] ?? 10);
        $price = ceil($rate['net'] * (1 + $markup / 100));
        if (! empty($rate['mandatory']) && ! empty($rate['selling'])) {
            $price = max($price, ceil($rate['selling']));
        }

        return (float) $price;
    }

    /* ---------------- website: destinations, search, hotel, booking ---------------- */

    /** Destinations offered in the website search (chosen in the app) */
    public static function destinations(int $agencyId): array
    {
        return Cache::remember('hotels:dest:'.$agencyId, 600, function () use ($agencyId) {
            $conns = self::connections($agencyId)->pluck('id');
            // hotels really shown on the website (imported and not hidden) per destination
            $counts = SupplierHotel::withoutGlobalScopes()->where('agency_id', $agencyId)->where('is_active', true)->whereIn('supplier_connection_id', $conns)
                ->selectRaw('supplier_connection_id, destination_code, count(*) as n')->groupBy('supplier_connection_id', 'destination_code')->get()
                ->mapWithKeys(fn ($r) => [$r->supplier_connection_id.':'.$r->destination_code => (int) $r->n]);

            return SupplierDestination::withoutGlobalScopes()
                ->where('agency_id', $agencyId)->where('is_active', true)->whereIn('supplier_connection_id', $conns)
                ->orderBy('country_name')->orderBy('name')->get()
                ->map(fn ($d) => ['id' => $d->supplier_connection_id.':'.$d->code, 'name' => $d->name, 'country' => $d->country_name ?: $d->country_code, 'hotels' => $counts[$d->supplier_connection_id.':'.$d->code] ?? 0])
                ->filter(fn ($d) => $d['hotels'] > 0)   // destinations without imported hotels are not offered
                ->values()->all();
        });
    }

    /** A photo for the header of the hotels page: the best featured (or top) hotel */
    public static function cover(int $agencyId): ?string
    {
        return Cache::remember('hotels:cover:'.$agencyId, 3600, function () use ($agencyId) {
            $h = SupplierHotel::withoutGlobalScopes()->where('agency_id', $agencyId)->where('is_active', true)
                ->whereIn('supplier_connection_id', self::connections($agencyId)->pluck('id'))->whereNotNull('images')
                ->orderByDesc('is_featured')->orderByDesc('stars')->orderByDesc('ranking')->first();
            $path = $h?->images[0]['path'] ?? null;

            return $path ? HotelbedsDriver::imageUrl($path, 'xl') : null;
        });
    }

    /** Hotels by name for the search field */
    public static function suggest(int $agencyId, string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        return SupplierHotel::withoutGlobalScopes()->where('agency_id', $agencyId)->where('is_active', true)
            ->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%')
            ->orderByDesc('is_featured')->orderByDesc('ranking')->limit(10)->get()
            ->map(fn ($h) => ['id' => $h->publicId(), 'name' => $h->name, 'stars' => $h->stars, 'place' => trim(($h->zone_name ? $h->zone_name.', ' : '').$h->destination_name, ', ')])->all();
    }

    /**
     * List of the imported hotels for the website (no prices – the price needs dates): name, stars, place, photo, facilities.
     * $f = destination "conn:code", stars [4,5], q (name), page, per_page.
     */
    public static function catalog(int $agencyId, array $f): array
    {
        $conns = self::connections($agencyId)->pluck('id');
        $q = SupplierHotel::withoutGlobalScopes()->where('agency_id', $agencyId)->where('is_active', true)->whereIn('supplier_connection_id', $conns);
        if (! empty($f['destination']) && str_contains($f['destination'], ':')) {
            [$conn, $code] = explode(':', $f['destination'], 2);
            $q->where('supplier_connection_id', (int) $conn)->where('destination_code', $code);
        } else {
            // only destinations switched on in the app
            $active = SupplierDestination::withoutGlobalScopes()->where('agency_id', $agencyId)->where('is_active', true)->whereIn('supplier_connection_id', $conns)
                ->get(['supplier_connection_id', 'code']);
            $q->where(function ($w) use ($active) {
                $w->whereRaw('1 = 0');
                foreach ($active->groupBy('supplier_connection_id') as $conn => $list) {
                    $w->orWhere(fn ($x) => $x->where('supplier_connection_id', $conn)->whereIn('destination_code', $list->pluck('code')));
                }
            });
        }
        if ($stars = array_filter(array_map('intval', (array) ($f['stars'] ?? [])))) {
            $q->whereIn('stars', $stars);
        }
        if (filled($f['q'] ?? null)) {
            $q->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], trim($f['q'])).'%');
        }
        $per = max(6, min(48, (int) ($f['per_page'] ?? 24)));
        $page = $q->orderByDesc('is_featured')->orderByDesc('stars')->orderByDesc('ranking')->orderBy('name')->paginate($per, ['*'], 'page', max(1, (int) ($f['page'] ?? 1)));

        return [
            'hotels' => collect($page->items())->map(fn (SupplierHotel $h) => [
                'id' => $h->publicId(),
                'name' => $h->name,
                'stars' => $h->stars,
                'place' => trim(($h->zone_name ? $h->zone_name.', ' : '').$h->destination_name, ', '),
                'destination' => $h->supplier_connection_id.':'.$h->destination_code,
                'country' => $h->country_code,
                'image' => ! empty($h->images[0]['path']) ? HotelbedsDriver::imageUrl($h->images[0]['path'], 'xl') : null,
                'images' => array_map(fn ($i) => HotelbedsDriver::imageUrl($i['path'], 'bigger'), array_slice($h->images ?? [], 0, 5)),
                'facilities' => array_map(fn ($x) => $x['name'], HotelText::facilities($h->facilities ?? [], 6)),
                'featured' => (bool) $h->is_featured,
            ])->all(),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'pages' => $page->lastPage(),
        ];
    }

    /**
     * Search: $q = from, to, rooms[[adults, children_ages]], destination "conn:code" or hotel "conn-code".
     * Returns ['search_id', 'hotels' => [summary…], 'nights', 'currency'] – the rates stay in the cache for the hotel page.
     */
    public function search(int $agencyId, array $q): array
    {
        $nights = (int) round((strtotime($q['to']) - strtotime($q['from'])) / 86400);
        if ($nights < 1 || $nights > 30) {
            throw ValidationException::withMessages(['to' => __('Choose between 1 and 30 nights.')]);
        }
        $norm = ['from' => $q['from'], 'to' => $q['to'], 'rooms' => array_values(array_map(fn ($r) => ['adults' => max(1, min(8, (int) ($r['adults'] ?? 2))), 'children_ages' => array_values(array_map('intval', array_slice((array) ($r['children_ages'] ?? []), 0, 4)))], array_slice($q['rooms'], 0, 5))),
            'destination' => $q['destination'] ?? null, 'hotel' => $q['hotel'] ?? null];
        $searchId = substr(sha1($agencyId.'|'.json_encode($norm)), 0, 20);

        $result = Cache::get('hotels:s:'.$searchId) ?: (function () use ($agencyId, $norm, $nights, $searchId) {
            $hotels = [];
            $errors = [];
            foreach (self::connections($agencyId) as $c) {
                $query = ['from' => $norm['from'], 'to' => $norm['to'], 'rooms' => $norm['rooms']];
                if ($norm['hotel']) {
                    [$cid, $code] = array_pad(explode('-', (string) $norm['hotel'], 2), 2, null);
                    if ((int) $cid !== $c->id || ! $code) {
                        continue;
                    }
                    $query['hotels'] = [$code];
                } else {
                    [$cid, $code] = array_pad(explode(':', (string) $norm['destination'], 2), 2, null);
                    if ((int) $cid !== $c->id || ! $code) {
                        continue;
                    }
                    $query['destination'] = $code;
                }
                try {
                    $found = self::driver($c)->search($c, $query);
                } catch (\Throwable $e) {
                    report($e);
                    $errors[] = $e->getMessage();

                    continue;
                }
                $content = SupplierHotel::withoutGlobalScopes()->where('supplier_connection_id', $c->id)
                    ->whereIn('code', array_column($found, 'code'))->get()->keyBy('code');
                foreach ($found as $h) {
                    $local = $content[$h['code']] ?? null;
                    if ($local && ! $local->is_active) {
                        continue;
                    }
                    foreach ($h['rooms'] as &$room) {
                        foreach ($room['rates'] as &$rate) {
                            $rate['price'] = self::sell($rate, $c, $local);
                            $rate['board_group'] = HotelText::boardGroup($rate['board_code']);
                            $rate['board_tr'] = HotelText::board($rate['board_code'], $rate['board']);
                        }
                        unset($rate);
                        usort($room['rates'], fn ($a, $b) => $a['price'] <=> $b['price']);
                    }
                    unset($room);
                    $h['rooms'] = array_values(array_filter($h['rooms'], fn ($r) => $r['rates']));
                    if (! $h['rooms']) {
                        continue;
                    }
                    $hotels[$c->id.'-'.$h['code']] = ['id' => $c->id.'-'.$h['code'], 'connection_id' => $c->id, 'live' => $h, 'local_id' => $local?->id];
                }
            }

            $result = ['hotels' => $hotels, 'errors' => $errors, 'nights' => $nights];
            // prices stay 15 minutes; an empty answer or an error only 2 minutes
            Cache::put('hotels:s:'.$searchId, $result, $hotels ? 900 : 120);

            return $result;
        })();

        $locals = SupplierHotel::withoutGlobalScopes()->whereIn('id', array_filter(array_column($result['hotels'], 'local_id')))->get()->keyBy('id');
        $list = [];
        foreach ($result['hotels'] as $id => $row) {
            $list[] = $this->summary($row, $locals[$row['local_id']] ?? null);
        }
        usort($list, fn ($a, $b) => [$b['featured'], $a['price']] <=> [$a['featured'], $b['price']]);

        return ['search_id' => $searchId, 'nights' => $result['nights'], 'currency' => $list[0]['currency'] ?? 'EUR', 'hotels' => $list,
            'error' => ! $list && $result['errors'] ? __('The hotel search is not available at the moment. Please try again or contact us.') : null];
    }

    protected function summary(array $row, ?SupplierHotel $l): array
    {
        $h = $row['live'];
        $rates = collect($h['rooms'])->flatMap(fn ($r) => $r['rates']);
        $cheapest = $rates->sortBy('price')->first();

        return [
            'id' => $row['id'],
            'name' => $l?->name ?: ucwords(mb_strtolower($h['name'])),
            'stars' => $l?->stars ?? $h['stars'],
            'place' => trim(($l?->zone_name ?? $h['zone'] ?? '').', '.($l?->destination_name ?? $h['destination'] ?? ''), ', '),
            'image' => $l && ! empty($l->images[0]['path']) ? HotelbedsDriver::imageUrl($l->images[0]['path'], 'xl') : null,
            'images' => $l ? array_map(fn ($i) => HotelbedsDriver::imageUrl($i['path'], 'bigger'), array_slice($l->images ?? [], 0, 5)) : [],
            'latitude' => $l?->latitude ?? $h['latitude'],
            'longitude' => $l?->longitude ?? $h['longitude'],
            'facilities' => $l ? array_map(fn ($f) => $f['name'], HotelText::facilities($l->facilities ?? [], 6)) : [],
            'price' => (float) $cheapest['price'],
            'currency' => $h['currency'],
            'board' => $cheapest['board_tr'],
            'boards' => $rates->pluck('board_group')->unique()->values()->all(),
            'refundable' => $rates->contains(fn ($r) => $r['refundable']),
            'featured' => (bool) $l?->is_featured,
            'ranking' => (int) ($l?->ranking ?? 0),
            'left' => $cheapest['allotment'] !== null && $cheapest['allotment'] <= 3 ? $cheapest['allotment'] : null,
        ];
    }

    /** One hotel: description, photos, facilities and (with a search) every room and rate */
    public function hotel(int $agencyId, string $id, ?string $searchId = null, ?array $q = null): array
    {
        [$cid, $code] = array_pad(explode('-', $id, 2), 2, null);
        $c = self::connections($agencyId)->firstWhere('id', (int) $cid);
        if (! $c || ! $code) {
            abort(404);
        }
        $l = SupplierHotel::withoutGlobalScopes()->where('supplier_connection_id', $c->id)->where('code', $code)->first();
        if ($l && ! $l->is_active) {
            abort(404);
        }
        $row = $searchId ? (Cache::get('hotels:s:'.$searchId)['hotels'][$id] ?? null) : null;
        if (! $row && $q) {
            // opened from a link (or the search expired): ask the prices for this hotel only
            $res = $this->search($agencyId, array_merge($q, ['hotel' => $id, 'destination' => null]));
            $searchId = $res['search_id'];
            $row = Cache::get('hotels:s:'.$searchId)['hotels'][$id] ?? null;
        }
        $live = $row['live'] ?? null;
        if (! $l && ! $live) {
            abort(404);
        }
        $rooms = [];
        foreach ($live['rooms'] ?? [] as $room) {
            $info = collect($l?->rooms ?? [])->firstWhere('code', $room['code']);
            $rooms[] = [
                'code' => $room['code'],
                'name' => ucfirst(mb_strtolower($room['name'])),
                'image' => ($img = collect($l?->images ?? [])->firstWhere('room', $room['code'])) ? HotelbedsDriver::imageUrl($img['path'], 'bigger') : null,
                'max_pax' => $info['max_pax'] ?? null,
                'rates' => array_map(fn ($r) => [
                    'key' => $r['key'], 'price' => $r['price'], 'board' => $r['board_tr'], 'board_group' => $r['board_group'],
                    'refundable' => $r['refundable'], 'free_cancel_until' => $this->freeCancelUntil($r), 'cancellation' => $r['cancellation'],
                    'pay_at_hotel' => $r['pay_at_hotel'], 'left' => $r['allotment'] !== null && $r['allotment'] <= 3 ? $r['allotment'] : null,
                    'offers' => $r['offers'], 'taxes_extra' => $r['taxes_extra'], 'rooms' => $r['rooms'],
                ], $room['rates']),
            ];
        }
        usort($rooms, fn ($a, $b) => ($a['rates'][0]['price'] ?? INF) <=> ($b['rates'][0]['price'] ?? INF));

        return [
            'id' => $id,
            'name' => $l?->name ?: ucwords(mb_strtolower($live['name'] ?? '')),
            'stars' => $l?->stars ?? ($live['stars'] ?? null),
            'place' => trim(($l?->zone_name ?? $live['zone'] ?? '').', '.($l?->destination_name ?? $live['destination'] ?? ''), ', '),
            'address' => trim(($l?->address ?? '').($l?->city ? ', '.$l->city : ''), ', '),
            'description' => $l?->description,
            'images' => array_map(fn ($i) => ['url' => HotelbedsDriver::imageUrl($i['path'], 'xl'), 'thumb' => HotelbedsDriver::imageUrl($i['path'], 'bigger')], $l?->images ?? []),
            'facilities' => HotelText::facilities($l?->facilities ?? []),
            'latitude' => $l?->latitude ?? ($live['latitude'] ?? null),
            'longitude' => $l?->longitude ?? ($live['longitude'] ?? null),
            'currency' => $live['currency'] ?? 'EUR',
            'rooms' => $rooms,
            'search_id' => $searchId,
        ];
    }

    protected function freeCancelUntil(array $rate): ?string
    {
        if (! $rate['refundable']) {
            return null;
        }
        $first = collect($rate['cancellation'])->sortBy('from')->first();

        return $first && $first['from'] ? date('Y-m-d H:i', strtotime($first['from']) - 86400) : null;
    }

    /** A rate of a cached search */
    protected function rateFromSearch(string $searchId, string $hotelId, string $rateKey): ?array
    {
        $row = Cache::get('hotels:s:'.$searchId)['hotels'][$hotelId] ?? null;
        foreach ($row['live']['rooms'] ?? [] as $room) {
            foreach ($room['rates'] as $r) {
                if ($r['key'] === $rateKey) {
                    return ['row' => $row, 'room' => $room, 'rate' => $r];
                }
            }
        }

        return null;
    }

    /**
     * Booking request from the website. The price is checked again at the supplier; when it went up the visitor
     * is asked to accept the new one. The real booking at the supplier is made by the agency in the app
     * (or at once when "Book automatically" is on for the connection).
     */
    public function book(int $agencyId, array $d): Booking
    {
        $found = $this->rateFromSearch($d['search_id'], $d['hotel'], $d['rate_key']);
        if (! $found) {
            throw ValidationException::withMessages(['rate_key' => __('This offer has expired. Search again, please.')]);
        }
        $c = self::connections($agencyId)->firstWhere('id', $found['row']['connection_id']);
        if (! $c) {
            throw ValidationException::withMessages(['hotel' => __('Unknown hotel.')]);
        }
        $local = $found['row']['local_id'] ? SupplierHotel::withoutGlobalScopes()->find($found['row']['local_id']) : null;
        $rate = $found['rate'];
        $price = (float) $rate['price'];

        // the final price and conditions from the supplier
        try {
            $checked = self::driver($c)->checkRate($c, $rate['key']);
            $new = $checked['rooms'][0]['rates'][0] ?? null;
            if ($new) {
                $newPrice = self::sell($new, $c, $local);
                if ($newPrice > $price + 0.01 && (float) ($d['accept_price'] ?? 0) < $newPrice) {
                    throw new PriceChanged($newPrice, $checked['currency'] ?? 'EUR');
                }
                $rate = array_merge($rate, $new, ['key' => $new['key'] ?: $rate['key']]);
                $price = $newPrice;
            }
        } catch (ValidationException|PriceChanged $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['rate_key' => __('This offer is no longer available. Search again, please.')]);
        }

        $q = $d['query'];
        $ages = collect($q['rooms'])->flatMap(fn ($r) => (array) ($r['children_ages'] ?? []))->values()->all();
        $board = HotelText::board($rate['board_code'], $rate['board']);
        $live = $found['row']['live'];
        $booking = new Booking([
            'agency_id' => $agencyId,
            'supplier_connection_id' => $c->id,
            'supplier_status' => 'quoted',
            'supplier_data' => [
                'provider' => $c->provider->value,
                'hotel_code' => $live['code'],
                'hotel_name' => $local?->name ?: ucwords(mb_strtolower($live['name'])),
                'stars' => $local?->stars ?? $live['stars'],
                'place' => trim(($local?->zone_name ?? $live['zone'] ?? '').', '.($local?->destination_name ?? $live['destination'] ?? ''), ', '),
                'room_name' => ucfirst(mb_strtolower($found['room']['name'])),
                'room_code' => $found['room']['code'],
                'rooms' => count($q['rooms']),
                'occupancy' => $q['rooms'],
                'board' => $board['mk'],
                'board_code' => $rate['board_code'],
                'rate_key' => $rate['key'],
                'net' => (float) $rate['net'],
                'price' => $price,
                'currency' => $live['currency'],
                'refundable' => (bool) $rate['refundable'],
                'cancellation' => $rate['cancellation'],
                'pay_at_hotel' => (bool) $rate['pay_at_hotel'],
                'comments' => $rate['comments'] ?? '',
                'taxes_extra' => $rate['taxes_extra'] ?? [],
            ],
            'adults' => collect($q['rooms'])->sum('adults'),
            'children' => count($ages),
            'children_ages' => $ages ?: null,
            'quantity' => count($q['rooms']),
            'date_from' => $q['from'],
            'date_to' => $q['to'],
            'customer_name' => $d['customer']['name'],
            'customer_email' => $d['customer']['email'],
            'customer_phone' => $d['customer']['phone'],
            'passengers' => $d['passengers'] ?? null,
            'message' => $d['message'] ?? null,
            'currency' => $live['currency'],
            'total' => $price,
            'source' => 'web',
            'locale' => $d['locale'] ?? 'mk',
            'status' => BookingStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
        ]);
        $booking->save();

        if (! empty($c->settings['auto_book'])) {
            $this->bookAtSupplier($booking);
        }

        return $booking->fresh();
    }

    /** Real booking at the supplier (from the app, or automatically) */
    public function bookAtSupplier(Booking $b): Booking
    {
        $c = $b->supplierConnection()->withoutGlobalScopes()->first();
        $s = (array) $b->supplier_data;
        if (! $c || empty($s['rate_key'])) {
            throw new RuntimeException(__('This booking has no hotel rate to book.'));
        }
        if ($b->external_ref && $b->supplier_status === 'booked') {
            throw new RuntimeException(__('Already booked: :ref', ['ref' => $b->external_ref]));
        }
        // guests: passengers of the booking split over the rooms; the first adult holds the booking
        $occ = array_values((array) ($s['occupancy'] ?? [['adults' => $b->adults, 'children_ages' => (array) $b->children_ages]]));
        $pax = array_values(array_filter((array) $b->passengers, fn ($p) => filled($p['first_name'] ?? null)));
        $guests = [];
        $i = 0;
        foreach ($occ as $n => $room) {
            for ($a = 0; $a < (int) $room['adults']; $a++, $i++) {
                $p = $pax[$i] ?? null;
                $guests[] = ['room' => $n + 1, 'type' => 'AD', 'first_name' => $p['first_name'] ?? Str::before($b->customer_name, ' '), 'last_name' => $p['last_name'] ?? (Str::after($b->customer_name, ' ') ?: $b->customer_name)];
            }
            foreach ((array) ($room['children_ages'] ?? []) as $age) {
                $p = $pax[$i++] ?? null;
                $guests[] = ['room' => $n + 1, 'type' => 'CH', 'age' => (int) $age, 'first_name' => $p['first_name'] ?? 'Child', 'last_name' => $p['last_name'] ?? (Str::after($b->customer_name, ' ') ?: $b->customer_name)];
            }
        }
        $holder = $pax[0] ?? ['first_name' => Str::before($b->customer_name, ' '), 'last_name' => Str::after($b->customer_name, ' ') ?: $b->customer_name];
        try {
            $r = self::driver($c)->book($c, [
                'rate_key' => $s['rate_key'], 'reference' => $b->reference, 'guests' => $guests,
                'holder_first' => $holder['first_name'], 'holder_last' => $holder['last_name'],
                'remark' => (string) ($s['remark'] ?? ''), 'tolerance' => (float) ($c->settings['tolerance'] ?? 2),
            ]);
        } catch (\Throwable $e) {
            $b->forceFill(['supplier_status' => 'failed', 'supplier_data' => $s + ['error' => $e->getMessage()]])->save();
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
        $b->forceFill([
            'external_ref' => $r['reference'],
            'supplier_status' => 'booked',
            'supplier_data' => array_merge($s, ['booked_net' => $r['net'], 'booked_at' => now()->toIso8601String(), 'error' => null]),
            'status' => BookingStatus::Confirmed,
        ])->save();

        return $b;
    }

    public function cancelAtSupplier(Booking $b, bool $simulate = false): array
    {
        $c = $b->supplierConnection()->withoutGlobalScopes()->first();
        if (! $c || ! $b->external_ref) {
            throw new RuntimeException(__('This booking was not booked at the supplier.'));
        }
        $r = self::driver($c)->cancel($c, $b->external_ref, $simulate);
        if (! $simulate) {
            $b->forceFill(['supplier_status' => 'cancelled', 'supplier_data' => array_merge((array) $b->supplier_data, ['cancelled_at' => now()->toIso8601String(), 'cancel_fee' => $r['fee']])])->save();
        }

        return $r;
    }

    /* ---------------- content import ---------------- */

    /** Destinations of the countries (Hotelbeds codes), keeps which ones are offered */
    public function importDestinations(SupplierConnection $c, array $countries): int
    {
        $names = ['GR' => 'Грција', 'BG' => 'Бугарија', 'TR' => 'Турција', 'AL' => 'Албанија', 'ME' => 'Црна Гора', 'HR' => 'Хрватска', 'IT' => 'Италија', 'ES' => 'Шпанија',
            'EG' => 'Египет', 'TN' => 'Тунис', 'CY' => 'Кипар', 'MK' => 'Македонија', 'RS' => 'Србија', 'AE' => 'ОАЕ', 'FR' => 'Франција', 'AT' => 'Австрија', 'DE' => 'Германија',
            'PT' => 'Португалија', 'MT' => 'Малта', 'TH' => 'Тајланд', 'MV' => 'Малдиви', 'CZ' => 'Чешка', 'HU' => 'Унгарија', 'NL' => 'Холандија', 'GB' => 'Велика Британија'];
        $n = 0;
        foreach (self::driver($c)->destinations($c, $countries) as $d) {
            SupplierDestination::withoutGlobalScopes()->updateOrCreate(
                ['supplier_connection_id' => $c->id, 'code' => $d['code']],
                ['agency_id' => $c->agency_id, 'name' => $d['name'], 'country_code' => $d['country_code'], 'country_name' => $names[$d['country_code']] ?? $d['country_code'], 'zones' => $d['zones']],
            );
            $n++;
        }
        Cache::forget('hotels:dest:'.$c->agency_id);

        return $n;
    }

    /** Hotel descriptions and photos of a destination (100 per request) */
    public function importHotels(SupplierConnection $c, string $destinationCode, int $minStars = 0, ?callable $progress = null): int
    {
        $dest = SupplierDestination::withoutGlobalScopes()->where('supplier_connection_id', $c->id)->where('code', $destinationCode)->first();
        $n = 0;
        for ($from = 1, $total = PHP_INT_MAX; $from <= $total && $from < 20000; $from += 100) {
            $page = self::driver($c)->hotels($c, $destinationCode, $from, $from + 99);
            $total = $page['total'];
            foreach ($page['hotels'] as $h) {
                if ($minStars && ($h['stars'] ?? 0) < $minStars) {
                    continue;
                }
                $zone = collect($dest?->zones ?? [])->firstWhere('code', $h['zone_code'])['name'] ?? null;
                SupplierHotel::withoutGlobalScopes()->updateOrCreate(
                    ['supplier_connection_id' => $c->id, 'code' => $h['code']],
                    [
                        'agency_id' => $c->agency_id, 'name' => $h['name'], 'destination_code' => $h['destination_code'], 'destination_name' => $dest?->name,
                        'zone_name' => $zone, 'country_code' => $h['country_code'], 'stars' => $h['stars'], 'category_code' => $h['category_code'],
                        'accommodation_type' => $h['accommodation_type'], 'latitude' => $h['latitude'], 'longitude' => $h['longitude'],
                        'address' => Str::limit($h['address'], 250, ''), 'city' => Str::limit($h['city'], 250, ''), 'postal_code' => Str::limit((string) $h['postal_code'], 20, ''),
                        'phone' => Str::limit((string) $h['phone'], 60, ''), 'email' => Str::limit((string) $h['email'], 250, ''), 'web' => Str::limit((string) $h['web'], 250, ''),
                        'description' => $h['description'], 'images' => $h['images'], 'facilities' => $h['facilities'], 'rooms' => $h['rooms'], 'boards' => $h['boards'], 'ranking' => $h['ranking'],
                    ],
                );
                $n++;
            }
            $progress && $progress($n, $total);
        }
        if ($dest) {
            $dest->forceFill(['hotels_count' => SupplierHotel::withoutGlobalScopes()->where('supplier_connection_id', $c->id)->where('destination_code', $destinationCode)->count()])->save();
        }
        $c->forceFill(['last_sync_at' => now()])->save();
        Cache::forget('hotels:dest:'.$c->agency_id);

        return $n;
    }
}
