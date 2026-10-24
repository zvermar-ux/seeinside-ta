<?php

defined('ABSPATH') || exit;

/**
 * Lists: as many as you want per content type, each with its own layout
 * (grid, grid with filters, arc slider, coverflow slider, carousel, masonry), query and heading.
 *   [sta_listing id="3"]                                       – a list saved in SeeInside Travel → Lists & cards
 *   [sta_listing source="tour" layout="carousel" limit="8"]   – ad hoc
 */
class STA_Listings
{
    public const OPTION = 'sta_listings';

    public static function layouts(): array
    {
        return [
            'grid' => __('Grid', 'seeinside-travel'),
            'filters' => __('Grid with filters (categories on the side)', 'seeinside-travel'),
            'arc' => __('Slider 1 – cards on an arc', 'seeinside-travel'),
            'coverflow' => __('Slider 2 – big card in the middle (coverflow)', 'seeinside-travel'),
            'carousel' => __('Carousel', 'seeinside-travel'),
            'masonry' => __('Masonry grid', 'seeinside-travel'),
        ];
    }

    public static function init(): void
    {
        add_action('init', fn () => add_shortcode('sta_listing', [self::class, 'shortcode']));
        add_action('wp_enqueue_scripts', [self::class, 'register_assets']);
        add_action('admin_enqueue_scripts', [self::class, 'register_assets']);
        add_action('rest_api_init', function () {
            register_rest_route('seeinside-travel/v1', '/listing', ['methods' => 'GET', 'callback' => [self::class, 'rest'], 'permission_callback' => '__return_true']);
        });
        add_action('init', [self::class, 'seed'], 30);
    }

    public static function register_assets(): void
    {
        wp_register_style('sta-listings', STA_URL.'assets/css/listings.css', [], STA_VERSION);
        wp_register_script('sta-listings', STA_URL.'assets/js/listings.js', [], STA_VERSION, ['strategy' => 'defer', 'in_footer' => true]);
    }

    public static function enqueue(): void
    {
        wp_enqueue_style('sta');
        wp_enqueue_style('sta-listings');
        wp_enqueue_script('sta-listings');
        static $done = false;
        if (! $done) {
            $done = true;
            wp_localize_script('sta-listings', 'STA_LIST', ['rest' => esc_url_raw(rest_url('seeinside-travel/v1/listing')), 'lang' => sta_lang(), 'drag' => __('Drag', 'seeinside-travel')]);
        }
    }

    /* ======================== configuration ======================== */

    public static function defaults(string $source = 'tour', string $layout = 'grid'): array
    {
        return [
            'id' => 0, 'name' => '', 'source' => $source, 'layout' => $layout,
            'limit' => in_array($layout, ['arc', 'coverflow', 'carousel'], true) ? 10 : 12, 'orderby' => in_array($source, ['tour', 'event', 'charter', 'last_minute'], true) ? 'departure' : 'date',
            'terms' => [], 'featured' => false, 'last_minute' => false, 'parent' => '', 'hide_empty' => true,
            'columns' => 3, 'columns_tablet' => 2, 'columns_mobile' => 1, 'gap' => 24,
            'autoplay' => in_array($layout, ['arc', 'coverflow'], true) ? 5 : 0, 'loop' => true, 'arrows' => $layout === 'carousel', 'dots' => in_array($layout, ['arc', 'carousel'], true),
            'pagination' => $layout === 'grid' || $layout === 'filters' ? 'more' : 'none',
            'filter_tax' => STA_Items::filter_taxonomies($source)[0] ?? '', 'filter_all' => true, 'filter_counts' => true,
            'filter_label' => ['mk' => 'патувања', 'en' => 'trips'], 'filter_tip' => ['mk' => '', 'en' => ''],
            'eyebrow' => ['mk' => '', 'en' => ''], 'title' => ['mk' => '', 'en' => ''], 'highlight' => ['mk' => '', 'en' => ''],
            'subtitle' => ['mk' => '', 'en' => ''], 'link_text' => ['mk' => '', 'en' => ''], 'link_url' => '', 'heading_align' => in_array($layout, ['arc', 'coverflow'], true) ? 'center' : 'left',
            'own_design' => false, 'design' => [],
        ];
    }

