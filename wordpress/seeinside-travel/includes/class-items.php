<?php

defined('ABSPATH') || exit;

/**
 * Every kind of content as the same "item" so one card designer and one set of layouts serve all of them:
 * tours, hotels, events, visas, last minute, charters, homepage slides, destinations / groups,
 * tour types, event categories and accommodation types.
 */
class STA_Items
{
    /** source => [label, kind, post type or taxonomy] */
    public static function sources(): array
    {
        return [
            'tour' => [__('Organized tours', 'seeinside-travel'), 'post', 'sta_tour'],
            'accommodation' => [__('Hotels & apartments', 'seeinside-travel'), 'post', 'sta_hotel'],
            'event' => [__('Events', 'seeinside-travel'), 'post', 'sta_event'],
            'visa' => [__('Visas', 'seeinside-travel'), 'post', 'sta_visa'],
            'last_minute' => ['Last minute', 'post', ''],
            'charter' => [__('Charters', 'seeinside-travel'), 'charter', ''],
            'slide' => [__('Homepage slider', 'seeinside-travel'), 'slide', ''],
            'destination' => [__('Destinations', 'seeinside-travel'), 'term', 'sta_destination'],
            'group' => [__('Groups', 'seeinside-travel'), 'term', 'sta_group'],
            'tour_type' => [__('Tour types', 'seeinside-travel'), 'term', 'sta_tour_type'],
            'event_category' => [__('Event categories', 'seeinside-travel'), 'term', 'sta_event_category'],
            'hotel_type' => [__('Accommodation types', 'seeinside-travel'), 'term', 'sta_hotel_type'],
        ];
    }

    public static function label(string $source): string
    {
        return self::sources()[$source][0] ?? $source;
    }

    public static function kind(string $source): string
    {
        return self::sources()[$source][1] ?? 'post';
    }

    /** Taxonomies that can filter a source (for the filter sidebar and the query) */
    public static function filter_taxonomies(string $source): array
    {
        $map = [
            'tour' => ['sta_group', 'sta_destination', 'sta_tour_type', 'sta_country'],
            'accommodation' => ['sta_destination', 'sta_hotel_type', 'sta_country'],
            'event' => ['sta_event_category', 'sta_country'],
            'visa' => ['sta_country'],
            'last_minute' => ['sta_group', 'sta_destination', 'sta_tour_type', 'sta_hotel_type', 'sta_country'],
        ];

        return $map[$source] ?? [];
    }

    public static function post_types(string $source): array
    {
        return $source === 'last_minute' ? array_values(STA_Schema::TYPES) : array_filter([self::sources()[$source][2] ?? '']);
    }

    /* ======================== loading ======================== */

    /**
     * @param array $q  limit, page, orderby, order, terms [tax => [slugs]], last_minute, featured, ids, upcoming, parent (terms), hide_empty
     * @return array{items: array, total: int, pages: int}
     */
    public static function load(string $source, array $q = []): array
    {
        $limit = max(1, min(100, (int) ($q['limit'] ?? 12)));
        $page = max(1, (int) ($q['page'] ?? 1));

        switch (self::kind($source)) {
            case 'term':
                return self::load_terms($source, $q, $limit, $page);
            case 'charter':
                $all = array_values(array_filter((array) get_option('sta_charters', []), fn ($c) => empty($c['departure_date']) || $c['departure_date'] >= current_time('Y-m-d')));
                usort($all, fn ($a, $b) => strcmp($a['departure_date'] ?? '', $b['departure_date'] ?? ''));

                return self::paged(array_map([self::class, 'from_charter'], $all), $limit, $page);
            case 'slide':
                return self::paged(array_map([self::class, 'from_slide'], (array) get_option('sta_slider', [])), $limit, $page);
        }

        $args = [
            'post_type' => self::post_types($source) ?: ['sta_tour'],
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'paged' => $page,
            'tax_query' => [],
            'meta_query' => [],
            'no_found_rows' => false,
        ];
        foreach ((array) ($q['terms'] ?? []) as $tax => $slugs) {
            $slugs = array_filter(array_map('sanitize_title', (array) $slugs));
            if ($slugs && taxonomy_exists($tax)) {
                $args['tax_query'][] = ['taxonomy' => $tax, 'field' => 'slug', 'terms' => $slugs, 'include_children' => true];
            }
        }
        if ($source === 'last_minute' || ! empty($q['last_minute'])) {
            $args['meta_query'][] = ['key' => '_sta_last_minute', 'value' => 1];
        }
        if (! empty($q['featured'])) {
            $args['meta_query'][] = ['key' => 'is_featured', 'value' => 1];
        }
        if (! empty($q['search'])) {
            $args['s'] = sanitize_text_field($q['search']);
        }
        if (! empty($q['ids'])) {
            $args['post__in'] = array_map('intval', (array) $q['ids']);
            $args['orderby'] = 'post__in';
        } else {
            self::order($args, (string) ($q['orderby'] ?? 'date'), (string) ($q['order'] ?? ''));
        }
        $wq = new WP_Query(apply_filters('sta_items_query', $args, $source, $q));

        return [
            'items' => array_values(array_filter(array_map([self::class, 'from_post'], $wq->posts))),
            'total' => (int) $wq->found_posts,
            'pages' => (int) $wq->max_num_pages,
        ];
    }

