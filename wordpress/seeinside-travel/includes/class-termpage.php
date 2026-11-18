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
        $a = shortcode_atts(['taxonomy' => '', 'term' => '', 'preview_tax' => '', 'height' => 340, 'kind' => '1', 'crumbs' => '1', 'count' => '1', 'desc' => '1', 'image' => '', 'image_mode' => 'fallback',
            'page_type' => '', 'title' => '', 'label' => ''], $atts, 'sta_term_header');
        // listing pages (tours, hotels, apartments, events): the same header with the page title
        if ($a['page_type'] && ! (get_queried_object() instanceof WP_Term)) {
            return self::page_header($a);
        }
        $t = self::term($a);
        if (! $t) {
            return '';
        }
        wp_enqueue_style('sta');
        $img = get_term_meta($t->term_id, 'sta_image', true);
        // a photo chosen in Elementor: instead of the one from the app, or when there is none
        if ($a['image'] && ($a['image_mode'] === 'always' || ! $img)) {
            $img = $a['image'];
        }
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
            .($a['kind'] ? '<span class="sta-term-kind">'.esc_html($tax ? $tax->labels->singular_name : '').'</span>' : '')
            .($crumbs && $a['crumbs'] ? '<nav class="sta-term-crumbs">'.implode(' › ', $crumbs).'</nav>' : '')
            .'<h1>'.esc_html(self::name($t)).'</h1>'
            .($a['count'] ? '<span class="sta-term-count">'.esc_html(sprintf(_n('%d offer', '%d offers', self::count($t), 'seeinside-travel'), self::count($t))).'</span>' : '')
            .'</div></header>'
            .($desc && $a['desc'] ? '<div class="sta sta-term-desc sta-prose">'.wp_kses_post($desc).'</div>' : '');
    }

    /** Labels of the listing pages: badge and what is counted */
    protected static function page_kinds(): array
    {
        return [
            'tour' => __('Travel offer', 'seeinside-travel'), 'accommodation' => __('Accommodation', 'seeinside-travel'),
            'event' => __('Events', 'seeinside-travel'), 'hotels' => __('Hotels', 'seeinside-travel'),
        ];
    }

    /** Photo of the newest offer of a kind (for a listing page without its own photo) */
    protected static function sample_image(string $type): string
    {
        $pt = STA_Schema::TYPES[$type] ?? null;
        if (! $pt) {
            return '';
        }
        $ids = get_posts(['post_type' => $pt, 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'meta_query' => [['key' => '_thumbnail_id', 'compare' => 'EXISTS']]]);
        if ($ids) {
            return (string) get_the_post_thumbnail_url($ids[0], 'full');
        }
        foreach (get_posts(['post_type' => $pt, 'post_status' => 'publish', 'numberposts' => 5, 'fields' => 'ids']) as $id) {
            $it = STA_Items::from_post($id);
            if (! empty($it['image'])) {
                return (string) $it['image'];
            }
        }

        return '';
    }

    /** Header of a listing page: same look as the destination / type pages */
    protected static function page_header(array $a): string
    {
        wp_enqueue_style('sta');
        $type = $a['page_type'];
        $pid = (int) get_queried_object_id();
        $img = $a['image_mode'] === 'always' && $a['image'] ? $a['image'] : ((string) get_the_post_thumbnail_url($pid ?: null, 'full') ?: $a['image']);
        // no photo chosen: the photo of a hotel (booking system) or of the newest offer of this kind
        if (! $img) {
            $img = $type === 'hotels' ? (string) ((get_option('sta_meta', [])['hotels']['cover'] ?? '') ?: '') : self::sample_image($type);
        }
        $qo = get_queried_object();
        if ($qo instanceof WP_Post_Type) {
            $pid = 0;   // the archive of tours / events (its address can be /aranzmani/)
        }
        $title = $a['title'] ?: ($pid ? get_the_title($pid) : ($qo instanceof WP_Post_Type ? $qo->labels->name : (self::page_kinds()[$type] ?? '')));
        if ($type === 'hotels') {
            $n = array_sum(array_map(fn ($d) => (int) ($d['hotels'] ?? 0), STA_Hotels::destinations()));
        } else {
            $pt = STA_Schema::TYPES[$type] ?? null;
            $n = $pt ? (int) (wp_count_posts($pt)->publish ?? 0) : 0;
        }
        $desc = $pid ? (string) get_post_field('post_excerpt', $pid) : '';

        return '<header class="sta sta-term-head'.($img ? ' has-img' : '').'" style="--th-h:'.max(160, (int) $a['height']).'px'.($img ? ';background-image:url(\''.esc_url($img).'\')' : '').'">'
            .'<div class="sta-term-head-in">'
            .($a['kind'] ? '<span class="sta-term-kind">'.esc_html($a['label'] ?: (self::page_kinds()[$type] ?? '')).'</span>' : '')
            .'<h1>'.esc_html($title).'</h1>'
            .($a['count'] && $n ? '<span class="sta-term-count">'.esc_html(sprintf(_n('%d offer', '%d offers', $n, 'seeinside-travel'), $n)).'</span>' : '')
            .'</div></header>'
            .($desc && $a['desc'] ? '<div class="sta sta-term-desc sta-prose">'.wp_kses_post(wpautop($desc)).'</div>' : '');
    }

    /** List of a listing page: tours / events / apartments with the filters, hotels with the hotel search */
    protected static function page_offers(array $a): string
    {
        $head = fn (string $text) => $a['titles'] && $text !== '' ? '<h2 class="sta-h">'.esc_html($text).'</h2>' : '';
        $type = $a['page_type'];
        $body = match ($type) {
            'accommodation' => STA_Finder::shortcode(['columns' => $a['columns']]),
            'hotels' => STA_Hotels::shortcode(['columns' => $a['columns']]),
            'event' => STA_Offer_Finder::shortcode(['type' => 'event', 'columns' => $a['columns']]),
            default => STA_Offer_Finder::shortcode(['type' => 'tour', 'columns' => $a['columns']]),
        };
        $title = ['tour' => $a['title_tour'], 'accommodation' => $a['title_accommodation'], 'event' => $a['title_event']][$type] ?? '';

        return '<section class="sta sta-term-sec">'.$head((string) $title).$body.'</section>';
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
        $a = shortcode_atts(['taxonomy' => '', 'term' => '', 'preview_tax' => '', 'types' => 'tour,accommodation,event', 'children' => '1', 'columns' => 3, 'limit' => 12,
            'titles' => '1', 'title_accommodation' => '', 'title_tour' => '', 'title_event' => '', 'title_children' => '', 'page_type' => ''], $atts, 'sta_term_offers');
        if ($a['page_type'] && ! (get_queried_object() instanceof WP_Term)) {
            return self::page_offers($a);
        }
        // section title (or none)
        $head = fn (string $text) => $a['titles'] ? '<h2 class="sta-h">'.esc_html($text).'</h2>' : '';
        $t = self::term($a);
        if (! $t) {
            return '';
        }
        wp_enqueue_style('sta');
        $h = '';
        if ($a['children'] && ($kids = self::children($t))) {
            $h .= '<section class="sta sta-term-sec">'.$head($a['title_children'] ?: ($t->taxonomy === 'sta_destination' ? __('Places and regions', 'seeinside-travel') : __('Also here', 'seeinside-travel'))).'<div class="sta-grid sta-dest-grid" style="--sta-cols:4">';
            foreach ($kids as $k) {
                $img = get_term_meta($k->term_id, 'sta_image', true);
                $n = self::count($k);
                $h .= '<a class="sta-dest" href="'.esc_url(get_term_link($k)).'"'.($img ? ' style="background-image:url(\''.esc_url($img).'\')"' : '').'><span>'.esc_html(self::name($k)).'</span><small>'.esc_html(sprintf(_n('%d offer', '%d offers', $n, 'seeinside-travel'), $n)).'</small></a>';
            }
            $h .= '</div></section>';
        }
        $attr = ['sta_destination' => 'destination', 'sta_place' => 'place', 'sta_group' => 'group', 'sta_tour_type' => 'tour_type', 'sta_event_category' => 'category', 'sta_country' => 'country'][$t->taxonomy] ?? null;
        $types = array_map('trim', explode(',', $a['types']));
        $titles = ['tour' => $a['title_tour'] ?: __('Organized tours', 'seeinside-travel'), 'accommodation' => $a['title_accommodation'] ?: __('Apartments', 'seeinside-travel'), 'event' => $a['title_event'] ?: __('Events', 'seeinside-travel')];
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
                $h .= '<section class="sta sta-term-sec">'.$head($titles[$type]).STA_Finder::shortcode($preset + ['columns' => $a['columns']]).'</section>';
                continue;
            }
            if ($type === 'tour' || $type === 'event') {
                // tours / events of this destination, place, group or type – with the same filter column
                $kindTax = $type === 'event' ? 'sta_event_category' : 'sta_tour_type';
                $preset = match ($t->taxonomy) {
                    'sta_destination' => ['destination' => $slug], 'sta_place' => ['place' => $slug], 'sta_group' => ['group' => $slug], $kindTax => ['kind' => $slug], default => [],
                };
                $h .= '<section class="sta sta-term-sec">'.$head($titles[$type]).STA_Offer_Finder::shortcode($preset + ['type' => $type, 'columns' => $a['columns']]).'</section>';
                continue;
            }
            if (! $attr) {
                continue;
            }
            $h .= '<section class="sta sta-term-sec">'.$head($titles[$type])
                .STA_Frontend::sc_list(['type' => $type, $attr => $slug, 'limit' => (int) $a['limit'], 'columns' => (int) $a['columns'], 'pagination' => '1', 'orderby' => $type === 'tour' ? 'departure' : 'date'])
                .'</section>';
        }

        return $h ?: '<div class="sta sta-empty">'.esc_html__('No offers here at the moment.', 'seeinside-travel').'</div>';
    }
}
