<?php

defined('ABSPATH') || exit;

/**
 * Hotels / apartments / villas with a filter sidebar on the left (like Booking.com):
 * type, destination, distance from the beach, organized transport, stars, board, amenities, price,
 * and "free for my dates" (checked live in SeeInside). Shortcode: [sta_hotel_finder]
 */
class STA_Finder
{
    public static function init(): void
    {
        add_action('init', function () {
            add_shortcode('sta_hotel_finder', [self::class, 'shortcode']);
        });
    }

    protected static function g(string $k, $default = '')
    {
        $v = $_GET[$k] ?? $default;   // phpcs:ignore WordPress.Security.NonceVerification

        return is_array($v) ? array_values(array_filter(array_map('sanitize_text_field', wp_unslash($v)))) : sanitize_text_field(wp_unslash((string) $v));
    }

    /** All published hotels with the values the filters need (one pass, cached per request) */
    protected static function rows(): array
    {
        static $rows = null;
        if ($rows !== null) {
            return $rows;
        }
        $ids = get_posts(['post_type' => 'sta_hotel', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'suppress_filters' => false]);
        update_meta_cache('post', $ids);
        update_object_term_cache($ids, 'sta_hotel');
        $rows = [];
        foreach ($ids as $id) {
            $d = sta_data($id);
            if (! $d) {
                continue;
            }
            $terms = fn ($tax) => wp_list_pluck(get_the_terms($id, $tax) ?: [], 'slug');
            $boards = array_values(array_unique(array_filter(array_merge(array_map('strtoupper', (array) ($d['board_types'] ?? [])), array_map(fn ($r) => strtoupper((string) ($r['board']['value'] ?? '')), (array) ($d['rooms'] ?? []))))));
            [$price] = sta_price_info($d, 'accommodation');
            $rows[$id] = [
                'id' => $id, 'slug' => $d['slug'] ?? '', 'title' => get_the_title($id),
                'groups' => self::with_parents($id, 'sta_group'), 'types' => $terms('sta_hotel_type'), 'dests' => self::with_parents($id), 'places' => self::with_parents($id, 'sta_place'),
                'amen' => $terms('sta_amenity'),
                'beach' => isset($d['distances']['beach_m']) && $d['distances']['beach_m'] !== null ? (int) $d['distances']['beach_m'] : (isset($d['beach_m']) ? (int) $d['beach_m'] : null),
                'transport' => ! empty($d['transport']) || ! empty($d['has_transport']),
                'stars' => (int) ($d['stars'] ?? 0), 'boards' => $boards,
                'price' => $price !== null ? (float) $price : null, 'promo' => ! empty($d['promotions']['last_minute']) || ! empty($d['promotions']['first_minute']),
                'date' => get_post_field('post_date', $id),
            ];
        }

        return $rows;
    }

    /** destination slugs of a hotel including the parent destinations (Lefkada → Greece) */
    protected static function with_parents(int $id, string $tax = 'sta_destination'): array
    {
        $out = [];
        foreach (get_the_terms($id, $tax) ?: [] as $t) {
            $out[] = $t->slug;
            foreach (get_ancestors($t->term_id, $tax) as $p) {
                $pt = get_term($p, $tax);
                if ($pt && ! is_wp_error($pt)) {
                    $out[] = $pt->slug;
                }
            }
        }

        return array_values(array_unique($out));
    }

