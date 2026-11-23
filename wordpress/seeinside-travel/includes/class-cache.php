<?php

defined('ABSPATH') || exit;

/**
 * Page cache (LiteSpeed Cache and others): what comes from the SeeInside app is shown at once.
 *  - every change synced from the app (offer, list, slider, agency data) and every save of the plugin settings
 *    empties the page cache once, at the end of that request;
 *  - the live answers of the plugin (prices, free places, search, availability) are never cached.
 */
class STA_Cache
{
    /** options whose change shows on the pages */
    protected const OPTIONS = ['sta_meta', 'sta_settings', 'sta_slider', 'sta_slider_settings', 'sta_ps', 'sta_header', 'sta_charters',
        'sta_pages', 'sta_card_designs', 'sta_listings', 'sta_archives', 'sta_archive_lists', 'sta_lists', 'sta_photo_stories', 'sta_reviews', 'sta_elementor_templates'];

    protected static bool $dirty = false;

    public static function init(): void
    {
        add_action('sta_synced', [self::class, 'dirty']);
        add_action('sta_list_updated', [self::class, 'dirty']);
        add_action('updated_option', function ($name) {
            if (in_array($name, self::OPTIONS, true) || str_starts_with((string) $name, 'sta_list_')) {
                self::dirty();
            }
        });
        add_action('added_option', function ($name) {
            if (in_array($name, self::OPTIONS, true)) {
                self::dirty();
            }
        });
        foreach (['trashed_post', 'deleted_post', 'untrashed_post'] as $h) {
            add_action($h, function ($id) {
                if (str_starts_with((string) get_post_type($id), 'sta_')) {
                    self::dirty();
                }
            });
        }
        add_action('shutdown', [self::class, 'flush'], 1);
        // live data of the plugin: never from a cache
        add_filter('rest_post_dispatch', function ($res, $server, $req) {
            if ($res instanceof WP_HTTP_Response && str_starts_with((string) $req->get_route(), '/seeinside-travel/')) {
                $res->header('X-LiteSpeed-Cache-Control', 'no-cache');
                $res->header('Cache-Control', 'no-store, no-cache, max-age=0');
                do_action('litespeed_control_set_nocache', 'SeeInside Travel live data');
            }

            return $res;
        }, 10, 3);
        // the page "Check your booking": never cached
        add_action('template_redirect', function () {
            $p = get_queried_object();
            if ($p instanceof WP_Post && has_shortcode((string) $p->post_content, 'sta_booking_status')) {
                do_action('litespeed_control_set_nocache', 'SeeInside Travel booking page');
                if (! defined('DONOTCACHEPAGE')) {
                    define('DONOTCACHEPAGE', true);
                }
            }
        }, 1);
    }

    public static function dirty(): void
    {
        self::$dirty = true;
    }

    /** Empties the page cache once (LiteSpeed, and the other usual cache plugins when they are there) */
    public static function flush(): void
    {
        if (! self::$dirty) {
            return;
        }
        self::$dirty = false;
        do_action('litespeed_purge_all', 'SeeInside Travel – data changed');
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
    }
}
