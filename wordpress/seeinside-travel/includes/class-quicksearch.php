<?php

defined('ABSPATH') || exit;

/**
 * Search from anywhere on the site: [sta_search_button] (e.g. in the header) opens a window with a quick search
 * (tours, apartments, hotels, events, visas, destinations – as you type) and the full search form below it.
 * Keys: "/" or Ctrl+K open it, Esc closes it.
 */
class STA_Quick_Search
{
    protected static bool $needed = false;

    public static function init(): void
    {
        add_action('init', fn () => add_shortcode('sta_search_button', [self::class, 'button']));
        add_action('wp_footer', [self::class, 'auto'], 1);
        add_action('wp_footer', [self::class, 'dialog'], 5);
        add_action('rest_api_init', fn () => register_rest_route('seeinside-travel/v1', '/quick', ['methods' => 'GET', 'callback' => [self::class, 'rest'], 'permission_callback' => '__return_true']));
        // the index follows the offers
        foreach (['save_post', 'deleted_post', 'created_term', 'edited_term', 'delete_term'] as $h) {
            add_action($h, fn () => delete_transient('sta_qs_index'));
        }
    }

    /** [sta_search_button style="icon|button|pill" label="Пребарај"] */
    public static function button($atts = []): string
    {
        $a = shortcode_atts(['style' => 'icon', 'label' => '', 'color' => ''], $atts, 'sta_search_button');
        self::$needed = true;
        wp_enqueue_style('sta');
        wp_enqueue_script('sta');
        $label = $a['label'] !== '' ? $a['label'] : __('Search', 'seeinside-travel');
        $style = in_array($a['style'], ['icon', 'button', 'pill'], true) ? $a['style'] : 'icon';
        $css = $a['color'] !== '' ? ' style="--qs-c:'.esc_attr($a['color']).'"' : '';

        return '<button type="button" class="sta-qs-open is-'.$style.'" data-sta-qs-open aria-haspopup="dialog" aria-label="'.esc_attr($label).'"'.$css.'>'
            .sta_icon('search').($style !== 'icon' ? '<span>'.esc_html($label).'</span>' : '').'</button>';
    }

    /** Settings → Display → "Search in the header": the magnifier is put into the theme header by itself */
    public static function auto(): void
    {
        if (! is_admin() && (get_option('sta_settings', [])['menu_more'] ?? '1') === '1') {
            echo '<span hidden data-sta-menu-more data-label="'.esc_attr__('More', 'seeinside-travel').'"></span>';
        }
        if (is_admin() || (get_option('sta_settings', [])['qs_header'] ?? 'auto') === 'off' || (isset($_GET['elementor-preview']))) {   // phpcs:ignore
            return;
        }
        echo '<template data-sta-qs-auto>'.self::button(['style' => 'icon']).'</template>';   // phpcs:ignore
    }

    /** The window (once per page, at the end) */
    public static function dialog(): void
    {
        if (! self::$needed) {
            return;
        }
        $box = class_exists('STA_Search_Box') ? STA_Search_Box::shortcode([]) : '';
        echo '<dialog class="sta-qs" data-sta-qs aria-label="'.esc_attr__('Search', 'seeinside-travel').'"><div class="sta-qs-in">'
            .'<button type="button" class="sta-qs-x" data-sta-qs-close aria-label="'.esc_attr__('Close', 'seeinside-travel').'">×</button>'
            .'<div class="sta-qs-field">'.sta_icon('search').'<input type="search" data-sta-qs-q placeholder="'.esc_attr__('Search tours, apartments, hotels, destinations…', 'seeinside-travel').'" autocomplete="off" enterkeyhint="search"></div>'
            .'<div class="sta-qs-res" data-sta-qs-res><p class="sta-qs-hint">'.esc_html__('Type a name, a place or a country – e.g. Lefkada, Istanbul, Nidri.', 'seeinside-travel').'</p></div>'
            .($box ? '<div class="sta-qs-box"><h3>'.esc_html__('Or search with dates', 'seeinside-travel').'</h3>'.$box.'</div>' : '')
            .'</div></dialog>';   // phpcs:ignore
    }

