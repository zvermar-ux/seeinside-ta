<?php

defined('ABSPATH') || exit;

/** HTTP client for the SeeInside Travel API (/api/v1) */
class STA_Api
{
    public static function base(): string
    {
        return rtrim((string) sta_setting('api_url', 'https://ta.seeinsidesolution.com'), '/').'/api/v1';
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
