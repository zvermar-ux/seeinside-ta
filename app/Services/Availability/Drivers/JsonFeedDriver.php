<?php

namespace App\Services\Availability\Drivers;

use App\Models\Accommodation;
use App\Models\SupplierConnection;
use App\Services\Availability\SupplierAvailability;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Generic availability feed for partners who expose a simple JSON URL.
 *
 * URL template (connection setting "availability_url"), e.g.
 *   https://partner.example/api/availability?hotel={hotel}&from={from}&to={to}
 *
 * Accepted answers:
 *   {"rooms": {"DBL": {"2026-07-01": 3, "2026-07-02": 0}}}
 *   [{"room": "DBL", "date": "2026-07-01", "available": 3}, ...]
 */
class JsonFeedDriver implements SupplierAvailability
{
    public function availability(SupplierConnection $connection, Accommodation $accommodation, CarbonInterface $from, CarbonInterface $to): array
    {
        $template = $connection->settings['availability_url'] ?? null;
        if (blank($template)) {
            throw new RuntimeException(__('The availability address is not set for this booking system.'));
        }

        $url = strtr($template, [
            '{hotel}' => rawurlencode((string) $accommodation->provider_code),
            '{from}' => $from->toDateString(),
            '{to}' => $to->toDateString(),
        ]);

        $credentials = $connection->credentials ?? [];
        $request = Http::timeout(8)->acceptJson();
        if ($key = $credentials['api_key'] ?? $credentials['token'] ?? null) {
            $request = $request->withToken($key)->withHeaders(['X-Api-Key' => $key]);
        } elseif ($connection->username && ($pass = $credentials['password'] ?? null)) {
            $request = $request->withBasicAuth($connection->username, $pass);
        }

        $json = $request->get($url)->throw()->json();

        $out = [];
        if (isset($json['rooms']) && is_array($json['rooms'])) {
            foreach ($json['rooms'] as $code => $days) {
                foreach ((array) $days as $date => $free) {
                    $out[(string) $code][substr((string) $date, 0, 10)] = max(0, (int) $free);
                }
            }
        } elseif (is_array($json) && array_is_list($json)) {
            foreach ($json as $row) {
                $code = $row['room'] ?? $row['code'] ?? null;
                $date = $row['date'] ?? null;
                if ($code !== null && $date) {
                    $out[(string) $code][substr((string) $date, 0, 10)] = max(0, (int) ($row['available'] ?? $row['free'] ?? 0));
                }
            }
        } else {
            throw new RuntimeException(__('Unexpected answer from the booking system.'));
        }

        return $out;
    }
}