    /** Everything that can be found (cached 30 minutes, renewed when an offer changes) */
    protected static function index(): array
    {
        $idx = get_transient('sta_qs_index');
        if (is_array($idx)) {
            return $idx;
        }
        $idx = [];
        $labels = ['sta_tour' => __('Tours', 'seeinside-travel'), 'sta_hotel' => __('Apartments', 'seeinside-travel'), 'sta_event' => __('Events', 'seeinside-travel'), 'sta_visa' => __('Visas', 'seeinside-travel'), 'sta_offer' => __('Other offers', 'seeinside-travel')];
        $posts = get_posts(['post_type' => array_keys($labels), 'post_status' => 'publish', 'numberposts' => -1, 'no_found_rows' => true]);
        foreach ($posts as $p) {
            $places = array_merge(wp_list_pluck(get_the_terms($p, 'sta_destination') ?: [], 'name'), wp_list_pluck(get_the_terms($p, 'sta_place') ?: [], 'name'));
            $idx[] = ['g' => $p->post_type, 't' => get_the_title($p), 'u' => get_permalink($p), 'i' => (string) get_the_post_thumbnail_url($p, 'thumbnail'),
                'm' => implode(', ', array_slice(array_unique($places), 0, 2)), 'n' => STA_Finder::norm(get_the_title($p).' '.implode(' ', $places))];
        }
        foreach (['sta_destination' => __('Destinations', 'seeinside-travel'), 'sta_place' => __('Places', 'seeinside-travel'), 'sta_group' => __('Groups', 'seeinside-travel')] as $tax => $l) {
            $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => true]);
            foreach (is_wp_error($terms) ? [] : $terms as $t) {
                $link = get_term_link($t);
                if (is_wp_error($link)) {
                    continue;
                }
                $idx[] = ['g' => $tax, 't' => $t->name, 'u' => $link, 'i' => (string) get_term_meta($t->term_id, 'sta_image', true),
                    'm' => sprintf(_n('%d offer', '%d offers', (int) $t->count, 'seeinside-travel'), (int) $t->count), 'n' => STA_Finder::norm($t->name)];
            }
        }
        set_transient('sta_qs_index', $idx, 30 * MINUTE_IN_SECONDS);

        return $idx;
    }

    public static function rest(WP_REST_Request $r): WP_REST_Response
    {
        $q = STA_Finder::norm(sanitize_text_field((string) $r->get_param('q')));
        if (mb_strlen($q) < 2) {
            return new WP_REST_Response(['groups' => []]);
        }
        $words = array_filter(explode(' ', $q));
        $names = ['sta_destination' => __('Destinations', 'seeinside-travel'), 'sta_place' => __('Places', 'seeinside-travel'), 'sta_group' => __('Groups', 'seeinside-travel'),
            'sta_tour' => __('Tours', 'seeinside-travel'), 'sta_hotel' => __('Apartments', 'seeinside-travel'), 'sta_event' => __('Events', 'seeinside-travel'),
            'sta_visa' => __('Visas', 'seeinside-travel'), 'sta_offer' => __('Other offers', 'seeinside-travel')];
        $groups = [];
        foreach (self::index() as $it) {
            foreach ($words as $w) {
                if (! str_contains($it['n'], $w)) {
                    continue 2;
                }
            }
            // names that start with the search come first
            $score = str_starts_with($it['n'], $words[array_key_first($words)]) ? 0 : 1;
            $groups[$it['g']][] = ['title' => $it['t'], 'url' => $it['u'], 'image' => $it['i'], 'meta' => $it['m'], 's' => $score];
        }
        $out = [];
        foreach ($names as $g => $label) {
            if (empty($groups[$g])) {
                continue;
            }
            usort($groups[$g], fn ($a, $b) => [$a['s'], $a['title']] <=> [$b['s'], $b['title']]);
            $out[] = ['label' => $label, 'items' => array_slice(array_map(fn ($x) => array_diff_key($x, ['s' => 1]), $groups[$g]), 0, 6), 'more' => max(0, count($groups[$g]) - 6)];
        }

        return new WP_REST_Response(['groups' => $out, 'hotels' => class_exists('STA_Hotels') && STA_Hotels::enabled(), 'hotels_url' => class_exists('STA_Hotels') ? STA_Hotels::page_url() : '']);
    }
}
