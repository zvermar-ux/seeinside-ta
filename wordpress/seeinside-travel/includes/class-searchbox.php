<?php

defined('ABSPATH') || exit;

/**
 * Big search box with tabs (Hotels & apartments · Tours · Flights), for the homepage hero or anywhere:
 * [sta_search_box tabs="hotels,tours,flights" active="hotels" title="" subtitle=""]
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
    protected static function terms(string $tax, string $postType): array
    {
        $ids = get_posts(['post_type' => $postType, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'no_found_rows' => true]);
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

    /** Suggestions for the "Destination" field: destinations, places, groups and the offers themselves */
    protected static function suggestions(string $postType, array $keys): array
    {
        $list = [];
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
        foreach (get_posts(['post_type' => $postType, 'post_status' => 'publish', 'numberposts' => 300, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true]) as $p) {
            $list[] = ['k' => 'item', 'l' => get_the_title($p), 'u' => get_permalink($p), 's' => (string) (sta_data($p->ID)['place'] ?? sta_data($p->ID)['location'] ?? '')];
        }

        return $list;
    }

    public static function shortcode($atts = []): string
    {
        $a = shortcode_atts(['tabs' => 'hotels,tours,flights', 'active' => '', 'title' => '', 'subtitle' => '', 'hotels_label' => '', 'tours_label' => '', 'flights_label' => ''], $atts, 'sta_search_box');
        $tabs = array_values(array_intersect(array_map('trim', explode(',', $a['tabs'])), ['hotels', 'tours', 'flights']));
        $flights = in_array('flights', $tabs, true) ? STA_Frontend::sc_flights([]) : '';
        if ($flights === '') {
            $tabs = array_values(array_diff($tabs, ['flights']));
        }
        if (! wp_count_posts('sta_hotel')->publish) {
            $tabs = array_values(array_diff($tabs, ['hotels']));
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
        $labels = ['hotels' => $a['hotels_label'] ?: __('Hotels & apartments', 'seeinside-travel'), 'tours' => $a['tours_label'] ?: __('Tours', 'seeinside-travel'), 'flights' => $a['flights_label'] ?: __('Flights', 'seeinside-travel')];

        $h = '<div class="sta sta-sbox" data-sta-sbox>';
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
                'hotels' => self::hotels(),
                'tours' => self::tours(),
                default => '<div class="sta-sbox-flights">'.$flights.'</div>',
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

    protected static function counter(string $label, string $name, int $value, int $min, int $max, string $hint = ''): string
    {
        return '<div class="sta-sb-count"><span>'.esc_html($label).($hint ? '<small>'.esc_html($hint).'</small>' : '').'</span><div>'
            .'<button type="button" data-sb-step="-1" aria-label="−">−</button><input type="number" name="'.esc_attr($name).'" value="'.$value.'" min="'.$min.'" max="'.$max.'" readonly tabindex="-1"><button type="button" data-sb-step="1" aria-label="+">+</button></div></div>';
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

    protected static function hotels(): string
    {
        $items = self::suggestions('sta_hotel', ['dest' => 'f_dest', 'place' => 'f_place', 'group' => 'f_group']);
        $h = '<form class="sta-sbox-form" method="get" action="'.esc_url(self::target('hoteli', 'sta_hotel')).'" data-sb-form>';
        $h .= self::dest_field($items, ['f_dest', 'f_place', 'f_group[]'], 'f_q');
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
        foreach (get_posts(['post_type' => 'sta_hotel', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'no_found_rows' => true]) as $id) {
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
        $adv = self::price(self::prices('sta_hotel'), 'f_pmin', 'f_price')
            .self::checks(__('Type of accommodation', 'seeinside-travel'), 'f_type', self::opts(self::terms('sta_hotel_type', 'sta_hotel')))
            .self::checks(__('Stars / category', 'seeinside-travel'), 'f_stars', $stars)
            .self::checks(__('Board', 'seeinside-travel'), 'f_board', $boards)
            .self::checks(__('Hotel theme', 'seeinside-travel'), 'f_group', self::opts(self::terms('sta_group', 'sta_hotel')));
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
}