    public static function all(): array
    {
        $all = get_option(self::OPTION, []);

        return is_array($all) ? $all : [];
    }

    public static function get(int $id): ?array
    {
        $c = self::all()[$id] ?? null;

        return $c ? array_replace(self::defaults($c['source'] ?? 'tour', $c['layout'] ?? 'grid'), $c, ['id' => $id]) : null;
    }

    public static function save(array $c): int
    {
        $all = self::all();
        $id = (int) ($c['id'] ?? 0);
        if (! $id) {
            $id = (int) get_option('sta_listings_seq', 0) + 1;
            update_option('sta_listings_seq', $id, false);
        }
        $c['id'] = $id;
        $all[$id] = $c;
        update_option(self::OPTION, $all, false);

        return $id;
    }

    public static function delete(int $id): void
    {
        $all = self::all();
        unset($all[$id]);
        update_option(self::OPTION, $all, false);
    }

    /** First run: one ready list for every kind of content, so everything can be shown right away */
    public static function seed(): void
    {
        if (get_option('sta_listings_seeded')) {
            return;
        }
        update_option('sta_listings_seeded', 1, false);
        $seed = [
            ['tour', 'filters', 'Тури – грид со филтри', ['filter_tax' => 'sta_group', 'eyebrow' => ['mk' => 'Есен 2026', 'en' => 'Autumn 2026'], 'title' => ['mk' => 'Организирани патувања', 'en' => 'Organized tours'], 'highlight' => ['mk' => 'патувања', 'en' => 'tours']]],
            ['tour', 'carousel', 'Тури – карусел', ['limit' => 10]],
            ['accommodation', 'grid', 'Хотели – грид', ['orderby' => 'date']],
            ['event', 'carousel', 'Настани – карусел', []],
            ['visa', 'grid', 'Визи – грид', ['columns' => 4, 'orderby' => 'title']],
            ['last_minute', 'carousel', 'Last minute – карусел', ['title' => ['mk' => 'Last minute понуди', 'en' => 'Last minute deals']]],
            ['charter', 'grid', 'Чартери – грид', ['columns' => 4]],
            ['slide', 'carousel', 'Слајдер на почетна – карусел', ['columns' => 3]],
            ['destination', 'coverflow', 'Дестинации – слајдер 2 (coverflow)', ['eyebrow' => ['mk' => 'Топ дестинации', 'en' => 'Top Destination'], 'title' => ['mk' => 'Популарни дестинации', 'en' => 'Popular Destination']]],
            ['group', 'arc', 'Групи – слајдер 1 (лак)', []],
            ['tour_type', 'arc', 'Типови тури – слајдер 1 (лак)', ['eyebrow' => ['mk' => 'Прекрасни места за вас', 'en' => 'Wonderful Place For You'], 'title' => ['mk' => 'Видови патувања', 'en' => 'Tour Categories']]],
            ['event_category', 'grid', 'Категории настани – грид', ['columns' => 4]],
            ['hotel_type', 'masonry', 'Типови сместување – масонри', ['columns' => 3]],
        ];
        foreach ($seed as [$source, $layout, $name, $extra]) {
            self::save(array_replace(self::defaults($source, $layout), ['name' => $name], $extra));
        }
    }