    protected static function paged(array $all, int $limit, int $page): array
    {
        return ['items' => array_slice($all, ($page - 1) * $limit, $limit), 'total' => count($all), 'pages' => (int) ceil(count($all) / $limit)];
    }

    protected static function order(array &$args, string $orderby, string $order): void
    {
        switch ($orderby) {
            case 'price':
                $args += ['meta_key' => '_sta_price', 'orderby' => 'meta_value_num', 'order' => $order ?: 'ASC'];
                break;
            case 'departure':
                $args += ['meta_key' => '_sta_date', 'orderby' => 'meta_value', 'order' => $order ?: 'ASC'];
                break;
            case 'title':
                $args += ['orderby' => 'title', 'order' => $order ?: 'ASC'];
                break;
            case 'random':
                $args['orderby'] = 'rand';
                break;
            case 'menu':
                $args += ['orderby' => ['menu_order' => 'ASC', 'date' => 'DESC']];
                break;
            default:
                $args += ['orderby' => 'date', 'order' => $order ?: 'DESC'];
        }
    }

    protected static function load_terms(string $source, array $q, int $limit, int $page): array
    {
        $tax = self::sources()[$source][2];
        $args = ['taxonomy' => $tax, 'hide_empty' => ! empty($q['hide_empty']), 'orderby' => ($q['orderby'] ?? '') === 'count' ? 'count' : 'name', 'order' => ($q['orderby'] ?? '') === 'count' ? 'DESC' : 'ASC'];
        if (isset($q['parent']) && $q['parent'] !== '') {
            $parent = is_numeric($q['parent']) ? (int) $q['parent'] : (int) (get_term_by('slug', sanitize_title($q['parent']), $tax)->term_id ?? 0);
            $args['parent'] = $parent;
        }
        if (! empty($q['ids'])) {
            $args['include'] = array_map('intval', (array) $q['ids']);
            $args['orderby'] = 'include';
        }
        $terms = get_terms($args);
        if (is_wp_error($terms)) {
            return ['items' => [], 'total' => 0, 'pages' => 0];
        }
        if (($q['orderby'] ?? '') === 'random') {
            shuffle($terms);
        }

        return self::paged(array_map(fn ($t) => self::from_term($t, $source), $terms), $limit, $page);
    }

    /* ======================== normalising ======================== */

    protected static function base(): array
    {
        return [
            'key' => '', 'kind' => 'post', 'type' => '', 'id' => 0, 'title' => '', 'url' => '', 'image' => '', 'eyebrow' => '', 'subtitle' => '',
            'place' => '', 'country' => '', 'country_code' => '', 'dates' => [], 'date_from' => null, 'date_to' => null,
            'price' => null, 'old_price' => null, 'currency' => 'EUR', 'rating' => 0, 'reviews' => 0, 'badges' => [], 'featured' => false,
            'duration' => '', 'transport' => '', 'stars' => 0, 'board' => '', 'amenities' => [], 'count' => null, 'promo_count' => 0,
            'seats' => null, 'excerpt' => '', 'category' => '',
        ];
    }

