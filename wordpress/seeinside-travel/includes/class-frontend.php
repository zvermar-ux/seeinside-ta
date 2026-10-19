<?php

defined('ABSPATH') || exit;

/** Shortcodes, automatic page layout, assets and the secure bridge (REST proxy) to SeeInside for forms */
class STA_Frontend
{
    public static function init(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'assets']);
        add_action('init', [self::class, 'shortcodes']);
        add_filter('the_content', [self::class, 'auto_layout'], 20);
        // the automatic layout has its own gallery: hide the theme's big featured image on those pages
        add_filter('post_thumbnail_html', function ($html, $postId) {
            return is_singular(array_values(STA_Schema::TYPES)) && (int) $postId === (int) get_queried_object_id() && ! doing_filter('the_content')
                && STA_Templates::use_auto_layout(get_post_type($postId), (int) $postId) ? '' : $html;
        }, 10, 2);
        add_filter('template_include', [self::class, 'archive_template'], 99);
        add_action('pre_get_posts', [self::class, 'filter_query']);
        add_action('rest_api_init', [self::class, 'routes']);
        add_action('wp_head', [self::class, 'css_vars'], 20);
    }

    public static function assets(): void
    {
        wp_enqueue_style('sta', STA_URL.'assets/css/frontend.css', [], STA_VERSION);
        wp_enqueue_script('sta', STA_URL.'assets/js/frontend.js', [], STA_VERSION, ['strategy' => 'defer', 'in_footer' => true]);
        wp_localize_script('sta', 'STA', [
            'rest' => esc_url_raw(rest_url('seeinside-travel/v1/')),
            // push mode: this server cannot reach the app, so forms go straight from the browser to the app
            'direct' => sta_push_mode() ? esc_url_raw(sta_public_api()) : '',
            'site' => home_url('/'),
            'lang' => sta_lang(),
            'currency' => (get_option('sta_meta', [])['agency']['currency'] ?? 'EUR'),
            'i18n' => [
                'sending' => __('Sending…', 'seeinside-travel'),
                'error' => __('Something went wrong. Please try again or call us.', 'seeinside-travel'),
                'thanks' => __('Thank you! Your request was received.', 'seeinside-travel'),
                'reference' => __('Reference number', 'seeinside-travel'),
                'total' => __('Total', 'seeinside-travel'),
                'estimate' => __('Estimated price', 'seeinside-travel'),
                'checking' => __('Checking prices…', 'seeinside-travel'),
                'noOffers' => __('No free rooms for these dates. Send an inquiry and we will find an option.', 'seeinside-travel'),
                'nights' => __('nights', 'seeinside-travel'),
                'book' => __('Book', 'seeinside-travel'),
                'available' => __('available', 'seeinside-travel'),
                'childAge' => __('Age of child', 'seeinside-travel'),
                'free' => __('Free', 'seeinside-travel'),
                'booked' => __('Booked', 'seeinside-travel'),
                'notFound' => __('Nothing found. Check the number and e-mail.', 'seeinside-travel'),
                'status' => __('Status', 'seeinside-travel'),
                'payment' => __('Payment', 'seeinside-travel'),
                'paid' => __('Paid', 'seeinside-travel'),
                'reviewThanks' => __('Thank you! Your review will appear after the agency checks it.', 'seeinside-travel'),
                'chooseRating' => __('Please choose a rating.', 'seeinside-travel'),
                'close' => __('Close', 'seeinside-travel'),
                'quickBook' => __('Book now', 'seeinside-travel'),
                'change' => __('Change dates', 'seeinside-travel'),
                'notFree' => __('This room is not free for all the chosen nights. Choose other dates.', 'seeinside-travel'),
                'pickArrival' => __('Click the day of arrival, then the day of departure.', 'seeinside-travel'),
                'pickDeparture' => __('Now click the day of departure.', 'seeinside-travel'),
                'needConsent' => __('Please tick the consent box above the button.', 'seeinside-travel'),
                'needFields' => __('Please fill in the fields marked in red.', 'seeinside-travel'),
                'months' => [__('January', 'seeinside-travel'), __('February', 'seeinside-travel'), __('March', 'seeinside-travel'), __('April', 'seeinside-travel'), __('May', 'seeinside-travel'), __('June', 'seeinside-travel'), __('July', 'seeinside-travel'), __('August', 'seeinside-travel'), __('September', 'seeinside-travel'), __('October', 'seeinside-travel'), __('November', 'seeinside-travel'), __('December', 'seeinside-travel')],
                'weekdays' => [__('Mo', 'seeinside-travel'), __('Tu', 'seeinside-travel'), __('We', 'seeinside-travel'), __('Th', 'seeinside-travel'), __('Fr', 'seeinside-travel'), __('Sa', 'seeinside-travel'), __('Su', 'seeinside-travel')],
            ],
        ]);
    }

    /** Colours for the components (Settings → Display) */
    public static function css_vars(): void
    {
        $primary = sanitize_hex_color((string) sta_setting('color_primary', '')) ?: '';
        $accent = sanitize_hex_color((string) sta_setting('color_accent', '')) ?: '';
        if (! $primary && ! $accent) {
            return;
        }
        echo '<style id="sta-vars">html:root{'.($primary ? '--sta-primary:'.$primary.';' : '').($accent ? '--sta-accent:'.$accent.';' : '').'}</style>';
    }

    /* ======================== shortcodes ======================== */

    public static function shortcodes(): void
    {
        add_shortcode('sta_section', fn ($a) => STA_Render::section(sanitize_key($a['name'] ?? 'layout'), ! empty($a['id']) ? (int) $a['id'] : null, self::title_arg($a)));
        foreach (array_keys(STA_Render::SECTIONS) as $name) {
            add_shortcode('sta_'.$name, fn ($a) => STA_Render::section($name, ! empty($a['id']) ? (int) $a['id'] : null, self::title_arg((array) $a)));
        }
        add_shortcode('sta_list', [self::class, 'sc_list']);
        add_shortcode('sta_last_minute', fn ($a) => self::sc_list(array_merge(['type' => 'all', 'last_minute' => '1'], (array) $a)));
        add_shortcode('sta_slider', [self::class, 'sc_slider']);
        add_shortcode('sta_search', [self::class, 'sc_search']);
        add_shortcode('sta_flight_search', [self::class, 'sc_flights']);
        add_shortcode('sta_charters', [self::class, 'sc_charters']);
        add_shortcode('sta_booking_status', [self::class, 'sc_status']);
        add_shortcode('sta_destinations', [self::class, 'sc_destinations']);
    }

    protected static function title_arg(array $a): array
    {
        return isset($a['title']) ? ['title' => in_array($a['title'], ['0', 'no', 'false'], true) ? false : $a['title']] : [];
    }

    /** Grid of cards (tours / hotels / events / visas) */
    public static function sc_list($atts = []): string
    {
        $a = shortcode_atts([
            'type' => 'tour', 'limit' => 9, 'columns' => 3, 'destination' => '', 'group' => '', 'tour_type' => '', 'category' => '',
            'country' => '', 'last_minute' => '', 'featured' => '', 'orderby' => 'date', 'order' => '', 'pagination' => '', 'search' => '', 'ids' => '',
            'query' => '',
        ], $atts, 'sta_list');

        if ($a['query'] === 'current' && (is_post_type_archive(array_values(STA_Schema::TYPES)) || is_tax(array_keys(STA_Schema::taxonomies())))) {
            global $wp_query;
            $q = $wp_query;
        } else {
            $alias = ['tour' => 'sta_tour', 'tours' => 'sta_tour', 'event' => 'sta_event', 'events' => 'sta_event', 'hotel' => 'sta_hotel', 'hotels' => 'sta_hotel',
                'accommodation' => 'sta_hotel', 'visa' => 'sta_visa', 'visas' => 'sta_visa'];
            $types = $a['type'] === 'all' ? array_values(STA_Schema::TYPES)
                : array_values(array_filter(array_map(fn ($t) => $alias[trim($t)] ?? (in_array(trim($t), STA_Schema::TYPES, true) ? trim($t) : null), explode(',', $a['type']))));
            $args = [
                'post_type' => $types ?: ['sta_tour'],
                'post_status' => 'publish',
                'posts_per_page' => (int) $a['limit'],
                'paged' => $a['pagination'] ? max(1, (int) get_query_var('paged'), (int) ($_GET['sta_page'] ?? 1)) : 1,
                'tax_query' => [],
                'meta_query' => [],
            ];
            foreach (['destination' => 'sta_destination', 'group' => 'sta_group', 'tour_type' => 'sta_tour_type', 'category' => 'sta_event_category', 'country' => 'sta_country'] as $att => $tax) {
                if ($a[$att] !== '') {
                    $args['tax_query'][] = ['taxonomy' => $tax, 'field' => 'slug', 'terms' => array_map('trim', explode(',', $a[$att])), 'include_children' => true];
                }
            }
            if ($a['last_minute']) {
                $args['meta_query'][] = ['key' => '_sta_last_minute', 'value' => 1];
            }
            if ($a['featured']) {
                $args['meta_query'][] = ['key' => 'is_featured', 'value' => 1];
            }
            if ($a['ids']) {
                $args['post__in'] = array_map('intval', explode(',', $a['ids']));
                $args['orderby'] = 'post__in';
            } else {
                self::apply_order($args, $a['orderby'], $a['order']);
            }
            if ($a['search']) {
                self::apply_filters_from_request($args);
            }
            $q = new WP_Query(apply_filters('sta_list_query', $args, $a));
        }

        if (! $q->have_posts()) {
            return '<div class="sta sta-empty">'.esc_html__('No offers at the moment.', 'seeinside-travel').'</div>';
        }
        STA_Listings::enqueue();
        $cols = max(1, min(6, (int) $a['columns']));
        $html = '<div class="sta sta-l sta-l--grid" style="--l-cols:'.$cols.';--l-cols-t:'.min($cols, 2).';--l-cols-m:1"><div class="sta-l-items">';
        foreach ($q->posts as $p) {
            $card = STA_Render::card($p->ID);
            $html .= $card ? '<div class="sta-l-cell">'.$card.'</div>' : '';
        }
        $html .= '</div>';
        $html .= '</div>';
        if ($a['pagination'] && $q->max_num_pages > 1) {
            $html .= '<nav class="sta sta-pagination">'.paginate_links(['total' => $q->max_num_pages, 'current' => max(1, (int) get_query_var('paged'), (int) ($_GET['sta_page'] ?? 1))]).'</nav>';
        }

        return $html;
    }

    protected static function apply_order(array &$args, string $orderby, string $order): void
    {
        switch ($orderby) {
            case 'price':
                $args['meta_key'] = '_sta_price';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = $order ?: 'ASC';
                break;
            case 'departure':
                $args['meta_key'] = '_sta_date';
                $args['orderby'] = 'meta_value';
                $args['order'] = $order ?: 'ASC';
                break;
            case 'title':
                $args['orderby'] = 'title';
                $args['order'] = $order ?: 'ASC';
                break;
            case 'random':
                $args['orderby'] = 'rand';
                break;
            default:
                $args['orderby'] = 'date';
                $args['order'] = $order ?: 'DESC';
        }
    }

    /** Filters from the search form (?sta_q, sta_dest, sta_type, sta_max, sta_lm, sta_month, sta_sort) */
    protected static function apply_filters_from_request(array &$args): void
    {
        $g = fn ($k) => isset($_GET[$k]) ? sanitize_text_field(wp_unslash($_GET[$k])) : '';
        if ($g('sta_q')) {
            $args['s'] = $g('sta_q');
        }
        if ($g('sta_dest')) {
            $args['tax_query'][] = ['taxonomy' => 'sta_destination', 'field' => 'slug', 'terms' => $g('sta_dest'), 'include_children' => true];
        }
        if ($g('sta_kind')) {
            $args['tax_query'][] = ['taxonomy' => 'sta_tour_type', 'field' => 'slug', 'terms' => $g('sta_kind')];
        }
        if ($g('sta_max')) {
            $args['meta_query'][] = ['key' => '_sta_price', 'value' => (float) $g('sta_max'), 'compare' => '<=', 'type' => 'NUMERIC'];
        }
        if ($g('sta_lm')) {
            $args['meta_query'][] = ['key' => '_sta_last_minute', 'value' => 1];
        }
        if ($g('sta_month') && preg_match('/^\d{4}-\d{2}$/', $g('sta_month'))) {
            $args['meta_query'][] = ['key' => '_sta_date', 'value' => [$g('sta_month').'-01', $g('sta_month').'-31'], 'compare' => 'BETWEEN'];
        }
        if ($g('sta_sort')) {
            self::apply_order($args, $g('sta_sort'), '');
        }
    }

    /** Search filters also work on the archives of the post types */
    public static function filter_query(WP_Query $q): void
    {
        if (is_admin() || ! $q->is_main_query()) {
            return;
        }
        if ($q->is_post_type_archive(array_values(STA_Schema::TYPES)) || $q->is_tax(array_keys(STA_Schema::taxonomies()))) {
            $args = ['tax_query' => (array) $q->get('tax_query'), 'meta_query' => (array) $q->get('meta_query')];
            self::apply_filters_from_request($args);
            foreach ($args as $k => $v) {
                $q->set($k, $v);
            }
            if (! $q->get('posts_per_page') || $q->get('posts_per_page') == get_option('posts_per_page')) {
                $q->set('posts_per_page', (int) sta_setting('per_page', 12));
            }
        }
    }

    public static function sc_slider($atts = []): string
    {
        return STA_Slider::render(is_array($atts) ? $atts : []);
    }

    public static function sc_search($atts = []): string
    {
        $a = shortcode_atts(['types' => 'tour,accommodation,event', 'action' => ''], $atts, 'sta_search');
        $types = array_filter(array_map('trim', explode(',', $a['types'])), fn ($t) => isset(STA_Schema::TYPES[$t]));
        $labels = ['tour' => __('Tours', 'seeinside-travel'), 'accommodation' => __('Hotels', 'seeinside-travel'), 'event' => __('Events', 'seeinside-travel'), 'visa' => __('Visas', 'seeinside-travel')];
        $dests = get_terms(['taxonomy' => 'sta_destination', 'hide_empty' => true]);

        $html = '<form class="sta sta-search" method="get" data-sta-search>';
        if (count($types) > 1) {
            $html .= '<div class="sta-search-tabs">';
            foreach ($types as $i => $t) {
                $html .= '<label><input type="radio" name="sta_t" value="'.esc_attr(get_post_type_archive_link(STA_Schema::TYPES[$t])).'"'.($i === 0 ? ' checked' : '').'><span>'.esc_html($labels[$t]).'</span></label>';
            }
            $html .= '</div>';
        } else {
            $html .= '<input type="hidden" name="sta_t" value="'.esc_attr($a['action'] ?: get_post_type_archive_link(STA_Schema::TYPES[$types[0] ?? 'tour'])).'">';
        }
        $html .= '<div class="sta-search-row">'
            .'<label class="sta-field"><span>'.esc_html__('Where to?', 'seeinside-travel').'</span><select name="sta_dest"><option value="">'.esc_html__('All destinations', 'seeinside-travel').'</option>';
        foreach (is_wp_error($dests) ? [] : $dests as $t) {
            $name = sta_lang() === 'en' ? (get_term_meta($t->term_id, 'name_en', true) ?: $t->name) : $t->name;
            $html .= '<option value="'.esc_attr($t->slug).'"'.selected($_GET['sta_dest'] ?? '', $t->slug, false).'>'.esc_html(($t->parent ? '— ' : '').$name).'</option>';
        }
        $html .= '</select></label>'
            .'<label class="sta-field"><span>'.esc_html__('When?', 'seeinside-travel').'</span><input type="month" name="sta_month" value="'.esc_attr(sanitize_text_field($_GET['sta_month'] ?? '')).'"></label>'
            .'<label class="sta-field"><span>'.esc_html__('Budget up to', 'seeinside-travel').'</span><input type="number" min="0" step="50" name="sta_max" placeholder="€" value="'.esc_attr(sanitize_text_field($_GET['sta_max'] ?? '')).'"></label>'
            .'<label class="sta-check"><input type="checkbox" name="sta_lm" value="1"'.checked(! empty($_GET['sta_lm']), true, false).'> Last minute</label>'
            .'<button class="sta-btn" type="submit">'.esc_html__('Search', 'seeinside-travel').'</button></div></form>';

        return $html;
    }

    public static function sc_flights($atts = []): string
    {
        $f = (get_option('sta_meta', [])['flights'] ?? []);
        $widget = $f['widget_code'] ?? '';
        if ($widget) {
            // embed code from the flight partner (GOL IBE), set by the agency in SeeInside
            return '<div class="sta sta-flights">'.$widget.'</div>';
        }
        $url = $f['search_url'] ?? '';
        if (! $url) {
            return '';
        }

        return '<form class="sta sta-search sta-flights-form" action="'.esc_url($url).'" method="get" target="_blank">'
            .'<div class="sta-search-row">'
            .'<label class="sta-field"><span>'.esc_html__('From', 'seeinside-travel').'</span><input name="from" value="Skopje"></label>'
            .'<label class="sta-field"><span>'.esc_html__('To', 'seeinside-travel').'</span><input name="to" placeholder="'.esc_attr__('City or airport', 'seeinside-travel').'"></label>'
            .'<label class="sta-field"><span>'.esc_html__('Departure', 'seeinside-travel').'</span><input type="date" name="departure"></label>'
            .'<label class="sta-field"><span>'.esc_html__('Return', 'seeinside-travel').'</span><input type="date" name="return"></label>'
            .'<button class="sta-btn" type="submit">'.sta_icon('plane').' '.esc_html__('Search flights', 'seeinside-travel').'</button></div></form>';
    }

    public static function sc_charters($atts = []): string
    {
        $rows = get_option('sta_charters', []);
        if (! $rows) {
            return '<div class="sta sta-empty">'.esc_html__('No charter seats at the moment.', 'seeinside-travel').'</div>';
        }

        return sta_part('charters', ['rows' => $rows]);
    }

    public static function sc_status($atts = []): string
    {
        return sta_part('status', ['kind' => sanitize_key($atts['kind'] ?? 'booking')]);
    }

    public static function sc_destinations($atts = []): string
    {
        $a = shortcode_atts(['taxonomy' => 'sta_destination', 'parent' => '', 'limit' => 12, 'columns' => 4], $atts, 'sta_destinations');
        $args = ['taxonomy' => $a['taxonomy'], 'hide_empty' => true, 'number' => (int) $a['limit']];
        if ($a['parent'] !== '') {
            $parent = get_term_by('slug', $a['parent'], $a['taxonomy']);
            $args['parent'] = $parent ? $parent->term_id : 0;
        }
        $terms = get_terms($args);
        if (is_wp_error($terms) || ! $terms) {
            return '';
        }
        $html = '<div class="sta sta-grid sta-dest-grid" style="--sta-cols:'.(int) $a['columns'].'">';
        foreach ($terms as $t) {
            $img = get_term_meta($t->term_id, 'sta_image', true);
            $name = sta_lang() === 'en' ? (get_term_meta($t->term_id, 'name_en', true) ?: $t->name) : $t->name;
            $html .= '<a class="sta-dest" href="'.esc_url(get_term_link($t)).'"'.($img ? ' style="background-image:url(\''.esc_url($img).'\')"' : '').'><span>'.esc_html($name).'</span><small>'.esc_html(sprintf(_n('%d offer', '%d offers', $t->count, 'seeinside-travel'), $t->count)).'</small></a>';
        }

        return $html.'</div>';
    }

    /* ======================== automatic layout ======================== */

    public static function auto_layout($content)
    {
        if (! is_singular(array_values(STA_Schema::TYPES)) || ! in_the_loop() || ! is_main_query()) {
            return $content;
        }
        $postId = get_the_ID();
        if (! STA_Templates::use_auto_layout(get_post_type($postId), $postId)) {
            return $content;
        }
        remove_filter('the_content', [self::class, 'auto_layout'], 20);
        $html = STA_Render::section('layout', $postId);
        add_filter('the_content', [self::class, 'auto_layout'], 20);

        return $html;
    }

    /** Archive of a post type / destination: our grid with search (classic themes, no builder template) */
    public static function archive_template($template)
    {
        if (! (is_post_type_archive(array_values(STA_Schema::TYPES)) || is_tax(array_keys(STA_Schema::taxonomies())))) {
            return $template;
        }
        if (! STA_Templates::use_auto_archive() || wp_is_block_theme()) {
            return $template;
        }
        $theme = locate_template('seeinside-travel/archive.php');

        return $theme ?: STA_DIR.'templates/archive.php';
    }

    /* ======================== REST bridge to SeeInside ======================== */

    public static function routes(): void
    {
        $ns = 'seeinside-travel/v1';
        register_rest_route($ns, '/booking', ['methods' => 'POST', 'callback' => [self::class, 'r_booking'], 'permission_callback' => '__return_true']);
        register_rest_route($ns, '/visa', ['methods' => 'POST', 'callback' => [self::class, 'r_visa'], 'permission_callback' => '__return_true']);
        register_rest_route($ns, '/quote', ['methods' => 'GET', 'callback' => [self::class, 'r_quote'], 'permission_callback' => '__return_true']);
        register_rest_route($ns, '/availability', ['methods' => 'GET', 'callback' => [self::class, 'r_availability'], 'permission_callback' => '__return_true']);
        register_rest_route($ns, '/status', ['methods' => 'GET', 'callback' => [self::class, 'r_status'], 'permission_callback' => '__return_true']);
    }

    /** Simple spam protection: hidden field must stay empty, form not sent too fast, max 10 forms per 10 min per visitor */
    protected static function guard(WP_REST_Request $r, bool $checkSpeed = true): ?WP_REST_Response
    {
        if (trim((string) $r->get_param('website')) !== '') {
            return new WP_REST_Response(['ok' => true], 200);   // bots: pretend success
        }
        $ts = (int) $r->get_param('sta_ts');
        if ($checkSpeed && $ts && (microtime(true) * 1000 - $ts) < 1200) {
            return new WP_REST_Response(['message' => __('Please try again.', 'seeinside-travel')], 429);
        }
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $key = 'sta_rl_'.md5($ip);
        $n = (int) get_transient($key);
        if ($n >= 10) {
            return new WP_REST_Response(['message' => __('Too many requests. Please call us.', 'seeinside-travel')], 429);
        }
        set_transient($key, $n + 1, 10 * MINUTE_IN_SECONDS);

        return null;
    }

    public static function check(WP_REST_Request $r, bool $checkSpeed = true): ?WP_REST_Response
    {
        return self::guard($r, $checkSpeed);
    }

    public static function pass(array $res): WP_REST_Response
    {
        return self::relay($res);
    }

    protected static function relay(array $res): WP_REST_Response
    {
        [$code, $body] = $res;
        if ($code === 0) {
            return new WP_REST_Response(['message' => __('Booking system is not reachable at the moment. Please call us.', 'seeinside-travel')], 502);
        }

        return new WP_REST_Response($body, $code);
    }

    public static function r_booking(WP_REST_Request $r): WP_REST_Response
    {
        if ($blocked = self::guard($r)) {
            return $blocked;
        }
        $p = $r->get_json_params() ?: $r->get_body_params();
        $allowed = ['type', 'slug', 'charter_id', 'transport_only', 'departure_id', 'departure_place_id', 'room_id', 'ticket_id', 'date_from', 'date_to', 'adults', 'children', 'children_ages', 'quantity', 'extras', 'customer', 'passengers', 'message'];
        $data = array_intersect_key((array) $p, array_flip($allowed));
        $data['locale'] = sta_lang();
        $data['message'] = trim(($data['message'] ?? '')."\n\n[".home_url('/').']');

        $res = STA_Api::post('bookings', $data);
        if ($res[0] === 201) {
            do_action('sta_booking_created', $res[1]['data'] ?? [], $data);
            sta_log('booking '.($res[1]['data']['reference'] ?? '').' ('.($data['type'] ?? '').' '.($data['slug'] ?? '').')');
        }

        return self::relay($res);
    }

    public static function r_visa(WP_REST_Request $r): WP_REST_Response
    {
        if ($blocked = self::guard($r)) {
            return $blocked;
        }
        $fields = [];
        foreach (['visa', 'first_name', 'last_name', 'email', 'phone', 'nationality', 'travel_date', 'express'] as $k) {
            if ($r->get_param($k) !== null) {
                $fields[$k] = sanitize_text_field((string) $r->get_param($k));
            }
        }
        $fields['locale'] = sta_lang();
        $answers = (array) $r->get_param('answers');
        // name / e-mail / phone are asked once at the top of the form; questionnaires may contain them too
        foreach (['first_name', 'last_name', 'email', 'phone'] as $k) {
            if (! isset($answers[$k]) && isset($fields[$k])) {
                $answers[$k] = $fields[$k];
            }
        }
        $fields['answers'] = array_map(fn ($v) => is_array($v) ? implode(', ', array_map('sanitize_text_field', $v)) : sanitize_textarea_field((string) $v), $answers);

        // uploaded documents (validated again by SeeInside: pdf/jpg/png/webp/heic, max 10 MB)
        $files = [];
        $raw = $_FILES['documents'] ?? null;
        if ($raw && is_array($raw['name'])) {
            foreach ($raw['name'] as $key => $names) {
                $names = (array) $names;
                foreach ($names as $i => $name) {
                    $tmp = is_array($raw['tmp_name'][$key]) ? $raw['tmp_name'][$key][$i] : $raw['tmp_name'][$key];
                    $err = is_array($raw['error'][$key]) ? $raw['error'][$key][$i] : $raw['error'][$key];
                    $size = is_array($raw['size'][$key]) ? $raw['size'][$key][$i] : $raw['size'][$key];
                    if ($err !== UPLOAD_ERR_OK || ! is_uploaded_file($tmp) || $size > 10 * MB_IN_BYTES) {
                        continue;
                    }
                    $check = wp_check_filetype_and_ext($tmp, $name, ['pdf' => 'application/pdf', 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'heic' => 'image/heic']);
                    $mime = $check['type'] ?: (str_ends_with(strtolower($name), '.heic') ? 'image/heic' : null);
                    if (! $mime) {
                        continue;
                    }
                    $files['documents['.sanitize_key($key).']'.(is_array($raw['name'][$key]) ? '[]' : '')][] = [$tmp, sanitize_file_name($name), $mime];
                }
            }
        }

        $res = STA_Api::post_multipart('visa-applications', $fields, $files);
        if ($res[0] === 201) {
            sta_log('visa application '.($res[1]['data']['reference'] ?? ''));
        }

        return self::relay($res);
    }

    public static function r_quote(WP_REST_Request $r): WP_REST_Response
    {
        $slug = sanitize_title((string) $r->get_param('slug'));

        return self::relay(STA_Api::get('accommodations/'.$slug.'/quote', array_filter([
            'from' => sanitize_text_field((string) $r->get_param('from')),
            'to' => sanitize_text_field((string) $r->get_param('to')),
            'adults' => (int) $r->get_param('adults') ?: 2,
            'children' => (int) $r->get_param('children'),
        ], fn ($v) => $v !== '' && $v !== null)));
    }

    public static function r_availability(WP_REST_Request $r): WP_REST_Response
    {
        $slug = sanitize_title((string) $r->get_param('slug'));
        $from = sanitize_text_field((string) $r->get_param('from'));
        $to = sanitize_text_field((string) $r->get_param('to'));
        $key = 'sta_av_'.md5($slug.$from.$to);
        $cached = get_transient($key);
        if ($cached) {
            return new WP_REST_Response($cached, 200);
        }
        $res = STA_Api::get('accommodations/'.$slug.'/availability', ['from' => $from, 'to' => $to]);
        if ($res[0] === 200) {
            set_transient($key, $res[1], 5 * MINUTE_IN_SECONDS);
        }

        return self::relay($res);
    }

    public static function r_status(WP_REST_Request $r): WP_REST_Response
    {
        if ($blocked = self::guard($r, false)) {
            return $blocked;
        }
        $ref = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string) $r->get_param('reference')));
        $email = sanitize_email((string) $r->get_param('email'));
        $path = (str_starts_with($ref, 'V') ? 'visa-applications/' : 'bookings/').rawurlencode($ref);

        return self::relay(STA_Api::get($path, ['email' => $email]));
    }
}
