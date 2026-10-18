<?php

defined('ABSPATH') || exit;

/**
 * Sync engine.
 *  - webhook (real time): item is queued and processed right after the response
 *  - every 15 minutes: GET /changes?since=… catches anything missed
 *  - full import: in small batches from the admin page
 */
class STA_Sync
{
    public static function init(): void
    {
        add_filter('cron_schedules', function ($s) {
            $s['sta_15min'] = ['interval' => 15 * MINUTE_IN_SECONDS, 'display' => 'SeeInside Travel – 15 min'];

            return $s;
        });
        add_action('sta_cron_changes', [self::class, 'pull_changes']);
        add_action('sta_process_queue', [self::class, 'process_queue']);
        add_action('admin_post_sta_resync', [self::class, 'resync_post']);
        add_action('init', [self::class, 'schedule']);
    }

    public static function schedule(): void
    {
        if (! wp_next_scheduled('sta_cron_changes')) {
            wp_schedule_event(time() + 60, 'sta_15min', 'sta_cron_changes');
        }
    }

    /* ======================== queue ======================== */

    /** @param string $action update|delete */
    public static function enqueue(string $apiType, int $id, ?string $slug, string $action = 'update'): void
    {
        $queue = get_option('sta_queue', []);
        $queue[$apiType.':'.$id] = ['type' => $apiType, 'id' => $id, 'slug' => $slug, 'action' => $action, 'at' => time()];
        update_option('sta_queue', $queue, false);
    }

    /** Process queued items for at most $seconds */
    public static function process_queue(int $seconds = 25): int
    {
        if (get_transient('sta_queue_lock')) {
            return 0;
        }
        set_transient('sta_queue_lock', 1, 120);
        @set_time_limit(max(60, $seconds + 30));
        $start = microtime(true);
        $done = 0;

        try {
            while (microtime(true) - $start < $seconds) {
                $queue = get_option('sta_queue', []);
                if (! $queue) {
                    break;
                }
                $key = array_key_first($queue);
                $item = $queue[$key];
                unset($queue[$key]);
                update_option('sta_queue', $queue, false);

                if (isset(self::LISTS[$item['type']])) {
                    self::sync_list($item['type']);
                } elseif ($item['type'] === 'slider') {
                    self::sync_slider();
                } elseif ($item['type'] === 'charter') {
                    self::sync_charters();
                } elseif ($item['type'] === 'meta') {
                    self::sync_meta();
                } elseif ($item['action'] === 'delete') {
                    self::remove($item['type'], (int) $item['id']);
                } else {
                    self::sync_one($item['type'], (int) $item['id'], $item['slug']);
                }
                $done++;
            }
        } finally {
            delete_transient('sta_queue_lock');
        }

        if (get_option('sta_queue', [])) {
            wp_schedule_single_event(time() + 5, 'sta_process_queue');
        }

        return $done;
    }

    /* ======================== pull ======================== */

    /** Everything changed since the last run (safety net for missed webhooks) */
    public static function pull_changes(): array
    {
        if (sta_push_mode()) {
            return ['skipped' => 'push mode'];
        }
        if (! STA_Api::configured()) {
            return ['error' => 'not configured'];
        }
        $since = get_option('sta_last_changes', '');
        [$code, $body] = STA_Api::get('changes', $since ? ['since' => $since] : []);
        if ($code !== 200 || ! is_array($body)) {
            sta_log('changes: HTTP '.$code.' '.wp_json_encode($body), 'error');

            return ['error' => 'HTTP '.$code];
        }

        foreach ($body['changed'] ?? [] as $c) {
            if (! STA_Schema::post_type($c['type'])) {
                continue;
            }
            $c['active'] ? self::enqueue($c['type'], (int) $c['id'], $c['slug']) : self::unpublish($c['type'], (int) $c['id']);
        }
        foreach ($body['deleted'] ?? [] as $d) {
            if (STA_Schema::post_type($d['type'])) {
                self::enqueue($d['type'], (int) $d['id'], $d['slug'], 'delete');
            }
        }
        self::enqueue('slider', 0, null);
        self::enqueue('charter', 0, null);
        foreach (array_keys(self::LISTS) as $list) {
            self::enqueue($list, 0, null);
        }
        if (! $since || (time() - (int) get_option('sta_meta_synced', 0)) > HOUR_IN_SECONDS) {
            self::enqueue('meta', 0, null);
        }
        update_option('sta_last_changes', $body['server_time'] ?? gmdate('c'), false);

        $n = count($body['changed'] ?? []) + count($body['deleted'] ?? []);
        if ($n) {
            sta_log(sprintf('changes: %d', $n));
        }
        self::process_queue(20);

        return ['changed' => count($body['changed'] ?? []), 'deleted' => count($body['deleted'] ?? [])];
    }

