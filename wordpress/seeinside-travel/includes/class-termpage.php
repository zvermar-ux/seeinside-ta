<?php

defined('ABSPATH') || exit;

/**
 * Pages of a group / destination / place / tour type / accommodation type:
 * [sta_term_header] – banner with photo, title, path (Greece › Lefkada) and description
 * [sta_term_offers] – sub-destinations / places, then the tours and the accommodation of this page
 */
class STA_Term_Page
{
    public static function init(): void
    {
        add_action('init', function () {
            add_shortcode('sta_term_header', [self::class, 'header']);
            add_shortcode('sta_term_offers', [self::class, 'offers']);
        });
    }

    /** The term of the page (or ?taxonomy=…&term=… in the Elementor editor preview) */
    public static function term(array $a = []): ?WP_Term
    {
        if (! empty($a['taxonomy']) && ! empty($a['term'])) {
            $t = get_term_by('slug', sanitize_title($a['term']), $a['taxonomy']);

            return $t instanceof WP_Term ? $t : null;
        }
        $qo = get_queried_object();
        if ($qo instanceof WP_Term && strpos($qo->taxonomy, 'sta_') === 0) {
            return $qo;
        }
        // Elementor editor: show the first term of the template's taxonomy
        if (! empty($a['preview_tax'])) {
            $first = get_terms(['taxonomy' => $a['preview_tax'], 'hide_empty' => true, 'number' => 1]);

            return $first && ! is_wp_error($first) ? $first[0] : null;
        }

        return null;
    }

    protected static function name(WP_Term $t): string
    {
        return sta_lang() === 'en' ? (get_term_meta($t->term_id, 'name_en', true) ?: $t->name) : $t->name;
    }

    public static function header($atts = []): string
    {
        $a = shortcode_atts(['taxonomy' => '', 'term' => '', 'preview_tax' => '', 'height' => 340], $atts, 'sta_term_header');
        $t = self::term($a);
        if (! $t) {
            return '';
        }
        wp_enqueue_style('sta');
        $img = get_term_meta($t->term_id, 'sta_image', true);
        $tax = get_taxonomy($t->taxonomy);
        $crumbs = [];
        foreach (array_reverse(get_ancestors($t->term_id, $t->taxonomy)) as $id) {
            $p = get_term($id, $t->taxonomy);
            if ($p instanceof WP_Term) {
                $crumbs[] = '<a href="'.esc_url(get_term_link($p)).'">'.esc_html(self::name($p)).'</a>';
            }
        }
        // a place shows its destination first: Greece › Lefkada › Nikiana
        if ($t->taxonomy === 'sta_place' && ($dest = self::destination_of_place($t))) {
            array_unshift($crumbs, '<a href="'.esc_url(get_term_link($dest)).'">'.esc_html(self::name($dest)).'</a>');
        }
        $desc = term_description($t);

        return '<header class="sta sta-term-head'.($img ? ' has-img' : '').'" style="--th-h:'.max(160, (int) $a['height']).'px'.($img ? ';background-image:url(\''.esc_url($img).'\')' : '').'">'
            .'<div class="sta-term-head-in">'
            .'<span class="sta-term-kind">'.esc_html($tax ? $tax->labels->singular_name : '').'</span>'
            .($crumbs ? '<nav class="sta-term-crumbs">'.implode(' › ', $crumbs).'</nav>' : '')
            .'<h1>'.esc_html(self::name($t)).'</h1>'
            .'<span class="sta-term-count">'.esc_html(sprintf(_n('%d offer', '%d offers', self::count($t), 'seeinside-travel'), self::count($t))).'</span>'
            .'</div></header>'
            .($desc ? '<div class="sta sta-term-desc sta-prose">'.wp_kses_post($desc).'</div>' : '');
    }

    /** Offers on this term and its children (the term count does not include children) */
    protected static function count(WP_Term $t): int
    {
        return (int) (new WP_Query(['post_type' => array_values(STA_Schema::TYPES), 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids',
            'tax_query' => [['taxonomy' => $t->taxonomy, 'terms' => $t->term_id, 'include_children' => true]]]))->found_posts;
    }

    protected static function destination_of_place(WP_Term $place): ?WP_Term
    {
        $top = $place;
        foreach (get_ancestors($place->term_id, 'sta_place') as $id) {
            $top = get_term($id, 'sta_place') ?: $top;
        }
        $remote = (int) get_term_meta($top->term_id, 'sta_destination_id', true);
        if (! $remote) {
            return null;
        }
        $f = get_terms(['taxonomy' => 'sta_destination', 'hide_empty' => false, 'meta_key' => 'sta_id', 'meta_value' => $remote, 'number' => 1]);

        return $f && ! is_wp_error($f) ? $f[0] : null;
    }

