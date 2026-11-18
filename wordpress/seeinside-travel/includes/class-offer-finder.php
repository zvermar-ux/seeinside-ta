<?php

defined('ABSPATH') || exit;

/**
 * Organized tours and events with the same filter column as the apartments ([sta_hotel_finder]):
 * name, destination, place, type / category, group, month of departure, transport, duration, price, promotions.
 * Shortcode: [sta_offer_finder type="tour|event"]
 */
class STA_Offer_Finder
{
    public static function init(): void
    {
        add_action('init', fn () => add_shortcode('sta_offer_finder', [self::class, 'shortcode']));
    }

    protected static function g(string $k, $default = '')
    {
        $v = $_GET[$k] ?? $default;   // phpcs:ignore WordPress.Security.NonceVerification

        return is_array($v) ? array_values(array_filter(array_map('sanitize_text_field', wp_unslash($v)))) : sanitize_text_field(wp_unslash((string) $v));
    }

    /** tour: type taxonomy sta_tour_type; event: sta_event_category */
    protected static function kind_tax(string $type): string
    {
        return $type === 'event' ? 'sta_event_category' : 'sta_tour_type';
    }

    protected static function with_parents(int $id, string $tax): array
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

    /** Every published tour / event with what the filters need */
    protected static function rows(string $type): array
    {
        static $cache = [];
        if (isset($cache[$type])) {
            return $cache[$type];
        }
        $pt = STA_Schema::TYPES[$type] ?? 'sta_tour';
        $ids = get_posts(['post_type' => $pt, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'suppress_filters' => false]);
        update_meta_cache('post', $ids);
        update_object_term_cache($ids, $pt);
        $today = current_time('Y-m-d');
        $rows = [];
        foreach ($ids as $id) {
            $it = STA_Items::from_post($id);
            $d = sta_data($id);
            if (! $it || ! $d) {
                continue;
            }
            $months = [];
            foreach ((array) $it['dates'] as $x) {
                if (! empty($x['from']) && $x['from'] >= $today) {
                    $months[] = substr($x['from'], 0, 7);
                }
            }
            if (! $months && ! empty($it['date_from']) && $it['date_from'] >= $today) {
                $months[] = substr($it['date_from'], 0, 7);
            }
            $rows[$id] = [
                'id' => $id, 'it' => $it, 'title' => get_the_title($id),
                'dests' => self::with_parents($id, 'sta_destination'), 'places' => self::with_parents($id, 'sta_place'),
                'groups' => self::with_parents($id, 'sta_group'), 'kinds' => self::with_parents($id, self::kind_tax($type)),
                'months' => array_values(array_unique($months)), 'next' => $it['date_from'] ?: '9999-12-31',
                'transport' => (string) $it['transport'], 'days' => (int) ($d['duration']['days'] ?? 0),
                'price' => $it['price'] !== null ? (float) $it['price'] : null,
                'promo' => ! empty($d['promotions']['last_minute']) || ! empty($d['promotions']['first_minute']) || ! empty($it['old_price']),
                'date' => get_post_field('post_date', $id),
            ];
        }

        return $cache[$type] = $rows;
    }

    protected static function filters(array $atts, string $type): array
    {
        $slug = fn ($v) => $v === '' ? '' : sanitize_title(rawurldecode((string) $v));
        $qo = get_queried_object();
        $term = fn ($tax) => is_tax($tax) && $qo instanceof WP_Term ? $qo->slug : '';
        $kind = (array) self::g('f_kind', []) ?: array_filter([$atts['kind'] ?? '', $term(self::kind_tax($type))]);

        return [
            'q' => self::g('f_q'), 'dest' => $slug(self::g('f_dest', $atts['destination'] ?: $term('sta_destination'))), 'place' => $slug(self::g('f_place', $atts['place'] ?: $term('sta_place'))),
            'group' => array_map($slug, (array) self::g('f_group', []) ?: array_filter([$atts['group'] ?? '', $term('sta_group')])),
            'kind' => array_map($slug, $kind), 'month' => (array) self::g('f_month', []), 'transport' => self::g('f_transport'),
            'days' => self::g('f_days'), 'price' => (float) self::g('f_price', 0), 'promo' => self::g('f_promo') === '1',
            'sort' => self::g('f_sort', 'recommended'),
        ];
    }