    public static function from_post($post): ?array
    {
        $post = get_post($post);
        if (! $post) {
            return null;
        }
        $d = sta_data($post->ID);
        if (! $d) {
            return null;
        }
        $type = $d['type'] ?? STA_Schema::api_type($post->post_type);
        $en = sta_lang() === 'en';
        $it = self::base();
        $it['key'] = $type.':'.$post->ID;
        $it['type'] = $type;
        $it['id'] = $post->ID;
        $it['title'] = $en ? (get_post_meta($post->ID, 'title_en', true) ?: get_the_title($post)) : get_the_title($post);
        $it['url'] = get_permalink($post);
        $it['image'] = get_the_post_thumbnail_url($post, 'large') ?: (! empty($d['image']) ? sta_local_image($d['image'], 'large') : '');
        $it['excerpt'] = wp_strip_all_tags((string) sta_t($d['short_description'] ?? null));
        $it['currency'] = $d['currency'] ?? 'EUR';
        $it['featured'] = ! empty($d['is_featured']);
        $it['country'] = (string) ($d['country'] ?? '');
        $it['country_code'] = strtolower((string) ($d['country_code'] ?? '')) ?: STA_Countries::code($it['country']);

        // price (last minute: new price, old one crossed out)
        $price = $type === 'visa' ? ($d['price'] ?? null) : ($d['price_from'] ?? null);
        $lm = $d['promotions']['last_minute'] ?? null;
        if ($lm && $price) {
            $new = $lm['price'] ?? (! empty($lm['discount_percent']) ? $price * (1 - $lm['discount_percent'] / 100) : $price);
            if ($new < $price) {
                $it['old_price'] = (float) $price;
            }
            $price = $new;
        }
        $it['price'] = $price !== null ? (float) $price : null;
        if ($lm) {
            $it['badges'][] = ['lm', 'Last minute'.(! empty($lm['discount_percent']) ? ' −'.(float) $lm['discount_percent'].'%' : '')];
        }
        if (! empty($d['promotions']['first_minute'])) {
            $fm = $d['promotions']['first_minute'];
            $it['badges'][] = ['fm', 'First minute '.(($fm['type'] ?? '') === 'percent' ? '−'.(float) $fm['discount'].'%' : '')];
        }

        $reviews = class_exists('STA_Reviews') ? STA_Reviews::summary(STA_Reviews::filter($type, (int) ($d['id'] ?? 0))) : ['count' => 0, 'average' => 0];
        $it['rating'] = $reviews['average'];
        $it['reviews'] = $reviews['count'];

        switch ($type) {
            case 'tour':
                $it['eyebrow'] = $it['country'] ?: sta_t($d['destination']['name'] ?? null);
                $it['subtitle'] = (string) sta_t($d['subtitle'] ?? null);
                $it['place'] = implode(', ', array_filter([$d['location'] ?? '', $d['country'] ?? '']));
                $it['category'] = (string) sta_t($d['tour_type']['label'] ?? null);
                $days = (int) ($d['duration']['days'] ?? 0);
                $nights = (int) ($d['duration']['nights'] ?? 0);
                $it['duration'] = $days ? sprintf(_n('%d day', '%d days', $days, 'seeinside-travel'), $days).($nights ? ' / '.sprintf(_n('%d night', '%d nights', $nights, 'seeinside-travel'), $nights) : '') : '';
                $it['transport'] = ! empty($d['is_flight']) ? 'flight' : ($it['category'] ? 'bus' : '');
                $deps = array_values(array_filter((array) ($d['departures'] ?? []), fn ($x) => ($x['start_date'] ?? '') >= current_time('Y-m-d')));
                usort($deps, fn ($a, $b) => strcmp($a['start_date'], $b['start_date']));
                $it['dates'] = array_map(fn ($x) => ['from' => $x['start_date'], 'to' => $x['end_date'] ?? null, 'lm' => ! empty($x['is_last_minute'])], $deps);
                if ($deps) {
                    $it['date_from'] = $deps[0]['start_date'];
                    $it['date_to'] = $deps[0]['end_date'] ?? null;
                    $seats = array_filter(array_column($deps, 'seats_left'), fn ($s) => $s !== null);
                    $it['seats'] = $seats ? min($seats) : null;
                } elseif (! empty($d['next_departure'])) {
                    $it['date_from'] = $d['next_departure'];
                }
                break;
            case 'event':
                $it['category'] = (string) sta_t($d['category']['label'] ?? null);
                $it['eyebrow'] = $it['category'];
                $it['subtitle'] = (string) ($d['performer'] ?? '');
                $it['place'] = implode(', ', array_filter([$d['venue'] ?? '', $d['city'] ?? '']));
                $it['date_from'] = ! empty($d['starts_at']) ? substr($d['starts_at'], 0, 10) : null;
                $it['dates'] = $it['date_from'] ? [['from' => $it['date_from'], 'to' => null, 'time' => substr((string) $d['starts_at'], 11, 5)]] : [];
                break;
            case 'accommodation':
                $it['title'] = (string) ($d['name'] ?? $it['title']);
                $it['category'] = (string) sta_t($d['accommodation_type']['label'] ?? null);
                $it['eyebrow'] = trim(($d['place'] ?? '') ?: ($d['country'] ?? ''));
                $it['place'] = implode(', ', array_filter([$d['place'] ?? '', $d['region'] ?? '', $d['country'] ?? '']));
                $it['stars'] = (int) ($d['stars'] ?? 0);
                $it['board'] = implode(' · ', array_map('strtoupper', (array) ($d['board_types'] ?? [])));
                $it['amenities'] = array_map(fn ($a) => sta_t($a['name'] ?? null), array_slice((array) ($d['amenities'] ?? []), 0, 6));
                break;
            case 'visa':
                $it['category'] = (string) sta_t($d['category']['label'] ?? null);
                $it['eyebrow'] = $it['category'];
                $it['subtitle'] = (string) sta_t($d['processing_time'] ?? null);
                $it['title'] = trim(($d['country'] ?? '').' – '.sta_t($d['name'] ?? null), ' –') ?: $it['title'];
                break;
        }

        return apply_filters('sta_item', $it, $post, $d);
    }