    /** Sub-destinations, places of a destination, child places / groups */
    protected static function children(WP_Term $t): array
    {
        $kids = get_terms(['taxonomy' => $t->taxonomy, 'parent' => $t->term_id, 'hide_empty' => false]);
        $kids = is_wp_error($kids) ? [] : $kids;
        if ($t->taxonomy === 'sta_destination' && ($sid = (int) get_term_meta($t->term_id, 'sta_id', true))) {
            $places = get_terms(['taxonomy' => 'sta_place', 'parent' => 0, 'hide_empty' => false, 'meta_key' => 'sta_destination_id', 'meta_value' => $sid]);
            $kids = array_merge($kids, is_wp_error($places) ? [] : $places);
        }

        return array_values(array_filter($kids, fn ($k) => self::count($k) > 0));
    }

    public static function offers($atts = []): string
    {
        $a = shortcode_atts(['taxonomy' => '', 'term' => '', 'preview_tax' => '', 'types' => 'tour,accommodation,event', 'children' => '1', 'columns' => 3, 'limit' => 12], $atts, 'sta_term_offers');
        $t = self::term($a);
        if (! $t) {
            return '';
        }
        wp_enqueue_style('sta');
        $h = '';
        if ($a['children'] && ($kids = self::children($t))) {
            $h .= '<section class="sta sta-term-sec"><h2 class="sta-h">'.esc_html($t->taxonomy === 'sta_destination' ? __('Places and regions', 'seeinside-travel') : __('Also here', 'seeinside-travel')).'</h2><div class="sta-grid sta-dest-grid" style="--sta-cols:4">';
            foreach ($kids as $k) {
                $img = get_term_meta($k->term_id, 'sta_image', true);
                $n = self::count($k);
                $h .= '<a class="sta-dest" href="'.esc_url(get_term_link($k)).'"'.($img ? ' style="background-image:url(\''.esc_url($img).'\')"' : '').'><span>'.esc_html(self::name($k)).'</span><small>'.esc_html(sprintf(_n('%d offer', '%d offers', $n, 'seeinside-travel'), $n)).'</small></a>';
            }
            $h .= '</div></section>';
        }
        $attr = ['sta_destination' => 'destination', 'sta_place' => 'place', 'sta_group' => 'group', 'sta_tour_type' => 'tour_type', 'sta_event_category' => 'category', 'sta_country' => 'country'][$t->taxonomy] ?? null;
        $types = array_map('trim', explode(',', $a['types']));
        $titles = ['tour' => __('Organized tours', 'seeinside-travel'), 'accommodation' => __('Accommodation', 'seeinside-travel'), 'event' => __('Events', 'seeinside-travel')];
        $slug = rawurldecode($t->slug);
        foreach ($types as $type) {
            $pt = STA_Schema::TYPES[$type] ?? null;
            if (! $pt || ! is_object_in_taxonomy($pt, $t->taxonomy)) {
                continue;
            }
            $has = (new WP_Query(['post_type' => $pt, 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'tax_query' => [['taxonomy' => $t->taxonomy, 'terms' => $t->term_id, 'include_children' => true]]]))->found_posts;
            if (! $has) {
                continue;
            }
            if ($type === 'accommodation') {
                // hotels with the filters on the left, this destination / place / group / type already chosen
                $preset = match ($t->taxonomy) {
                    'sta_destination' => ['destination' => $slug], 'sta_place' => ['place' => $slug], 'sta_hotel_type' => ['type' => $slug], 'sta_group' => ['group' => $slug], default => [],
                };
                $h .= '<section class="sta sta-term-sec"><h2 class="sta-h">'.esc_html($titles[$type]).'</h2>'.STA_Finder::shortcode($preset + ['columns' => $a['columns']]).'</section>';
                continue;
            }
            if (! $attr) {
                continue;
            }
            $h .= '<section class="sta sta-term-sec"><h2 class="sta-h">'.esc_html($titles[$type]).'</h2>'
                .STA_Frontend::sc_list(['type' => $type, $attr => $slug, 'limit' => (int) $a['limit'], 'columns' => (int) $a['columns'], 'pagination' => '1', 'orderby' => $type === 'tour' ? 'departure' : 'date'])
                .'</section>';
        }

        return $h ?: '<div class="sta sta-empty">'.esc_html__('No offers here at the moment.', 'seeinside-travel').'</div>';
    }
}