    /** Clean a config from the admin form, a shortcode or a request */
    public static function sanitize(array $in, ?array $base = null): array
    {
        $source = array_key_exists($in['source'] ?? '', STA_Items::sources()) ? $in['source'] : ($base['source'] ?? 'tour');
        $layout = array_key_exists($in['layout'] ?? '', self::layouts()) ? $in['layout'] : ($base['layout'] ?? 'grid');
        $d = array_replace(self::defaults($source, $layout), $base ?? []);
        $c = ['id' => (int) ($in['id'] ?? $d['id']), 'name' => sanitize_text_field((string) ($in['name'] ?? $d['name'])), 'source' => $source, 'layout' => $layout];
        foreach (['limit' => [1, 100], 'columns' => [1, 6], 'columns_tablet' => [1, 4], 'columns_mobile' => [1, 3], 'gap' => [0, 80], 'autoplay' => [0, 30]] as $k => [$min, $max]) {
            $c[$k] = max($min, min($max, (int) ($in[$k] ?? $d[$k])));
        }
        $c['orderby'] = in_array($in['orderby'] ?? '', ['date', 'departure', 'price', 'title', 'random', 'count', 'menu'], true) ? $in['orderby'] : $d['orderby'];
        $c['pagination'] = in_array($in['pagination'] ?? '', ['none', 'more', 'numbers'], true) ? $in['pagination'] : $d['pagination'];
        foreach (['featured', 'last_minute', 'hide_empty', 'loop', 'arrows', 'dots', 'filter_all', 'filter_counts', 'own_design'] as $k) {
            $c[$k] = array_key_exists($k, $in) ? ! empty($in[$k]) && $in[$k] !== '0' && $in[$k] !== 'no' : (bool) $d[$k];
        }
        $c['parent'] = sanitize_title((string) ($in['parent'] ?? $d['parent']));
        $taxes = array_keys(STA_Schema::taxonomies());
        $c['filter_tax'] = in_array($in['filter_tax'] ?? '', $taxes, true) ? $in['filter_tax'] : (in_array($d['filter_tax'], $taxes, true) ? $d['filter_tax'] : '');
        $terms = [];
        foreach ((array) ($in['terms'] ?? $d['terms']) as $tax => $slugs) {
            if (in_array($tax, $taxes, true)) {
                $slugs = array_values(array_filter(array_map('sanitize_title', is_array($slugs) ? $slugs : explode(',', (string) $slugs))));
                if ($slugs) {
                    $terms[$tax] = $slugs;
                }
            }
        }
        $c['terms'] = $terms;
        foreach (['filter_label', 'filter_tip', 'eyebrow', 'title', 'highlight', 'subtitle', 'link_text'] as $k) {
            $v = $in[$k] ?? $d[$k];
            $c[$k] = is_array($v) ? ['mk' => sanitize_text_field((string) ($v['mk'] ?? '')), 'en' => sanitize_text_field((string) ($v['en'] ?? ''))] : ['mk' => sanitize_text_field((string) $v), 'en' => sanitize_text_field((string) $v)];
        }
        $c['link_url'] = esc_url_raw((string) ($in['link_url'] ?? $d['link_url']));
        $c['heading_align'] = ($in['heading_align'] ?? $d['heading_align']) === 'center' ? 'center' : 'left';
        $c['design'] = $c['own_design'] ? STA_Cards::sanitize($source, is_array($in['design'] ?? null) ? $in['design'] : (array) $d['design']) : [];

        return $c;
    }

    /* ======================== output ======================== */

    protected static function tr($v): string
    {
        return is_array($v) ? (string) ($v[sta_lang()] ?? '' ?: ($v['mk'] ?? '')) : (string) $v;
    }

    public static function query(array $c, int $page = 1, string $filterTerm = '', string $search = ''): array
    {
        $terms = $c['terms'];
        if ($filterTerm !== '' && $c['filter_tax']) {
            $terms[$c['filter_tax']] = [$filterTerm];
        }

        return STA_Items::load($c['source'], [
            'limit' => $c['limit'], 'page' => $page, 'orderby' => $c['orderby'], 'terms' => $terms, 'featured' => $c['featured'],
            'last_minute' => $c['last_minute'], 'parent' => $c['parent'], 'hide_empty' => $c['hide_empty'], 'search' => $search,
        ]);
    }

    public static function design_of(array $c): array
    {
        return $c['own_design'] && $c['design'] ? STA_Cards::design($c['source'], $c['design']) : STA_Cards::design($c['source']);
    }

    public static function cards(array $items, array $c): string
    {
        $d = self::design_of($c);
        $html = '';
        foreach ($items as $it) {
            $html .= '<div class="sta-l-cell">'.STA_Cards::render($it, $d, $c['source']).'</div>';
        }

        return $html;
    }

    protected static function encode(array $c): string
    {
        return rtrim(strtr(base64_encode((string) wp_json_encode($c)), '+/', '-_'), '=');
    }

    protected static function decode(string $s): ?array
    {
        $j = json_decode((string) base64_decode(strtr($s, '-_', '+/')), true);

        return is_array($j) ? $j : null;
    }