    protected static function match(array $r, array $f, string $skip = ''): bool
    {
        if ($f['q'] !== '' && stripos(remove_accents($r['title']), remove_accents($f['q'])) === false) {
            return false;
        }
        if ($skip !== 'dest' && $f['dest'] && ! in_array($f['dest'], $r['dests'], true)) {
            return false;
        }
        if ($skip !== 'place' && $f['place'] && ! in_array($f['place'], $r['places'], true)) {
            return false;
        }
        if ($skip !== 'group' && $f['group'] && ! array_intersect($f['group'], $r['groups'])) {
            return false;
        }
        if ($skip !== 'kind' && $f['kind'] && ! array_intersect($f['kind'], $r['kinds'])) {
            return false;
        }
        if ($skip !== 'month' && $f['month'] && ! array_intersect($f['month'], $r['months'])) {
            return false;
        }
        if ($skip !== 'transport' && $f['transport'] !== '' && $r['transport'] !== $f['transport']) {
            return false;
        }
        if ($skip !== 'days' && $f['days'] !== '' && ! self::in_days($r['days'], $f['days'])) {
            return false;
        }
        if ($skip !== 'price' && $f['price'] > 0 && $r['price'] !== null && $r['price'] > $f['price']) {
            return false;
        }
        if ($skip !== 'promo' && $f['promo'] && ! $r['promo']) {
            return false;
        }

        return true;
    }

    protected const DAYS = ['1-3' => [1, 3], '4-7' => [4, 7], '8-14' => [8, 14], '15+' => [15, 999]];

    protected static function in_days(int $days, string $bucket): bool
    {
        [$a, $b] = self::DAYS[$bucket] ?? [0, 999];

        return $days >= $a && $days <= $b;
    }

    public static function shortcode($atts = []): string
    {
        $atts = shortcode_atts(['type' => 'tour', 'destination' => '', 'place' => '', 'group' => '', 'kind' => '', 'per_page' => 12, 'columns' => 3], $atts, 'sta_offer_finder');
        $type = $atts['type'] === 'event' ? 'event' : 'tour';
        STA_Listings::enqueue();
        $f = self::filters($atts, $type);
        $rows = self::rows($type);
        $hits = array_filter($rows, fn ($r) => self::match($r, $f));
        $count = fn (string $skip, callable $test) => count(array_filter($rows, fn ($r) => self::match($r, $f, $skip) && $test($r)));

        usort($hits, match ($f['sort']) {
            'price' => fn ($a, $b) => ($a['price'] ?? PHP_INT_MAX) <=> ($b['price'] ?? PHP_INT_MAX),
            'date' => fn ($a, $b) => $a['next'] <=> $b['next'],
            'new' => fn ($a, $b) => $b['date'] <=> $a['date'],
            default => fn ($a, $b) => [(int) ! $a['promo'], $a['next']] <=> [(int) ! $b['promo'], $b['next']],
        });
        $per = max(3, (int) $atts['per_page']);
        $page = max(1, (int) self::g('f_page', 1));
        $pages = (int) ceil(count($hits) / $per);
        $shown = array_slice($hits, ($page - 1) * $per, $per);
        $base = remove_query_arg(['f_page']);

        $html = '<section class="sta sta-finder sta-offer-finder alignwide" data-sta-finder>';
        $html .= '<button type="button" class="sta-finder-toggle" data-sta-finder-toggle>'.sta_icon('filter').' '.esc_html__('Filters', 'seeinside-travel').'</button>';
        $html .= '<div class="sta-finder-wrap"><aside class="sta-finder-side">'.self::sidebar($type, $f, $rows, $count).'</aside><div class="sta-finder-main">';
        $sorts = ['recommended' => __('Recommended', 'seeinside-travel'), 'date' => __('Nearest date', 'seeinside-travel'), 'price' => __('Lowest price', 'seeinside-travel'), 'new' => __('Newest', 'seeinside-travel')];
        $label = $type === 'event' ? _n('%d event', '%d events', count($hits), 'seeinside-travel') : _n('%d tour', '%d tours', count($hits), 'seeinside-travel');
        $html .= '<div class="sta-finder-head"><b data-sta-finder-count>'.esc_html(sprintf($label, count($hits))).'</b><label>'.esc_html__('Sort', 'seeinside-travel').' <select data-sta-finder-sort>';
        foreach ($sorts as $k => $l) {
            $html .= '<option value="'.esc_attr($k).'"'.selected($f['sort'], $k, false).'>'.esc_html($l).'</option>';
        }
        $html .= '</select></label></div>';
        $html .= '<div class="sta-l sta-l--grid" style="--l-cols:'.(int) $atts['columns'].';--l-cols-t:2;--l-cols-m:1;--l-gap:22px"><div class="sta-l-items">';
        foreach ($shown as $r) {
            $html .= '<div class="sta-l-cell sta-finder-cell">'.STA_Cards::render($r['it'], null, $type).'</div>';
        }
        $html .= '</div></div>';
        if (! $hits) {
            $html .= '<div class="sta-empty">'.esc_html__('Nothing matches these filters – remove one of them.', 'seeinside-travel').' <a href="'.esc_url(strtok($base, '?')).'">'.esc_html__('Clear all filters', 'seeinside-travel').'</a></div>';
        }
        if ($pages > 1) {
            // pages for search engines; in the browser the next page loads by itself
            $html .= '<nav class="sta-pagination" data-sta-more'.($page < $pages ? ' data-next="'.esc_url(add_query_arg('f_page', $page + 1, $base)).'"' : '').'>';
            for ($i = 1; $i <= $pages; $i++) {
                $html .= $i === $page ? '<span class="current">'.$i.'</span>' : '<a href="'.esc_url(add_query_arg('f_page', $i, $base)).'">'.$i.'</a>';
            }
            $html .= '</nav>';
        }

        return $html.'</div></div></section>';
    }

