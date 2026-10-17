<?php

namespace App\Services\Aries;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talks to the legacy Aries administration exactly like its browser UI does:
 * form POSTs to operacii.php with a "komanda" parameter, flexigrid JSON for lists.
 */
class AriesClient
{
    protected CookieJar $cookies;

    protected ?array $credentials = null;

    public function __construct(
        protected string $baseUrl,
        protected int $timeout = 60,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/').'/';
        $this->cookies = new CookieJar();
    }

    protected function http(): PendingRequest
    {
        return Http::withOptions(['cookies' => $this->cookies])
            ->timeout($this->timeout)
            ->retry(3, 1500, throw: false)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'User-Agent' => 'SeeInside-TA-Importer/1.0'])
            ->asForm();
    }

    public function login(string $username, string $password): void
    {
        $this->credentials = [$username, $password];
        $this->cookies = new CookieJar();

        // Open the page first so the PHP session cookie exists
        $this->http()->get($this->baseUrl);

        $body = trim((string) $this->http()->post($this->baseUrl.'login.php', [
            'username' => $username,
            'password' => $password,
        ])->body());

        if ($body === '' || $body === 'no') {
            throw new RuntimeException('Aries login failed: wrong username or password.');
        }
    }

    /** POST a "komanda" to operacii.php (or another script) and return the raw body */
    public function command(string $komanda, array $params = [], string $script = 'operacii.php'): string
    {
        $response = $this->http()->post($this->baseUrl.$script, ['komanda' => $komanda] + $params);

        if ($response->failed()) {
            throw new RuntimeException("Aries request {$komanda} failed with HTTP {$response->status()}");
        }

        return (string) $response->body();
    }

    /**
     * Load a flexigrid list (all rows at once).
     *
     * @return array<int, array{id: mixed, cell: array}>
     */
    public function grid(string $pathWithQuery, bool $retry = true): array
    {
        $response = $this->http()->post($this->baseUrl.$pathWithQuery, [
            'page' => 1, 'rp' => 100000, 'sortname' => 'id', 'sortorder' => 'asc', 'query' => '', 'qtype' => '',
        ]);

        $json = $response->json();

        // Aries answers with an empty body when the session has expired: log in again once
        if ((! is_array($json) || ! isset($json['rows'])) && $retry && $this->credentials && trim((string) $response->body()) === '') {
            $this->login(...$this->credentials);

            return $this->grid($pathWithQuery, false);
        }

        if (! is_array($json) || ! isset($json['rows'])) {
            throw new RuntimeException("Unexpected response from Aries for {$pathWithQuery}: ".mb_substr((string) $response->body(), 0, 200));
        }

        return $json['rows'];
    }

    /** Turn a src like "../hoteli/1/5_a b.jpg" into an absolute, encoded URL */
    public function absoluteUrl(string $src): string
    {
        $src = html_entity_decode(trim($src), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (preg_match('#^https?://#i', $src)) {
            $url = $src;
        } else {
            $parts = parse_url($this->baseUrl);
            $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
            $path = str_starts_with($src, '/') ? $src : ($parts['path'] ?? '/').$src;

            // resolve ./ and ../
            $out = [];
            foreach (explode('/', $path) as $seg) {
                if ($seg === '..') {
                    array_pop($out);
                } elseif ($seg !== '.' && $seg !== '') {
                    $out[] = $seg;
                }
            }
            $url = $origin.'/'.implode('/', $out);
        }

        // encode each path segment (spaces, brackets, unicode) without double-encoding
        $parts = parse_url($url);
        $segments = array_map(fn ($s) => rawurlencode(rawurldecode($s)), explode('/', ltrim($parts['path'] ?? '', '/')));

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').'/'.implode('/', $segments)
            .(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /** Download many files concurrently. $files: url => absolute local path. Returns number saved. */
    public function download(array $files, int $concurrency = 8): int
    {
        $saved = 0;

        foreach (array_chunk($files, $concurrency, true) as $chunk) {
            $responses = Http::pool(function ($pool) use ($chunk) {
                $reqs = [];
                foreach (array_keys($chunk) as $url) {
                    $reqs[] = $pool->as($url)->withOptions(['cookies' => $this->cookies])->timeout($this->timeout)->get($url);
                }

                return $reqs;
            });

            foreach ($chunk as $url => $target) {
                $res = $responses[$url] ?? null;
                if ($res instanceof \Illuminate\Http\Client\Response && $res->successful() && strlen($res->body()) > 0) {
                    @mkdir(dirname($target), 0775, true);
                    file_put_contents($target, $res->body());
                    $saved++;
                }
            }
        }

        return $saved;
    }
}
