<?php

defined('ABSPATH') || exit;

/**
 * Big search box with tabs (Apartments · Hotels · Tours · Flights), for the homepage hero or anywhere:
 * [sta_search_box tabs="apartments,hotels,tours,flights" active="hotels" title="" subtitle=""]
 * Hotels go to the hotels page with filters, tours to the tours page, flights show the GOL IBE form.
 */
class STA_Search_Box
{
    public static function init(): void
    {
        add_action('init', fn () => add_shortcode('sta_search_box', [self::class, 'shortcode']));
    }

    /** Hotels page (filters on the left) / tours page */
    protected static function target(string $slug, string $postType): string
    {
        $pages = get_option('sta_pages', []);
        if (! empty($pages[$slug]) && get_post_status($pages[$slug]) === 'publish') {
            return (string) get_permalink($pages[$slug]);
        }

        return (string) (get_post_type_archive_link($postType) ?: home_url('/'));
    }

    /** Price range of the published offers of a type */
    protected static function prices(string $postType): array
    {
        global $wpdb;
        $r = $wpdb->get_row($wpdb->prepare(
            "SELECT MIN(CAST(m.meta_value AS DECIMAL(12,2))) lo, MAX(CAST(m.meta_value AS DECIMAL(12,2))) hi FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
             WHERE m.meta_key = '_sta_price' AND m.meta_value <> '' AND m.meta_value > 0 AND p.post_type = %s AND p.post_status = 'publish'", $postType
        ));
        $lo = (int) floor((float) ($r->lo ?? 0));
        $hi = (int) ceil((float) ($r->hi ?? 0));

        return $hi > $lo ? [(int) (floor($lo / 10) * 10), (int) (ceil($hi / 10) * 10)] : [0, 0];
    }

    /** Terms of a taxonomy used by the offers of a post type: [slug => [name, count, parent slug]] */
    /** @var int[]|null restricts terms() to these offers (apartments only) */
    protected static ?array $only = null;