    public static function from_term(WP_Term $t, string $source = ''): array
    {
        $it = self::base();
        $it['key'] = 'term:'.$t->term_id;
        $it['kind'] = 'term';
        $it['type'] = $source ?: $t->taxonomy;
        $it['id'] = $t->term_id;
        $it['title'] = sta_lang() === 'en' ? (get_term_meta($t->term_id, 'name_en', true) ?: $t->name) : $t->name;
        $link = get_term_link($t);
        $it['url'] = is_wp_error($link) ? '' : $link;
        $img = (string) get_term_meta($t->term_id, 'sta_image', true);
        if (! $img) {
            // photo of the newest offer in this category
            $p = get_posts(['post_type' => array_values(STA_Schema::TYPES), 'numberposts' => 1, 'tax_query' => [['taxonomy' => $t->taxonomy, 'terms' => $t->term_id]], 'meta_key' => '_thumbnail_id', 'fields' => 'ids']);
            $img = $p ? (string) get_the_post_thumbnail_url($p[0], 'large') : '';
        }
        $it['image'] = $img;
        $it['count'] = (int) $t->count;
        $it['excerpt'] = wp_strip_all_tags(term_description($t));
        $it['country_code'] = STA_Countries::code($t->name);
        $promo = get_posts(['post_type' => array_values(STA_Schema::TYPES), 'numberposts' => -1, 'fields' => 'ids', 'no_found_rows' => true,
            'tax_query' => [['taxonomy' => $t->taxonomy, 'terms' => $t->term_id]], 'meta_query' => [['key' => '_sta_last_minute', 'value' => 1]]]);
        $it['promo_count'] = count($promo);
        $prices = [];
        foreach (get_posts(['post_type' => array_values(STA_Schema::TYPES), 'numberposts' => 50, 'fields' => 'ids', 'no_found_rows' => true, 'tax_query' => [['taxonomy' => $t->taxonomy, 'terms' => $t->term_id]], 'meta_key' => '_sta_price']) as $pid) {
            $v = (float) get_post_meta($pid, '_sta_price', true);
            if ($v > 0) {
                $prices[] = $v;
            }
        }
        $it['price'] = $prices ? min($prices) : null;
        $it['currency'] = get_option('sta_meta', [])['agency']['currency'] ?? 'EUR';

        return apply_filters('sta_item', $it, $t, null);
    }

