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
        });
    }

    public static function url(): string
    {
        return rest_url('seeinside-travel/v1/webhook');
    }

    public static function handle(WP_REST_Request $request)
    {
        $secret = (string) sta_setting('webhook_secret');
        $body = $request->get_body();
        $given = (string) $request->get_header('x_ta_signature');
        $expected = 'sha256='.hash_hmac('sha256', $body, $secret);

        if (! $secret || ! hash_equals($expected, $given)) {
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
        if ($type === 'slider' || $type === 'charter') {
            STA_Sync::enqueue($type, 0, null);
        } elseif (STA_Schema::post_type($type)) {
            STA_Sync::enqueue($type, (int) ($p['id'] ?? 0), $p['slug'] ?? null, str_ends_with($event, '.deleted') ? 'delete' : 'update');
        } else {
            return new WP_REST_Response(['ok' => true, 'ignored' => $event], 200);
        }

        self::process_after_response();

        return new WP_REST_Response(['ok' => true, 'queued' => $event], 202);
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
