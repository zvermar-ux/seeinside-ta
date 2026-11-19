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

    /** Close spelling for comparing names: Катизма / Kathisma → katisma, Армониа / Armonia → armonia */
    public static function sound(?string $s, bool $withPlace = false): string
    {
        // "Вила Армониа-Катизма,Лефкада" → without the place after the comma (unless asked)
        $k = self::key($withPlace ? (string) $s : preg_replace('/,.*$/u', '', (string) $s));
        $k = strtr($k, ['th' => 't', 'ph' => 'f', 'kh' => 'h', 'ch' => 'h', 'zh' => 'z', 'dz' => 'z', 'gj' => 'g', 'kj' => 'k', 'ou' => 'u', 'y' => 'i', 'w' => 'v', 'x' => 'ks', 'q' => 'k']);
        $k = str_replace('villa', 'vila', $k);

        return preg_replace('/(.)\1+/', '$1', $k);   // double letters
    }

    /** The product with the closest name (clearly better than the next one), or null */
    public static function closest(Collection $products, string $name): ?SupplierProduct
    {
        $keys = array_unique([self::sound($name), self::sound($name, true)]);
        if (strlen($keys[0]) < 4) {
            return null;
        }
        $scored = $products->map(function ($p) use ($keys) {
            $best = 0;
            foreach ($keys as $k) {
                foreach (array_unique([self::sound($p->name), self::sound($p->name, true)]) as $pk) {
                    similar_text($k, $pk, $pct);
                    if (strlen($pk) >= 6 && strlen($k) >= 6 && (str_contains($pk, $k) || str_contains($k, $pk))) {
                        $pct = max($pct, 92);
                    }
                    $best = max($best, $k === $pk ? 100 : $pct);
                }
            }

            return ['p' => $p, 'pct' => $best];
        })->sortByDesc('pct')->values();
        $best = $scored[0] ?? null;
        if (! $best || $best['pct'] < 82) {
            return null;
        }
        $next = $scored[1]['pct'] ?? 0;
        // the same object can come once per room: only different objects compete
        if ($next > $best['pct'] - 4 && ($scored[1]['p']->code ?? null) !== $best['p']->code) {
            return null;
        }

        return $best['p'];
    }

    /** "h:151" / "p:151" (apartment linked to a hotel or a package of the old system); a plain code = hotel */
    public static function ref(?string $code): array
    {
        if (preg_match('/^(h|p):(.+)$/', (string) $code, $m)) {
            return [$m[1] === 'p' ? 'package' : 'hotel', $m[2]];
        }

        return ['hotel', (string) $code];
    }

    public static function refKey(SupplierProduct $p): string
    {
        return ($p->kind === 'package' ? 'p:' : 'h:').$p->code;
    }

    /** Products for the apartment select: "p:151" => "Аранжман · Vila Armonia Kathisma · Agios Nikitas (#151)" */
    public static function accommodationOptions(?int $connectionId): array
    {
        if (! $connectionId) {
            return [];
        }

        return SupplierProduct::withoutGlobalScopes()->where('supplier_connection_id', $connectionId)->orderBy('name')->get()
            ->mapWithKeys(fn ($p) => [self::refKey($p) => ($p->kind === 'package' ? __('Package') : __('Hotel')).' · '.$p->label()])->all();
    }

    public static function accommodationProduct(Accommodation $a): ?SupplierProduct
    {
        if (! $a->supplier_connection_id || blank($a->provider_code)) {
            return null;
        }
        [$kind, $code] = self::ref($a->provider_code);

        return SupplierProduct::withoutGlobalScopes()->where('supplier_connection_id', $a->supplier_connection_id)->where('kind', $kind)->where('code', $code)->first();
    }

    /** Links our apartments and tours to the old ones with the same (or very close) name – only those not linked yet */
    public static function autoLink(SupplierConnection $c): array
    {
        $out = ['linked_accommodations' => 0, 'linked_tours' => 0, 'linked_rooms' => 0];
        $products = SupplierProduct::withoutGlobalScopes()->where('supplier_connection_id', $c->id)->where('is_active', true)->get();

        // apartments, villas, studios… (not hotels: their prices are not taken from the old system).
        // In the old system villas are often "packages" (fixed terms), so both kinds are compared.
        $accs = Accommodation::withoutGlobalScopes()->where('agency_id', $c->agency_id)->where('type', '!=', 'hotel')
            ->where(fn ($q) => $q->whereNull('supplier_connection_id')->orWhere('supplier_connection_id', $c->id))->with('rooms')->get();
        foreach ($accs as $a) {
            if (filled($a->provider_code) && $a->supplier_connection_id === $c->id) {
                $p = self::accommodationProduct($a);
                $out['linked_rooms'] += $p ? self::linkRooms($a, $p) : 0;   // already linked: only rooms still missing

                continue;
            }
            $p = self::closest($products, (string) $a->name);
            if (! $p) {
                continue;
            }
            $a->forceFill(['supplier_connection_id' => $c->id, 'provider_code' => self::refKey($p), 'use_supplier_availability' => false])->save();
            $out['linked_rooms'] += self::linkRooms($a, $p);
            $out['linked_accommodations']++;
        }

        $tours = Tour::withoutGlobalScopes()->where('agency_id', $c->agency_id)
            ->where(fn ($q) => $q->whereNull('provider_code')->orWhere('provider_code', ''))->get();
        $packages = $products->where('kind', 'package');
        foreach ($tours as $t) {
            $p = self::closest($packages, (string) $t->tr('title', 'mk'));
            if (! $p) {
                continue;
            }
            $rooms = (array) $p->rooms;
            $t->forceFill(['supplier_connection_id' => $c->id, 'provider_code' => $p->code, 'provider_room_code' => count($rooms) === 1 ? $rooms[0]['code'] : null])->save();
            $out['linked_tours']++;
        }

        return $out;
    }

    /** Persons of an old room name: "1/2+1 studio" → 3, "1/2+2" → 4, "1/2-3 room" → 3, "1/3" → 3 */
    public static function persons(?string $room): ?int
    {
        if (preg_match('#1\s*/\s*(\d+)\s*(?:([+\-])\s*(\d+))?#', (string) $room, $m)) {
            $base = (int) $m[1];
            $add = isset($m[3]) ? (int) $m[3] : 0;

            return ($m[2] ?? '') === '-' ? max($base, $add) : $base + $add;
        }

        return null;
    }

    /** Rooms: the only room, the same name, or the same number of persons (when that is unique). @return int newly linked */
    public static function linkRooms(Accommodation $a, SupplierProduct $p): int
    {
        $rooms = collect((array) $p->rooms);
        $a->loadMissing('rooms');
        $used = $a->rooms->pluck('provider_code')->filter()->map(fn ($x) => (string) $x)->all();
        $ours = $a->rooms->filter(fn ($r) => blank($r->provider_code));
        $n = 0;
        if ($rooms->count() === 1 && $a->rooms->count() === 1 && $ours->count() === 1) {
            $ours->first()->forceFill(['provider_code' => $rooms[0]['code']])->save();

            return 1;
        }
        foreach ($ours as $r) {
            $free = $rooms->reject(fn ($x) => in_array((string) $x['code'], $used, true));
            $k = self::key((string) $r->tr('name', 'mk'));
            $hit = $free->filter(fn ($x) => $k !== '' && (self::key($x['room']) === $k || self::key($x['room'].$x['content']) === $k));
            if ($hit->count() !== 1) {
                $cap = (int) $r->max_adults + (int) $r->max_children;
                $hit = $free->filter(fn ($x) => $cap > 0 && self::persons($x['room'].' '.$x['content']) === $cap);
            }
            if ($hit->count() === 1) {
                $r->forceFill(['provider_code' => (string) $hit->first()['code']])->save();
                $used[] = (string) $hit->first()['code'];
                $n++;
            }
        }

        return $n;
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
            [$kind, $code] = self::ref($item->provider_code);
            $p = self::product($c, $kind, $code);
            $roomCode = $b->room?->provider_code;
            if ($kind === 'package') {
                // villa sold in fixed terms (a "package" in the old system): the term starts on the check-in day
                $rows = $api->packageSearch(['checkin' => $b->date_from->toDateString(), 'package' => $code, 'room' => $roomCode, 'adults' => $adults, 'children_ages' => $ages] + (self::where($p) ?: ['country' => $item->country]));
                $rows = array_values(array_filter($rows, fn ($r) => (string) ($r['id'] ?? '') === (string) $code));
                // the answer has no room (accommodationid empty) although this room was asked: it is the answer for this room
                if ($roomCode && $rows && collect($rows)->every(fn ($r) => blank($r['accommodationid'] ?? null))) {
                    $rows = [['accommodationid' => $roomCode] + $rows[0]];
                    $rows[0]['accommodationid'] = $roomCode;
                }
                $row = self::pick($rows, 'accommodationid', $roomCode, __('the room (Rooms → room in the old Aries system)'));
                $params = ['booktype' => 'package', 'id' => $code, 'accommodationid' => $row['accommodationid']];
            } else {
                $nights = max(1, (int) $b->date_from->diffInDays($b->date_to));
                $rows = $api->hotelSearch(['checkin' => $b->date_from->toDateString(), 'nights' => min(30, $nights), 'hotel' => $code, 'adults' => $adults, 'children_ages' => $ages] + (self::where($p) ?: ['country' => $item->country]));
                $rows = array_values(array_filter($rows, fn ($r) => (string) ($r['id'] ?? '') === (string) $code));
                $row = self::pick($rows, 'roomid', $roomCode, __('the room (Rooms → room in the old Aries system)'));
                $params = ['booktype' => 'hotel', 'id' => $code, 'roomid' => $row['roomid']];
            }
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

    /** @return array{departures:int, sold_out:int, terms:int, term_prices:int, stop_sale:int, calls:int, errors:array} */
    public static function syncPrices(SupplierConnection $c, ?int $daysAhead = null): array
    {
        $out = ['departures' => 0, 'sold_out' => 0, 'terms' => 0, 'term_prices' => 0, 'stop_sale' => 0, 'calls' => 0, 'errors' => [], 'notes' => [], 'results' => 0];
        if (($c->settings['sync_prices'] ?? true) === false) {
            return $out;
        }
        $days = max(7, min(400, (int) ($daysAhead ?? ($c->settings['days_ahead'] ?? 365))));
        $api = self::client($c);
        $k = 1 + self::markup($c) / 100;

        $tours = Tour::withoutGlobalScopes()->where('supplier_connection_id', $c->id)->where('sync_supplier_prices', true)
            ->whereNotNull('provider_code')->where('provider_code', '!=', '')->get();
        $accs = Accommodation::withoutGlobalScopes()->where('supplier_connection_id', $c->id)->where('sync_supplier_prices', true)
            ->whereNotNull('provider_code')->where('provider_code', '!=', '')->with('rooms')->get();
        $termAccs = $accs->filter(fn ($a) => self::ref($a->provider_code)[0] === 'package');   // villas in fixed terms
        $nightAccs = $accs->reject(fn ($a) => self::ref($a->provider_code)[0] === 'package');

        // packages (tours and fixed-term villas): one search per country and day finds every package starting that day
        $codes = $tours->pluck('provider_code')->merge($termAccs->map(fn ($a) => self::ref($a->provider_code)[1]))->map(fn ($x) => (string) $x)->unique();
        $found = [];   // package => [checkin => [rows]]
        $months = array_map('intval', array_filter((array) ($c->settings['months'] ?? []))) ?: range(1, 12);   // none chosen = all
        if ($codes->isNotEmpty()) {
            // per package and day (the old server times out on whole-country searches), a few at the same time
            $products = SupplierProduct::withoutGlobalScopes()->where('supplier_connection_id', $c->id)->where('kind', 'package')
                ->whereIn('code', $codes)->get()->keyBy('code');
            $queries = [];
            foreach ($codes as $code) {
                $p = $products[$code] ?? null;
                for ($d = 1; $d <= $days; $d++) {
                    $day = now()->addDays($d);
                    if (! in_array((int) $day->month, $months, true)) {
                        continue;
                    }
                    $queries[] = ['checkin' => $day->toDateString(), 'package' => $code, 'adults' => 2] + (self::where($p) ?: ['country' => $p?->country]);
                }
            }
            $concurrency = max(1, min(8, (int) ($c->settings['concurrency'] ?? 1)));
            $timeout = max(20, min(300, (int) ($c->settings['timeout'] ?? 90)));
            $failedInRow = 0;
            $chunks = array_chunk($queries, $concurrency * 5, true);
            foreach ($chunks as $i => $chunk) {
                self::progress($c, __('searches :a of :b', ['a' => min(count($queries), ($i + 1) * $concurrency * 5), 'b' => count($queries)]));
                foreach ($api->packageSearchMany($chunk, $concurrency, $timeout) as $k => $rows) {
                    $out['calls']++;
                    if ($rows instanceof Throwable) {
                        $out['errors'][$rows->getMessage()] = true;
                        $failedInRow++;

                        continue;
                    }
                    $failedInRow = 0;
                    foreach ($rows as $r) {
                        $out['results']++;
                        $found[(string) ($r['id'] ?? $chunk[$k]['package'])][(string) ($r['checkin'] ?? $chunk[$k]['checkin'])][] = $r;
                    }
                }
                // the old server does not answer at all: stop instead of waiting for hours
                if ($failedInRow >= 15) {
                    $out['errors'][__('Stopped: the old system did not answer 15 searches in a row.')] = true;

                    break;
                }
            }
            // only results; nothing is marked as sold out when the searches failed
            if ($failedInRow >= 15 || ($out['results'] === 0 && $out['errors'])) {
                $out['errors'] = array_keys($out['errors']);
                $c->refresh();
                $c->forceFill(['settings' => array_merge((array) $c->settings, ['last_sync_result' => $out + ['at' => now()->toIso8601String(), 'days' => $days]])])->saveQuietly();
                \Illuminate\Support\Facades\Cache::forget('mcs-sync-progress-'.$c->id);

                return $out;
            }
            self::progress($c, __('saving terms and prices'));
        }
        $window = [now()->addDay()->toDateString(), now()->addDays($days)->toDateString()];

        // tours: departures and the price per person (price for 2 adults / 2)
        foreach ($tours as $t) {
            $seen = [];
            foreach ($found[(string) $t->provider_code] ?? [] as $checkin => $rows) {
                if ($t->provider_room_code) {
                    $rows = array_filter($rows, fn ($r) => (string) ($r['accommodationid'] ?? '') === (string) $t->provider_room_code);
                }
                if (! $rows) {
                    continue;
                }
                $min = collect($rows)->sortBy(fn ($r) => (float) $r['price'])->first();
                $dep = TourDeparture::query()->firstOrNew(['tour_id' => $t->id, 'start_date' => $checkin]);
                $dep->end_date = $min['checkout'] ?? $dep->end_date;
                $dep->price_adult = round((float) $min['price'] / 2 * $k, 2);
                if (in_array($dep->status instanceof DepartureStatus ? $dep->status->value : $dep->status, [null, '', 'sold_out'], true)) {
                    $dep->status = DepartureStatus::Available;
                }
                $dep->save();
                $seen[] = $checkin;
                $out['departures']++;
            }
            // future departures inside the window that the old system does not offer any more
            $ids = TourDeparture::query()->where('tour_id', $t->id)->whereBetween('start_date', $window)
                ->whereNotIn('start_date', $seen ?: ['1900-01-01'])->where('status', '!=', DepartureStatus::Cancelled->value)
                ->get(['id', 'start_date'])->filter(fn ($x) => in_array((int) $x->start_date->month, $months, true))->pluck('id');
            $out['sold_out'] += $ids->isEmpty() ? 0 : TourDeparture::query()->whereIn('id', $ids)->update(['status' => DepartureStatus::SoldOut->value]);
        }

        // villas in fixed terms: the terms of the old system (check-in → check-out) and the price of every room for each term
        foreach ($termAccs as $a) {
            $code = self::ref($a->provider_code)[1];
            $rooms = $a->rooms->filter(fn ($r) => filled($r->provider_code));
            $byDate = $found[$code] ?? [];
            if ($rooms->isEmpty()) {
                // rooms not linked yet: link them now by the rooms the old system offers (when it is clear)
                $p = self::product($c, 'package', $code);
                if ($p) {
                    self::linkRooms($a, $p);
                    $a->load('rooms');
                    $rooms = $a->rooms->filter(fn ($r) => filled($r->provider_code));
                }
            }
            if ($rooms->isEmpty()) {
                $out['notes'][] = $a->name.': '.__('rooms not linked (Rooms → Room in the old Aries system)');

                continue;
            }
            if (! $byDate) {
                $out['notes'][] = $a->name.': '.__('no terms found in the old system for Id :c in the next :d days', ['c' => $code, 'd' => $days]);

                continue;
            }
            $byDate = self::perRoom($api, $c, $code, $byDate, $rooms, $out);
            TermPrice::$quiet = true;
            $seenTerms = [];
            foreach ($byDate as $checkin => $rows) {
                foreach (collect($rows)->groupBy(fn ($r) => (string) ($r['checkout'] ?? '')) as $checkout => $termRows) {
                    if ($checkout === '' || $checkout <= $checkin) {
                        continue;
                    }
                    $term = StayTerm::query()->firstOrCreate(
                        ['accommodation_id' => $a->id, 'date_from' => $checkin, 'date_to' => $checkout],
                        ['is_active' => true],
                    );
                    if ($term->wasRecentlyCreated) {
                        $out['terms']++;
                    } elseif (! $term->is_active) {
                        $term->forceFill(['is_active' => true])->save();
                    }
                    $seenTerms[] = $term->id;
                    $byRoom = $termRows->groupBy(fn ($r) => (string) ($r['accommodationid'] ?? ''));
                    foreach ($rooms as $room) {
                        $hit = $byRoom->get((string) $room->provider_code)?->sortBy(fn ($r) => (float) $r['price'])->first();
                        $tp = TermPrice::query()->firstOrNew(['stay_term_id' => $term->id, 'accommodation_room_id' => $room->id]);
                        if ($hit) {
                            $tp->price = round((float) $hit['price'] * $k, 2);
                            $tp->stop_sale = false;
                            $out['term_prices']++;
                        } else {
                            if (! $tp->exists) {
                                continue;
                            }
                            $tp->stop_sale = true;   // this room is full in the old system for this term
                            $out['stop_sale']++;
                        }
                        $tp->save();
                    }
                }
            }
            // our future terms inside the window that the old system does not offer any more: every room stop sale
            $gone = StayTerm::query()->where('accommodation_id', $a->id)->whereBetween('date_from', $window)->whereNotIn('id', $seenTerms ?: [0])
                ->get(['id', 'date_from'])->filter(fn ($x) => in_array((int) $x->date_from->month, $months, true))->pluck('id');
            if ($gone->isNotEmpty()) {
                $out['stop_sale'] += TermPrice::query()->whereIn('stay_term_id', $gone)->where('stop_sale', false)->update(['stop_sale' => true]);
            }
            TermPrice::$quiet = false;
            if (! $a->book_by_terms) {
                $a->forceFill(['book_by_terms' => true])->save();   // sold only in these terms, like in the old system
            }
            $a->touch();
        }

        // apartments linked to a "hotel" of the old system: every own term is asked with its dates
        foreach ($nightAccs as $a) {
            [, $code] = self::ref($a->provider_code);
            $p = self::product($c, 'hotel', $code);
            $rooms = $a->rooms->filter(fn ($r) => filled($r->provider_code));
            if ($rooms->isEmpty()) {
                continue;
            }
            TermPrice::$quiet = true;
            $terms = StayTerm::query()->where('accommodation_id', $a->id)->where('is_active', true)
                ->where('date_from', '>', now()->toDateString())->where('date_from', '<=', $window[1])->get();
            foreach ($terms as $term) {
                $nights = $term->nights();
                if ($nights < 1 || $nights > 30) {
                    continue;
                }
                try {
                    $out['calls']++;
                    $rows = $api->hotelSearch(['checkin' => $term->date_from->toDateString(), 'nights' => $nights, 'hotel' => $code, 'adults' => 2] + (self::where($p) ?: ['country' => $a->country]));
                } catch (Throwable $e) {
                    $out['errors'][$e->getMessage()] = true;

                    continue;
                }
                $rows = collect($rows)->filter(fn ($r) => (string) ($r['id'] ?? '') === (string) $code)->groupBy(fn ($r) => (string) ($r['roomid'] ?? ''));
                foreach ($rooms as $room) {
                    $hit = $rows->get((string) $room->provider_code)?->sortBy(fn ($r) => (float) $r['price'])->first();
                    $tp = TermPrice::query()->firstOrNew(['stay_term_id' => $term->id, 'accommodation_room_id' => $room->id]);
                    if ($hit) {
                        $tp->price = round((float) $hit['price'] * $k, 2);
                        $tp->stop_sale = false;
                        $out['term_prices']++;
                    } else {
                        if (! $tp->exists) {
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
        $out['errors'] = array_keys($out['errors']);
        Log::info('MCS price sync', $out);
        // the result stays on the connection (shown in Booking systems)
        $c->refresh();
        $c->forceFill(['last_sync_at' => now(), 'settings' => array_merge((array) $c->settings, ['last_sync_result' => $out + ['at' => now()->toIso8601String(), 'days' => $days]])])->saveQuietly();
        \Illuminate\Support\Facades\Cache::forget('mcs-sync-progress-'.$c->id);

        return $out;
    }

    /** What the background sync is doing now (shown in Booking systems) */
    public static function progress(SupplierConnection $c, ?string $text = null): ?string
    {
        $key = 'mcs-sync-progress-'.$c->id;
        if ($text !== null) {
            \Illuminate\Support\Facades\Cache::put($key, $text, 1800);
        }

        return \Illuminate\Support\Facades\Cache::get($key);
    }

    /** The button asks for a sync: the scheduler runs it within a minute in the background (no time limit) */
    public static function requestSync(SupplierConnection $c, int $days): void
    {
        $c->forceFill(['settings' => array_merge((array) $c->settings, ['sync_request' => ['days' => $days, 'at' => now()->toIso8601String()]])])->saveQuietly();
        \Illuminate\Support\Facades\Cache::put('mcs-sync-progress-'.$c->id, __('waiting to start (within a minute)…'), 1800);
    }

    /** Runs one sync of a connection at a time */
    public static function runLocked(SupplierConnection $c, ?int $days = null): ?array
    {
        $lock = \Illuminate\Support\Facades\Cache::lock('mcs-sync-'.$c->id, 4 * 3600);
        if (! $lock->get()) {
            return null;   // already running
        }
        try {
            @set_time_limit(0);
            self::progress($c, __('started'));

            return self::syncPrices($c, $days);
        } catch (Throwable $e) {
            report($e);
            $c->refresh();
            $c->forceFill(['settings' => array_merge((array) $c->settings, ['last_sync_result' => ['errors' => [$e->getMessage()], 'at' => now()->toIso8601String()]])])->saveQuietly();
            \Illuminate\Support\Facades\Cache::forget('mcs-sync-progress-'.$c->id);

            return null;
        } finally {
            $lock->release();
        }
    }

    /**
     * The package search of the old system can answer without the room (accommodationid empty, one row per room,
     * the same price). Then every linked room is asked on its own for each term, so each room gets its own price.
     */
    protected static function perRoom(McsClient $api, SupplierConnection $c, string $code, array $byDate, Collection $rooms, array &$out): array
    {
        $known = fn ($rows) => collect($rows)->contains(fn ($r) => filled($r['accommodationid'] ?? null));
        if (collect($byDate)->every(fn ($rows) => $known($rows))) {
            return $byDate;
        }
        $p = self::product($c, 'package', $code);
        $where = self::where($p) ?: ['country' => $p?->country];
        $queries = [];
        foreach ($byDate as $checkin => $rows) {
            if ($known($rows)) {
                continue;
            }
            foreach ($rooms as $room) {
                $queries[$checkin.'|'.$room->provider_code] = ['checkin' => $checkin, 'package' => $code, 'room' => (string) $room->provider_code, 'adults' => 2] + $where;
            }
        }
        $prices = [];
        foreach ($api->packageSearchMany($queries, max(1, (int) ($c->settings['concurrency'] ?? 1))) as $key => $rows) {
            $out['calls']++;
            [$checkin, $room] = explode('|', $key, 2);
            if ($rows instanceof Throwable || ! $rows) {
                continue;   // this room is not free in this term
            }
            $rows = array_values(array_filter($rows, fn ($r) => (string) ($r['id'] ?? $code) === (string) $code));
            // the answer for this room: its own row, or (when the API still gives no room) the first one
            $mine = array_values(array_filter($rows, fn ($r) => (string) ($r['accommodationid'] ?? '') === $room)) ?: $rows;
            if ($mine) {
                $byDate[$checkin] = array_values(array_filter($byDate[$checkin], fn ($r) => filled($r['accommodationid'] ?? null)));
                $prices[$checkin][$room] = (float) $mine[0]['price'];
                $byDate[$checkin][] = ['id' => $code, 'accommodationid' => $room] + $mine[0];
                $byDate[$checkin][count($byDate[$checkin]) - 1]['accommodationid'] = $room;
            }
        }
        // the old system ignored the room: the same price for every room → say so
        $same = collect($prices)->filter(fn ($p) => count($p) > 1 && count(array_unique($p)) === 1)->count();
        if ($same && $same === collect($prices)->filter(fn ($p) => count($p) > 1)->count()) {
            $out['notes'][] = ($p?->name ?? '#'.$code).': '.__('the old system gives the same price for every room (it does not answer per room) – ask MCS to fill "accommodationid" in PackageSearch.');
        }

        return $byDate;
    }

    /** Raw answer of a search, to see what the old system gives for one Id and date */
    public static function probe(SupplierConnection $c, string $kind, string $code, string $date, int $nights = 7): array
    {
        $p = self::product($c, $kind, $code);
        $where = self::where($p) ?: ['country' => $p?->country];
        $api = self::client($c);
        $rows = $kind === 'package'
            ? $api->packageSearch(['checkin' => $date, 'package' => $code, 'adults' => 2] + $where)
            : $api->hotelSearch(['checkin' => $date, 'nights' => $nights, 'hotel' => $code, 'adults' => 2] + $where);

        $perRoom = [];
        if ($kind === 'package') {
            // each room on its own: does the old system give a price per room?
            foreach ((array) ($p?->rooms ?? []) as $room) {
                try {
                    $rr = $api->packageSearch(['checkin' => $date, 'package' => $code, 'room' => (string) $room['code'], 'adults' => 2] + $where);
                    $perRoom[] = ['room' => $room['code'].' · '.$room['room'], 'offers' => count($rr), 'prices' => array_values(array_unique(array_map(fn ($r) => $r['price'] ?? null, $rr))),
                        'accommodationid' => array_values(array_unique(array_map(fn ($r) => $r['accommodationid'] ?? null, $rr)))];
                } catch (Throwable $e) {
                    $perRoom[] = ['room' => $room['code'], 'error' => $e->getMessage()];
                }
            }
        }
        $prices = array_filter(array_map(fn ($x) => $x['prices'][0] ?? null, $perRoom), fn ($v) => $v !== null);

        return ['per_room' => $perRoom, 'per_room_prices_differ' => count(array_unique($prices)) > 1,
            'rows' => $rows, 'where' => $where, 'product' => $p?->only(['kind', 'code', 'name', 'country', 'region', 'city', 'rooms'])];
    }
}
