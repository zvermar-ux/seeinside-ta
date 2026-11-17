<?php

namespace App\Services\Hotels\Hotelbeds;

use App\Models\SupplierConnection;
use App\Services\Hotels\HotelSupplier;
use Illuminate\Support\Facades\Cache;

class HotelbedsDriver implements HotelSupplier
{
    public const PHOTO_URL = 'https://photos.hotelbeds.com/giata/';

    protected function client(SupplierConnection $c): HotelbedsClient
    {
        return new HotelbedsClient($c);
    }

    public function test(SupplierConnection $c): array
    {
        try {
            $r = $this->client($c)->request('GET', '/hotel-api/1.0/status', timeout: 15);

            return ['ok' => true, 'message' => __('Connected (:env).', ['env' => $this->client($c)->baseUrl()]).' '.($r['status'] ?? '')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /* ---------------- content (kept in the app) ---------------- */

    public function destinations(SupplierConnection $c, array $countryCodes): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_filter(array_map('strtoupper', $countryCodes))), 10) as $chunk) {
            for ($from = 1; $from < 20000; $from += 1000) {
                $r = $this->client($c)->request('GET', '/hotel-content-api/1.0/locations/destinations', [
                    'fields' => 'all', 'countryCodes' => implode(',', $chunk), 'language' => 'ENG', 'from' => $from, 'to' => $from + 999, 'useSecondaryLanguage' => 'false',
                ]);
                foreach ($r['destinations'] ?? [] as $d) {
                    $out[] = [
                        'code' => (string) $d['code'],
                        'name' => self::txt($d['name'] ?? $d['code']),
                        'country_code' => $d['countryCode'] ?? null,
                        'zones' => array_values(array_map(fn ($z) => ['code' => $z['zoneCode'] ?? null, 'name' => self::txt($z['name'] ?? '')], $d['zones'] ?? [])),
                    ];
                }
                if (($r['total'] ?? 0) < $from + 1000) {
                    break;
                }
            }
        }