    /** The whole list */
    public static function render(array $c, array $opts = []): string
    {
        self::enqueue();
        if ($c['layout'] === 'filters') {
            $c['filter_tax'] = self::filter_tax($c);
        }
        $filterTerm = sanitize_title((string) ($opts['term'] ?? ($_GET['sta_f'] ?? '')));
        $page = max(1, (int) ($opts['page'] ?? 1));
        $res = self::query($c, $page, $filterTerm);
        $layout = $c['layout'];
        $uid = 'sta-l-'.wp_unique_id();
        if (in_array($layout, ['arc', 'coverflow'], true) && self::tr($c['eyebrow']) !== '') {
            wp_enqueue_style('sta-script-font', 'https://fonts.bunny.net/css?family=marck-script:400&display=swap', [], null);
        }

        $vars = '--l-cols:'.(int) $c['columns'].';--l-cols-t:'.(int) $c['columns_tablet'].';--l-cols-m:'.(int) $c['columns_mobile'].';--l-gap:'.(int) $c['gap'].'px';
        $js = ['layout' => $layout, 'autoplay' => (int) $c['autoplay'], 'loop' => (bool) $c['loop'], 'arrows' => (bool) $c['arrows'], 'dots' => (bool) $c['dots'], 'pages' => (int) $res['pages'], 'page' => $page];
        $cfg = $c['id'] ? 'id:'.$c['id'] : self::encode($c);

        $html = '<section id="'.esc_attr($uid).'" class="sta sta-l sta-l--'.esc_attr($layout).' alignwide" style="'.esc_attr($vars).'" data-sta-listing="'.esc_attr((string) wp_json_encode($js)).'" data-cfg="'.esc_attr($cfg).'">';
        $html .= empty($opts['no_heading']) ? self::heading($c) : '';

        if (! $res['items'] && $layout !== 'filters') {
            return $html.'<div class="sta-empty">'.esc_html__('No offers at the moment.', 'seeinside-travel').'</div></section>';
        }

        $items = self::cards($res['items'], $c);
        $nav = '';
        if (in_array($layout, ['arc', 'coverflow', 'carousel'], true)) {
            $arrow = fn ($dir) => '<button type="button" class="sta-l-arrow is-'.$dir.'" aria-label="'.esc_attr($dir === 'prev' ? __('Previous', 'seeinside-travel') : __('Next', 'seeinside-travel')).'"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="'.($dir === 'prev' ? 'm15 6-6 6 6 6' : 'm9 6 6 6-6 6').'" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>';
            $html .= '<div class="sta-l-stage">'.($c['arrows'] ? $arrow('prev') : '').'<div class="sta-l-track">'.$items.'</div>'.($c['arrows'] ? $arrow('next') : '').'</div>';
            $html .= $c['dots'] ? '<div class="sta-l-dots" role="tablist"></div>' : '';
            if ($layout === 'coverflow') {
                $html .= '<div class="sta-l-drag" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m15 6-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2.2"/></svg>'.esc_html__('Drag', 'seeinside-travel').'<svg viewBox="0 0 24 24"><path d="m9 6 6 6-6 6" fill="none" stroke="currentColor" stroke-width="2.2"/></svg></div>';
            }

            return $html.'</section>';
        }

        $more = self::more($c, $res, $page);
        if ($layout === 'filters') {
            $side = self::sidebar($c, $filterTerm);
            $html .= '<div class="sta-l-wrap'.($side ? '' : ' is-noside').'">'.$side.'<div class="sta-l-main">'
                .'<h3 class="sta-l-current">'.esc_html(self::current_title($c, $filterTerm)).'</h3>'
                .'<div class="sta-l-items">'.($items ?: '<div class="sta-empty">'.esc_html__('No offers at the moment.', 'seeinside-travel').'</div>').'</div>'.$more.'</div></div>';
        } else {
            $html .= '<div class="sta-l-items">'.$items.'</div>'.$more;
        }

        return $html.'</section>';
    }