    /** Filters from the URL */
    protected static function filters(array $atts): array
    {
        $dest = self::g('f_dest', $atts['destination'] ?? '');
        if (! $dest && is_tax('sta_destination') && ($qo = get_queried_object()) instanceof WP_Term) {
            $dest = $qo->slug;
        }
        $type = (array) self::g('f_type', []);
        if (! $type && ! empty($atts['type'])) {
            $type = array_map('trim', explode(',', $atts['type']));
        }
        if (! $type && is_tax('sta_hotel_type') && ($qo = get_queried_object()) instanceof WP_Term) {
            $type = [$qo->slug];
        }

        // slugs of Cyrillic terms are stored URL-encoded (%d1%85…) – the URL gives them decoded
        $slug = fn ($v) => $v === '' ? '' : sanitize_title(rawurldecode((string) $v));

        return [
            'type' => array_map($slug, $type), 'dest' => $slug($dest), 'place' => $slug(self::g('f_place', $atts['place'] ?? (is_tax('sta_place') && ($qp = get_queried_object()) instanceof WP_Term ? $qp->slug : ''))), 'beach' => (int) self::g('f_beach', 0), 'transport' => self::g('f_transport') === '1',
            'stars' => array_map('intval', (array) self::g('f_stars', [])), 'board' => array_map('strtoupper', (array) self::g('f_board', [])),
            'amen' => array_map($slug, (array) self::g('f_amen', [])), 'price' => (float) self::g('f_price', 0), 'pmin' => (float) self::g('f_pmin', 0),
            'group' => array_map($slug, (array) self::g('f_group', []) ?: array_filter([$atts['group'] ?? '', is_tax('sta_group') && ($qg = get_queried_object()) instanceof WP_Term ? $qg->slug : ''])), 'promo' => self::g('f_promo') === '1',
            'from' => self::g('f_from'), 'to' => self::g('f_to'), 'ad' => max(1, (int) self::g('f_ad', 2)), 'ch' => max(0, (int) self::g('f_ch', 0)),
            'sort' => self::g('f_sort', 'recommended'), 'q' => self::g('f_q'),
        ];
    }

    /** Does a hotel pass every filter except $skip (for the counts next to the options) */
    protected static function match(array $r, array $f, string $skip = ''): bool
    {
        if ($skip !== 'type' && $f['type'] && ! array_intersect($f['type'], $r['types'])) {
            return false;
        }
        if ($skip !== 'dest' && $f['dest'] && ! in_array($f['dest'], $r['dests'], true)) {
            return false;
        }
        if ($skip !== 'place' && $f['place'] && ! in_array($f['place'], $r['places'], true)) {
            return false;
        }
        if ($skip !== 'beach' && $f['beach'] && ($r['beach'] === null || $r['beach'] > $f['beach'])) {
            return false;
        }
        if ($skip !== 'transport' && $f['transport'] && ! $r['transport']) {
            return false;
        }
        if ($skip !== 'stars' && $f['stars'] && ! in_array($r['stars'], $f['stars'], true)) {
            return false;
        }
        if ($skip !== 'board' && $f['board'] && ! array_intersect($f['board'], $r['boards'])) {
            return false;
        }
        if ($skip !== 'amen' && $f['amen'] && array_diff($f['amen'], $r['amen'])) {
            return false;
        }
        if ($skip !== 'group' && $f['group'] && ! array_intersect($f['group'], $r['groups'])) {
            return false;
        }
        if ($skip !== 'price' && $f['pmin'] > 0 && $r['price'] !== null && $r['price'] < $f['pmin']) {
            return false;
        }
        if ($skip !== 'price' && $f['price'] > 0 && $r['price'] !== null && $r['price'] > $f['price']) {
            return false;
        }
        if ($skip !== 'promo' && $f['promo'] && ! $r['promo']) {
            return false;
        }
        if ($f['q'] !== '' && stripos(remove_accents($r['title']), remove_accents($f['q'])) === false) {
            return false;
        }

        return true;
    }