    /* ======================== full import (batches) ======================== */

    /** One step of the full import. State: ['type' => 'tour', 'page' => 1] → returns next state or done */
    public static function import_step(array $state): array
    {
        $types = array_keys(STA_Schema::ENDPOINTS);
        $type = $state['type'] ?? 'meta';
        $page = max(1, (int) ($state['page'] ?? 1));
        $stats = $state['stats'] ?? [];

        if ($type === 'meta') {
            self::sync_meta();
            self::sync_slider();
            self::sync_charters();
            foreach (array_keys(self::LISTS) as $list) {
                self::sync_list($list);
            }

            return ['type' => $types[0], 'page' => 1, 'stats' => $stats, 'message' => __('Destinations, groups and slider imported', 'seeinside-travel')];
        }

        [$code, $body] = STA_Api::get(STA_Schema::ENDPOINTS[$type], ['page' => $page, 'per_page' => 5]);
        if ($code !== 200) {
            return ['error' => sprintf('%s: HTTP %d %s', $type, $code, $body['message'] ?? '')];
        }

        $names = [];
        foreach ($body['data'] ?? [] as $row) {
            $postId = self::sync_one($type, (int) $row['id'], $row['slug']);
            $names[] = $postId ? get_the_title($postId) : '#'.$row['id'];
            $stats[$type] = ($stats[$type] ?? 0) + 1;
        }

        $last = (int) ($body['meta']['last_page'] ?? 1);
        $total = (int) ($body['meta']['total'] ?? 0);
        $message = sprintf('%s %d/%d: %s', $type, min($page * 5, $total), $total, implode(', ', $names));

        if ($page < $last) {
            return ['type' => $type, 'page' => $page + 1, 'stats' => $stats, 'message' => $message, 'progress' => [$type, $page, $last]];
        }

        $next = $types[array_search($type, $types, true) + 1] ?? null;
        if ($next) {
            return ['type' => $next, 'page' => 1, 'stats' => $stats, 'message' => $message];
        }

        // finished: remove posts that no longer exist / are inactive in SeeInside
        self::cleanup($state['started'] ?? null);
        update_option('sta_last_changes', gmdate('c'), false);
        update_option('sta_last_full_import', current_time('mysql'), false);
        sta_log('full import: '.wp_json_encode($stats));

        return ['done' => true, 'stats' => $stats, 'message' => $message];
    }

    /** After a full import: posts not touched since the import started are unpublished */
    protected static function cleanup(?int $started): void
    {
        if (! $started) {
            return;
        }
        foreach (STA_Schema::TYPES as $postType) {
            $old = get_posts([
                'post_type' => $postType, 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => -1,
                'meta_query' => [['key' => '_sta_synced_ts', 'value' => $started, 'compare' => '<', 'type' => 'NUMERIC']],
            ]);
            foreach ($old as $id) {
                wp_update_post(['ID' => $id, 'post_status' => 'draft']);
            }
        }
    }

    public static function resync_post(): void
    {
        $postId = (int) ($_GET['post'] ?? 0);
        check_admin_referer('sta_resync_'.$postId);
        if (! current_user_can('edit_post', $postId)) {
            wp_die('403');
        }
        $type = STA_Schema::api_type(get_post_type($postId));
        self::sync_one($type, (int) get_post_meta($postId, 'sta_id', true), get_post_field('post_name', $postId));
        wp_safe_redirect(get_edit_post_link($postId, 'url'));
        exit;
    }

    /* ======================== single item ======================== */

    /** Fetch one item from the API and create / update its post. Returns the post id. */
    public static function sync_one(string $apiType, int $id, ?string $slug): ?int
    {
        $postType = STA_Schema::post_type($apiType);
        if (! $postType || ! $slug) {
            return null;
        }
        [$code, $body] = STA_Api::get(STA_Schema::ENDPOINTS[$apiType].'/'.rawurlencode($slug));
        if ($code === 404) {
            self::unpublish($apiType, $id);   // inactive or renamed

            return null;
        }
        if ($code !== 200 || empty($body['data'])) {
            sta_log(sprintf('%s %s: HTTP %d', $apiType, $slug, $code), 'error');

            return null;
        }

        try {
            return self::save($apiType, $body['data']);
        } catch (Throwable $e) {
            sta_log(sprintf('%s %s: %s', $apiType, $slug, $e->getMessage()), 'error');

            return null;
        }
    }

    public static function unpublish(string $apiType, int $id): void
    {
        $postId = sta_find_post(STA_Schema::post_type($apiType), $id);
        if ($postId && get_post_status($postId) === 'publish') {
            wp_update_post(['ID' => $postId, 'post_status' => 'draft']);
            sta_log(sprintf('%s #%d unpublished', $apiType, $id));
        }
    }