    protected static function terms(string $tax, string $postType): array
    {
        $ids = self::$only ?? get_posts(['post_type' => $postType, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'no_found_rows' => true]);
        if (! $ids) {
            return [];
        }
        $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => true, 'object_ids' => $ids]);
        if (is_wp_error($terms)) {
            return [];
        }
        $out = [];
        foreach ($terms as $t) {
            // count only this post type (a destination can have tours and hotels)
            $n = count(array_intersect($ids, get_objects_in_term($t->term_id, $tax) ?: []));
            $out[] = ['slug' => rawurldecode($t->slug), 'name' => sta_lang() === 'en' ? (get_term_meta($t->term_id, 'name_en', true) ?: $t->name) : $t->name, 'n' => $n, 'id' => $t->term_id, 'parent' => $t->parent];
        }
        usort($out, fn ($a, $b) => strcoll($a['name'], $b['name']));

        return $out;
    }

    /** Own accommodation without hotels (apartments, villas, studios…) when the hotels come from a booking system */
    protected static function local_ids(bool $withoutHotels = true): array
    {
        static $cache = [];
        $key = (int) $withoutHotels;
        if (! isset($cache[$key])) {
            $args = ['post_type' => 'sta_hotel', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'no_found_rows' => true];
            if ($withoutHotels) {
                $args['tax_query'] = [['taxonomy' => 'sta_hotel_type', 'field' => 'slug', 'terms' => ['hotel'], 'operator' => 'NOT IN']];
            }
            $cache[$key] = get_posts($args);
        }

        return $cache[$key];
    }

    /** Suggestions for the "Destination" field: destinations, places, groups and the offers themselves */
    protected static function suggestions(string $postType, array $keys, ?array $only = null): array
    {
        $list = [];
        self::$only = $only;
        foreach (self::terms('sta_destination', $postType) as $t) {
            $list[] = ['k' => 'dest', 'v' => $t['slug'], 'l' => $t['name'], 's' => sprintf(_n('%d offer', '%d offers', $t['n'], 'seeinside-travel'), $t['n']), 'f' => $keys['dest']];
        }
        foreach (self::terms('sta_place', $postType) as $t) {
            $parent = $t['parent'] ? get_term($t['parent'], 'sta_place') : null;
            $list[] = ['k' => 'place', 'v' => $t['slug'], 'l' => $t['name'], 's' => trim(($parent instanceof WP_Term ? $parent->name.' · ' : '').sprintf(_n('%d offer', '%d offers', $t['n'], 'seeinside-travel'), $t['n']), ' ·'), 'f' => $keys['place']];
        }
        if (! empty($keys['group'])) {
            foreach (self::terms('sta_group', $postType) as $t) {
                $list[] = ['k' => 'group', 'v' => $t['slug'], 'l' => $t['name'], 's' => __('Category', 'seeinside-travel'), 'f' => $keys['group']];
            }
        }
        self::$only = null;
        foreach (get_posts(['post_type' => $postType, 'post_status' => 'publish', 'numberposts' => 300, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true] + ($only !== null ? ['post__in' => $only ?: [0]] : [])) as $p) {
            $list[] = ['k' => 'item', 'l' => get_the_title($p), 'u' => get_permalink($p), 's' => (string) (sta_data($p->ID)['place'] ?? sta_data($p->ID)['location'] ?? '')];
        }

        return $list;
    }

    public static function shortcode($atts = []): string
    {
        $a = shortcode_atts(['tabs' => 'hotels,apartments,tours,flights', 'active' => '', 'title' => '', 'subtitle' => '', 'hotels_label' => '', 'apartments_label' => '', 'tours_label' => '', 'flights_label' => '', 'compact' => ''], $atts, 'sta_search_box');
        $order = ['apartments', 'hotels', 'tours', 'flights'];
        $tabs = array_values(array_intersect(array_map('trim', explode(',', $a['tabs'])), $order));
        $api = STA_Hotels::enabled();
        // hotels and apartments are always separate tabs (older shortcodes/widgets only had "hotels")
        if (count($tabs) > 1 && in_array('hotels', $tabs, true) && ! in_array('apartments', $tabs, true)) {
            $tabs[] = 'apartments';
        }
        $tabs = array_values(array_intersect($order, $tabs));
        // hotels: only from the booking system (Hotelbeds…)
        if (! $api) {
            $tabs = array_values(array_diff($tabs, ['hotels']));
        }
        // apartments: all own accommodation from the app (apartments, studios, villas and own hotels)
        if (in_array('apartments', $tabs, true) && ! self::local_ids(false)) {
            $tabs = array_values(array_diff($tabs, ['apartments']));
        }
        if (! wp_count_posts('sta_tour')->publish) {
            $tabs = array_values(array_diff($tabs, ['tours']));
        }
        if (! $tabs) {
            return '';
        }
        $active = in_array($a['active'], $tabs, true) ? $a['active'] : $tabs[0];
        wp_enqueue_style('sta');
        wp_enqueue_script('sta');
        if (in_array('hotels', $tabs, true) && $api) {
            STA_Hotels::enqueue();
        }
        $labels = [
            'hotels' => $a['hotels_label'] ?: __('Hotels', 'seeinside-travel'),
            'apartments' => $a['apartments_label'] ?: __('Apartments', 'seeinside-travel'),
            'tours' => $a['tours_label'] ?: __('Tours', 'seeinside-travel'),
            'flights' => $a['flights_label'] ?: __('Flights', 'seeinside-travel'),
        ];

        $h = '<div class="sta sta-sbox'.($a['compact'] ? ' sta-sbox--compact' : '').'" data-sta-sbox>';
        if ($a['title'] !== '' || $a['subtitle'] !== '') {
            $h .= '<div class="sta-sbox-head">'.($a['title'] !== '' ? '<h2>'.esc_html($a['title']).'</h2>' : '').($a['subtitle'] !== '' ? '<p>'.esc_html($a['subtitle']).'</p>' : '').'</div>';
        }
        if (count($tabs) > 1) {
            $h .= '<div class="sta-sbox-tabs" role="tablist">';
            foreach ($tabs as $t) {
                $h .= '<button type="button" role="tab" data-tab="'.$t.'" aria-selected="'.($t === $active ? 'true' : 'false').'">'.esc_html($labels[$t]).'</button>';
            }
            $h .= '</div>';
        }
        foreach ($tabs as $t) {
            $h .= '<div class="sta-sbox-panel" data-panel="'.$t.'"'.($t === $active ? '' : ' hidden').'>';
            $h .= match ($t) {
                'hotels' => self::hotels_api(),
                'apartments' => self::hotels(),
                'tours' => self::tours(),
                default => self::flights(),
            };
            $h .= '</div>';
        }

        return $h.'</div>';
    }

    /* ---------- pieces ---------- */

    protected static function dest_field(array $items, array $hidden, string $qName): string
    {
        $h = '<div class="sta-sb-f sta-sb-dest" data-sb-dest>'.sta_icon('pin')
            .'<label class="sta-sb-lab"><span>'.esc_html__('Destination', 'seeinside-travel').'</span>'
            .'<input type="text" class="sta-sb-q" name="'.esc_attr($qName).'" placeholder="'.esc_attr__('Where are you going?', 'seeinside-travel').'" autocomplete="off" spellcheck="false" data-sb-q></label>';
        foreach ($hidden as $name) {
            $h .= '<input type="hidden" name="'.esc_attr($name).'" data-sb-h="'.esc_attr($name).'">';
        }

        return $h.'<div class="sta-sb-pop sta-sb-sugg" data-sb-sugg hidden></div>'
            .'<script type="application/json" data-sb-items>'.wp_json_encode($items, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP).'</script></div>';
    }

    protected static function dates_field(string $label, string $from, string $to): string
    {
        return '<div class="sta-sb-f sta-sb-dates" data-sb-dates>'.sta_icon('calendar')
            .'<button type="button" class="sta-sb-lab sta-sb-btn" data-sb-open><span>'.esc_html($label).'</span><b data-sb-dates-val>'.esc_html__('dd.mm.yyyy - dd.mm.yyyy', 'seeinside-travel').'</b></button>'
            .'<input type="hidden" name="'.esc_attr($from).'" data-sb-from><input type="hidden" name="'.esc_attr($to).'" data-sb-to>'
            .'<div class="sta-sb-pop sta-sb-cal" data-sb-pop hidden><div class="sta-sb-cal-nav"><button type="button" data-sb-m="-1" aria-label="‹">‹</button><button type="button" data-sb-m="1" aria-label="›">›</button></div><div class="sta-sb-months" data-sb-months></div>'
            .'<div class="sta-sb-cal-foot"><button type="button" class="sta-sb-link" data-sb-clear>'.esc_html__('Clear', 'seeinside-travel').'</button><button type="button" class="sta-btn sta-btn-sm" data-sb-done>'.esc_html__('Done', 'seeinside-travel').'</button></div></div></div>';
    }

    protected static function counter(string $label, string $name, int $value, int $min, int $max, string $hint = '', string $role = ''): string
    {
        $role = $role ?: ['f_ad' => 'ad', 'f_ch' => 'ch'][$name] ?? '';

        return '<div class="sta-sb-count"><span>'.esc_html($label).($hint ? '<small>'.esc_html($hint).'</small>' : '').'</span><div>'
            .'<button type="button" data-sb-step="-1" aria-label="−">−</button><input type="number" name="'.esc_attr($name).'" value="'.$value.'" min="'.$min.'" max="'.$max.'" readonly tabindex="-1"'.($role ? ' data-sb-role="'.esc_attr($role).'"' : '').'><button type="button" data-sb-step="1" aria-label="+">+</button></div></div>';
    }

    protected static function checks(string $title, string $name, array $opts): string
    {
        if (! $opts) {
            return '';
        }
        $h = '<div class="sta-sb-adv-g"><h5>'.esc_html($title).'</h5><div class="sta-sb-checks">';
        foreach ($opts as $v => $l) {
            $h .= '<label><input type="checkbox" name="'.esc_attr($name).'[]" value="'.esc_attr((string) $v).'"> <span>'.esc_html($l).'</span></label>';
        }

        return $h.'</div></div>';
    }

    protected static function price(array $range, string $min, string $max): string
    {
        [$lo, $hi] = $range;
        if ($hi <= $lo) {
            return '';
        }
        $cur = sta_money(0, (get_option('sta_meta', [])['agency']['currency'] ?? 'EUR'));
        $sym = trim(preg_replace('/[\d.,\s]+/u', '', $cur)) ?: '€';

        return '<div class="sta-sb-adv-g sta-sb-price" data-sb-price data-lo="'.$lo.'" data-hi="'.$hi.'" data-sym="'.esc_attr($sym).'"><h5>'.esc_html__('Price', 'seeinside-travel').' <output data-sb-price-out>'.esc_html($sym.$lo.' – '.$sym.$hi).'</output></h5>'
            .'<div class="sta-sb-range"><div class="sta-sb-range-fill" data-sb-fill></div><input type="range" min="'.$lo.'" max="'.$hi.'" step="5" value="'.$lo.'" data-sb-lo aria-label="'.esc_attr__('Minimum price', 'seeinside-travel').'"><input type="range" min="'.$lo.'" max="'.$hi.'" step="5" value="'.$hi.'" data-sb-hi aria-label="'.esc_attr__('Maximum price', 'seeinside-travel').'"></div>'
            .'<input type="hidden" name="'.esc_attr($min).'" data-sb-pmin disabled><input type="hidden" name="'.esc_attr($max).'" data-sb-pmax disabled></div>';
    }

    protected static function adv_field(string $inner): string
    {
        if ($inner === '') {
            return '';
        }

        return '<div class="sta-sb-f sta-sb-adv" data-sb-adv><button type="button" class="sta-sb-lab sta-sb-btn" data-sb-open><span>'.esc_html__('Advanced', 'seeinside-travel').'</span><b>'.esc_html__('More', 'seeinside-travel').' <i class="sta-sb-caret"></i></b></button>'
            .'<div class="sta-sb-pop sta-sb-adv-pop" data-sb-pop hidden>'.$inner.'<div class="sta-sb-cal-foot"><button type="button" class="sta-sb-link" data-sb-reset>'.esc_html__('Clear', 'seeinside-travel').'</button><button type="button" class="sta-btn sta-btn-sm" data-sb-done>'.esc_html__('Done', 'seeinside-travel').'</button></div></div></div>';
    }

    protected static function opts(array $terms): array
    {
        $o = [];
        foreach ($terms as $t) {
            $o[$t['slug']] = $t['name'];
        }

        return $o;
    }

    /** Own accommodation (apartments, villas…; with hotels when there is no hotel booking system) – the page with filters */
    /** Own accommodation from the app: apartments, studios, villas, own hotels… with the type to choose */
    protected static function hotels(): string
    {
        $only = null;
        $items = self::suggestions('sta_hotel', ['dest' => 'f_dest', 'place' => 'f_place', 'group' => 'f_group'], $only);
        $h = '<form class="sta-sbox-form" method="get" action="'.esc_url(self::target('apartmani', 'sta_hotel')).'" data-sb-form>';
        $h .= self::dest_field($items, ['f_dest', 'f_place', 'f_group[]'], 'f_q');
        $types = self::opts(self::terms('sta_hotel_type', 'sta_hotel'));
        if (count($types) > 1) {
            $o = '<option value="">'.esc_html__('All types', 'seeinside-travel').'</option>';
            foreach ($types as $slug => $name) {
                $o .= '<option value="'.esc_attr($slug).'">'.esc_html($name).'</option>';
            }
            $h .= '<div class="sta-sb-f sta-sb-type">'.sta_icon('home')
                .'<label class="sta-sb-lab"><span>'.esc_html__('Type', 'seeinside-travel').'</span><select name="f_type[]" data-sb-type aria-label="'.esc_attr__('Type of accommodation', 'seeinside-travel').'">'.$o.'</select></label></div>';
        }
        $h .= self::dates_field(__('Check-in – out', 'seeinside-travel'), 'f_from', 'f_to');
        $h .= '<div class="sta-sb-f sta-sb-guests" data-sb-guests>'.sta_icon('users')
            .'<button type="button" class="sta-sb-lab sta-sb-btn" data-sb-open><span>'.esc_html__('Guests', 'seeinside-travel').'</span><b data-sb-guests-val></b><i class="sta-sb-caret"></i></button>'
            .'<div class="sta-sb-pop sta-sb-guests-pop" data-sb-pop hidden>'
            .self::counter(__('Adults', 'seeinside-travel'), 'f_ad', 2, 1, 20)
            .self::counter(__('Children', 'seeinside-travel'), 'f_ch', 0, 0, 10, __('up to 12 years', 'seeinside-travel'))
            .'</div></div>';
        $stars = [];
        $boards = [];
        $names = ['RO' => __('Room only', 'seeinside-travel'), 'BB' => __('Bed & breakfast', 'seeinside-travel'), 'HB' => __('Half board', 'seeinside-travel'), 'FB' => __('Full board', 'seeinside-travel'), 'AI' => 'All inclusive', 'UAI' => 'Ultra all inclusive'];
        foreach ($only ?? get_posts(['post_type' => 'sta_hotel', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'no_found_rows' => true]) as $id) {
            $d = sta_data($id);
            if (! empty($d['stars'])) {
                $stars[(int) $d['stars']] = str_repeat('★', (int) $d['stars']);
            }
            foreach (array_merge((array) ($d['board_types'] ?? []), array_map(fn ($r) => (string) ($r['board']['value'] ?? ''), (array) ($d['rooms'] ?? []))) as $b) {
                $b = strtoupper((string) $b);
                if ($b !== '') {
                    $boards[$b] = $names[$b] ?? $b;
                }
            }
        }
        krsort($stars);
        self::$only = $only;
        $adv = self::price(self::prices('sta_hotel'), 'f_pmin', 'f_price')
            .self::checks(__('Stars / category', 'seeinside-travel'), 'f_stars', $stars)
            .self::checks(__('Board', 'seeinside-travel'), 'f_board', $boards)
            .self::checks(__('Group', 'seeinside-travel'), 'f_group', self::opts(self::terms('sta_group', 'sta_hotel')));
        self::$only = null;
        $h .= self::adv_field($adv);

        return $h.'<button type="submit" class="sta-sb-go">'.esc_html__('Find offers', 'seeinside-travel').'</button></form>';
    }

    protected static function tours(): string
    {
        $items = self::suggestions('sta_tour', ['dest' => 'sta_dest', 'place' => 'sta_place', 'group' => 'sta_group']);
        $h = '<form class="sta-sbox-form" method="get" action="'.esc_url(self::target('aranzmani', 'sta_tour')).'" data-sb-form>';
        $h .= self::dest_field($items, ['sta_dest', 'sta_place', 'sta_group[]'], 'sta_q');
        $h .= self::dates_field(__('From – To', 'seeinside-travel'), 'sta_from', 'sta_to');
        $adv = self::price(self::prices('sta_tour'), 'sta_min', 'sta_max')
            .self::checks(__('Tour categories', 'seeinside-travel'), 'sta_group', self::opts(self::terms('sta_group', 'sta_tour')))
            .self::checks(__('Tour type', 'seeinside-travel'), 'sta_types', self::opts(self::terms('sta_tour_type', 'sta_tour')));
        $h .= self::adv_field($adv);

        return $h.'<button type="submit" class="sta-sb-go">'.esc_html__('Find offers', 'seeinside-travel').'</button></form>';
    }

    /** Guests for hotels: rooms, adults per room, children per room with their ages */
    protected static function guests_rooms(): string
    {
        $ages = '';
        for ($i = 0; $i <= 17; $i++) {
            $ages .= '<option value="'.$i.'">'.$i.'</option>';
        }

        return '<div class="sta-sb-f sta-sb-guests" data-sb-guests data-sb-rooms-mode>'.sta_icon('users')
            .'<button type="button" class="sta-sb-lab sta-sb-btn" data-sb-open><span>'.esc_html__('Guests', 'seeinside-travel').'</span><b data-sb-guests-val></b><i class="sta-sb-caret"></i></button>'
            .'<div class="sta-sb-pop sta-sb-guests-pop" data-sb-pop hidden>'
            .self::counter(__('Rooms', 'seeinside-travel'), 'room_count', 1, 1, 5, '', 'rooms')
            .self::counter(__('Adults', 'seeinside-travel'), 'adults', 2, 1, 8, __('per room', 'seeinside-travel'), 'ad')
            .self::counter(__('Children', 'seeinside-travel'), 'children', 0, 0, 4, __('per room', 'seeinside-travel'), 'ch')
            .'<div class="sta-sb-ages" data-sb-ages hidden><small>'.esc_html__('Age of each child at check-out', 'seeinside-travel').'</small><div data-sb-ages-list></div>'
            .'<template data-sb-age-tpl><label><span></span><select>'.$ages.'</select></label></template></div>'
            .'<input type="hidden" name="ages" data-sb-ages-value>'
            .'</div></div>';
    }

    /** Hotels from the booking systems (live prices) → the hotel results page */
    protected static function hotels_api(): string
    {
        $items = [];
        foreach (STA_Hotels::destinations() as $d) {
            $items[] = ['k' => 'dest', 'v' => $d['id'], 'l' => $d['name'], 's' => trim(($d['country'] ?? '').(! empty($d['hotels']) ? ' · '.sprintf(_n('%d hotel', '%d hotels', (int) $d['hotels'], 'seeinside-travel'), (int) $d['hotels']) : ''), ' ·'), 'f' => 'destination'];
        }
        $h = '<form class="sta-sbox-form" method="get" action="'.esc_url(STA_Hotels::page_url()).'" data-sb-form data-sb-hotels>';
        $h .= str_replace('data-sb-dest>', 'data-sb-dest data-sb-remote="hotels/suggest">', self::dest_field($items, ['destination', 'hotel'], ''));
        $h .= self::dates_field(__('Check-in – out', 'seeinside-travel'), 'from', 'to');
        $h .= self::guests_rooms();
        $adv = self::checks(__('Stars', 'seeinside-travel'), 'h_stars', [5 => '★★★★★', 4 => '★★★★', 3 => '★★★', 2 => '★★'])
            .self::checks(__('Board', 'seeinside-travel'), 'h_board', ['RO' => __('Room only', 'seeinside-travel'), 'BB' => __('Bed & breakfast', 'seeinside-travel'), 'HB' => __('Half board', 'seeinside-travel'), 'FB' => __('Full board', 'seeinside-travel'), 'AI' => 'All inclusive']);
        $h .= self::adv_field($adv);

        return $h.'<button type="submit" class="sta-sb-go">'.esc_html__('Find offers', 'seeinside-travel').'</button></form>';
    }

    /** Airport field: suggestions from the list below, or any 3-letter code */
    /** Departure airports of the agency (Skopje, Thessaloniki, Sofia, Belgrade) – filter sta_departure_airports */
    protected static function departures(): array
    {
        return (array) apply_filters('sta_departure_airports', ['SKP', 'SKG', 'SOF', 'BEG']);
    }

    /**
     * Airport field: "from" = only the departure airports, "to" = the whole world
     * (assets/data/airports.json, about 3,250 airports with scheduled flights, loaded on the first click).
     */
    protected static function airport_field(string $name, string $label, string $placeholder, string $default = '', bool $world = false): string
    {
        $codes = $world ? (array) apply_filters('sta_popular_airports', ['IST', 'VIE', 'LON', 'PAR', 'ROM', 'MIL', 'BER', 'ZRH', 'DXB', 'NYC']) : self::departures();
        $byCode = [];
        foreach (self::airports() as $a) {
            $byCode[$a[0]] = $a;
        }
        $items = [];
        $label_default = '';
        foreach ($codes as $code) {
            [$code, $city, $airport, $country] = $byCode[$code] ?? [$code, $code, '', ''];
            $items[] = ['k' => 'air', 'v' => $code, 'l' => $city.' ('.$code.')', 's' => trim($airport.', '.$country, ', '), 'f' => $name];
            if ($code === $default) {
                $label_default = $city.' ('.$code.')';
            }
        }

        return '<div class="sta-sb-f sta-sb-dest sta-sb-air" data-sb-dest'.($world ? ' data-sb-world="'.esc_url(STA_URL.'assets/data/airports.json?ver='.STA_VERSION).'"' : ' data-sb-strict').'>'.sta_icon('plane')
            .'<label class="sta-sb-lab"><span>'.esc_html($label).'</span>'
            .'<input type="text" class="sta-sb-q" placeholder="'.esc_attr($placeholder).'" autocomplete="off" spellcheck="false" data-sb-q value="'.esc_attr($label_default).'"'.($world ? '' : ' readonly').'></label>'
            .'<input type="hidden" name="'.esc_attr($name).'" data-sb-h="'.esc_attr($name).'" value="'.esc_attr($default).'">'
            .'<div class="sta-sb-pop sta-sb-sugg" data-sb-sugg hidden></div>'
            .'<script type="application/json" data-sb-items>'.wp_json_encode($items, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP).'</script></div>';
    }

    /**
     * Flights: the search runs on the GOL IBE site of the agency (aviokarti.golibe.com) –
     * /results?from=SKP&to=IST&departureDate=…&returnDate=…&ADT=1&CHD=0&INF=0&flightClass=ECO
     */
    protected static function flights(): string
    {
        $base = (string) (get_option('sta_meta', [])['flights']['search_url'] ?? '');
        if (! preg_match('#^https?://[^/]+\.golibe\.com#i', $base)) {
            $base = 'https://aviokarti.golibe.com/';
        }
        $base = preg_replace('#^(https?://[^/]+).*$#i', '$1', $base).'/results';

        $h = '<form class="sta-sbox-form sta-sb-flights" method="get" action="'.esc_url($base).'" target="_blank" data-sb-form data-sb-flights>';
        $h .= '<div class="sta-sb-trip"><label><input type="radio" name="sb_trip" value="return" checked data-sb-trip> '.esc_html__('Return trip', 'seeinside-travel').'</label>'
            .'<label><input type="radio" name="sb_trip" value="oneway" data-sb-trip data-sb-oneway> '.esc_html__('One way', 'seeinside-travel').'</label></div>';
        $h .= self::airport_field('from', __('From', 'seeinside-travel'), __('City or airport', 'seeinside-travel'), self::departures()[0] ?? 'SKP');
        $h .= self::airport_field('to', __('To', 'seeinside-travel'), __('City or airport', 'seeinside-travel'), '', true);
        $h .= self::dates_field(__('Departure – return', 'seeinside-travel'), 'departureDate', 'returnDate');
        $h .= '<div class="sta-sb-f sta-sb-guests" data-sb-guests data-sb-flight-pax>'.sta_icon('users')
            .'<button type="button" class="sta-sb-lab sta-sb-btn" data-sb-open><span>'.esc_html__('Passengers / class', 'seeinside-travel').'</span><b data-sb-guests-val></b><i class="sta-sb-caret"></i></button>'
            .'<div class="sta-sb-pop sta-sb-guests-pop" data-sb-pop hidden>'
            .self::counter(__('Adults', 'seeinside-travel'), 'ADT', 1, 1, 9, __('12+ years', 'seeinside-travel'), 'ad')
            .self::counter(__('Children', 'seeinside-travel'), 'CHD', 0, 0, 8, __('2–11 years', 'seeinside-travel'), 'ch')
            .self::counter(__('Infants', 'seeinside-travel'), 'INF', 0, 0, 4, __('under 2 years', 'seeinside-travel'), 'inf')
            .'<p class="sta-sb-note">'.esc_html__('Maximum 9 passengers', 'seeinside-travel').'</p>'
            .'<div class="sta-sb-checks sta-sb-class"><label><input type="checkbox" value="BUS" data-sb-business> <span>'.esc_html__('Business class', 'seeinside-travel').'</span></label></div>'
            .'<input type="hidden" name="flightClass" value="ECO" data-sb-class>'
            .'<input type="hidden" name="lang" value="'.esc_attr(sta_lang() === 'en' ? 'en' : 'mk').'">'
            .'</div></div>';

        return $h.'<button type="submit" class="sta-sb-go">'.esc_html__('Find offers', 'seeinside-travel').'</button></form>';
    }

    /** Airports for the suggestions (any other airport works with its 3-letter code) */
    protected static function airports(): array
    {
        return apply_filters('sta_airports', [
            ['SKP', 'Скопје', 'Аеродром Скопје', 'Македонија'], ['OHD', 'Охрид', 'Аеродром Охрид', 'Македонија'],
            ['SOF', 'Софија', 'Sofia', 'Бугарија'], ['BEG', 'Белград', 'Nikola Tesla', 'Србија'], ['INI', 'Ниш', 'Constantine the Great', 'Србија'], ['PRN', 'Приштина', 'Adem Jashari', 'Косово'],
            ['TIA', 'Тирана', 'Nënë Tereza', 'Албанија'], ['SKG', 'Солун', 'Makedonia', 'Грција'], ['ATH', 'Атина', 'Eleftherios Venizelos', 'Грција'], ['TGD', 'Подгорица', 'Podgorica', 'Црна Гора'],
            ['TIV', 'Тиват', 'Tivat', 'Црна Гора'], ['ZAG', 'Загреб', 'Franjo Tuđman', 'Хрватска'], ['SPU', 'Сплит', 'Split', 'Хрватска'], ['DBV', 'Дубровник', 'Dubrovnik', 'Хрватска'], ['LJU', 'Љубљана', 'Jože Pučnik', 'Словенија'],
            ['SJJ', 'Сараево', 'Sarajevo', 'БиХ'], ['IST', 'Истанбул', 'Istanbul Airport', 'Турција'], ['SAW', 'Истанбул', 'Sabiha Gökçen', 'Турција'], ['AYT', 'Анталија', 'Antalya', 'Турција'],
            ['ADB', 'Измир', 'Adnan Menderes', 'Турција'], ['BJV', 'Бодрум', 'Milas-Bodrum', 'Турција'], ['DLM', 'Даламан', 'Dalaman', 'Турција'],
            ['HER', 'Ираклион (Крит)', 'Heraklion', 'Грција'], ['CHQ', 'Ханиа (Крит)', 'Chania', 'Грција'], ['RHO', 'Родос', 'Rhodes', 'Грција'], ['CFU', 'Крф', 'Corfu', 'Грција'], ['JTR', 'Санторини', 'Santorini', 'Грција'], ['JMK', 'Миконос', 'Mykonos', 'Грција'], ['ZTH', 'Закинтос', 'Zakynthos', 'Грција'], ['KGS', 'Кос', 'Kos', 'Грција'], ['PVK', 'Превеза (Лефкада)', 'Aktion', 'Грција'],
            ['VIE', 'Виена', 'Vienna', 'Австрија'], ['SZG', 'Салцбург', 'Salzburg', 'Австрија'], ['BUD', 'Будимпешта', 'Ferenc Liszt', 'Унгарија'], ['PRG', 'Прага', 'Václav Havel', 'Чешка'], ['WAW', 'Варшава', 'Chopin', 'Полска'], ['KRK', 'Краков', 'Kraków', 'Полска'],
            ['BER', 'Берлин', 'Brandenburg', 'Германија'], ['MUC', 'Минхен', 'Munich', 'Германија'], ['FRA', 'Франкфурт', 'Frankfurt', 'Германија'], ['DUS', 'Диселдорф', 'Düsseldorf', 'Германија'], ['HAM', 'Хамбург', 'Hamburg', 'Германија'], ['STR', 'Штутгарт', 'Stuttgart', 'Германија'], ['CGN', 'Келн', 'Köln/Bonn', 'Германија'], ['NUE', 'Нирнберг', 'Nürnberg', 'Германија'], ['DTM', 'Дортмунд', 'Dortmund', 'Германија'], ['MMX', 'Малме', 'Malmö', 'Шведска'],
            ['ZRH', 'Цирих', 'Zürich', 'Швајцарија'], ['GVA', 'Женева', 'Genève', 'Швајцарија'], ['BSL', 'Базел', 'EuroAirport', 'Швајцарија'],
            ['PAR', 'Париз', 'сите аеродроми', 'Франција'], ['CDG', 'Париз', 'Charles de Gaulle', 'Франција'], ['ORY', 'Париз', 'Orly', 'Франција'], ['NCE', 'Ница', 'Côte d’Azur', 'Франција'], ['LYS', 'Лион', 'Saint-Exupéry', 'Франција'],
            ['LON', 'Лондон', 'сите аеродроми', 'Велика Британија'], ['LHR', 'Лондон', 'Heathrow', 'Велика Британија'], ['LGW', 'Лондон', 'Gatwick', 'Велика Британија'], ['STN', 'Лондон', 'Stansted', 'Велика Британија'], ['LTN', 'Лондон', 'Luton', 'Велика Британија'], ['MAN', 'Манчестер', 'Manchester', 'Велика Британија'],
            ['AMS', 'Амстердам', 'Schiphol', 'Холандија'], ['EIN', 'Ајндховен', 'Eindhoven', 'Холандија'], ['BRU', 'Брисел', 'Brussels', 'Белгија'], ['CRL', 'Брисел', 'Charleroi', 'Белгија'], ['CPH', 'Копенхаген', 'Kastrup', 'Данска'], ['OSL', 'Осло', 'Gardermoen', 'Норвешка'], ['ARN', 'Стокхолм', 'Arlanda', 'Шведска'], ['GOT', 'Гетеборг', 'Landvetter', 'Шведска'], ['HEL', 'Хелсинки', 'Vantaa', 'Финска'],
            ['ROM', 'Рим', 'сите аеродроми', 'Италија'], ['FCO', 'Рим', 'Fiumicino', 'Италија'], ['CIA', 'Рим', 'Ciampino', 'Италија'], ['MIL', 'Милано', 'сите аеродроми', 'Италија'], ['MXP', 'Милано', 'Malpensa', 'Италија'], ['BGY', 'Милано', 'Bergamo', 'Италија'], ['VCE', 'Венеција', 'Marco Polo', 'Италија'], ['TSF', 'Венеција', 'Treviso', 'Италија'], ['BLQ', 'Болоња', 'Bologna', 'Италија'], ['NAP', 'Неапол', 'Napoli', 'Италија'], ['PSA', 'Пиза', 'Pisa', 'Италија'], ['FLR', 'Фиренца', 'Firenze', 'Италија'], ['BRI', 'Бари', 'Bari', 'Италија'], ['CTA', 'Катанија', 'Catania', 'Италија'],
            ['BCN', 'Барселона', 'El Prat', 'Шпанија'], ['MAD', 'Мадрид', 'Barajas', 'Шпанија'], ['PMI', 'Палма де Мајорка', 'Palma', 'Шпанија'], ['AGP', 'Малага', 'Málaga', 'Шпанија'], ['VLC', 'Валенсија', 'Valencia', 'Шпанија'], ['TFS', 'Тенерифе', 'Tenerife Sur', 'Шпанија'], ['LIS', 'Лисабон', 'Lisboa', 'Португалија'], ['OPO', 'Порто', 'Porto', 'Португалија'],
            ['MLA', 'Малта', 'Malta', 'Малта'], ['LCA', 'Ларнака', 'Larnaca', 'Кипар'], ['PFO', 'Пафос', 'Paphos', 'Кипар'], ['DUB', 'Даблин', 'Dublin', 'Ирска'], ['OTP', 'Букурешт', 'Henri Coandă', 'Романија'], ['VAR', 'Варна', 'Varna', 'Бугарија'], ['BOJ', 'Бургас', 'Burgas', 'Бугарија'],
            ['DXB', 'Дубаи', 'Dubai', 'ОАЕ'], ['AUH', 'Абу Даби', 'Abu Dhabi', 'ОАЕ'], ['DOH', 'Доха', 'Hamad', 'Катар'], ['HRG', 'Хургада', 'Hurghada', 'Египет'], ['SSH', 'Шарм ел Шеик', 'Sharm el-Sheikh', 'Египет'], ['CAI', 'Каиро', 'Cairo', 'Египет'], ['TUN', 'Тунис', 'Carthage', 'Тунис'], ['RAK', 'Маракеш', 'Menara', 'Мароко'],
            ['TLV', 'Тел Авив', 'Ben Gurion', 'Израел'], ['MLE', 'Мале', 'Velana', 'Малдиви'], ['BKK', 'Бангкок', 'Suvarnabhumi', 'Тајланд'], ['HKT', 'Пукет', 'Phuket', 'Тајланд'], ['SIN', 'Сингапур', 'Changi', 'Сингапур'], ['DPS', 'Бали', 'Ngurah Rai', 'Индонезија'], ['PEK', 'Пекинг', 'Capital', 'Кина'], ['HKG', 'Хонг Конг', 'Hong Kong', 'Кина'], ['NRT', 'Токио', 'Narita', 'Јапонија'], ['DEL', 'Делхи', 'Indira Gandhi', 'Индија'],
            ['NYC', 'Њујорк', 'сите аеродроми', 'САД'], ['JFK', 'Њујорк', 'John F. Kennedy', 'САД'], ['EWR', 'Њујорк', 'Newark', 'САД'], ['ORD', 'Чикаго', 'O’Hare', 'САД'], ['LAX', 'Лос Анџелес', 'Los Angeles', 'САД'], ['MIA', 'Мајами', 'Miami', 'САД'], ['DTW', 'Детроит', 'Detroit', 'САД'], ['YYZ', 'Торонто', 'Pearson', 'Канада'],
            ['SYD', 'Сиднеј', 'Kingsford Smith', 'Австралија'], ['MEL', 'Мелбурн', 'Tullamarine', 'Австралија'], ['PER', 'Перт', 'Perth', 'Австралија'], ['AKL', 'Окланд', 'Auckland', 'Нов Зеланд'],
        ]);
    }
}