    public static function shortcode($atts = []): string
    {
        $atts = shortcode_atts(['destination' => '', 'place' => '', 'group' => '', 'type' => '', 'per_page' => 12, 'columns' => 3], $atts, 'sta_hotel_finder');
        STA_Listings::enqueue();
        $f = self::filters($atts);
        $rows = self::rows();
        $hits = array_filter($rows, fn ($r) => self::match($r, $f));
        $count = fn (string $skip, callable $test) => count(array_filter($rows, fn ($r) => self::match($r, $f, $skip) && $test($r)));

        usort($hits, match ($f['sort']) {
            'price' => fn ($a, $b) => ($a['price'] ?? PHP_INT_MAX) <=> ($b['price'] ?? PHP_INT_MAX),
            'beach' => fn ($a, $b) => ($a['beach'] ?? PHP_INT_MAX) <=> ($b['beach'] ?? PHP_INT_MAX),
            'stars' => fn ($a, $b) => $b['stars'] <=> $a['stars'],
            default => fn ($a, $b) => [(int) ! $a['promo'], $b['date']] <=> [(int) ! $b['promo'], $a['date']],
        });
        $per = max(3, (int) $atts['per_page']);
        // with dates: every result on one page – the free ones are found in the browser
        $dates = $f['from'] && $f['to'];
        $page = max(1, (int) self::g('f_page', 1));
        $pages = $dates ? 1 : (int) ceil(count($hits) / $per);
        $shown = $dates ? $hits : array_slice($hits, ($page - 1) * $per, $per);

        $base = remove_query_arg(['f_page']);
        $html = '<section class="sta sta-finder alignwide" data-sta-finder'.($dates ? ' data-from="'.esc_attr($f['from']).'" data-to="'.esc_attr($f['to']).'" data-ad="'.$f['ad'].'" data-ch="'.$f['ch'].'"' : '').'>';
        $html .= '<button type="button" class="sta-finder-toggle" data-sta-finder-toggle>'.sta_icon('filter').' '.esc_html__('Filters', 'seeinside-travel').'</button>';
        $html .= '<div class="sta-finder-wrap"><aside class="sta-finder-side">'.self::sidebar($f, $rows, $count).'</aside><div class="sta-finder-main">';
        // head: number + sort
        $sorts = ['recommended' => __('Recommended', 'seeinside-travel'), 'price' => __('Lowest price', 'seeinside-travel'), 'beach' => __('Closest to the beach', 'seeinside-travel'), 'stars' => __('Stars', 'seeinside-travel')];
        $html .= '<div class="sta-finder-head"><b data-sta-finder-count>'.sprintf(esc_html(_n('%d place to stay', '%d places to stay', count($hits), 'seeinside-travel')), count($hits)).'</b>'
            .'<label>'.esc_html__('Sort', 'seeinside-travel').' <select data-sta-finder-sort>';
        foreach ($sorts as $k => $l) {
            $html .= '<option value="'.esc_attr($k).'"'.selected($f['sort'], $k, false).'>'.esc_html($l).'</option>';
        }
        $html .= '</select></label></div>';
        if ($dates) {
            $html .= '<div class="sta-finder-note" data-sta-finder-note>'.esc_html__('Checking which are free for your dates…', 'seeinside-travel').'</div>';
        }
        $html .= '<div class="sta-l sta-l--grid" style="--l-cols:'.(int) $atts['columns'].';--l-cols-t:2;--l-cols-m:1;--l-gap:22px"><div class="sta-l-items">';
        foreach ($shown as $r) {
            $it = STA_Items::from_post($r['id']);
            if (! $it) {
                continue;
            }
            $extra = array_filter([
                $r['beach'] !== null ? sprintf(__('%s to the beach', 'seeinside-travel'), $r['beach'] >= 1000 ? round($r['beach'] / 1000, 1).' km' : $r['beach'].' m') : null,
                $r['transport'] ? __('Organized transport', 'seeinside-travel') : null,
            ]);
            $html .= '<div class="sta-l-cell sta-finder-cell" data-slug="'.esc_attr($r['slug']).'">'.STA_Cards::render($it, null, 'accommodation')
                .($extra ? '<div class="sta-finder-tags">'.implode('', array_map(fn ($x) => '<span>'.esc_html($x).'</span>', $extra)).'</div>' : '')
                .'<div class="sta-finder-avail" hidden></div></div>';
        }
        $html .= '</div></div>';
        if (! $hits) {
            $html .= '<div class="sta-empty">'.esc_html__('Nothing matches these filters – remove one of them.', 'seeinside-travel').' <a href="'.esc_url(strtok($base, '?')).'">'.esc_html__('Clear all filters', 'seeinside-travel').'</a></div>';
        }
        if ($pages > 1) {
            $html .= '<nav class="sta-pagination">';
            for ($i = 1; $i <= $pages; $i++) {
                $html .= $i === $page ? '<span class="current">'.$i.'</span>' : '<a href="'.esc_url(add_query_arg('f_page', $i, $base)).'">'.$i.'</a>';
            }
            $html .= '</nav>';
        }

        return $html.'</div></div></section>';
    }

