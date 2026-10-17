<?php

defined('ABSPATH') || exit;

/**
 * POST /wp-json/seeinside-travel/v1/webhook  (signed with X-TA-Signature: sha256=HMAC(body, secret))
 * The answer is sent at once; the item is synced right after (same request, after the connection closes).
 */
class STA_Webhook
{
    public static function init(): void
    {
        add_action('rest_api_init', function () {
            register_rest_route('seeinside-travel/v1', '/webhook', [
                'methods' => 'POST',
                'callback' => [self::class, 'handle'],
                'permission_callback' => '__return_true',   // verified by the HMAC signature
            ]);
            // push mode: the app uploads photos first (only those the site does not have)
            register_rest_route('seeinside-travel/v1', '/media-check', ['methods' => 'POST', 'callback' => [self::class, 'media_check'], 'permission_callback' => '__return_true']);
            register_rest_route('seeinside-travel/v1', '/media', ['methods' => 'POST', 'callback' => [self::class, 'media'], 'permission_callback' => '__return_true']);
        });
    }

    public static function url(): string
    {
        return rest_url('seeinside-travel/v1/webhook');
    }

    protected static function verified(WP_REST_Request $request): bool
    {
        $secret = (string) sta_setting('webhook_secret');
        $expected = 'sha256='.hash_hmac('sha256', $request->get_body(), $secret);

        return $secret && hash_equals($expected, (string) $request->get_header('x_ta_signature'));
    }

    public static function media_check(WP_REST_Request $request)
    {
        if (! self::verified($request)) {
            return new WP_REST_Response(['error' => 'invalid signature'], 401);
        }
        $urls = array_filter((array) ($request->get_json_params()['urls'] ?? []), 'is_string');

        return new WP_REST_Response(['missing' => array_values(array_filter($urls, fn ($u) => ! STA_Sync::has_media($u)))], 200);
    }

    public static function media(WP_REST_Request $request)
    {
        if (! self::verified($request)) {
            return new WP_REST_Response(['error' => 'invalid signature'], 401);
        }
        @set_time_limit(120);
        $p = $request->get_json_params();
        $binary = base64_decode((string) ($p['content'] ?? ''), true);
        if (! $binary || empty($p['url'])) {
            return new WP_REST_Response(['error' => 'empty'], 422);
        }
        $id = STA_Sync::store_media((string) $p['url'], (string) ($p['name'] ?? 'image.jpg'), $binary);

        return new WP_REST_Response(['ok' => (bool) $id, 'id' => $id], $id ? 200 : 422);
    }

    public static function handle(WP_REST_Request $request)
    {
        $body = $request->get_body();
        if (! self::verified($request)) {
            sta_log('webhook: invalid signature', 'error');

            return new WP_REST_Response(['ok' => false, 'error' => 'invalid signature'], 401);
        }

        $p = json_decode($body, true) ?: [];
        $event = (string) ($p['event'] ?? '');
        update_option('sta_last_webhook', current_time('mysql'), false);

        if ($event === 'ping') {
            sta_log('webhook: ping');

            return new WP_REST_Response(['ok' => true, 'pong' => true, 'site' => home_url('/')], 200);
        }

        $type = (string) ($p['type'] ?? '');

        // push mode: the full record is in the message
        if (array_key_exists('data', $p)) {
            return self::apply_push($type, $event, $p);
        }

        if ($type === 'meta') {
            STA_Sync::enqueue('meta', 0, null);
        } elseif ($type === 'slider' || $type === 'charter') {
            STA_Sync::enqueue($type, 0, null);
        } elseif (STA_Schema::post_type($type)) {
            STA_Sync::enqueue($type, (int) ($p['id'] ?? 0), $p['slug'] ?? null, str_ends_with($event, '.deleted') ? 'delete' : 'update');
        } else {
            return new WP_REST_Response(['ok' => true, 'ignored' => $event], 200);
        }

        self::process_after_response();

        return new WP_REST_Response(['ok' => true, 'queued' => $event], 202);
    }

    protected static function apply_push(string $type, string $event, array $p): WP_REST_Response
    {
        @set_time_limit(120);
        $data = $p['data'];
        try {
            if ($type === 'meta') {
                is_array($data) && STA_Sync::sync_meta($data);
            } elseif ($type === 'slider') {
                update_option('sta_slider', (array) $data, false);
            } elseif ($type === 'charter') {
                update_option('sta_charters', (array) $data, false);
            } elseif (STA_Schema::post_type($type)) {
                if (str_ends_with($event, '.deleted')) {
                    STA_Sync::remove($type, (int) ($p['id'] ?? 0));
                } elseif (! is_array($data)) {
                    STA_Sync::unpublish($type, (int) ($p['id'] ?? 0));
                } else {
                    $postId = STA_Sync::save($type, $data);
                    sta_log(sprintf('push %s: %s', $type, get_the_title($postId)));
                }
            } else {
                return new WP_REST_Response(['ok' => true, 'ignored' => $event], 200);
            }
        } catch (Throwable $e) {
            sta_log('push '.$event.': '.$e->getMessage(), 'error');

            return new WP_REST_Response(['ok' => false, 'error' => $e->getMessage()], 500);
        }
        update_option('sta_last_changes', gmdate('c'), false);

        return new WP_REST_Response(['ok' => true, 'saved' => $event], 200);
    }

    /** Sync after the HTTP answer went out (PHP-FPM / LiteSpeed), otherwise via WP-Cron at once */
    protected static function process_after_response(): void
    {
        if (function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request')) {
            add_action('shutdown', function () {
                function_exists('fastcgi_finish_request') ? fastcgi_finish_request() : litespeed_finish_request();
                STA_Sync::process_queue(25);
            }, 999);

            return;
        }
        if (! wp_next_scheduled('sta_process_queue')) {
            wp_schedule_single_event(time(), 'sta_process_queue');
        }
        spawn_cron();
    }
}