    protected static function heading(array $c): string
    {
        $title = self::tr($c['title']);
        $eyebrow = self::tr($c['eyebrow']);
        $sub = self::tr($c['subtitle']);
        $link = self::tr($c['link_text']);
        if (! $title && ! $eyebrow && ! $sub && ! $link) {
            return '';
        }
        $hl = self::tr($c['highlight']);
        $t = esc_html($title);
        if ($hl && str_contains($title, $hl)) {
            $t = str_replace(esc_html($hl), '<span class="sta-l-hl">'.esc_html($hl).'<svg viewBox="0 0 200 12" preserveAspectRatio="none" aria-hidden="true"><path d="M2 9c40-6 120-8 196-3" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"/></svg></span>', $t);
        }

        return '<header class="sta-l-head is-'.esc_attr($c['heading_align']).'"><div>'
            .($eyebrow ? '<div class="sta-l-eyebrow">'.esc_html($eyebrow).'</div>' : '')
            .($title ? '<h2 class="sta-l-title">'.$t.'</h2>' : '')
            .($sub ? '<p class="sta-l-sub">'.esc_html($sub).'</p>' : '')
            .'</div>'.($link && $c['link_url'] ? '<a class="sta-l-all" href="'.esc_url($c['link_url']).'">'.esc_html($link).' <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg></a>' : '').'</header>';
    }

    protected static function more(array $c, array $res, int $page): string
    {
        if ($res['pages'] <= 1 || $c['pagination'] === 'none') {
            return '';
        }
        if ($c['pagination'] === 'more') {
            return $page < $res['pages'] ? '<div class="sta-l-more"><button type="button" class="sta-l-more-btn" data-page="'.($page + 1).'">'.esc_html__('Show more', 'seeinside-travel').'</button></div>' : '';
        }
        $out = '<nav class="sta-l-pages">';
        for ($i = 1; $i <= $res['pages']; $i++) {
            $out .= '<button type="button" class="sta-l-page'.($i === $page ? ' is-active' : '').'" data-page="'.$i.'">'.$i.'</button>';
        }

        return $out.'</nav>';
    }

    protected static function current_title(array $c, string $term): string
    {
        if ($term && $c['filter_tax']) {
            $t = get_term_by('slug', $term, $c['filter_tax']);
            if ($t) {
                return sta_lang() === 'en' ? (get_term_meta($t->term_id, 'name_en', true) ?: $t->name) : $t->name;
            }
        }

        return __('All offers', 'seeinside-travel');
    }

    /** Icon for a category in the filter sidebar */
    public static function term_icon(WP_Term $t, int $i): string
    {
        $icons = [
            'landmark' => '<path d="M3 21h18M4 10h16M12 3 3 8h18zM6 10v8M10 10v8M14 10v8M18 10v8"/>',
            'food' => '<path d="M7 3v7a2 2 0 0 0 4 0V3M9 10v11M17 3c-2 0-3 2-3 5s1 4 3 4v9"/>',
            'castle' => '<path d="M4 21V9h3V6h2v3h6V6h2v3h3v12zM10 21v-4a2 2 0 0 1 4 0v4M4 9V5M20 9V5"/>',
            'mountain' => '<path d="m3 20 6-10 4 6 3-4 5 8z"/>',
            'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
            'ship' => '<path d="M3 17c2 2 4 2 6 0 2 2 4 2 6 0 2 2 4 2 6 0M5 14l-1-4h16l-1 4M12 4v6M8 7h8"/>',
            'plane' => '<path d="M10.5 13.5 3 11l1.5-1.5 7 1 4-4a2 2 0 0 1 3 3l-4 4 1 7L19 22l-2.5-7.5"/>',
            'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
            'snow' => '<path d="M12 2v20M4 7l16 10M20 7 4 17M9 4l3 2 3-2M9 20l3-2 3 2"/>',
            'heart' => '<path d="M12 20s-7-4.4-9-8.6C1.6 8.3 3.4 5 6.8 5c1.9 0 3.3 1 5.2 3 1.9-2 3.3-3 5.2-3 3.4 0 5.2 3.3 3.8 6.4C19 15.6 12 20 12 20z"/>',
        ];
        $name = mb_strtolower($t->name);
        $guess = match (true) {
            (bool) preg_match('/престолн|capital|град|city|европ/u', $name) => 'landmark',
            (bool) preg_match('/итал|храна|food|вино|wine|гастро/u', $name) => 'food',
            (bool) preg_match('/шпан|португ|замок|castle/u', $name) => 'castle',
            (bool) preg_match('/планин|mountain|алп|западн/u', $name) => 'mountain',
            (bool) preg_match('/сонце|летув|плаж|summer|beach|егзот|exotic/u', $name) => 'sun',
            (bool) preg_match('/крстар|cruise|брод/u', $name) => 'ship',
            (bool) preg_match('/ски|зим|winter|ski/u', $name) => 'snow',
            (bool) preg_match('/авион|flight|лет/u', $name) => 'plane',
            (bool) preg_match('/спа|spa|велнес|romant|романт/u', $name) => 'heart',
            default => array_keys($icons)[$i % count($icons)],
        };
        $key = (string) get_term_meta($t->term_id, 'sta_icon', true) ?: $guess;

        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($icons[$key] ?? $icons['globe']).'</svg>';
    }