        return $out;
    }

    /** Facility names by "group-code" (asked once a month) */
    protected function facilityNames(SupplierConnection $c): array
    {
        return Cache::remember('hb:facilities:'.$c->id, now()->addDays(30), function () use ($c) {
            $names = [];
            for ($from = 1; $from < 5000; $from += 1000) {
                $r = $this->client($c)->request('GET', '/hotel-content-api/1.0/types/facilities', ['fields' => 'all', 'language' => 'ENG', 'from' => $from, 'to' => $from + 999]);
                foreach ($r['facilities'] ?? [] as $f) {
                    $names[($f['facilityGroupCode'] ?? '').'-'.($f['code'] ?? '')] = self::txt($f['description'] ?? '');
                }
                if (($r['total'] ?? 0) < $from + 1000) {
                    break;
                }
            }

            return $names;
        });
    }

    public function hotels(SupplierConnection $c, string $destinationCode, int $from, int $to): array
    {
        $r = $this->client($c)->request('GET', '/hotel-content-api/1.0/hotels', [
            'fields' => 'all', 'destinationCode' => $destinationCode, 'language' => 'ENG', 'from' => $from, 'to' => $to, 'useSecondaryLanguage' => 'false',
        ], timeout: 90);
        $fac = $this->facilityNames($c);
        $hotels = [];
        foreach ($r['hotels'] ?? [] as $h) {
            $images = collect($h['images'] ?? [])
                ->sortBy(fn ($i) => [($i['imageTypeCode'] ?? '') === 'GEN' ? 0 : 1, $i['visualOrder'] ?? $i['order'] ?? 99])
                ->map(fn ($i) => ['path' => $i['path'], 'type' => $i['imageTypeCode'] ?? null, 'room' => $i['roomCode'] ?? null])
                ->values()->take(40)->all();
            $facilities = collect($h['facilities'] ?? [])
                ->filter(fn ($f) => ($f['indLogic'] ?? true) !== false && ($f['indYesOrNo'] ?? true) !== false)
                ->map(fn ($f) => ['group' => $f['facilityGroupCode'] ?? null, 'code' => $f['facilityCode'] ?? null,
                    'name' => $fac[($f['facilityGroupCode'] ?? '').'-'.($f['facilityCode'] ?? '')] ?? null, 'fee' => (bool) ($f['indFee'] ?? false)])
                ->filter(fn ($f) => filled($f['name']))->unique('name')->values()->all();
            $hotels[] = [
                'code' => (string) $h['code'],
                'name' => self::txt($h['name'] ?? ''),
                'description' => self::txt($h['description'] ?? ''),
                'destination_code' => $h['destinationCode'] ?? $destinationCode,
                'zone_code' => $h['zoneCode'] ?? null,
                'country_code' => $h['countryCode'] ?? null,
                'stars' => self::stars($h['categoryCode'] ?? null),
                'category_code' => $h['categoryCode'] ?? null,
                'accommodation_type' => $h['accommodationTypeCode'] ?? null,
                'latitude' => isset($h['coordinates']['latitude']) ? (float) $h['coordinates']['latitude'] : null,
                'longitude' => isset($h['coordinates']['longitude']) ? (float) $h['coordinates']['longitude'] : null,
                'address' => self::txt($h['address'] ?? ''),
                'city' => self::txt($h['city'] ?? ''),
                'postal_code' => $h['postalCode'] ?? null,
                'email' => $h['email'] ?? null,
                'phone' => $h['phones'][0]['phoneNumber'] ?? null,
                'web' => $h['web'] ?? null,
                'ranking' => (int) ($h['ranking'] ?? 0),
                'boards' => array_values((array) ($h['boardCodes'] ?? [])),
                'rooms' => collect($h['rooms'] ?? [])->map(fn ($rm) => [
                    'code' => $rm['roomCode'] ?? null, 'name' => self::txt($rm['description'] ?? ''), 'max_pax' => $rm['maxPax'] ?? null,
                    'max_adults' => $rm['maxAdults'] ?? null, 'max_children' => $rm['maxChildren'] ?? null,
                ])->values()->all(),
                'images' => $images,
                'facilities' => $facilities,
            ];
        }

        return ['hotels' => $hotels, 'total' => (int) ($r['total'] ?? count($hotels))];
    }

    /* ---------------- live: availability, check rate, booking ---------------- */

    protected function occupancies(array $rooms): array
    {
        // identical rooms are asked together ("2 rooms for 2 adults")
        $groups = [];
        foreach ($rooms as $r) {
            $ages = array_values(array_map('intval', (array) ($r['children_ages'] ?? [])));
            sort($ages);
            $k = (int) $r['adults'].'|'.implode(',', $ages);
            $groups[$k] ??= ['rooms' => 0, 'adults' => (int) $r['adults'], 'children' => count($ages), 'ages' => $ages];
            $groups[$k]['rooms']++;
        }

        return array_values(array_map(function ($g) {
            $paxes = [];
            foreach ($g['ages'] as $age) {
                $paxes[] = ['type' => 'CH', 'age' => $age];
            }
            $o = ['rooms' => $g['rooms'], 'adults' => $g['adults'], 'children' => $g['children']];

            return $paxes ? $o + ['paxes' => $paxes] : $o;
        }, $groups));
    }

    public function search(SupplierConnection $c, array $q): array
    {
        $body = [
            'stay' => ['checkIn' => $q['from'], 'checkOut' => $q['to']],
            'occupancies' => $this->occupancies($q['rooms']),
            'filter' => array_filter([
                'maxHotels' => (int) ($q['max_hotels'] ?? 250),
                'maxRatesPerRoom' => 6,
                'minCategory' => ! empty($q['min_stars']) ? (int) $q['min_stars'] : null,
            ]),
        ];
        if (! empty($q['hotels'])) {
            $body['hotels'] = ['hotel' => array_values(array_map('intval', $q['hotels']))];
        } else {
            $body['destination'] = ['code' => $q['destination']];
        }
        $r = $this->client($c)->request('POST', '/hotel-api/1.0/hotels', body: $body, timeout: 60);

        return array_map(fn ($h) => $this->hotel($h), $r['hotels']['hotels'] ?? []);
    }

    public function checkRate(SupplierConnection $c, string $rateKey): array
    {
        $r = $this->client($c)->request('POST', '/hotel-api/1.0/checkrates', body: ['rooms' => [['rateKey' => $rateKey]]]);
        $h = $r['hotel'] ?? null;
        if (! $h) {
            throw new \RuntimeException(__('This price is no longer available. Search again.'));
        }

        return $this->hotel($h);
    }

    public function book(SupplierConnection $c, array $data): array
    {
        $paxes = [];
        foreach ($data['guests'] as $g) {
            $paxes[] = array_filter([
                'roomId' => (int) ($g['room'] ?? 1),
                'type' => ($g['type'] ?? 'AD') === 'CH' ? 'CH' : 'AD',
                'age' => isset($g['age']) && ($g['type'] ?? 'AD') === 'CH' ? (int) $g['age'] : null,
                'name' => self::latin($g['first_name']),
                'surname' => self::latin($g['last_name']),
            ], fn ($v) => $v !== null);
        }
        $r = $this->client($c)->request('POST', '/hotel-api/1.0/bookings', body: [
            'holder' => ['name' => self::latin($data['holder_first']), 'surname' => self::latin($data['holder_last'])],
            'rooms' => [['rateKey' => $data['rate_key'], 'paxes' => $paxes]],
            'clientReference' => mb_substr($data['reference'], 0, 20),
            'remark' => mb_substr((string) ($data['remark'] ?? ''), 0, 2000),
            'tolerance' => (float) ($data['tolerance'] ?? 2),
        ], timeout: 90);
        $b = $r['booking'] ?? [];

        return ['reference' => $b['reference'] ?? null, 'status' => $b['status'] ?? null, 'net' => isset($b['totalNet']) ? (float) $b['totalNet'] : null, 'currency' => $b['currency'] ?? null, 'raw' => $b];
    }

    public function cancel(SupplierConnection $c, string $reference, bool $simulate = false): array
    {
        $r = $this->client($c)->request('DELETE', '/hotel-api/1.0/bookings/'.rawurlencode($reference), ['cancellationFlag' => $simulate ? 'SIMULATION' : 'CANCELLATION']);
        $b = $r['booking'] ?? [];

        return ['status' => $b['status'] ?? null, 'fee' => isset($b['hotel']['totalNet']) ? (float) $b['hotel']['totalNet'] : (isset($b['totalNet']) ? (float) $b['totalNet'] : null), 'raw' => $b];
    }

    /* ---------------- helpers ---------------- */

    protected function hotel(array $h): array
    {
        return [
            'code' => (string) $h['code'],
            'name' => $h['name'] ?? '',
            'stars' => self::stars($h['categoryCode'] ?? null),
            'destination_code' => $h['destinationCode'] ?? null,
            'destination' => $h['destinationName'] ?? null,
            'zone' => $h['zoneName'] ?? null,
            'latitude' => isset($h['latitude']) ? (float) $h['latitude'] : null,
            'longitude' => isset($h['longitude']) ? (float) $h['longitude'] : null,
            'currency' => $h['currency'] ?? 'EUR',
            'rooms' => array_map(fn ($rm) => [
                'code' => $rm['code'] ?? null,
                'name' => $rm['name'] ?? '',
                'rates' => array_map(fn ($rt) => [
                    'key' => $rt['rateKey'],
                    'net' => (float) ($rt['net'] ?? 0),
                    'selling' => isset($rt['sellingRate']) ? (float) $rt['sellingRate'] : null,
                    'mandatory' => (bool) ($rt['hotelMandatory'] ?? false),
                    'recheck' => ($rt['rateType'] ?? 'BOOKABLE') === 'RECHECK',
                    'board_code' => $rt['boardCode'] ?? null,
                    'board' => $rt['boardName'] ?? null,
                    'refundable' => ($rt['rateClass'] ?? 'NOR') !== 'NRF',
                    'cancellation' => array_map(fn ($p) => ['amount' => (float) ($p['amount'] ?? 0), 'from' => $p['from'] ?? null], $rt['cancellationPolicies'] ?? []),
                    'allotment' => isset($rt['allotment']) ? (int) $rt['allotment'] : null,
                    'rooms' => (int) ($rt['rooms'] ?? 1),
                    'adults' => (int) ($rt['adults'] ?? 0),
                    'children' => (int) ($rt['children'] ?? 0),
                    'pay_at_hotel' => ($rt['paymentType'] ?? 'AT_WEB') === 'AT_HOTEL',
                    'comments' => trim((string) ($rt['rateComments'] ?? '')),
                    'offers' => array_values(array_filter(array_map(fn ($o) => $o['name'] ?? null, $rt['offers'] ?? []))),
                    'taxes_extra' => collect($rt['taxes']['taxes'] ?? [])->filter(fn ($t) => empty($t['included']))
                        ->map(fn ($t) => ['amount' => (float) ($t['clientAmount'] ?? $t['amount'] ?? 0), 'currency' => $t['clientCurrency'] ?? $t['currency'] ?? null, 'type' => $t['type'] ?? null])->values()->all(),
                ], $rm['rates'] ?? []),
            ], $h['rooms'] ?? []),
        ];
    }

    public static function txt($v): string
    {
        return trim((string) (is_array($v) ? ($v['content'] ?? '') : $v));
    }

    /** "4EST" / "4LL" / "H4_5" → 4 */
    public static function stars(?string $category): ?int
    {
        if (! $category || ! preg_match('/(\d)/', $category, $m)) {
            return null;
        }

        return max(1, min(5, (int) $m[1]));
    }

    public static function imageUrl(string $path, string $size = 'bigger'): string
    {
        return self::PHOTO_URL.($size === 'small' ? '' : $size.'/').ltrim($path, '/');
    }

    /** Hotelbeds names are Latin only: Марко Петров → Marko Petrov */
    public static function latin(string $s): string
    {
        $map = ['А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Ѓ' => 'Gj', 'Е' => 'E', 'Ж' => 'Zh', 'З' => 'Z', 'Ѕ' => 'Dz', 'И' => 'I', 'Ј' => 'J', 'К' => 'K', 'Л' => 'L', 'Љ' => 'Lj', 'М' => 'M', 'Н' => 'N', 'Њ' => 'Nj', 'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'Ќ' => 'Kj', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C', 'Ч' => 'Ch', 'Џ' => 'Dj', 'Ш' => 'Sh'];
        foreach ($map as $c => $l) {
            $map[mb_strtolower($c)] = mb_strtolower($l);
        }
        $s = strtr(trim($s), $map);
        $s = \Illuminate\Support\Str::ascii($s);

        return trim(preg_replace('/[^A-Za-z \-\']/', '', $s)) ?: 'Guest';
    }
}