    protected static function sidebar(array $f, array $rows, callable $count): string
    {
        $action = strtok(remove_query_arg(['f_page']), '?');
        $h = '<form class="sta-finder-form" method="get" action="'.esc_url($action).'" data-sta-finder-form>';
        $h .= '<input type="hidden" name="f_sort" value="'.esc_attr($f['sort']).'">';
        // dates & guests
        $h .= '<div class="sta-ff sta-ff-dates"><h4>'.esc_html__('Your dates', 'seeinside-travel').'</h4>'
            .'<label>'.esc_html__('Check-in', 'seeinside-travel').'<input type="date" name="f_from" value="'.esc_attr($f['from']).'" min="'.esc_attr(wp_date('Y-m-d')).'"></label>'
            .'<label>'.esc_html__('Check-out', 'seeinside-travel').'<input type="date" name="f_to" value="'.esc_attr($f['to']).'" min="'.esc_attr(wp_date('Y-m-d')).'"></label>'
            .'<div class="sta-ff-row"><label>'.esc_html__('Adults', 'seeinside-travel').'<input type="number" name="f_ad" min="1" max="20" value="'.(int) $f['ad'].'"></label>'
            .'<label>'.esc_html__('Children', 'seeinside-travel').'<input type="number" name="f_ch" min="0" max="10" value="'.(int) $f['ch'].'"></label></div>'
            .'<button type="submit" class="sta-btn sta-btn-block">'.esc_html__('Show free places', 'seeinside-travel').'</button></div>';
        $h .= '<div class="sta-ff"><label class="sta-ff-search">'.esc_html__('Name', 'seeinside-travel').'<input type="search" name="f_q" value="'.esc_attr($f['q']).'" placeholder="'.esc_attr__('e.g. Vila Afroditi', 'seeinside-travel').'"></label></div>';

        // destination
        $dests = get_terms(['taxonomy' => 'sta_destination', 'hide_empty' => true, 'object_ids' => array_keys($rows) ?: [0]]);
        if (! is_wp_error($dests) && $dests) {
            // with the parent destinations (Greece → Halkidiki, Lefkada…), each parent followed by its children
            $byId = [];
            foreach ($dests as $t) {
                $byId[$t->term_id] = $t;
                foreach (get_ancestors($t->term_id, 'sta_destination') as $a) {
                    $byId[$a] ??= get_term($a, 'sta_destination');
                }
            }
            $byId = array_filter($byId, fn ($t) => $t instanceof WP_Term);
            $path = fn ($t) => implode(' / ', array_map(fn ($id) => $byId[$id]->name ?? '', array_reverse(get_ancestors($t->term_id, 'sta_destination')))).' / '.$t->name;
            uasort($byId, fn ($a, $b) => strcoll($path($a), $path($b)));
            $dests = array_values($byId);
            $h .= '<div class="sta-ff"><h4>'.esc_html__('Destination', 'seeinside-travel').'</h4><select name="f_dest"><option value="">'.esc_html__('All destinations', 'seeinside-travel').'</option>';
            foreach ($dests as $t) {
                $n = $count('dest', fn ($r) => in_array($t->slug, $r['dests'], true));
                $h .= '<option value="'.esc_attr(rawurldecode($t->slug)).'"'.selected($f['dest'], $t->slug, false).'>'.esc_html(str_repeat('— ', count(get_ancestors($t->term_id, 'sta_destination'))).$t->name).' ('.$n.')</option>';
            }
            $h .= '</select></div>';
        }
        // place (Sandanski, Lefkada › Nikiana) – only places with offers for the other filters
        $places = get_terms(['taxonomy' => 'sta_place', 'hide_empty' => false]);
        if (! is_wp_error($places) && $places) {
            $opts = '';
            $byParent = [];
            foreach ($places as $t) {
                $byParent[$t->parent][] = $t;
            }
            $walk = function (int $parent, int $depth) use (&$walk, &$opts, $byParent, $count, $f) {
                $list = $byParent[$parent] ?? [];
                usort($list, fn ($a, $b) => strcoll($a->name, $b->name));
                foreach ($list as $t) {
                    $n = $count('place', fn ($r) => in_array($t->slug, $r['places'], true));
                    if ($n || $f['place'] === $t->slug) {
                        $opts .= '<option value="'.esc_attr(rawurldecode($t->slug)).'"'.selected($f['place'], $t->slug, false).'>'.esc_html(str_repeat('— ', $depth).$t->name).' ('.$n.')</option>';
                    }
                    $walk($t->term_id, $depth + 1);
                }
            };
            $walk(0, 0);
            if ($opts) {
                $h .= '<div class="sta-ff"><h4>'.esc_html__('Place', 'seeinside-travel').'</h4><select name="f_place"><option value="">'.esc_html__('All places', 'seeinside-travel').'</option>'.$opts.'</select></div>';
            }
        }
        // type
        $types = get_terms(['taxonomy' => 'sta_hotel_type', 'hide_empty' => true]);
        if (! is_wp_error($types) && $types) {
            $h .= self::checks(__('Type of accommodation', 'seeinside-travel'), 'f_type', array_map(fn ($t) => [$t->slug, $t->name, $count('type', fn ($r) => in_array($t->slug, $r['types'], true))], $types), $f['type']);
        }
        // groups (spa hotels, apartments, early booking…)
        $grs = get_terms(['taxonomy' => 'sta_group', 'hide_empty' => true, 'object_ids' => array_keys($rows) ?: [0]]);
        if (! is_wp_error($grs) && $grs) {
            $h .= self::checks(__('Group', 'seeinside-travel'), 'f_group', array_map(fn ($t) => [rawurldecode($t->slug), $t->name, $count('group', fn ($r) => in_array($t->slug, $r['groups'], true))], $grs), array_map('rawurldecode', $f['group']));
        }
        if ($f['pmin'] > 0) {
            $h .= '<input type="hidden" name="f_pmin" value="'.esc_attr((string) $f['pmin']).'">';
        }
        // beach
        $h .= '<div class="sta-ff"><h4>'.esc_html__('Distance from the beach', 'seeinside-travel').'</h4>';
        foreach ([0 => __('Any', 'seeinside-travel'), 100 => __('up to 100 m', 'seeinside-travel'), 300 => __('up to 300 m', 'seeinside-travel'), 500 => __('up to 500 m', 'seeinside-travel'), 1000 => __('up to 1 km', 'seeinside-travel')] as $m => $l) {
            $n = $m ? $count('beach', fn ($r) => $r['beach'] !== null && $r['beach'] <= $m) : null;
            if ($m && ! $n) {
                continue;
            }
            $h .= '<label class="sta-ff-opt"><input type="radio" name="f_beach" value="'.$m.'"'.checked($f['beach'], $m, false).'> <span>'.esc_html($l).'</span>'.($n !== null ? '<small>'.$n.'</small>' : '').'</label>';
        }
        $h .= '</div>';
        // transport & promo
        $nt = $count('transport', fn ($r) => $r['transport']);
        $np = $count('promo', fn ($r) => $r['promo']);
        if ($nt || $np) {
            $h .= '<div class="sta-ff"><h4>'.esc_html__('Offer', 'seeinside-travel').'</h4>'
                .($nt ? '<label class="sta-ff-opt"><input type="checkbox" name="f_transport" value="1"'.checked($f['transport'], true, false).'> <span>'.esc_html__('With organized transport', 'seeinside-travel').'</span><small>'.$nt.'</small></label>' : '')
                .($np ? '<label class="sta-ff-opt"><input type="checkbox" name="f_promo" value="1"'.checked($f['promo'], true, false).'> <span>'.esc_html__('Early booking / last minute', 'seeinside-travel').'</span><small>'.$np.'</small></label>' : '')
                .'</div>';
        }
        // price
        $prices = array_filter(array_column($rows, 'price'));
        if ($prices) {
            $max = (int) ceil(max($prices) / 10) * 10;
            $h .= '<div class="sta-ff"><h4>'.esc_html__('Price up to', 'seeinside-travel').' <output data-sta-price-out>'.($f['price'] ? (int) $f['price'] : $max).'</output></h4>'
                .'<input type="range" name="f_price" min="'.(int) floor(min($prices)).'" max="'.$max.'" step="5" value="'.($f['price'] ? (int) $f['price'] : $max).'" data-sta-price></div>';
        }
        // stars
        $stars = array_values(array_unique(array_filter(array_column($rows, 'stars'))));
        rsort($stars);
        if ($stars) {
            $h .= self::checks(__('Stars / category', 'seeinside-travel'), 'f_stars', array_map(fn ($s) => [(string) $s, str_repeat('★', $s), $count('stars', fn ($r) => $r['stars'] === $s)], $stars), array_map('strval', $f['stars']));
        }
        // board
        $boards = array_values(array_unique(array_merge(...array_values(array_map(fn ($r) => $r['boards'], $rows)) ?: [[]])));
        if ($boards) {
            $names = ['RO' => __('Room only', 'seeinside-travel'), 'BB' => __('Bed & breakfast', 'seeinside-travel'), 'HB' => __('Half board', 'seeinside-travel'), 'FB' => __('Full board', 'seeinside-travel'), 'AI' => 'All inclusive', 'UAI' => 'Ultra all inclusive'];
            $h .= self::checks(__('Board', 'seeinside-travel'), 'f_board', array_map(fn ($b) => [$b, $names[$b] ?? $b, $count('board', fn ($r) => in_array($b, $r['boards'], true))], $boards), $f['board']);
        }
        // amenities
        $amen = get_terms(['taxonomy' => 'sta_amenity', 'hide_empty' => true, 'object_ids' => array_keys($rows) ?: [0]]);
        if (! is_wp_error($amen) && $amen) {
            $opts = array_map(fn ($t) => [$t->slug, $t->name, $count('amen', fn ($r) => in_array($t->slug, $r['amen'], true))], $amen);
            usort($opts, fn ($a, $b) => $b[2] <=> $a[2]);
            $h .= self::checks(__('Amenities', 'seeinside-travel'), 'f_amen', array_slice($opts, 0, 14), $f['amen']);
        }
        $h .= '<a class="sta-ff-clear" href="'.esc_url($action).'">'.esc_html__('Clear all filters', 'seeinside-travel').'</a>';

        return $h.'</form>';
    }

    protected static function checks(string $title, string $name, array $opts, array $sel): string
    {
        $h = '<div class="sta-ff"><h4>'.esc_html($title).'</h4>';
        foreach ($opts as [$v, $l, $n]) {
            if (! $n && ! in_array($v, $sel, true)) {
                continue;
            }
            $h .= '<label class="sta-ff-opt"><input type="checkbox" name="'.esc_attr($name).'[]" value="'.esc_attr($v).'"'.(in_array($v, $sel, true) ? ' checked' : '').'> <span>'.esc_html($l).'</span><small>'.(int) $n.'</small></label>';
        }

        return $h.'</div>';
    }
}
