<?php

defined('ABSPATH') || exit;

/** HTTP client for the SeeInside Travel API (/api/v1) */
class STA_Api
{
    public static function base(): string
    {
        return rtrim((string) sta_setting('api_url', 'https://ta.seeinsidesolution.com'), '/').'/api/v1';
    }

    /**
     * Optional fixed IP for the SeeInside server (setting "server_ip"). Needed when the website runs on the
     * same server / network and cannot reach the public address (hairpin NAT) or when DNS is wrong.
     */
    public static function init(): void
    {
        add_action('http_api_curl', function ($handle, $args, $url) {
            $ip = trim((string) sta_setting('server_ip'));
            $base = wp_parse_url((string) sta_setting('api_url', 'https://ta.seeinsidesolution.com'));
            if (empty($base['host']) || wp_parse_url($url, PHP_URL_HOST) !== $base['host']) {
                return;
            }
            // many shared hosts have a broken IPv6 route: always use IPv4 for SeeInside
            if (defined('CURLOPT_IPRESOLVE') && sta_setting('ipv6', '') !== '1') {
                curl_setopt($handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            }
            if (! $ip || ! filter_var($ip, FILTER_VALIDATE_IP)) {
                return;
            }
            $port = $base['port'] ?? (($base['scheme'] ?? 'https') === 'https' ? 443 : 80);
            curl_setopt($handle, CURLOPT_RESOLVE, [$base['host'].':'.$port.':'.$ip]);
        }, 10, 3);
    }

    /** Step-by-step check of the connection (for hosts without SSH) */
    public static function diagnose(): array
    {
        $base = wp_parse_url((string) sta_setting('api_url', 'https://ta.seeinsidesolution.com'));
        $host = $base['host'] ?? '';
        $port = $base['port'] ?? (($base['scheme'] ?? 'https') === 'https' ? 443 : 80);
        $out = [];

        $v4 = $host ? (gethostbynamel($host) ?: []) : [];
        $v6 = [];
        if ($host && function_exists('dns_get_record')) {
            foreach ((array) @dns_get_record($host, DNS_AAAA) as $r) {
                $v6[] = $r['ipv6'] ?? '';
            }
        }
        $out[] = ['DNS '.$host, $v4 ? 'ok' : 'error', 'IPv4: '.($v4 ? implode(', ', $v4) : '—').' · IPv6: '.($v6 ? implode(', ', array_filter($v6)) : '—')];

        $target = trim((string) sta_setting('server_ip')) ?: ($v4[0] ?? '');
        if ($target) {
            $t = microtime(true);
            $sock = @stream_socket_client('tcp://'.$target.':'.$port, $errno, $errstr, 6);
            $ms = (int) ((microtime(true) - $t) * 1000);
            $out[] = ['TCP '.$target.':'.$port, $sock ? 'ok' : 'error', $sock ? $ms.' ms' : ($errstr ?: 'timeout').' ('.$ms.' ms)'];
            $sock && fclose($sock);
        }

        $t = microtime(true);
        $res = wp_remote_get(self::base().'/meta', ['headers' => self::headers(), 'timeout' => 15]);
        $ms = (int) ((microtime(true) - $t) * 1000);
        $code = is_wp_error($res) ? 0 : (int) wp_remote_retrieve_response_code($res);
        $out[] = ['HTTPS '.self::base().'/meta', $code === 200 ? 'ok' : 'error', (is_wp_error($res) ? $res->get_error_message() : 'HTTP '.$code).' ('.$ms.' ms)'];

        $g = wp_remote_head('https://www.google.com', ['timeout' => 8]);
        $out[] = [__('Outgoing internet (google.com)', 'seeinside-travel'), is_wp_error($g) ? 'error' : 'ok', is_wp_error($g) ? $g->get_error_message() : 'HTTP '.wp_remote_retrieve_response_code($g)];

        $ip = wp_remote_get('https://api.ipify.org', ['timeout' => 8]);
        $out[] = [__('IP address of this website server', 'seeinside-travel'), is_wp_error($ip) ? 'error' : 'info', is_wp_error($ip) ? '—' : trim(wp_remote_retrieve_body($ip))];

        return $out;
    }

    public static function configured(): bool
    {
        return (bool) sta_setting('api_key');
    }

    protected static function headers(): array
    {
        return [
            'X-Api-Key' => (string) sta_setting('api_key'),
            'Accept' => 'application/json',
            'User-Agent' => 'SeeInside-Travel-WP/'.STA_VERSION.'; '.home_url('/'),
        ];
    }

    /** @return array{0:int,1:mixed} status and decoded body (WP_Error → status 0) */
    public static function get(string $path, array $query = [], int $timeout = 20): array
    {
        $url = self::base().'/'.ltrim($path, '/');
        if ($query) {
            $url = add_query_arg(array_map('rawurlencode', $query), $url);
        }
        $res = wp_remote_get($url, ['headers' => self::headers(), 'timeout' => $timeout]);

        return self::result($res);
    }

    public static function post(string $path, array $body, int $timeout = 30): array
    {
        $res = wp_remote_post(self::base().'/'.ltrim($path, '/'), [
            'headers' => self::headers() + ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($body),
            'timeout' => $timeout,
        ]);

        return self::result($res);
    }

    /**
     * multipart/form-data (visa applications with documents)
     * @param array $fields  name => value (arrays allowed: answers[key])
     * @param array $files   field name => [ [path, filename, mime], … ]
     */
    public static function post_multipart(string $path, array $fields, array $files, int $timeout = 60): array
    {
        $boundary = 'sta'.wp_generate_password(24, false);
        $body = '';
        $add = function ($name, $value) use (&$body, $boundary, &$add) {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $add($name.'['.$k.']', $v);
                }

                return;
            }
            $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$name}\"\r\n\r\n{$value}\r\n";
        };
        foreach ($fields as $name => $value) {
            $add($name, $value);
        }
        foreach ($files as $name => $list) {
            foreach ($list as $f) {
                [$path_, $filename, $mime] = $f;
                $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$name}\"; filename=\"".str_replace('"', '', $filename)."\"\r\nContent-Type: {$mime}\r\n\r\n".file_get_contents($path_)."\r\n";
            }
        }
        $body .= "--{$boundary}--\r\n";

        $res = wp_remote_post(self::base().'/'.ltrim($path, '/'), [
            'headers' => self::headers() + ['Content-Type' => 'multipart/form-data; boundary='.$boundary],
            'body' => $body,
            'timeout' => $timeout,
        ]);

        return self::result($res);
    }

    protected static function result($res): array
    {
        if (is_wp_error($res)) {
            return [0, ['message' => $res->get_error_message()]];
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        $body = json_decode(wp_remote_retrieve_body($res), true);

        return [$code, $body];
    }
}