    public static function from_charter(array $c): array
    {
        $it = self::base();
        $it['key'] = 'charter:'.($c['id'] ?? 0);
        $it['kind'] = 'charter';
        $it['type'] = 'charter';
        $it['id'] = (int) ($c['id'] ?? 0);
        $dest = is_array($c['destination'] ?? null) ? sta_t($c['destination']['name'] ?? null) : '';
        $it['title'] = trim(($c['region'] ?? '') ?: $dest) ?: __('Charter', 'seeinside-travel');
        $from = is_array($c['departure_place'] ?? null) ? sta_t($c['departure_place']['name'] ?? null) : '';
        $it['subtitle'] = $from ? $from.' → '.$it['title'] : '';
        $it['eyebrow'] = __('Charter flight', 'seeinside-travel');
        $it['date_from'] = $c['departure_date'] ?? null;
        $it['date_to'] = $c['return_date'] ?? null;
        $it['dates'] = $it['date_from'] ? [['from' => $it['date_from'], 'to' => $it['date_to']]] : [];
        $it['price'] = isset($c['price']) ? (float) $c['price'] : null;
        $it['currency'] = $c['currency'] ?? 'EUR';
        $it['seats'] = $c['seats_left'] ?? null;
        $it['transport'] = 'flight';
        $it['excerpt'] = (string) ($c['note'] ?? '');
        $it['country_code'] = STA_Countries::code($it['title']);
        $page = (get_option('sta_pages', [])['carteri'] ?? 0);
        $it['url'] = $page ? get_permalink($page).'#charter-'.$it['id'] : '';
        $it['image'] = is_array($c['destination'] ?? null) && ! empty($c['destination']['image']) ? sta_local_image($c['destination']['image'], 'large') : '';

        return apply_filters('sta_item', $it, $c, null);
    }

    public static function from_slide(array $s): array
    {
        $it = self::base();
        $it['key'] = 'slide:'.($s['id'] ?? 0);
        $it['kind'] = 'slide';
        $it['type'] = 'slide';
        $it['id'] = (int) ($s['id'] ?? 0);
        $it['title'] = (string) sta_t($s['title'] ?? null);
        $it['eyebrow'] = (string) sta_t($s['supertitle'] ?? null);
        $it['excerpt'] = (string) sta_t($s['subtitle'] ?? null);
        $it['image'] = sta_local_image($s['image'] ?? null, 'large');
        $url = $s['button']['url'] ?? '';
        $it['url'] = $url && str_starts_with($url, '/') ? home_url($url) : $url;
        $it['button'] = (string) sta_t($s['button']['label'] ?? null);

        return apply_filters('sta_item', $it, $s, null);
    }
}