    /** Filter taxonomy actually used: the chosen one, or the first one that has offers of this type */
    public static function filter_tax(array $c): string
    {
        $types = STA_Items::post_types($c['source']);
        $try = array_unique(array_filter(array_merge([$c['filter_tax']], STA_Items::filter_taxonomies($c['source']))));
        foreach ($try as $tax) {
            $ids = get_posts(['post_type' => $types, 'numberposts' => 1, 'fields' => 'ids', 'no_found_rows' => true, 'tax_query' => [['taxonomy' => $tax, 'operator' => 'EXISTS']]]);
            if ($ids) {
                return $tax;
            }
        }

        return '';
    }

    protected static function sidebar(array $c, string $active): string
    {
        if (! $c['filter_tax']) {
            return '';
        }
        $types = STA_Items::post_types($c['source']);
        $terms = get_terms(['taxonomy' => $c['filter_tax'], 'hide_empty' => true, 'parent' => is_taxonomy_hierarchical($c['filter_tax']) ? 0 : '']);
        if (is_wp_error($terms) || ! $terms) {
            return '';
        }
        $label = self::tr($c['filter_label']);
        $html = '<aside class="sta-l-side"><nav class="sta-l-filters" aria-label="'.esc_attr__('Categories', 'seeinside-travel').'">';
        if ($c['filter_all']) {
            $html .= '<button type="button" class="sta-l-f'.($active === '' ? ' is-active' : '').'" data-term=""><span class="sta-l-f-ico">'.self::term_icon(new WP_Term((object) ['term_id' => 0, 'name' => 'globe']), 7).'</span><span class="sta-l-f-txt"><b>'.esc_html__('All offers', 'seeinside-travel').'</b></span><i class="sta-l-f-go"><svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></i></button>';
        }
        foreach ($terms as $i => $t) {
            $ids = get_posts(['post_type' => $types, 'numberposts' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'tax_query' => [['taxonomy' => $t->taxonomy, 'terms' => $t->term_id]]]);
            if (! $ids) {
                continue;
            }
            $promo = count(array_filter($ids, fn ($id) => (int) get_post_meta($id, '_sta_last_minute', true) === 1));
            $name = sta_lang() === 'en' ? (get_term_meta($t->term_id, 'name_en', true) ?: $t->name) : $t->name;
            $html .= '<button type="button" class="sta-l-f'.($active === $t->slug ? ' is-active' : '').'" data-term="'.esc_attr($t->slug).'" data-title="'.esc_attr($name).'">'
                .'<span class="sta-l-f-ico">'.self::term_icon($t, $i).'</span>'
                .'<span class="sta-l-f-txt"><b>'.esc_html($name).'</b>'.($c['filter_counts'] ? '<small>'.count($ids).' '.esc_html($label).($promo ? ' <em>'.$promo.' promo</em>' : '').'</small>' : '').'</span>'
                .'<i class="sta-l-f-go"><svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></i></button>';
        }
        $html .= '</nav>';
        $tip = self::tr($c['filter_tip']);
        if ($tip) {
            $html .= '<div class="sta-l-tip"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-4 10.5c.8.8 1 1.5 1 2.5h6c0-1 .2-1.7 1-2.5A6 6 0 0 0 12 3z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg><p>'.wp_kses($tip, ['b' => [], 'strong' => [], 'em' => []]).'</p></div>';
        }

        return $html.'</aside>';
    }