    protected static function sidebar(string $type, array $f, array $rows, callable $count): string
    {
        $action = strtok(remove_query_arg(['f_page']), '?');
        $h = '<form method="get" action="'.esc_url($action).'" data-sta-finder-form>';
        $h .= '<input type="hidden" name="f_sort" value="'.esc_attr($f['sort']).'">';
        $h .= '<div class="sta-ff"><label class="sta-ff-search">'.esc_html__('Name', 'seeinside-travel').'<input type="search" name="f_q" value="'.esc_attr($f['q']).'" placeholder="'.esc_attr($type === 'event' ? __('e.g. concert', 'seeinside-travel') : __('e.g. Istanbul', 'seeinside-travel')).'"></label></div>';

        // destination (with the parents: Greece → Halkidiki)
        $dests = get_terms(['taxonomy' => 'sta_destination', 'hide_empty' => true, 'object_ids' => array_keys($rows) ?: [0]]);
        if (! is_wp_error($dests) && $dests) {
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
            $h .= '<div class="sta-ff"><h4>'.esc_html__('Destination', 'seeinside-travel').'</h4><select name="f_dest"><option value="">'.esc_html__('All destinations', 'seeinside-travel').'</option>';
            foreach ($byId as $t) {
                $n = $count('dest', fn ($r) => in_array($t->slug, $r['dests'], true));
                if (! $n && $f['dest'] !== $t->slug) {
                    continue;
                }
                $h .= '<option value="'.esc_attr(rawurldecode($t->slug)).'"'.selected($f['dest'], $t->slug, false).'>'.esc_html(str_repeat('— ', count(get_ancestors($t->term_id, 'sta_destination'))).$t->name).' ('.$n.')</option>';
            }
            $h .= '</select></div>';
        }
        // place
        $places = get_terms(['taxonomy' => 'sta_place', 'hide_empty' => true, 'object_ids' => array_keys($rows) ?: [0]]);
        if (! is_wp_error($places) && $places) {
            $opts = '';
            foreach ($places as $t) {
                $n = $count('place', fn ($r) => in_array($t->slug, $r['places'], true));
                if ($n || $f['place'] === $t->slug) {
                    $opts .= '<option value="'.esc_attr(rawurldecode($t->slug)).'"'.selected($f['place'], $t->slug, false).'>'.esc_html($t->name).' ('.$n.')</option>';
                }
            }
            if ($opts) {
                $h .= '<div class="sta-ff"><h4>'.esc_html__('Place', 'seeinside-travel').'</h4><select name="f_place"><option value="">'.esc_html__('All places', 'seeinside-travel').'</option>'.$opts.'</select></div>';
            }
        }
        // month of departure / of the event
        $months = [];
        foreach ($rows as $r) {
            foreach ($r['months'] as $m) {
                $months[$m] = true;
            }
        }
        ksort($months);
        if ($months) {
            $opts = [];
            foreach (array_keys($months) as $m) {
                $opts[] = [$m, date_i18n('F Y', strtotime($m.'-01')), $count('month', fn ($r) => in_array($m, $r['months'], true))];
            }
            $h .= self::checks($type === 'event' ? __('Month', 'seeinside-travel') : __('Month of departure', 'seeinside-travel'), 'f_month', array_slice($opts, 0, 14), $f['month']);
        }
        // tour type / event category
        $kinds = get_terms(['taxonomy' => self::kind_tax($type), 'hide_empty' => true, 'object_ids' => array_keys($rows) ?: [0]]);
        if (! is_wp_error($kinds) && $kinds) {
            $h .= self::checks($type === 'event' ? __('Category', 'seeinside-travel') : __('Type of tour', 'seeinside-travel'), 'f_kind',
                array_map(fn ($t) => [rawurldecode($t->slug), $t->name, $count('kind', fn ($r) => in_array($t->slug, $r['kinds'], true))], $kinds), array_map('rawurldecode', $f['kind']));
        }
        // group
        $grs = get_terms(['taxonomy' => 'sta_group', 'hide_empty' => true, 'object_ids' => array_keys($rows) ?: [0]]);
        if (! is_wp_error($grs) && $grs) {
            $h .= self::checks(__('Group', 'seeinside-travel'), 'f_group', array_map(fn ($t) => [rawurldecode($t->slug), $t->name, $count('group', fn ($r) => in_array($t->slug, $r['groups'], true))], $grs), array_map('rawurldecode', $f['group']));
        }
        if ($type === 'tour') {
            // transport
            $nb = $count('transport', fn ($r) => $r['transport'] === 'bus');
            $nf = $count('transport', fn ($r) => $r['transport'] === 'flight');
            if ($nb && $nf) {
                $h .= '<div class="sta-ff"><h4>'.esc_html__('Transport', 'seeinside-travel').'</h4>';
                foreach (['' => [__('Any', 'seeinside-travel'), null], 'bus' => [__('Bus', 'seeinside-travel'), $nb], 'flight' => [__('Flight', 'seeinside-travel'), $nf]] as $v => [$l, $n]) {
                    $h .= '<label class="sta-ff-opt"><input type="radio" name="f_transport" value="'.esc_attr($v).'"'.checked($f['transport'], $v, false).'> <span>'.esc_html($l).'</span>'.($n !== null ? '<small>'.$n.'</small>' : '').'</label>';
                }
                $h .= '</div>';
            }
            // duration
            $opts = [];
            foreach (array_keys(self::DAYS) as $b) {
                $n = $count('days', fn ($r) => self::in_days($r['days'], $b));
                if ($n) {
                    $opts[$b] = $n;
                }
            }
            if (count($opts) > 1) {
                $h .= '<div class="sta-ff"><h4>'.esc_html__('Duration', 'seeinside-travel').'</h4>'
                    .'<label class="sta-ff-opt"><input type="radio" name="f_days" value=""'.checked($f['days'], '', false).'> <span>'.esc_html__('Any', 'seeinside-travel').'</span></label>';
                foreach ($opts as $b => $n) {
                    $h .= '<label class="sta-ff-opt"><input type="radio" name="f_days" value="'.esc_attr($b).'"'.checked($f['days'], $b, false).'> <span>'.esc_html(sprintf(__('%s days', 'seeinside-travel'), $b)).'</span><small>'.$n.'</small></label>';
                }
                $h .= '</div>';
            }
        }
        // promotions
        $np = $count('promo', fn ($r) => $r['promo']);
        if ($np) {
            $h .= '<div class="sta-ff"><h4>'.esc_html__('Offer', 'seeinside-travel').'</h4><label class="sta-ff-opt"><input type="checkbox" name="f_promo" value="1"'.checked($f['promo'], true, false).'> <span>'.esc_html__('Early booking / last minute', 'seeinside-travel').'</span><small>'.$np.'</small></label></div>';
        }
        // price
        $prices = array_filter(array_column($rows, 'price'));
        if ($prices) {
            $max = (int) ceil(max($prices) / 10) * 10;
            $h .= '<div class="sta-ff"><h4>'.esc_html__('Price up to', 'seeinside-travel').' <output data-sta-price-out>'.($f['price'] ? (int) $f['price'] : $max).'</output></h4>'
                .'<input type="range" name="f_price" min="'.(int) floor(min($prices)).'" max="'.$max.'" step="5" value="'.($f['price'] ? (int) $f['price'] : $max).'" data-sta-price></div>';
        }
        $h .= '<a class="sta-ff-clear" href="'.esc_url($action).'">'.esc_html__('Clear all filters', 'seeinside-travel').'</a>';

        return $h.'</form>';
    }

    protected static function checks(string $title, string $name, array $opts, array $sel): string
    {
        $out = '';
        foreach ($opts as [$v, $l, $n]) {
            if (! $n && ! in_array($v, $sel, true)) {
                continue;
            }
            $out .= '<label class="sta-ff-opt"><input type="checkbox" name="'.esc_attr($name).'[]" value="'.esc_attr($v).'"'.(in_array($v, $sel, true) ? ' checked' : '').'> <span>'.esc_html($l).'</span><small>'.(int) $n.'</small></label>';
        }

        return $out ? '<div class="sta-ff"><h4>'.esc_html($title).'</h4>'.$out.'</div>' : '';
    }
}