    public static function remove(string $apiType, int $id): void
    {
        $postId = sta_find_post(STA_Schema::post_type($apiType), $id);
        if (! $postId) {
            return;
        }
        sta_setting('delete_mode', 'trash') === 'delete' ? wp_delete_post($postId, true) : wp_trash_post($postId);
        sta_log(sprintf('%s #%d deleted', $apiType, $id));
    }

    /** Write a record into its post (fields, terms, images) */
    public static function save(string $apiType, array $d): int
    {
        $postType = STA_Schema::post_type($apiType);
        $map = self::map($apiType, $d);
        $postId = sta_find_post($postType, (int) $d['id']);

        $postarr = [
            'post_type' => $postType,
            'post_status' => 'publish',
            'post_title' => wp_strip_all_tags($map['title']),
            'post_name' => sanitize_title($d['slug']),
            'post_content' => wp_kses_post($map['content'] ?? ''),
            'post_excerpt' => wp_strip_all_tags($map['excerpt'] ?? ''),
        ];

        // don't let our own update trigger other syncs or revisions
        remove_action('post_updated', 'wp_save_post_revision');
        if ($postId) {
            $postarr['ID'] = $postId;
            wp_update_post(wp_slash($postarr), true);
        } else {
            $postId = wp_insert_post(wp_slash($postarr), true);
            if (is_wp_error($postId)) {
                throw new RuntimeException($postId->get_error_message());
            }
        }
        add_action('post_updated', 'wp_save_post_revision', 10, 3);

        update_post_meta($postId, 'sta_id', (int) $d['id']);
        update_post_meta($postId, '_sta_payload', wp_slash(wp_json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
        update_post_meta($postId, '_sta_synced_at', current_time('mysql'));
        update_post_meta($postId, '_sta_synced_ts', time());
        // numbers for sorting / filtering in listings (also without ACF)
        update_post_meta($postId, '_sta_price', $map['fields']['price_from'] ?? $map['fields']['price'] ?? '');
        update_post_meta($postId, '_sta_last_minute', ! empty($d['promotions']['last_minute']) ? 1 : 0);
        update_post_meta($postId, '_sta_date', $map['sort_date'] ?? '');

        // images
        $featured = ! empty($d['image']) ? self::attach($d['image'], $postId) : null;
        if (! $featured && ! empty($d['gallery'][0]['url'])) {
            $featured = self::attach($d['gallery'][0]['url'], $postId);
        }
        $featured ? set_post_thumbnail($postId, $featured) : delete_post_thumbnail($postId);
        foreach ($d['info_cards'] ?? [] as $c) {
            ! empty($c['image']) && self::attach($c['image'], $postId);   // local copy for the visa info cards
        }
        $map['fields']['gallery'] = array_values(array_filter(array_map(fn ($g) => self::attach($g['url'], $postId, sta_t($g['caption'] ?? null)), $d['gallery'] ?? [])));
        if ($apiType === 'accommodation') {
            foreach ($map['fields']['rooms'] as $i => $room) {
                $src = $d['rooms'][$i] ?? [];
                $map['fields']['rooms'][$i]['image'] = ! empty($src['cover_image']) ? self::attach($src['cover_image'], $postId) : null;
                $map['fields']['rooms'][$i]['room_gallery'] = array_values(array_filter(array_map(fn ($g) => self::attach($g['url'], $postId), $src['images'] ?? [])));
            }
        }

        // fields (ACF or meta)
        foreach (STA_Schema::fields($postType) as $field) {
            if ($field['type'] === 'tab') {
                continue;
            }
            STA_Fields::save($postId, $postType, $field, $map['fields'][$field['name']] ?? null);
        }

        // terms
        foreach ($map['terms'] as $tax => $terms) {
            wp_set_object_terms($postId, array_values(array_filter(array_map(fn ($t) => self::term($tax, $t), $terms))), $tax);
        }

        self::seo($postId, $d);
        clean_post_cache($postId);
        do_action('sta_synced', $postId, $apiType, $d);

        return (int) $postId;
    }

    /* ======================== mapping ======================== */

    protected static function lines($v, string $lang): string
    {
        return implode("\n", (array) ($v[$lang] ?? []));
    }

    /** API record → title, content, field values, terms */
    public static function map(string $apiType, array $d): array
    {
        $mk = fn ($v) => is_array($v) ? (string) ($v['mk'] ?? '') : (string) $v;
        $en = fn ($v) => is_array($v) ? (string) ($v['en'] ?? '') : '';
        $lm = $d['promotions']['last_minute'] ?? null;
        $fm = $d['promotions']['first_minute'] ?? null;

        $common = [
            'title_en' => $en($d['title'] ?? $d['name'] ?? null),
            'short_description' => $mk($d['short_description'] ?? null),
            'short_description_en' => $en($d['short_description'] ?? null),
            'description_en' => $en($d['description'] ?? $d['overview'] ?? null),
            'videos' => array_map(fn ($u) => ['url' => is_array($u) ? ($u['url'] ?? '') : $u], $d['videos'] ?? []),
            'is_last_minute' => (bool) $lm,
            'last_minute_price' => $lm['price'] ?? null,
            'last_minute_discount' => $lm['discount_percent'] ?? null,
            'last_minute_until' => $lm['until'] ?? null,
            'first_minute_discount' => $fm['discount'] ?? null,
            'first_minute_type' => $fm['type'] ?? null,
            'first_minute_until' => $fm['until'] ?? null,
            'is_featured' => ! empty($d['is_featured']),
            'seo_title' => $mk($d['seo']['title'] ?? null),
            'seo_title_en' => $en($d['seo']['title'] ?? null),
            'seo_description' => $mk($d['seo']['description'] ?? null),
            'seo_description_en' => $en($d['seo']['description'] ?? null),
            'sta_id' => (int) $d['id'],
            'sta_updated_at' => $d['updated_at'] ?? '',
            'booking_enabled' => ! empty($d['booking']['enabled']),
            'booking_mode' => $d['booking']['mode'] ?? '',
            'price_from' => $d['price_from'] ?? null,
            'currency' => $d['currency'] ?? 'EUR',
        ];
        $country = array_filter([$d['country'] ?? null]);

        switch ($apiType) {
            case 'tour':
                $f = $common + [
                    'subtitle' => $mk($d['subtitle']), 'subtitle_en' => $en($d['subtitle']),
                    'tour_type' => $d['tour_type']['value'] ?? '',
                    'location' => $d['location'] ?? '', 'country' => $d['country'] ?? '',
                    'duration_days' => $d['duration']['days'] ?? null, 'duration_nights' => $d['duration']['nights'] ?? null,
                    'group_size_min' => $d['group_size']['min'] ?? null, 'group_size_max' => $d['group_size']['max'] ?? null,
                    'map' => $d['map'] ?? '',
                    'aries_quality' => ! empty($d['aries_quality']), 'has_guide' => ! empty($d['has_guide']), 'is_flight' => ! empty($d['is_flight']),
                    'airline_name' => $d['airline']['name'] ?? '', 'airline_iata' => $d['airline']['iata'] ?? '', 'airline_logo' => $d['airline']['logo'] ?? '',
                    'price_adult' => $d['prices']['adult'] ?? null, 'price_child' => $d['prices']['child'] ?? null,
                    'child_age_from' => $d['prices']['child_age']['from'] ?? null, 'child_age_to' => $d['prices']['child_age']['to'] ?? null,
                    'single_supplement' => $d['prices']['single_supplement'] ?? null, 'deposit_percent' => $d['prices']['deposit_percent'] ?? null,
                    'next_departure' => $d['next_departure'] ?? null,
                    'departures' => array_map(fn ($x) => [
                        'departure_id' => $x['id'], 'start_date' => $x['start_date'], 'end_date' => $x['end_date'],
                        'price_adult' => $x['price_adult'], 'price_child' => $x['price_child'], 'seats_left' => $x['seats_left'],
                        'status' => $x['status'], 'status_label' => $mk($x['status_label'] ?? null), 'status_label_en' => $en($x['status_label'] ?? null),
                        'is_last_minute' => ! empty($x['is_last_minute']), 'note' => $x['note'] ?? '',
                    ], $d['departures'] ?? []),
                    'departure_places' => array_map(fn ($x) => [
                        'place_id' => $x['id'], 'name' => $mk($x['name']), 'name_en' => $en($x['name']), 'time' => $x['time'] ?? '', 'surcharge' => $x['surcharge'] ?? 0,
                    ], $d['departure_places'] ?? []),
                    'extras' => array_map(fn ($x) => [
                        'extra_id' => $x['id'], 'name' => $mk($x['name']), 'name_en' => $en($x['name']),
                        'description' => $mk($x['description']), 'description_en' => $en($x['description']),
                        'price' => $x['price'], 'price_type' => $x['price_type'], 'kind' => $x['kind'], 'optional' => ! empty($x['optional']),
                    ], $d['extras'] ?? []),
                    'program' => array_map(fn ($x) => [
                        'day' => $x['day'], 'title' => $mk($x['title']), 'title_en' => $en($x['title']),
                        'description' => $mk($x['description']), 'description_en' => $en($x['description']),
                        'meals' => is_array($x['meals'] ?? null) ? implode(', ', $x['meals']) : ($x['meals'] ?? ''), 'overnight' => $x['overnight'] ?? '',
                    ], $d['program'] ?? []),
                    'program_pdf' => $d['program_pdf'] ?? '',
                    'highlights' => self::lines($d['highlights'] ?? [], 'mk'), 'highlights_en' => self::lines($d['highlights'] ?? [], 'en'),
                    'agenda' => $mk($d['agenda'] ?? null), 'agenda_en' => $en($d['agenda'] ?? null),
                    'included' => self::lines($d['included'] ?? [], 'mk'), 'included_en' => self::lines($d['included'] ?? [], 'en'),
                    'not_included' => self::lines($d['not_included'] ?? [], 'mk'), 'not_included_en' => self::lines($d['not_included'] ?? [], 'en'),
                    'links' => array_map(fn ($l) => ['label' => is_array($l) ? ($l['label'] ?? '') : '', 'url' => is_array($l) ? ($l['url'] ?? '') : $l], $d['links'] ?? []),
                ];
                foreach (['notes', 'important_info', 'baggage_info', 'payment_terms', 'cancellation_policy'] as $k) {
                    $f[$k] = $mk($d[$k] ?? null);
                    $f[$k.'_en'] = $en($d[$k] ?? null);
                }

                return [
                    'title' => $mk($d['title']), 'content' => $mk($d['description'] ?? null), 'excerpt' => $mk($d['short_description'] ?? null),
                    'fields' => $f, 'sort_date' => $d['next_departure'] ?? '',
                    'terms' => [
                        'sta_destination' => array_filter([$d['destination'] ?? null]),
                        'sta_group' => array_filter([$d['group'] ?? null]),
                        'sta_tour_type' => array_filter([$d['tour_type']['value'] ? ['slug' => $d['tour_type']['value'], 'name' => $d['tour_type']['label']] : null]),
                        'sta_country' => $country,
                    ],
                ];

            case 'event':
                $f = $common + [
                    'category' => $d['category']['value'] ?? '', 'event_status' => $d['status']['value'] ?? '',
                    'performer' => $d['performer'] ?? '', 'starts_at' => $d['starts_at'] ?? null, 'ends_at' => $d['ends_at'] ?? null,
                    'doors_open' => $d['doors_open'] ?? '', 'venue' => $d['venue'] ?? '', 'city' => $d['city'] ?? '', 'country' => $d['country'] ?? '',
                    'address' => $d['address'] ?? '', 'map' => $d['map'] ?? '', 'organizer' => $d['organizer'] ?? '', 'age_restriction' => $d['age_restriction'] ?? '',
                    'tickets' => array_map(fn ($x) => [
                        'ticket_id' => $x['id'], 'name' => $mk($x['name']), 'name_en' => $en($x['name']),
                        'description' => $mk($x['description']), 'description_en' => $en($x['description']),
                        'price' => $x['price'], 'available' => $x['available'],
                    ], $d['tickets'] ?? []),
                    'external_ticket_url' => $d['external_ticket_url'] ?? '',
                    'related_tour' => $d['tour']['slug'] ?? '',
                    'included' => self::lines($d['included'] ?? [], 'mk'), 'included_en' => self::lines($d['included'] ?? [], 'en'),
                    'not_included' => self::lines($d['not_included'] ?? [], 'mk'), 'not_included_en' => self::lines($d['not_included'] ?? [], 'en'),
                    'notes' => $mk($d['notes'] ?? null), 'notes_en' => $en($d['notes'] ?? null),
                ];

                return [
                    'title' => $mk($d['title']), 'content' => $mk($d['description'] ?? null), 'excerpt' => $mk($d['short_description'] ?? null),
                    'fields' => $f, 'sort_date' => substr((string) ($d['starts_at'] ?? ''), 0, 10),
                    'terms' => [
                        'sta_event_category' => array_filter([$d['category']['value'] ? ['slug' => $d['category']['value'], 'name' => $d['category']['label']] : null]),
                        'sta_country' => $country,
                    ],
                ];

            case 'accommodation':
                $f = $common + [
                    'title_en' => '',
                    'accommodation_type' => $d['accommodation_type']['value'] ?? '', 'stars' => $d['stars'] ?? null,
                    'place' => $d['place'] ?? '', 'region' => $d['region'] ?? '', 'country' => $d['country'] ?? '',
                    'address' => $d['address'] ?? '', 'map' => $d['map'] ?? '',
                    'check_in' => $d['check_in'] ?? '', 'check_out' => $d['check_out'] ?? '',
                    'distance_beach_m' => $d['distances']['beach_m'] ?? null, 'distance_center_m' => $d['distances']['center_m'] ?? null,
                    'distance_ski_lift_m' => $d['distances']['ski_lift_m'] ?? null, 'distance_airport_km' => $d['distances']['airport_km'] ?? null,
                    'board_types' => implode(', ', $d['board_types'] ?? []),
                    'rooms' => array_map(fn ($r) => [
                        'room_id' => $r['id'], 'name' => $mk($r['name']), 'name_en' => $en($r['name']),
                        'room_type' => $r['room_type']['value'] ?? '', 'board' => $r['board']['value'] ?? '',
                        'max_adults' => $r['max_adults'], 'max_children' => $r['max_children'], 'beds' => $r['beds'] ?? '', 'size_m2' => $r['size_m2'] ?? null,
                        'description' => $mk($r['description']), 'description_en' => $en($r['description']),
                        'room_amenities' => implode(', ', $r['amenities'] ?? []), 'price' => $r['price'], 'price_type' => $r['price_type'],
                        'rates' => array_map(fn ($x) => [
                            'from' => $x['from'], 'to' => $x['to'], 'price' => $x['price'], 'price_type' => $x['price_type'],
                            'min_nights' => $x['min_nights'], 'is_last_minute' => ! empty($x['is_last_minute']),
                        ], $r['rates'] ?? []),
                    ], $d['rooms'] ?? []),
                    'price_list_pdf' => $d['price_list_pdf'] ?? '',
                    'house_rules' => $mk($d['house_rules'] ?? null), 'house_rules_en' => $en($d['house_rules'] ?? null),
                    'notes' => $mk($d['notes'] ?? null), 'notes_en' => $en($d['notes'] ?? null),
                    'cancellation_policy' => $mk($d['cancellation_policy'] ?? null), 'cancellation_policy_en' => $en($d['cancellation_policy'] ?? null),
                ];

                return [
                    'title' => (string) $d['name'], 'content' => $mk($d['overview'] ?? null), 'excerpt' => $mk($d['short_description'] ?? null),
                    'fields' => $f,
                    'terms' => [
                        'sta_destination' => array_filter([$d['destination'] ?? null]),
                        'sta_hotel_type' => array_filter([$d['accommodation_type']['value'] ? ['slug' => $d['accommodation_type']['value'], 'name' => $d['accommodation_type']['label']] : null]),
                        'sta_amenity' => array_map(fn ($a) => ['slug' => $a['key'], 'name' => $a['name']], $d['amenities'] ?? []),
                        'sta_country' => $country,
                    ],
                ];

            case 'visa':
                $f = $common + [
                    'country' => $d['country'] ?? '', 'country_code' => $d['country_code'] ?? '', 'category' => $d['category']['value'] ?? '',
                    'price' => $d['price'] ?? null, 'price_from' => $d['price'] ?? null,
                    'fee_service' => $d['fees']['service'] ?? null, 'fee_consular' => $d['fees']['consular'] ?? null, 'fee_express' => $d['fees']['express'] ?? null,
                    'online_payment' => ! empty($d['online_payment']),
                    'processing_time' => $mk($d['processing_time'] ?? null), 'processing_time_en' => $en($d['processing_time'] ?? null),
                    'validity' => $mk($d['validity'] ?? null), 'validity_en' => $en($d['validity'] ?? null),
                    'requirements' => self::lines($d['requirements'] ?? [], 'mk'), 'requirements_en' => self::lines($d['requirements'] ?? [], 'en'),
                    'questionnaire_json' => wp_json_encode($d['questionnaire'] ?? [], JSON_UNESCAPED_UNICODE),
                    'documents_json' => wp_json_encode($d['required_documents'] ?? [], JSON_UNESCAPED_UNICODE),
                    'title_en' => trim(($d['country'] ?? '').': '.$en($d['name'] ?? null), ': '),
                ];

                return [
                    'title' => trim(($d['country'] ?? '').': '.$mk($d['name'] ?? null), ': '),
                    'content' => $mk($d['description'] ?? null), 'excerpt' => $mk($d['short_description'] ?? null),
                    'fields' => $f,
                    'terms' => ['sta_country' => $country],
                ];
        }

        throw new RuntimeException('Unknown type '.$apiType);
    }

    /* ======================== terms ======================== */

    /**
     * $t: string (name) | ['id' => remote id, 'name' => {mk,en}] (destination / group) | ['slug' => …, 'name' => {mk,en}]
     */
    public static function term(string $tax, $t): ?int
    {
        if (! $t) {
            return null;
        }
        if (is_string($t)) {
            $existing = get_term_by('name', $t, $tax);
            if ($existing) {
                return (int) $existing->term_id;
            }
            $r = wp_insert_term($t, $tax);

            return is_wp_error($r) ? null : (int) $r['term_id'];
        }

        $name = is_array($t['name']) ? ($t['name']['mk'] ?? '') ?: ($t['name']['en'] ?? '') : (string) $t['name'];
        $nameEn = is_array($t['name']) ? ($t['name']['en'] ?? '') : '';

        if (isset($t['id'])) {
            $found = get_terms(['taxonomy' => $tax, 'hide_empty' => false, 'meta_key' => 'sta_id', 'meta_value' => (int) $t['id'], 'fields' => 'ids', 'number' => 1]);
            $termId = $found && ! is_wp_error($found) ? (int) $found[0] : null;
            $args = ['parent' => isset($t['parent_id']) && $t['parent_id'] ? (int) self::term_by_remote($tax, (int) $t['parent_id']) : 0];
            if (isset($t['slug_hint'])) {
                $args['slug'] = $t['slug_hint'];
            }
            if ($termId) {
                if (! isset($t['parent_id'])) {
                    unset($args['parent']);
                }
                wp_update_term($termId, $tax, ['name' => $name] + $args);
            } else {
                $r = wp_insert_term($name ?: '#'.$t['id'], $tax, $args);
                if (is_wp_error($r)) {
                    $termId = (int) ($r->get_error_data('term_exists') ?: 0) ?: null;
                } else {
                    $termId = (int) $r['term_id'];
                }
                if ($termId) {
                    update_term_meta($termId, 'sta_id', (int) $t['id']);
                }
            }
        } else {
            $slug = sanitize_title($t['slug']);
            $existing = get_term_by('slug', $slug, $tax);
            if ($existing) {
                $termId = (int) $existing->term_id;
                if ($name && $existing->name !== $name) {
                    wp_update_term($termId, $tax, ['name' => $name]);
                }
            } else {
                $r = wp_insert_term($name ?: $slug, $tax, ['slug' => $slug]);
                $termId = is_wp_error($r) ? null : (int) $r['term_id'];
            }
        }

        if ($termId && $nameEn) {
            update_term_meta($termId, 'name_en', $nameEn);
        }

        return $termId;
    }

    protected static function term_by_remote(string $tax, int $remoteId): ?int
    {
        $found = get_terms(['taxonomy' => $tax, 'hide_empty' => false, 'meta_key' => 'sta_id', 'meta_value' => $remoteId, 'fields' => 'ids', 'number' => 1]);

        return $found && ! is_wp_error($found) ? (int) $found[0] : null;
    }

    /** Destinations, groups, agency info, departure places, flights (from /meta) */
    public static function sync_meta(?array $body = null): void
    {
        if ($body === null) {
            [$code, $body] = STA_Api::get('meta');
            if ($code !== 200 || ! is_array($body)) {
                sta_log('meta: HTTP '.$code, 'error');

                return;
            }
        }
        foreach (['destinations' => 'sta_destination', 'groups' => 'sta_group'] as $key => $tax) {
            $items = $body[$key] ?? [];
            // parents first
            usort($items, fn ($a, $b) => (int) ! empty($a['parent_id']) <=> (int) ! empty($b['parent_id']));
            foreach ($items as $g) {
                $termId = self::term($tax, ['id' => $g['id'], 'name' => $g['name'], 'parent_id' => $g['parent_id'] ?? null]);
                if ($termId) {
                    update_term_meta($termId, 'sta_image', $g['image'] ?? '');
                    $att = ! empty($g['image']) ? self::attach($g['image']) : null;   // local copy when available
                    if ($att) {
                        update_term_meta($termId, 'sta_image', wp_get_attachment_image_url($att, 'large'));
                    }
                }
            }
        }
        update_option('sta_meta', [
            'agency' => $body['agency'] ?? [],
            'flights' => $body['flights'] ?? [],
            'departure_places' => $body['departure_places'] ?? [],
            'amenities' => $body['amenities'] ?? [],
            'enums' => $body['enums'] ?? [],
        ], false);
        if (isset($body['slider'])) {
            update_option('sta_slider', $body['slider'], false);
        }
        update_option('sta_meta_synced', time(), false);
    }

    public static function sync_slider(): void
    {
        [$code, $body] = STA_Api::get('slider');
        if ($code === 200 && isset($body['data'])) {
            foreach ($body['data'] as $sl) {
                self::attach($sl['image'] ?? null);   // same-origin copy for the WebGL effects
            }
            update_option('sta_slider', $body['data'], false);
        }
    }

    /** Lists the website keeps in one option: type => [API path, option] */
    public const LISTS = [
        'reviews' => ['reviews', 'sta_reviews'],
        'chat' => ['chat', 'sta_chat'],
    ];

    public static function sync_list(string $type): void
    {
        [$path, $option] = self::LISTS[$type];
        [$code, $body] = STA_Api::get($path);
        if ($code === 200 && isset($body['data'])) {
            self::store_list($type, $body['data']);
        }
    }

    public static function store_list(string $type, $data): void
    {
        update_option(self::LISTS[$type][1], is_array($data) ? $data : [], false);
        if ($type === 'chat') {
            foreach ((array) ($data['questionnaires'] ?? []) as $q) {
                ! empty($q['image']) && self::attach($q['image']);
            }
        }
        do_action('sta_list_updated', $type, $data);
    }

    /** Charter seats (shown with [sta_charters]) */
    public static function sync_charters(): void
    {
        [$code, $body] = STA_Api::get('charters');
        if ($code === 200 && isset($body['data'])) {
            update_option('sta_charters', $body['data'], false);
        }
    }

    /* ======================== images ======================== */

    /** Download an image once (re-used by its source URL) and return the attachment id */
    public static function attach(?string $url, int $postId = 0, string $caption = ''): ?int
    {
        if (! $url) {
            return null;
        }
        $hash = md5($url);
        $found = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_sta_source', 'meta_value' => $hash, 'fields' => 'ids', 'posts_per_page' => 1, 'no_found_rows' => true]);
        if ($found) {
            return (int) $found[0];
        }

        if (sta_push_mode()) {
            return null;   // photos arrive from the app (/media) before the record
        }

        require_once ABSPATH.'wp-admin/includes/file.php';
        require_once ABSPATH.'wp-admin/includes/media.php';
        require_once ABSPATH.'wp-admin/includes/image.php';

        $tmp = download_url($url, 30);
        if (is_wp_error($tmp)) {
            sta_log('image '.$url.': '.$tmp->get_error_message(), 'error');

            return null;
        }
        $name = sanitize_file_name(wp_basename(parse_url($url, PHP_URL_PATH)) ?: 'image.jpg');
        $id = media_handle_sideload(['name' => $name, 'tmp_name' => $tmp], $postId, $caption ?: null);
        if (is_wp_error($id)) {
            @unlink($tmp);
            sta_log('image '.$url.': '.$id->get_error_message(), 'error');

            return null;
        }
        update_post_meta($id, '_sta_source', $hash);
        update_post_meta($id, '_sta_source_url', esc_url_raw($url));

        return (int) $id;
    }

    /** Photo sent by the app (push mode) */
    public static function store_media(string $url, string $name, string $binary): ?int
    {
        $hash = md5($url);
        $found = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_sta_source', 'meta_value' => $hash, 'fields' => 'ids', 'posts_per_page' => 1, 'no_found_rows' => true]);
        if ($found) {
            return (int) $found[0];
        }
        require_once ABSPATH.'wp-admin/includes/file.php';
        require_once ABSPATH.'wp-admin/includes/media.php';
        require_once ABSPATH.'wp-admin/includes/image.php';

        $tmp = wp_tempnam($name);
        file_put_contents($tmp, $binary);
        $check = wp_check_filetype_and_ext($tmp, $name);
        if (empty($check['type']) || ! str_starts_with((string) $check['type'], 'image/') && $check['type'] !== 'application/pdf') {
            @unlink($tmp);
            sta_log('media '.$name.': not an image', 'error');

            return null;
        }
        $id = media_handle_sideload(['name' => sanitize_file_name($name), 'tmp_name' => $tmp], 0);
        if (is_wp_error($id)) {
            @unlink($tmp);
            sta_log('media '.$name.': '.$id->get_error_message(), 'error');

            return null;
        }
        update_post_meta($id, '_sta_source', $hash);
        update_post_meta($id, '_sta_source_url', esc_url_raw($url));

        return (int) $id;
    }

    public static function has_media(string $url): bool
    {
        return (bool) get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_sta_source', 'meta_value' => md5($url), 'fields' => 'ids', 'posts_per_page' => 1, 'no_found_rows' => true]);
    }

    /* ======================== SEO plugins ======================== */

    protected static function seo(int $postId, array $d): void
    {
        $title = sta_t($d['seo']['title'] ?? null) ?: null;
        $desc = sta_t($d['seo']['description'] ?? null) ?: sta_t($d['short_description'] ?? null);
        if (! $title && ! $desc) {
            return;
        }
        if (defined('WPSEO_VERSION')) {
            $title && update_post_meta($postId, '_yoast_wpseo_title', $title);
            $desc && update_post_meta($postId, '_yoast_wpseo_metadesc', wp_strip_all_tags($desc));
        }
        if (defined('RANK_MATH_VERSION')) {
            $title && update_post_meta($postId, 'rank_math_title', $title);
            $desc && update_post_meta($postId, 'rank_math_description', wp_strip_all_tags($desc));
        }
    }
}
