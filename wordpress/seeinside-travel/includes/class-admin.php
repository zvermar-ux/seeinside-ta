<?php

defined('ABSPATH') || exit;

/** WP admin: connection, sync / import, display options, help */
class STA_Admin
{
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_post_sta_save', [self::class, 'save']);
        add_action('admin_post_sta_action', [self::class, 'action']);
        add_action('wp_ajax_sta_import', [self::class, 'ajax_import']);
        add_action('wp_ajax_sta_changes', [self::class, 'ajax_changes']);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
        add_filter('plugin_action_links_'.plugin_basename(STA_FILE), fn ($l) => array_merge(['<a href="'.esc_url(admin_url('admin.php?page=seeinside-travel')).'">'.esc_html__('Settings', 'seeinside-travel').'</a>'], $l));
        add_action('admin_notices', [self::class, 'notices']);
    }

    public static function menu(): void
    {
        add_menu_page('SeeInside Travel', 'SeeInside Travel', 'manage_options', 'seeinside-travel', [self::class, 'page'], 'dashicons-airplane', 24);
    }

    public static function assets($hook): void
    {
        if ($hook !== 'toplevel_page_seeinside-travel') {
            return;
        }
        wp_enqueue_style('sta-admin', STA_URL.'assets/css/admin.css', [], STA_VERSION);
        wp_enqueue_script('sta-admin', STA_URL.'assets/js/admin.js', [], STA_VERSION, true);
        wp_localize_script('sta-admin', 'STA_ADMIN', [
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sta_admin'),
            'i18n' => [
                'running' => __('Importing… keep this page open.', 'seeinside-travel'),
                'done' => __('Import finished.', 'seeinside-travel'),
                'error' => __('Error', 'seeinside-travel'),
            ],
        ]);
    }

    public static function notices(): void
    {
        if (! current_user_can('manage_options') || STA_Api::configured() || (get_current_screen()->id ?? '') === 'toplevel_page_seeinside-travel') {
            return;
        }
        echo '<div class="notice notice-warning"><p><b>SeeInside Travel:</b> '.esc_html__('connect the plugin with your API key to start the sync.', 'seeinside-travel').' <a href="'.esc_url(admin_url('admin.php?page=seeinside-travel')).'">'.esc_html__('Settings', 'seeinside-travel').'</a></p></div>';
    }

    protected static function tabs(): array
    {
        return [
            'connection' => __('Connection', 'seeinside-travel'),
            'sync' => __('Sync', 'seeinside-travel'),
            'display' => __('Display & templates', 'seeinside-travel'),
            'help' => __('Shortcodes & help', 'seeinside-travel'),
        ];
    }

    public static function page(): void
    {
        $tab = sanitize_key($_GET['tab'] ?? (STA_Api::configured() ? 'sync' : 'connection'));
        $s = get_option('sta_settings', []);
        echo '<div class="wrap sta-admin"><h1><span class="dashicons dashicons-airplane"></span> SeeInside Travel</h1>';
        if (! empty($_GET['sta_msg'])) {
            $ok = ($_GET['sta_ok'] ?? '1') === '1';
            echo '<div class="notice notice-'.($ok ? 'success' : 'error').' is-dismissible"><p>'.esc_html(wp_unslash($_GET['sta_msg'])).'</p></div>';
        }
        echo '<nav class="nav-tab-wrapper">';
        foreach (self::tabs() as $k => $label) {
            echo '<a class="nav-tab'.($tab === $k ? ' nav-tab-active' : '').'" href="'.esc_url(admin_url('admin.php?page=seeinside-travel&tab='.$k)).'">'.esc_html($label).'</a>';
        }
        echo '</nav><div class="sta-admin-body">';
        $method = 'tab_'.$tab;
        method_exists(self::class, $method) ? self::$method($s) : self::tab_connection($s);
        echo '</div></div>';
    }

    protected static function form_start(string $tab): void
    {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('sta_save');
        echo '<input type="hidden" name="action" value="sta_save"><input type="hidden" name="tab" value="'.esc_attr($tab).'">';
    }

    protected static function button(string $do, string $label, string $class = 'button', string $confirm = ''): string
    {
        $url = wp_nonce_url(admin_url('admin-post.php?action=sta_action&do='.$do), 'sta_action');

        return '<a class="'.esc_attr($class).'" href="'.esc_url($url).'"'.($confirm ? ' onclick="return confirm(\''.esc_js($confirm).'\')"' : '').'>'.esc_html($label).'</a>';
    }

    protected static function tab_connection(array $s): void
    {
        self::form_start('connection');
        echo '<div class="sta-card"><h2>'.esc_html__('Connect with SeeInside Travel', 'seeinside-travel').'</h2>';
        echo '<ol class="sta-steps"><li>'.wp_kses_post(__('In SeeInside Travel open <b>Settings → Websites (WordPress)</b> and add this site.', 'seeinside-travel')).'</li>';
        echo '<li>'.wp_kses_post(__('As <b>Webhook URL</b> enter:', 'seeinside-travel')).' <code class="sta-copy">'.esc_html(STA_Webhook::url()).'</code></li>';
        echo '<li>'.wp_kses_post(__('Click the key icon, copy the <b>API key</b> and the <b>webhook secret</b> and paste them below.', 'seeinside-travel')).'</li></ol>';
        echo '<table class="form-table">';
        echo '<tr><th><label for="api_url">'.esc_html__('SeeInside address', 'seeinside-travel').'</label></th><td><input class="regular-text" id="api_url" name="api_url" value="'.esc_attr($s['api_url'] ?? 'https://ta.seeinsidesolution.com').'"></td></tr>';
        echo '<tr><th><label for="api_key">'.esc_html__('API key', 'seeinside-travel').'</label></th><td><input class="regular-text code" id="api_key" name="api_key" autocomplete="off" placeholder="'.(! empty($s['api_key']) ? esc_attr__('saved – leave empty to keep', 'seeinside-travel') : 'ta_xxxxxxxx_…').'"></td></tr>';
        echo '<tr><th><label for="webhook_secret">'.esc_html__('Webhook secret', 'seeinside-travel').'</label></th><td><input class="regular-text code" id="webhook_secret" name="webhook_secret" autocomplete="off" placeholder="'.(! empty($s['webhook_secret']) ? esc_attr__('saved – leave empty to keep', 'seeinside-travel') : '').'"></td></tr>';
        echo '</table>';
        submit_button(__('Save', 'seeinside-travel'), 'primary', 'submit', false);
        echo ' '.self::button('test', __('Test connection', 'seeinside-travel'));
        echo '</div></form>';
    }

    protected static function tab_sync(array $s): void
    {
        $counts = [];
        foreach (STA_Schema::post_types() as $type => $pt) {
            $c = wp_count_posts($type);
            $counts[] = '<li><b>'.(int) ($c->publish ?? 0).'</b> '.esc_html($pt['plural']).((int) ($c->draft ?? 0) ? ' <small>(+'.(int) $c->draft.' '.esc_html__('inactive', 'seeinside-travel').')</small>' : '').'</li>';
        }
        $queue = count(get_option('sta_queue', []));
        echo '<div class="sta-grid2"><div class="sta-card"><h2>'.esc_html__('Status', 'seeinside-travel').'</h2><ul class="sta-counts">'.implode('', $counts).'<li><b>'.count(get_option('sta_charters', [])).'</b> '.esc_html__('charters', 'seeinside-travel').'</li><li><b>'.count(get_option('sta_slider', [])).'</b> '.esc_html__('slides', 'seeinside-travel').'</li></ul>';
        echo '<p>'.esc_html__('Last webhook (real time):', 'seeinside-travel').' <b>'.esc_html(get_option('sta_last_webhook') ?: '—').'</b><br>'
            .esc_html__('Last check of changes:', 'seeinside-travel').' <b>'.esc_html(get_option('sta_last_changes') ? sta_date(get_option('sta_last_changes'), true) : '—').'</b><br>'
            .esc_html__('Last full import:', 'seeinside-travel').' <b>'.esc_html(get_option('sta_last_full_import') ?: '—').'</b><br>'
            .esc_html__('Waiting in queue:', 'seeinside-travel').' <b>'.(int) $queue.'</b></p>';
        echo '<p><button class="button" data-sta-changes>'.esc_html__('Sync changes now', 'seeinside-travel').'</button></p></div>';

        echo '<div class="sta-card"><h2>'.esc_html__('Full import', 'seeinside-travel').'</h2><p>'.esc_html__('Brings in all tours, events, hotels, visas, destinations, slider and charters with photos. Needed once; after that changes arrive automatically in real time.', 'seeinside-travel').'</p>';
        echo '<p><button class="button button-primary button-hero" data-sta-import'.(STA_Api::configured() ? '' : ' disabled').'>'.esc_html__('Start full import', 'seeinside-travel').'</button></p>';
        echo '<div class="sta-progress" hidden><div class="sta-bar"><span></span></div><ol class="sta-import-log"></ol></div></div></div>';

        echo '<div class="sta-card"><h2>'.esc_html__('Log', 'seeinside-travel').'</h2><table class="widefat striped"><tbody>';
        foreach (array_slice(get_option('sta_log', []), 0, 40) as $l) {
            echo '<tr class="sta-log-'.esc_attr($l['l']).'"><td style="width:150px">'.esc_html($l['t']).'</td><td>'.esc_html($l['m']).'</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    protected static function tab_display(array $s): void
    {
        $builder = STA_Templates::builder();
        self::form_start('display');
        echo '<div class="sta-card"><h2>'.esc_html__('Page builder', 'seeinside-travel').'</h2><p>'.esc_html__('Detected:', 'seeinside-travel').' <b>'.esc_html(STA_Templates::builder_label()).'</b></p>';
        if ($builder === 'elementor_pro') {
            echo '<p>'.esc_html__('The plugin creates Theme Builder templates (single page and list) for tours, events, hotels and visas, made of "Travel" widgets. You can then change the design freely in Elementor.', 'seeinside-travel').'</p><p>'
                .self::button('templates', __('Create Elementor templates', 'seeinside-travel'), 'button button-primary').' '
                .self::button('templates_replace', __('Re-create (overwrite my changes)', 'seeinside-travel'), 'button', __('Your design changes in these templates will be lost. Continue?', 'seeinside-travel')).' '
                .'<a class="button" href="'.esc_url(admin_url('edit.php?post_type=elementor_library&tabs_group=theme')).'">'.esc_html__('Open Theme Builder', 'seeinside-travel').'</a></p>';
        } elseif ($builder === 'elementor') {
            echo '<p>'.esc_html__('Elementor (free): single pages use the automatic layout; on any page you can use the "Travel" widgets (lists, slider, search…). With Elementor Pro the plugin also creates Theme Builder templates.', 'seeinside-travel').'</p>';
        } else {
            echo '<p>'.esc_html__('Single pages use the automatic layout. On pages use the blocks "SeeInside Travel" (Gutenberg) or the shortcodes from the Help tab – they work in every builder.', 'seeinside-travel').'</p>';
        }
        echo '<p>'.self::button('pages', __('Create pages (tours, hotels, events, visas, last minute, charters, flights, booking check)', 'seeinside-travel')).'</p></div>';

        $layout = $s['layout_mode'] ?? 'auto';
        echo '<div class="sta-card"><h2>'.esc_html__('Layout', 'seeinside-travel').'</h2><table class="form-table">';
        echo '<tr><th>'.esc_html__('Single pages', 'seeinside-travel').'</th><td><label><input type="radio" name="layout_mode" value="auto"'.checked($layout, 'auto', false).'> '.esc_html__('Automatic layout when the builder has no template', 'seeinside-travel').'</label><br><label><input type="radio" name="layout_mode" value="off"'.checked($layout, 'off', false).'> '.esc_html__('Off – I build everything with widgets / shortcodes', 'seeinside-travel').'</label></td></tr>';
        echo '<tr><th>'.esc_html__('Lists (archives)', 'seeinside-travel').'</th><td><label><input type="radio" name="archive_mode" value="auto"'.checked($s['archive_mode'] ?? 'auto', 'auto', false).'> '.esc_html__('Plugin grid with search', 'seeinside-travel').'</label><br><label><input type="radio" name="archive_mode" value="theme"'.checked($s['archive_mode'] ?? 'auto', 'theme', false).'> '.esc_html__('Theme archive', 'seeinside-travel').'</label></td></tr>';
        echo '<tr><th>'.esc_html__('Offers per page', 'seeinside-travel').'</th><td><input type="number" min="3" max="60" name="per_page" value="'.esc_attr($s['per_page'] ?? 12).'"></td></tr>';
        echo '<tr><th>'.esc_html__('Colours', 'seeinside-travel').'</th><td><label>'.esc_html__('Main', 'seeinside-travel').' <input type="color" name="color_primary" value="'.esc_attr($s['color_primary'] ?? '#0ea5e9').'"></label> &nbsp; <label>'.esc_html__('Accent (badges)', 'seeinside-travel').' <input type="color" name="color_accent" value="'.esc_attr($s['color_accent'] ?? '#f97316').'"></label><p class="description">'.esc_html__('Elementor widgets also have their own colour settings.', 'seeinside-travel').'</p></td></tr>';
        echo '</table></div>';

        echo '<div class="sta-card"><h2>'.esc_html__('Addresses (URL)', 'seeinside-travel').'</h2><table class="form-table">';
        foreach (STA_Schema::post_types() as $type => $pt) {
            echo '<tr><th>'.esc_html($pt['plural']).'</th><td><code>'.esc_html(home_url('/')).'</code><input name="slugs['.esc_attr($type).']" value="'.esc_attr(STA_Post_Types::slug($type)).'"><code>/…</code></td></tr>';
        }
        $del = $s['delete_mode'] ?? 'trash';
        echo '<tr><th>'.esc_html__('Deleted in SeeInside', 'seeinside-travel').'</th><td><label><input type="radio" name="delete_mode" value="trash"'.checked($del, 'trash', false).'> '.esc_html__('Move to trash', 'seeinside-travel').'</label> &nbsp; <label><input type="radio" name="delete_mode" value="delete"'.checked($del, 'delete', false).'> '.esc_html__('Delete permanently', 'seeinside-travel').'</label></td></tr>';
        echo '</table>';
        submit_button(__('Save', 'seeinside-travel'));
        echo '</div></form>';
    }

    protected static function tab_help(array $s): void
    {
        $sections = implode(', ', array_map(fn ($k) => '<code>'.$k.'</code>', array_keys(STA_Render::SECTIONS)));
        $rows = [
            ['[sta_slider height="560"]', __('Homepage slider (supertitle, title, subtitle, button)', 'seeinside-travel')],
            ['[sta_search types="tour,accommodation,event"]', __('Search: destination, month, budget, last minute', 'seeinside-travel')],
            ['[sta_list type="tour" limit="6" columns="3" destination="grcija" orderby="departure"]', __('Grid of offers. type: tour, hotel, event, visa, all · last_minute="1" · featured="1" · orderby: date, departure, price, title, random · pagination="1" · search="1"', 'seeinside-travel')],
            ['[sta_last_minute]', __('All last minute offers', 'seeinside-travel')],
            ['[sta_destinations taxonomy="sta_destination" limit="8"]', __('Destination tiles', 'seeinside-travel')],
            ['[sta_charters]', __('Charter seats with booking', 'seeinside-travel')],
            ['[sta_flight_search]', __('Flight search (GOL IBE widget from SeeInside)', 'seeinside-travel')],
            ['[sta_booking_status]', __('Customer checks a booking / visa application (number + e-mail)', 'seeinside-travel')],
            ['[sta_section name="program"]', __('One part of the current tour/hotel/event/visa page. Names:', 'seeinside-travel').' '.$sections],
            ['[sta_booking] [sta_program] [sta_gallery] …', __('Short form of the same', 'seeinside-travel')],
        ];
        echo '<div class="sta-card"><h2>'.esc_html__('Shortcodes', 'seeinside-travel').'</h2><table class="widefat striped"><tbody>';
        foreach ($rows as [$code, $desc]) {
            echo '<tr><td style="width:46%"><code class="sta-copy">'.esc_html($code).'</code></td><td>'.wp_kses_post($desc).'</td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<div class="sta-card"><h2>'.esc_html__('For designers', 'seeinside-travel').'</h2><ul class="ul-disc">'
            .'<li>'.wp_kses_post(__('ACF field groups are registered in code (no import needed). In Elementor Pro use <b>Dynamic Tags → ACF Field</b> for single values.', 'seeinside-travel')).'</li>'
            .'<li>'.wp_kses_post(__('Post types: <code>sta_tour</code>, <code>sta_event</code>, <code>sta_hotel</code>, <code>sta_visa</code>. Taxonomies: <code>sta_destination</code>, <code>sta_group</code>, <code>sta_tour_type</code>, <code>sta_event_category</code>, <code>sta_hotel_type</code>, <code>sta_amenity</code>, <code>sta_country</code>.', 'seeinside-travel')).'</li>'
            .'<li>'.wp_kses_post(__('Any section can be replaced from the theme: <code>/your-theme/seeinside-travel/section-program.php</code> (variables <code>$d</code>, <code>$type</code>, <code>$post_id</code>). Forms: <code>form-booking.php</code>, <code>form-visa.php</code>.', 'seeinside-travel')).'</li>'
            .'<li>'.wp_kses_post(__('The whole record is in the meta <code>_sta_payload</code> (JSON, both languages) – helper <code>sta_data()</code>.', 'seeinside-travel')).'</li>'
            .'<li>'.wp_kses_post(__('English: texts switch automatically when the page language is English (Polylang / WPML / site language).', 'seeinside-travel')).'</li>'
            .'</ul></div>';
    }

    /* ---------------- handlers ---------------- */

    protected static function back(string $tab, string $msg, bool $ok = true): void
    {
        wp_safe_redirect(admin_url('admin.php?page=seeinside-travel&tab='.$tab.'&sta_ok='.($ok ? '1' : '0').'&sta_msg='.rawurlencode($msg)));
        exit;
    }

    public static function save(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('403');
        }
        check_admin_referer('sta_save');
        $s = get_option('sta_settings', []);
        $tab = sanitize_key($_POST['tab'] ?? 'connection');

        if ($tab === 'connection') {
            $s['api_url'] = esc_url_raw(trim(wp_unslash($_POST['api_url'] ?? ''))) ?: 'https://ta.seeinsidesolution.com';
            foreach (['api_key', 'webhook_secret'] as $k) {
                $v = trim(sanitize_text_field(wp_unslash($_POST[$k] ?? '')));
                if ($v !== '') {
                    $s[$k] = $v;
                }
            }
        } elseif ($tab === 'display') {
            $old = (array) ($s['slugs'] ?? []);
            $s['layout_mode'] = in_array($_POST['layout_mode'] ?? '', ['auto', 'off'], true) ? $_POST['layout_mode'] : 'auto';
            $s['archive_mode'] = in_array($_POST['archive_mode'] ?? '', ['auto', 'theme'], true) ? $_POST['archive_mode'] : 'auto';
            $s['per_page'] = max(3, min(60, (int) ($_POST['per_page'] ?? 12)));
            $s['color_primary'] = sanitize_hex_color(wp_unslash($_POST['color_primary'] ?? '')) ?: '';
            $s['color_accent'] = sanitize_hex_color(wp_unslash($_POST['color_accent'] ?? '')) ?: '';
            $s['delete_mode'] = ($_POST['delete_mode'] ?? '') === 'delete' ? 'delete' : 'trash';
            $s['slugs'] = array_map('sanitize_title', array_intersect_key((array) wp_unslash($_POST['slugs'] ?? []), STA_Schema::post_types()));
            update_option('sta_settings', $s);
            if ($old != $s['slugs']) {
                STA_Post_Types::register();
                flush_rewrite_rules();
            }
            self::back($tab, __('Saved.', 'seeinside-travel'));
        }
        update_option('sta_settings', $s);
        self::back($tab, __('Saved.', 'seeinside-travel'));
    }

    public static function action(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('403');
        }
        check_admin_referer('sta_action');
        switch (sanitize_key($_GET['do'] ?? '')) {
            case 'test':
                [$code, $body] = STA_Api::get('meta');
                if ($code === 200) {
                    STA_Sync::sync_meta();
                    self::back('connection', sprintf(__('Connected: %s', 'seeinside-travel'), $body['agency']['name'] ?? ''));
                }
                self::back('connection', sprintf(__('Connection failed (HTTP %1$d): %2$s', 'seeinside-travel'), $code, $body['message'] ?? ''), false);
                // no break
            case 'templates':
            case 'templates_replace':
                $r = STA_Templates::create_elementor_templates($_GET['do'] === 'templates_replace');
                self::back('display', isset($r['error']) ? $r['error'] : sprintf(__('Templates ready: %s', 'seeinside-travel'), $r['made'] ? implode(', ', $r['made']) : __('already existed', 'seeinside-travel')), ! isset($r['error']));
                // no break
            case 'pages':
                $r = STA_Templates::create_pages();
                self::back('display', sprintf(__('Pages ready: %s', 'seeinside-travel'), $r['made'] ? implode(', ', $r['made']) : __('already existed', 'seeinside-travel')));
        }
        self::back('sync', '—');
    }

    public static function ajax_import(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error('403', 403);
        }
        check_ajax_referer('sta_admin');
        @set_time_limit(120);
        $state = json_decode(wp_unslash($_POST['state'] ?? '{}'), true) ?: [];
        if (empty($state['started'])) {
            $state['started'] = time();
        }
        $next = STA_Sync::import_step($state);
        $next['started'] = $state['started'];
        isset($next['error']) ? wp_send_json_error($next) : wp_send_json_success($next);
    }

    public static function ajax_changes(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error('403', 403);
        }
        check_ajax_referer('sta_admin');
        $r = STA_Sync::pull_changes();
        $r['queue'] = STA_Sync::process_queue(40);
        wp_send_json_success($r);
    }
}
