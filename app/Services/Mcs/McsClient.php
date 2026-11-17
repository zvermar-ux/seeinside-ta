<?php

namespace App\Services\Mcs;

use App\Models\SupplierConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * MCS eTours Outbound (B2B) API of the old Aries system: POST <base>/<Operation> with username + password
 * and form parameters, JSON answers. Docs: "MCS eTours Outbound API" (15.06.2021).
 */
class McsClient
{
    public function __construct(protected SupplierConnection $c) {}

    protected function base(): string
    {
        $url = rtrim((string) ($this->c->base_url ?: 'https://aries.travel/api/apib2b'), '/');
        if (! str_starts_with($url, 'https://') && ! str_starts_with($url, 'http://')) {
            $url = 'https://'.$url;
        }

        return $url;
    }

    /** @return mixed decoded JSON (or the plain text answer, e.g. a booking number) */
    public function call(string $operation, array $params = [], int $timeout = 60)
    {
        $user = (string) ($this->c->username ?: ($this->c->credentials['username'] ?? ''));
        $pass = (string) ($this->c->credentials['password'] ?? '');
        if ($user === '' || $pass === '') {
            throw new RuntimeException(__('Enter the username and password of the API (Booking systems → edit the connection).'));
        }
        $started = microtime(true);
        try {
            $res = Http::asForm()->acceptJson()->timeout($timeout)->connectTimeout(10)
                ->post($this->base().'/'.$operation, ['username' => $user, 'password' => $pass] + $params);
        } catch (\Throwable $e) {
            throw new RuntimeException(__('The old system does not answer: :e', ['e' => $e->getMessage()]));
        }
        $body = trim((string) $res->body());
        Log::info('MCS '.$operation, ['status' => $res->status(), 'ms' => (int) ((microtime(true) - $started) * 1000), 'bytes' => strlen($body)]);
        if (in_array($res->status(), [401, 403], true)) {
            throw new RuntimeException(__('The old system refused the username or password (HTTP :s).', ['s' => $res->status()]));
        }
        if ($res->failed()) {
            throw new RuntimeException(__('The old system answered with an error (HTTP :s): :b', ['s' => $res->status(), 'b' => mb_substr(strip_tags($body), 0, 300)]));
        }
        $json = json_decode($body, true);
        $value = json_last_error() === JSON_ERROR_NONE ? $json : $body;
        // {"status":"Error", "message": …} or plain "Error"
        $status = is_array($value) ? (string) ($value['status'] ?? $value['Status'] ?? '') : (string) $value;
        if (strcasecmp($status, 'Error') === 0 || (is_string($value) && stripos($value, 'error') === 0)) {
            $msg = is_array($value) ? ($value['message'] ?? $value['Message'] ?? $value['error'] ?? json_encode($value, JSON_UNESCAPED_UNICODE)) : $value;
            throw new RuntimeException(__('The old system: :m', ['m' => mb_substr((string) $msg, 0, 300)]));
        }

        return $value;
    }

    /** The list inside an answer: [..] or {"Hotels": [..]} or {"123": {..}, …} */
    public static function rows($value, array $keys = []): array
    {
        if (! is_array($value)) {
            return [];
        }
        foreach ($keys as $k) {
            foreach ([$k, strtolower($k), ucfirst(strtolower($k))] as $kk) {
                if (isset($value[$kk]) && is_array($value[$kk])) {
                    return self::rows($value[$kk]);
                }
            }
        }
        if (array_is_list($value)) {
            return array_values(array_filter($value, 'is_array'));
        }
        // keyed by id
        $out = [];
        foreach ($value as $id => $row) {
            if (is_array($row)) {
                $out[] = $row + ['id' => $id];
            }
        }

        return $out;
    }

    public function geography(): array
    {
        try {
            return (array) $this->call('GetGeography');
        } catch (RuntimeException $e) {
            // the documentation writes it "GetGegraphy"
            return (array) $this->call('GetGegraphy');
        }
    }

    public function hotels(): array
    {
        return self::rows($this->call('GetHotels', [], 180), ['Hotels', 'hotel']);
    }

    public function packages(): array
    {
        return self::rows($this->call('GetPackages', [], 180), ['Packages', 'package']);
    }

    /** checkin Y-m-d, nights 1–30, one of country/region/city, hotel (optional), adults, children ages */
    public function hotelSearch(array $q): array
    {
        return self::rows($this->call('HotelSearch', self::params($q) + ['nights' => (int) $q['nights']] + array_filter(['hotel' => $q['hotel'] ?? null, 'category' => $q['category'] ?? null])), ['HotelResult', 'Hotels', 'result']);
    }

    public function packageSearch(array $q): array
    {
        return self::rows($this->call('PackageSearch', self::params($q) + array_filter(['package' => $q['package'] ?? null])), ['PackagesResult', 'PackageResult', 'Packages', 'result']);
    }

    /** @return string the booking number in the old system, or "NotAvailable" */
    public function book(array $p): string
    {
        $r = $this->call('BookRequest', $p, 90);
        if (is_array($r)) {
            foreach (['reference', 'Reference', 'referent', 'booking', 'BookingReference', 'id', 'result', 'status'] as $k) {
                if (! empty($r[$k]) && is_scalar($r[$k])) {
                    return (string) $r[$k];
                }
            }

            return json_encode($r, JSON_UNESCAPED_UNICODE);
        }

        return trim((string) $r, " \t\n\r\0\x0B\"");
    }

    protected static function params(array $q): array
    {
        $ages = array_values(array_map('intval', (array) ($q['children_ages'] ?? [])));
        $p = ['checkin' => $q['checkin'], 'adults' => max(1, (int) ($q['adults'] ?? 2))] + array_filter([
            'country' => $q['country'] ?? null, 'region' => $q['region'] ?? null, 'city' => $q['city'] ?? null,
        ]);
        if ($ages) {
            $p['childs'] = count($ages);
            foreach ($ages as $i => $a) {
                $p['child'.($i + 1)] = $a;
            }
        }

        return $p;
    }
}