/** Country name (MK / EN) → ISO code, for the flag on the cards */
class STA_Countries
{
    public const MAP = [
        'mk' => ['македонија', 'северна македонија', 'macedonia', 'north macedonia'],
        'gr' => ['грција', 'greece', 'халкидики', 'тасос', 'крит'], 'tr' => ['турција', 'turkey', 'türkiye', 'истанбул', 'анталија'],
        'it' => ['италија', 'italy'], 'fr' => ['франција', 'france', 'париз'], 'es' => ['шпанија', 'spain'], 'pt' => ['португалија', 'portugal'],
        'de' => ['германија', 'germany'], 'at' => ['австрија', 'austria'], 'ch' => ['швајцарија', 'switzerland'], 'hu' => ['унгарија', 'hungary'],
        'cz' => ['чешка', 'czech republic', 'czechia'], 'sk' => ['словачка', 'slovakia'], 'pl' => ['полска', 'poland'], 'nl' => ['холандија', 'netherlands', 'холандија'],
        'be' => ['белгија', 'belgium'], 'gb' => ['англија', 'велика британија', 'обединето кралство', 'united kingdom', 'england', 'uk'], 'ie' => ['ирска', 'ireland'],
        'dk' => ['данска', 'denmark'], 'se' => ['шведска', 'sweden'], 'no' => ['норвешка', 'norway'], 'fi' => ['финска', 'finland'], 'is' => ['исланд', 'iceland'],
        'rs' => ['србија', 'serbia', 'белград'], 'hr' => ['хрватска', 'croatia'], 'si' => ['словенија', 'slovenia'], 'ba' => ['босна и херцеговина', 'bosnia and herzegovina', 'босна'],
        'me' => ['црна гора', 'montenegro'], 'al' => ['албанија', 'albania'], 'bg' => ['бугарија', 'bulgaria'], 'ro' => ['романија', 'romania'], 'cy' => ['кипар', 'cyprus'],
        'mt' => ['малта', 'malta'], 'eg' => ['египет', 'egypt'], 'tn' => ['тунис', 'tunisia'], 'ma' => ['мароко', 'morocco'], 'ae' => ['обединети арапски емирати', 'оае', 'дубаи', 'united arab emirates', 'uae', 'dubai'],
        'th' => ['тајланд', 'thailand'], 'mv' => ['малдиви', 'maldives'], 'id' => ['индонезија', 'бали', 'indonesia', 'bali'], 'jp' => ['јапонија', 'japan'], 'cn' => ['кина', 'china'],
        'in' => ['индија', 'india'], 'us' => ['сад', 'америка', 'united states', 'usa'], 'ca' => ['канада', 'canada'], 'mx' => ['мексико', 'mexico'], 'br' => ['бразил', 'brazil'],
        'au' => ['австралија', 'australia'], 'ru' => ['русија', 'russia'], 'ua' => ['украина', 'ukraine'], 'ge' => ['грузија', 'georgia'], 'am' => ['ерменија', 'armenia'],
        'az' => ['азербејџан', 'azerbaijan'], 'il' => ['израел', 'israel'], 'jo' => ['јордан', 'jordan'], 'lk' => ['шри ланка', 'sri lanka'], 'vn' => ['виетнам', 'vietnam'],
        'za' => ['јужна африка', 'south africa'], 'ke' => ['кенија', 'kenya'], 'tz' => ['танзанија', 'занзибар', 'tanzania', 'zanzibar'], 'cu' => ['куба', 'cuba'], 'do' => ['доминиканска република', 'dominican republic'],
        'gb-sct' => ['шкотска', 'scotland'], 'lu' => ['луксембург', 'luxembourg'], 'mc' => ['монако', 'monaco'], 'ee' => ['естонија', 'estonia'], 'lv' => ['латвија', 'latvia'], 'lt' => ['литванија', 'lithuania'],
    ];

    public static function code(string $name): string
    {
        $n = mb_strtolower(trim($name));
        if ($n === '') {
            return '';
        }
        static $index = null;
        if ($index === null) {
            foreach (self::MAP as $code => $names) {
                foreach ($names as $x) {
                    $index[$x] = $code;
                }
            }
        }
        if (isset($index[$n])) {
            return $index[$n];
        }
        foreach ($index as $x => $code) {
            if (mb_strlen($x) > 3 && str_contains($n, $x)) {
                return $code;
            }
        }

        return '';
    }

    public static function flag(string $code, int $w = 40): string
    {
        return $code ? 'https://flagcdn.com/w'.($w > 40 ? 80 : 40).'/'.rawurlencode($code).'.png' : '';
    }
}