    /* ======================== shortcode / REST ======================== */

    public static function from_atts(array $atts): ?array
    {
        $atts = array_change_key_case($atts);
        $base = null;
        if (! empty($atts['id'])) {
            $base = self::get((int) $atts['id']);
            if (! $base) {
                return null;
            }
        } elseif (! empty($atts['name'])) {
            foreach (self::all() as $id => $c) {
                if (strcasecmp($c['name'] ?? '', $atts['name']) === 0) {
                    $base = self::get((int) $id);
                }
            }
        }
        $source = $atts['source'] ?? $atts['type'] ?? ($base['source'] ?? 'tour');
        $source = ['hotel' => 'accommodation', 'hotels' => 'accommodation', 'tours' => 'tour', 'events' => 'event', 'visas' => 'visa', 'charters' => 'charter', 'destinations' => 'destination', 'groups' => 'group', 'lastminute' => 'last_minute', 'offers' => 'custom', 'other' => 'custom'][$source] ?? $source;
        $in = ['source' => $source] + array_intersect_key($atts, array_flip(['layout', 'limit', 'columns', 'columns_tablet', 'columns_mobile', 'gap', 'autoplay', 'loop', 'arrows', 'dots', 'orderby', 'pagination', 'featured', 'last_minute', 'parent', 'filter_tax']));
        foreach (['title', 'eyebrow', 'subtitle', 'link_text', 'highlight'] as $k) {
            if (isset($atts[$k])) {
                $in[$k] = ['mk' => $atts[$k], 'en' => $atts[$k]];
            }
        }
        if (isset($atts['link'])) {
            $in['link_url'] = $atts['link'];
        }
        $terms = $base['terms'] ?? [];
        foreach (['destination' => 'sta_destination', 'group' => 'sta_group', 'tour_type' => 'sta_tour_type', 'category' => 'sta_event_category', 'hotel_type' => 'sta_hotel_type', 'country' => 'sta_country', 'offer_type' => 'sta_offer_type'] as $k => $tax) {
            if (! empty($atts[$k])) {
                $terms[$tax] = explode(',', $atts[$k]);
            }
        }
        $in['terms'] = $terms;
        if ($base && ! isset($atts['source']) && ! isset($atts['type'])) {
            $in['source'] = $base['source'];
        }

        return self::sanitize($in, $base);
    }

    public static function shortcode($atts = []): string
    {
        $c = self::from_atts(is_array($atts) ? $atts : []);
        if (! $c) {
            return current_user_can('edit_posts') ? '<div class="sta-empty">'.esc_html__('List not found – check the id in SeeInside Travel → Lists & cards.', 'seeinside-travel').'</div>' : '';
        }
        // on a category page: that category
        $opts = [];
        if (! empty($atts['current']) && is_tax() && ($qo = get_queried_object()) instanceof WP_Term) {
            $c['terms'][$qo->taxonomy] = [$qo->slug];
        }

        return self::render($c, $opts);
    }

    public static function rest(WP_REST_Request $r): WP_REST_Response
    {
        $cfg = (string) $r->get_param('cfg');
        $c = str_starts_with($cfg, 'id:') ? self::get((int) substr($cfg, 3)) : (($raw = self::decode($cfg)) ? self::sanitize($raw) : null);
        if (! $c) {
            return new WP_REST_Response(['message' => 'unknown list'], 404);
        }
        if (in_array($r->get_param('lang'), ['mk', 'en'], true)) {
            $lang = $r->get_param('lang');
            add_filter('sta_language', fn () => $lang);
        }
        if ($c['layout'] === 'filters') {
            $c['filter_tax'] = self::filter_tax($c);
        }
        $page = max(1, (int) $r->get_param('page'));
        $term = sanitize_title((string) $r->get_param('term'));
        $res = self::query($c, $page, $term);

        return new WP_REST_Response([
            'html' => self::cards($res['items'], $c) ?: '<div class="sta-empty">'.esc_html__('No offers at the moment.', 'seeinside-travel').'</div>',
            'more' => self::more($c, $res, $page),
            'title' => self::current_title($c, $term),
            'pages' => $res['pages'],
        ], 200);
    }
}
