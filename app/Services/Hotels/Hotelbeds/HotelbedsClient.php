<?php

namespace App\Services\Hotels\Hotelbeds;

use App\Models\SupplierConnection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Hotelbeds APItude: every request is signed with X-Signature = sha256(api key + secret + unix time).
 * Keys come from developer.hotelbeds.com (My API keys); the "test" keys only work on api.test.hotelbeds.com
 * and have a small daily quota, so the app caches searches.
 */
class HotelbedsClient
{
    public const TEST_URL = 'https://api.test.hotelbeds.com';

    public const LIVE_URL = 'https://api.hotelbeds.com';

    public function __construct(protected SupplierConnection $c) {}

    public function baseUrl(): string
    {
        if (filled($this->c->base_url)) {
            return rtrim((string) $this->c->base_url, '/');
        }

        return ($this->c->settings['environment'] ?? 'test') === 'live' ? self::LIVE_URL : self::TEST_URL;
    }

    public static function signature(string $apiKey, string $secret, ?int $time = null): string
    {
        return hash('sha256', $apiKey.$secret.($time ?? time()));
    }

    protected function headers(): array
    {
        $cred = (array) ($this->c->credentials ?? []);
        $key = trim((string) ($cred['api_key'] ?? $cred['Api-key'] ?? $cred['apikey'] ?? ''));
        $secret = trim((string) ($cred['secret'] ?? $cred['Secret'] ?? ''));
        if ($key === '' || $secret === '') {
            throw new RuntimeException(__('Enter the Hotelbeds "api_key" and "secret" in the connection (Access keys).'));
        }

        return [
            'Api-key' => $key,
            'X-Signature' => self::signature($key, $secret),
            'Accept' => 'application/json',
            'Accept-Encoding' => 'gzip',
        ];
    }

    /** @return array decoded JSON */
    public function request(string $method, string $path, array $query = [], ?array $body = null, int $timeout = 40): array
    {
        $http = Http::withHeaders($this->headers())->timeout($timeout)->connectTimeout(10);
        $url = $this->baseUrl().$path;
        $res = match (strtoupper($method)) {
            'GET' => $http->get($url, $query),
            'DELETE' => $http->delete($url.($query ? '?'.http_build_query($query) : '')),
            default => $http->asJson()->post($url.($query ? '?'.http_build_query($query) : ''), $body ?? []),
        };
        $json = $res->json() ?? [];
        if ($res->failed()) {
            $msg = $json['error']['message'] ?? $json['message'] ?? $json['error'] ?? null;
            if ($res->status() === 403 && ! $msg) {
                $msg = __('Access refused – check the API key / secret and that the keys belong to this environment (test or live).');
            }
            if ($res->status() === 429) {
                $msg = __('Hotelbeds request limit reached (test keys allow few searches per day). Try again later.');
            }
            throw new RuntimeException('Hotelbeds '.$res->status().': '.(is_string($msg) ? $msg : json_encode($msg)));
        }

        return is_array($json) ? $json : [];
    }
}
