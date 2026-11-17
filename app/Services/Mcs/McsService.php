<?php

namespace App\Services\Mcs;

use App\Enums\DepartureStatus;
use App\Enums\SupplierProvider;
use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\StayTerm;
use App\Models\SupplierConnection;
use App\Models\SupplierProduct;
use App\Models\TermPrice;
use App\Models\Tour;
use App\Models\TourDeparture;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * While the old Aries system is still used next to this app:
 *  - its hotels/apartments and packages are read (GetHotels / GetPackages) to link our offers to them,
 *  - every new booking of a linked offer is sent there at once (HotelSearch / PackageSearch → BookRequest),
 *  - dates and prices of linked tours and apartments are taken from there (the content stays ours).
 * Cancellations are not sent: the old system is handled by hand.
 */
class McsService
{
    public static function connections(int $agencyId): Collection
    {
        return SupplierConnection::withoutGlobalScopes()->where('agency_id', $agencyId)
            ->where('provider', SupplierProvider::Mcs->value)->where('is_active', true)->get();
    }

    public static function client(SupplierConnection $c): McsClient
    {
        return new McsClient($c);
    }

    protected static function markup(SupplierConnection $c): float
    {
        return (float) (($c->settings['markup_percent'] ?? 0) ?: 0);
    }

    public static function test(SupplierConnection $c): array
    {
        try {
            $geo = self::client($c)->geography();
            $countries = is_array($geo) ? count(array_is_list($geo) ? $geo : array_keys($geo)) : 0;

            return ['ok' => true, 'message' => __('Connected to the old system. Countries with active locations: :n', ['n' => $countries])];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /* ---------------- catalog and linking ---------------- */

    /** @return array{hotels:int, packages:int, linked_accommodations:int, linked_tours:int} */
    public static function importCatalog(SupplierConnection $c): array
    {
        $api = self::client($c);
        $seen = [];
        $counts = ['hotels' => 0, 'packages' => 0];
        foreach (['hotel' => $api->hotels(), 'package' => $api->packages()] as $kind => $rows) {
            foreach ($rows as $r) {
                $code = (string) ($r['id'] ?? $r['Id'] ?? '');
                if ($code === '') {
                    continue;
                }
                $rooms = [];
                foreach ((array) ($r['rooms'] ?? $r['accommodations'] ?? []) as $room) {
                    if (! is_array($room) || ! isset($room['roomid'])) {
                        continue;
                    }
                    $rooms[] = ['code' => (string) $room['roomid'], 'category' => $room['category'] ?? null, 'room' => trim((string) ($room['room'] ?? '')),
                        'content' => trim((string) ($room['content'] ?? '')), 'service' => trim((string) ($room['service'] ?? ''))];
                }
                SupplierProduct::withoutGlobalScopes()->updateOrCreate(
                    ['supplier_connection_id' => $c->id, 'kind' => $kind, 'code' => $code],
                    ['agency_id' => $c->agency_id, 'name' => trim(strip_tags((string) ($r[$kind] ?? $r['name'] ?? $r['hotel'] ?? $r['package'] ?? ('#'.$code)))),
                        'country' => $r['country'] ?? null, 'region' => $r['region'] ?? null, 'city' => $r['city'] ?? null,
                        'rooms' => $rooms, 'is_active' => true],
                );
                $seen[$kind][] = $code;
                $counts[$kind === 'hotel' ? 'hotels' : 'packages']++;
            }
        }
        // what is no longer active there
        foreach (['hotel', 'package'] as $kind) {
            SupplierProduct::withoutGlobalScopes()->where('supplier_connection_id', $c->id)->where('kind', $kind)
                ->whereNotIn('code', $seen[$kind] ?? ['-'])->update(['is_active' => false]);
        }
        $c->forceFill(['last_sync_at' => now()])->saveQuietly();

        return $counts + self::autoLink($c);
    }

    /** Lower case, Latin letters and digits only (Скопје / Skopje → skopje) */
    public static function key(?string $s): string
    {
        $s = Str::lower(strip_tags((string) $s));
        $s = strtr($s, ['ѓ' => 'gj', 'ќ' => 'kj', 'љ' => 'lj', 'њ' => 'nj', 'џ' => 'dz', 'ѕ' => 'dz', 'ј' => 'j', 'ж' => 'zh', 'ч' => 'ch', 'ш' => 'sh', 'ц' => 'c']);
        $s = Str::ascii($s);

        return preg_replace('/[^a-z0-9]/', '', $s);
    }

    /** Links our apartments and tours to the old ones with the same name (only those not linked yet) */
    public static function autoLink(SupplierConnection $c): array
    {
        $out = ['linked_accommodations' => 0, 'linked_tours' => 0];
        $products = SupplierProduct::withoutGlobalScopes()->where('supplier_connection_id', $c->id)->where('is_active', true)->get();
        $find = function (string $kind, string $name) use ($products): ?SupplierProduct {
            $k = self::key($name);
            if (strlen($k) < 4) {
                return null;
            }
            $same = $products->where('kind', $kind)->filter(fn ($p) => self::key($p->name) === $k);
            if ($same->count() === 1) {
                return $same->first();
            }
            $near = $products->where('kind', $kind)->filter(function ($p) use ($k) {
                $pk = self::key($p->name);

                return strlen($pk) >= 6 && (str_contains($pk, $k) || str_contains($k, $pk));
            });

            return $near->count() === 1 ? $near->first() : null;
        };

        $accs = Accommodation::withoutGlobalScopes()->where('agency_id', $c->agency_id)
            ->where(fn ($q) => $q->whereNull('supplier_connection_id')->orWhere('supplier_connection_id', $c->id))
            ->where(fn ($q) => $q->whereNull('provider_code')->orWhere('provider_code', ''))->with('rooms')->get();
        foreach ($accs as $a) {
            $p = $find('hotel', (string) $a->name);
            if (! $p) {
                continue;
            }
            $a->forceFill(['supplier_connection_id' => $c->id, 'provider_code' => $p->code, 'use_supplier_availability' => false])->save();
            self::linkRooms($a, $p);
            $out['linked_accommodations']++;
        }

        $tours = Tour::withoutGlobalScopes()->where('agency_id', $c->agency_id)
            ->where(fn ($q) => $q->whereNull('provider_code')->orWhere('provider_code', ''))->get();
        foreach ($tours as $t) {
            $p = $find('package', (string) $t->tr('title', 'mk'));
            if (! $p) {
                continue;
            }
            $rooms = (array) $p->rooms;
            $t->forceFill(['supplier_connection_id' => $c->id, 'provider_code' => $p->code, 'provider_room_code' => count($rooms) === 1 ? $rooms[0]['code'] : null])->save();
            $out['linked_tours']++;
        }

        return $out;
    }

    /** Rooms: the only room, or the same room name */
    public static function linkRooms(Accommodation $a, SupplierProduct $p): void
    {
        $rooms = collect((array) $p->rooms);
        $ours = $a->rooms->filter(fn ($r) => blank($r->provider_code));
        if ($rooms->count() === 1 && $a->rooms->count() === 1 && $ours->count() === 1) {
            $ours->first()->forceFill(['provider_code' => $rooms[0]['code']])->save();

            return;
        }
        foreach ($ours as $r) {
            $k = self::key((string) $r->tr('name', 'mk'));
            $hit = $rooms->filter(fn ($x) => $k !== '' && (self::key($x['room']) === $k || self::key($x['room'].$x['content']) === $k));
            if ($hit->count() === 1) {
                $r->forceFill(['provider_code' => $hit->first()['code']])->save();
            }
        }
    }

    protected static function product(SupplierConnection $c, string $kind, ?string $code): ?SupplierProduct
    {
        return $code ? SupplierProduct::withoutGlobalScopes()->where('supplier_connection_id', $c->id)->where('kind', $kind)->where('code', $code)->first() : null;
    }

    /** One of country / region / city is required by the searches */
    protected static function where(?SupplierProduct $p): array
    {
        if (! $p) {
            return [];
        }
        foreach (['city', 'region', 'country'] as $k) {
            if (filled($p->{$k})) {
                return [$k => $p->{$k}];
            }
        }

        return [];
    }

    /* ---------------- bookings → the old system ---------------- */

    /** The connection a booking goes to (linked tour or apartment), or null */
    public static function connectionFor(Booking $b): ?SupplierConnection
    {
        if ($b->supplier_connection_id) {
            return null;   // hotels from Hotelbeds… are booked there
        }
        $item = $b->bookable()->withoutGlobalScopes()->first();
        if (! ($item instanceof Tour || $item instanceof Accommodation) || ! $item->supplier_connection_id || blank($item->provider_code)) {
            return null;
        }
        $c = SupplierConnection::withoutGlobalScopes()->find($item->supplier_connection_id);
        if (! $c || ! $c->is_active || $c->provider !== SupplierProvider::Mcs || ($c->settings['push_bookings'] ?? true) === false) {
            return null;
        }

        return $c;
    }

    /** Right after the request: a new booking goes to the old system at once */
    public static function queue(Booking $b): void
    {
        $id = $b->id;
        app()->terminating(function () use ($id) {
            $b = Booking::withoutGlobalScopes()->find($id);
            if ($b && ! $b->legacy_ref && self::connectionFor($b)) {
                self::push($b);
            }
        });
    }

    /** @return array{ok: bool, message: string} */
    public static function push(Booking $b): array
    {
        $c = self::connectionFor($b);
        if (! $c) {
            return ['ok' => false, 'message' => __('This offer is not linked to the old system.')];
        }
        if ($b->legacy_ref) {
            return ['ok' => true, 'message' => __('Already in the old system: :r', ['r' => $b->legacy_ref])];
        }
        try {
            $params = self::bookParams($b, $c);
            $ref = self::client($c)->book($params);
            if ($ref === '' || strcasecmp($ref, 'NotAvailable') === 0) {
                $b->forceFill(['legacy_status' => 'not_available', 'legacy_error' => __('The old system answered "NotAvailable" – no free place for these dates and price.'), 'legacy_sent_at' => now()])->saveQuietly();

                return ['ok' => false, 'message' => $b->legacy_error];
            }
            $b->forceFill(['legacy_status' => 'sent', 'legacy_ref' => mb_substr($ref, 0, 80), 'legacy_error' => null, 'legacy_sent_at' => now()])->saveQuietly();
            Log::info('MCS booking sent', ['booking' => $b->reference, 'ref' => $ref]);

            return ['ok' => true, 'message' => __('Booked in the old system: :r', ['r' => $ref])];
        } catch (Throwable $e) {
            report($e);
            $b->forceFill(['legacy_status' => 'failed', 'legacy_error' => mb_substr($e->getMessage(), 0, 1000), 'legacy_sent_at' => now()])->saveQuietly();

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /** Search the exact offer again (the price must be the one of the search), then the BookRequest fields */
    protected static function bookParams(Booking $b, SupplierConnection $c): array
    {
        $item = $b->bookable()->withoutGlobalScopes()->first();
        $ages = array_values(array_map('intval', array_slice((array) $b->children_ages, 0, max(0, (int) $b->children))));
        while (count($ages) < (int) $b->children) {
            $ages[] = 7;   // age not given
        }
        $adults = max(1, (int) $b->adults);
        $api = self::client($c);

        if ($item instanceof Accommodation) {
            if (! $b->date_from || ! $b->date_to) {
                throw new RuntimeException(__('The booking has no dates.'));
            }
            $p = self::product($c, 'hotel', $item->provider_code);
            $nights = max(1, (int) $b->date_from->diffInDays($b->date_to));
            $rows = $api->hotelSearch(['checkin' => $b->date_from->toDateString(), 'nights' => min(30, $nights), 'hotel' => $item->provider_code, 'adults' => $adults, 'children_ages' => $ages] + (self::where($p) ?: ['country' => $item->country]));
            $rows = array_values(array_filter($rows, fn ($r) => (string) ($r['id'] ?? '') === (string) $item->provider_code));
            $roomCode = $b->room?->provider_code;
            $row = self::pick($rows, 'roomid', $roomCode, __('the room (Rooms → code in the booking system)'));
            $params = ['booktype' => 'hotel', 'id' => $item->provider_code, 'roomid' => $row['roomid']];
        } elseif ($item instanceof Tour) {
            $dep = $b->departure()->withoutGlobalScopes()->first();
            $checkin = $dep?->start_date ?? $b->date_from;
            if (! $checkin) {
                throw new RuntimeException(__('The booking has no departure date.'));
            }
            $p = self::product($c, 'package', $item->provider_code);
            $rows = $api->packageSearch(['checkin' => Carbon::parse($checkin)->toDateString(), 'package' => $item->provider_code, 'adults' => $adults, 'children_ages' => $ages] + (self::where($p) ?: ['country' => $item->country]));
            $rows = array_values(array_filter($rows, fn ($r) => (string) ($r['id'] ?? '') === (string) $item->provider_code));
            $row = self::pick($rows, 'accommodationid', $b->room?->provider_code ?: $item->provider_room_code, __('the accommodation of the package (tour → Old system)'));
            $params = ['booktype' => 'package', 'id' => $item->provider_code, 'accommodationid' => $row['accommodationid']];
        } else {
            throw new RuntimeException(__('Only tours and apartments are sent to the old system.'));
        }

        $params += ['checkin' => $row['checkin'], 'checkout' => $row['checkout'], 'price' => $row['price'], 'currency' => $row['currency'] ?? 'EUR', 'adults' => $adults];
        if ($ages) {
            $params['childs'] = count($ages);
            foreach ($ages as $i => $a) {
                $params['child'.($i + 1)] = $a;
            }
        }
        // passengers: adults and children separately (names in Latin letters as in the passport)
        $list = $b->passengerList();
        $isChild = fn ($p) => in_array(strtoupper((string) ($p['category'] ?? '')), ['CHD', 'INF'], true) || in_array(strtolower((string) ($p['title'] ?? '')), ['chd', 'inf'], true);
        // only the name in one field (holder without passengers): split it
        $list = array_map(function ($p) {
            if (trim((string) ($p['last_name'] ?? '')) === '' && str_contains(trim((string) ($p['first_name'] ?? '')), ' ')) {
                [$p['first_name'], $p['last_name']] = self::split((string) $p['first_name']);
            }

            return $p;
        }, $list);
        $grown = array_values(array_filter($list, fn ($p) => ! $isChild($p)));
        $kids = array_values(array_filter($list, $isChild));
        // the website lists the adults first, then the children, without marking them
        if (! $kids && count($list) > $adults && $ages) {
            $grown = array_slice($list, 0, $adults);
            $kids = array_slice($list, $adults);
        }
        [$hf, $hl] = self::split((string) $b->customer_name);
        for ($i = 0; $i < $adults; $i++) {
            $p = $grown[$i] ?? null;
            $params['passenger'.($i + 1).'_name'] = $p ? trim((string) ($p['first_name'] ?? '')) : ($i === 0 ? $hf : 'TBA');
            $params['passenger'.($i + 1).'_surname'] = $p ? trim((string) ($p['last_name'] ?? '')) : $hl;
            $params['passenger'.($i + 1).'_birthdate'] = self::date($p['birth_date'] ?? null);
        }
        foreach ($ages as $i => $age) {
            $p = $kids[$i] ?? null;
            $params['child_passenger'.($i + 1).'_name'] = $p ? trim((string) ($p['first_name'] ?? '')) : 'TBA';
            $params['child_passenger'.($i + 1).'_surname'] = $p ? trim((string) ($p['last_name'] ?? '')) : $hl;
            $params['child_passenger'.($i + 1).'_birthdate'] = self::date($p['birth_date'] ?? null);
        }

        return $params;
    }

    /** The result for the linked room; without a link only when there is exactly one */
    protected static function pick(array $rows, string $field, ?string $code, string $what): array
    {
        if (! $rows) {
            throw new RuntimeException(__('The old system has no free place for these dates and passengers.'));
        }
        if (filled($code)) {
            $hit = array_values(array_filter($rows, fn ($r) => (string) ($r[$field] ?? '') === (string) $code));
            if (! $hit) {
                throw new RuntimeException(__('The old system has no free place in the linked room (#:c) for these dates.', ['c' => $code]));
            }

            return $hit[0];
        }
        $codes = array_unique(array_map(fn ($r) => (string) ($r[$field] ?? ''), $rows));
        if (count($codes) === 1) {
            return $rows[0];
        }
        throw new RuntimeException(__('Several rooms are free in the old system – link :what so the right one is booked.', ['what' => $what]));
    }

    protected static function split(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    protected static function date($v): string
    {
        try {
            return $v ? Carbon::parse($v)->toDateString() : '';
        } catch (Throwable) {
            return '';
        }
    }

    /* ---------------- dates and prices from the old system ---------------- */

    /** @return array{departures:int, sold_out:int, term_prices:int, stop_sale:int, calls:int, errors:array} */
    public static function syncPrices(SupplierConnection $c, ?int $daysAhead = null): array
    {
        $out = ['departures' => 0, 'sold_out' => 0, 'term_prices' => 0, 'stop_sale' => 0, 'calls' => 0, 'errors' => []];
        if (($c->settings['sync_prices'] ?? true) === false) {
            return $out;
        }
        $days = max(7, min(400, (int) ($daysAhead ?? ($c->settings['days_ahead'] ?? 240))));
        $api = self::client($c);
        $k = 1 + self::markup($c) / 100;

        // tours: one search per country and day finds every package leaving that day
        $tours = Tour::withoutGlobalScopes()->where('supplier_connection_id', $c->id)->where('sync_supplier_prices', true)
            ->whereNotNull('provider_code')->where('provider_code', '!=', '')->get();
        if ($tours->isNotEmpty()) {
            $products = SupplierProduct::withoutGlobalScopes()->where('supplier_connection_id', $c->id)->where('kind', 'package')
                ->whereIn('code', $tours->pluck('provider_code'))->get()->keyBy('code');
            $countries = $products->pluck('country')->filter()->unique()->values();
            $found = [];   // package => [checkin => [rows]]
            for ($d = 1; $d <= $days; $d++) {
                $date = now()->addDays($d)->toDateString();
                foreach ($countries as $country) {
                    try {
                        $out['calls']++;
                        foreach ($api->packageSearch(['checkin' => $date, 'country' => $country, 'adults' => 2]) as $r) {
                            $found[(string) ($r['id'] ?? '')][$r['checkin'] ?? $date][] = $r;
                        }
                    } catch (Throwable $e) {
                        $out['errors'][$e->getMessage()] = true;
                    }
                }
            }
            foreach ($tours as $t) {
                $byDate = $found[(string) $t->provider_code] ?? [];
                $seen = [];
                foreach ($byDate as $checkin => $rows) {
                    if ($t->provider_room_code) {
                        $rows = array_filter($rows, fn ($r) => (string) ($r['accommodationid'] ?? '') === (string) $t->provider_room_code);
                    }
                    if (! $rows) {
                        continue;
                    }
                    $min = collect($rows)->sortBy(fn ($r) => (float) $r['price'])->first();
                    $dep = TourDeparture::query()->firstOrNew(['tour_id' => $t->id, 'start_date' => $checkin]);
                    $dep->end_date = $min['checkout'] ?? $dep->end_date;
                    $dep->price_adult = round((float) $min['price'] / 2 * $k, 2);   // price for 2 adults → per person
                    if (in_array($dep->status instanceof DepartureStatus ? $dep->status->value : $dep->status, [null, '', 'sold_out'], true)) {
                        $dep->status = DepartureStatus::Available;
                    }
                    $dep->save();
                    $seen[] = $checkin;
                    $out['departures']++;
                }
                // future departures inside the window that the old system does not have any more
                $out['sold_out'] += TourDeparture::query()->where('tour_id', $t->id)
                    ->whereBetween('start_date', [now()->addDay()->toDateString(), now()->addDays($days)->toDateString()])
                    ->whereNotIn('start_date', $seen ?: ['1900-01-01'])->where('status', '!=', DepartureStatus::Cancelled->value)
                    ->update(['status' => DepartureStatus::SoldOut->value]);
            }
        }

        // apartments: every own term is asked with its dates; rooms not offered there → stop sale
        $accs = Accommodation::withoutGlobalScopes()->where('supplier_connection_id', $c->id)->where('sync_supplier_prices', true)
            ->whereNotNull('provider_code')->where('provider_code', '!=', '')->with('rooms')->get();
        foreach ($accs as $a) {
            $p = self::product($c, 'hotel', $a->provider_code);
            $rooms = $a->rooms->filter(fn ($r) => filled($r->provider_code));
            if ($rooms->isEmpty()) {
                continue;
            }
            TermPrice::$quiet = true;   // the accommodation is touched once below
            $terms = StayTerm::query()->where('accommodation_id', $a->id)->where('is_active', true)
                ->where('date_from', '>', now()->toDateString())->where('date_from', '<=', now()->addDays($days)->toDateString())->get();
            foreach ($terms as $term) {
                $nights = $term->nights();
                if ($nights < 1 || $nights > 30) {
                    continue;
                }
                try {
                    $out['calls']++;
                    $rows = $api->hotelSearch(['checkin' => $term->date_from->toDateString(), 'nights' => $nights, 'hotel' => $a->provider_code, 'adults' => 2] + (self::where($p) ?: ['country' => $a->country]));
                } catch (Throwable $e) {
                    $out['errors'][$e->getMessage()] = true;

                    continue;
                }
                $rows = collect($rows)->filter(fn ($r) => (string) ($r['id'] ?? '') === (string) $a->provider_code)->groupBy(fn ($r) => (string) ($r['roomid'] ?? ''));
                foreach ($rooms as $room) {
                    $hit = $rows->get((string) $room->provider_code)?->sortBy(fn ($r) => (float) $r['price'])->first();
                    $tp = TermPrice::query()->firstOrNew(['stay_term_id' => $term->id, 'accommodation_room_id' => $room->id]);
                    if ($hit) {
                        $tp->price = round((float) $hit['price'] * $k, 2);
                        $tp->stop_sale = false;
                        $out['term_prices']++;
                    } else {
                        if (! $tp->exists && $tp->price === null) {
                            continue;
                        }
                        $tp->stop_sale = true;
                        $out['stop_sale']++;
                    }
                    $tp->save();
                }
            }
            TermPrice::$quiet = false;
            if ($terms->isNotEmpty()) {
                $a->touch();
            }
        }
        $c->forceFill(['last_sync_at' => now()])->saveQuietly();
        $out['errors'] = array_keys($out['errors']);

        return $out;
    }
}
