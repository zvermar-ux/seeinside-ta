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
        wp_enqueue_style('sta-admin', STA_URL.'assets/css/admin.css', ['wp-color-picker'], STA_VERSION);
        wp_enqueue_script('sta-admin', STA_URL.'assets/js/admin.js', ['wp-color-picker'], STA_VERSION, true);
        wp_add_inline_script('sta-admin', 'jQuery(function($){$(".sta-color").wpColorPicker();});');
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
            'header' => __('Header', 'seeinside-travel'),
            'photos' => __('Photo stories', 'seeinside-travel'),
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
        $mode = $s['mode'] ?? 'pull';
        echo '<tr><th>'.esc_html__('Connection mode', 'seeinside-travel').'</th><td>'
            .'<label><input type="radio" name="mode" value="pull"'.checked($mode, 'pull', false).'> '.esc_html__('Normal – this site reads data from the app', 'seeinside-travel').'</label><br>'
            .'<label><input type="radio" name="mode" value="push"'.checked($mode, 'push', false).'> '.esc_html__('The hosting blocks connections to the app – the app sends everything (also turn on the same option in the app)', 'seeinside-travel').'</label>'
            .'<p class="description">'.esc_html__('In the second mode photos and data arrive from the app, and the website forms go straight from the visitor\'s browser to the app.', 'seeinside-travel').'</p></td></tr>';
        echo '<tr><th><label for="server_ip">'.esc_html__('Server IP (optional)', 'seeinside-travel').'</label></th><td><input class="regular-text code" id="server_ip" name="server_ip" value="'.esc_attr($s['server_ip'] ?? '').'" placeholder="123.45.67.89"><p class="description">'.esc_html__('Only if the test times out: the IP address of the SeeInside server (127.0.0.1 when the website is on the same server).', 'seeinside-travel').'</p></td></tr>';
        echo '</table>';
        submit_button(__('Save', 'seeinside-travel'), 'primary', 'submit', false);
        echo ' '.self::button('test', __('Test connection', 'seeinside-travel')).' '.self::button('diagnose', __('Diagnose connection', 'seeinside-travel'));
        echo '</div></form>';
        $diag = get_transient('sta_diag');
        if ($diag) {
            delete_transient('sta_diag');
            echo '<div class="sta-card"><h2>'.esc_html__('Connection diagnosis', 'seeinside-travel').'</h2><table class="widefat striped"><tbody>';
            foreach ($diag as [$label, $state, $detail]) {
                $icon = ['ok' => '✅', 'error' => '❌', 'info' => 'ℹ️'][$state] ?? '';
                echo '<tr><td style="width:40%"><b>'.esc_html($label).'</b></td><td>'.$icon.' '.esc_html($detail).'</td></tr>';
            }
            echo '</tbody></table><p class="description">'.esc_html__('DNS and outgoing internet OK but TCP / HTTPS to SeeInside fails → the SeeInside server firewall blocks this website: allow the IP address shown above in aaPanel → Security. Outgoing internet fails too → the hosting blocks outgoing connections: ask the hosting support.', 'seeinside-travel').'</p></div>';
        }
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

        if (sta_push_mode()) {
            echo '<div class="sta-card"><h2>'.esc_html__('Full import', 'seeinside-travel').'</h2><p>'.wp_kses_post(__('The app sends the data to this site. In SeeInside open <b>Settings → Websites (WordPress)</b> and click the <b>upload icon</b> (Send everything to the website). Refresh this page to follow the progress.', 'seeinside-travel')).'</p></div></div>';
        } else {
        echo '<div class="sta-card"><h2>'.esc_html__('Full import', 'seeinside-travel').'</h2><p>'.esc_html__('Brings in all tours, events, hotels, visas, destinations, slider and charters with photos. Needed once; after that changes arrive automatically in real time.', 'seeinside-travel').'</p>';
        echo '<p><button class="button button-primary button-hero" data-sta-import'.(STA_Api::configured() ? '' : ' disabled').'>'.esc_html__('Start full import', 'seeinside-travel').'</button></p>';
        echo '<div class="sta-progress" hidden><div class="sta-bar"><span></span></div><ol class="sta-import-log"></ol></div></div></div>';
        }

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
            $known = array_filter((array) get_option('sta_elementor_templates', []), fn ($id) => get_post_status($id));
            if ($known) {
                echo '<details><summary>'.esc_html(sprintf(__('Templates made by the plugin (%d) – edit in Elementor', 'seeinside-travel'), count($known))).'</summary><ul class="sta-tpl-list">';
                foreach ($known as $id) {
                    echo '<li><a href="'.esc_url(admin_url('post.php?post='.(int) $id.'&action=elementor')).'">'.esc_html(get_the_title($id)).'</a></li>';
                }
                echo '</ul><p class="description">'.esc_html__('Pages of destinations, places, groups, tour types and accommodation types each have their own template; every accommodation type (hotel, apartment, villa…) also has its own single page. Click "Create Elementor templates" again after adding a new accommodation type.', 'seeinside-travel').'</p></details>';
            }
        } elseif ($builder === 'elementor') {
            echo '<p>'.esc_html__('Elementor (free): single pages use the automatic layout; on any page you can use the "Travel" widgets (lists, slider, search…). With Elementor Pro the plugin also creates Theme Builder templates.', 'seeinside-travel').'</p>';
        } else {
            echo '<p>'.esc_html__('Single pages use the automatic layout. On pages use the blocks "SeeInside Travel" (Gutenberg) or the shortcodes from the Help tab – they work in every builder.', 'seeinside-travel').'</p>';
        }
        echo '<p>'.self::button('pages', __('Create pages (tours, hotels, events, visas, last minute, charters, flights, booking check)', 'seeinside-travel')).'</p>';
        if ($builder === 'elementor_pro') {
            // one look for every list: destinations, places, groups, types, categories + Organized tours, Hotels, Apartments, Events
            echo '<p>'.self::button('unify', __('Unified design of all lists', 'seeinside-travel'), 'button button-primary', __('The design of the page "Accommodation type" (header and list) becomes the design of the pages of destinations, places, groups, tour types and event categories, and is copied to the pages Organized tours, Hotels, Apartments and Events. Photos chosen on each page stay. Continue?', 'seeinside-travel'))
                .' '.self::button('restore_pages', __('Undo on the 4 pages', 'seeinside-travel'), 'button-link', __('The pages Organized tours, Hotels, Apartments and Events go back to how they were. Continue?', 'seeinside-travel')).'<br><span class="description">'
                .esc_html__('All lists then use one template: "Page: lists – SeeInside" (Templates → Theme Builder → Archive). Changes there show on every destination, group and type at once; click here again to copy them to the 4 pages. A page photo: the featured image of the page, or "Background photo" in its header. Other pages are not changed.', 'seeinside-travel').'</span></p>';
        }
        echo '<p>'.self::button('menu', __('Update main menu (add only new)', 'seeinside-travel'), 'button button-primary', __('Your arrangement of "SeeInside – main menu" stays; only new groups, destinations and types are added, and hotel areas without hotels are removed. Continue?', 'seeinside-travel'))
            .' '.self::button('menu_fresh', __('Create the menu again from the beginning', 'seeinside-travel'), 'button-link', __('The order, names and places of the items stay as you arranged them; items you deleted come back, items without offers are removed, new ones are added where they belong. Continue?', 'seeinside-travel')).'<br><span class="description">'
            .esc_html__('Both buttons keep the order, names and places of the items as you arranged them in Appearance → Menus. New groups, destinations, hotel regions and types are added under their parent in their logical place (e.g. a country in alphabetical order). "Update": items you deleted do not come back. "Create again": items you deleted come back and everything without offers is cleaned out.', 'seeinside-travel').'</span></p></div>';

        $layout = $s['layout_mode'] ?? 'auto';
        echo '<div class="sta-card"><h2>'.esc_html__('Layout', 'seeinside-travel').'</h2><table class="form-table">';
        echo '<tr><th>'.esc_html__('Single pages', 'seeinside-travel').'</th><td><label><input type="radio" name="layout_mode" value="auto"'.checked($layout, 'auto', false).'> '.esc_html__('Automatic layout when the builder has no template', 'seeinside-travel').'</label><br><label><input type="radio" name="layout_mode" value="off"'.checked($layout, 'off', false).'> '.esc_html__('Off – I build everything with widgets / shortcodes', 'seeinside-travel').'</label></td></tr>';
        echo '<tr><th>'.esc_html__('Lists (archives)', 'seeinside-travel').'</th><td><label><input type="radio" name="archive_mode" value="auto"'.checked($s['archive_mode'] ?? 'auto', 'auto', false).'> '.esc_html__('Plugin grid with search', 'seeinside-travel').'</label><br><label><input type="radio" name="archive_mode" value="theme"'.checked($s['archive_mode'] ?? 'auto', 'theme', false).'> '.esc_html__('Theme archive', 'seeinside-travel').'</label></td></tr>';
        $chat = get_option('sta_chat', []);
        echo '<tr><th>'.esc_html__('Live chat', 'seeinside-travel').'</th><td><label><input type="checkbox" name="chat_off" value="1"'.checked(! empty($s['chat_off']), true, false).'> '.esc_html__('Hide the live chat on this website', 'seeinside-travel').'</label><p class="description">'
            .esc_html(! empty($chat['enabled']) ? __('The chat is turned on in SeeInside (AI & Live chat → Live chat settings).', 'seeinside-travel') : __('The chat is turned off in SeeInside. Turn it on in AI & Live chat → Live chat settings.', 'seeinside-travel')).'</p></td></tr>';
        $qs = $s['qs_header'] ?? 'auto';
        echo '<tr><th>'.esc_html__('Search in the header', 'seeinside-travel').'</th><td><label><input type="radio" name="qs_header" value="auto"'.checked($qs, 'auto', false).'> '.esc_html__('Magnifier in the site header automatically (next to the menu / Contact button)', 'seeinside-travel').'</label><br>'
            .'<label><input type="radio" name="qs_header" value="off"'.checked($qs, 'off', false).'> '.esc_html__('Off – I place [sta_search_button] myself', 'seeinside-travel').'</label>'
            .'<p class="description">'.esc_html__('Opens the quick search and the search form from any page ("/" or Ctrl+K too). Without a header found, it is shown as a round button at the bottom left.', 'seeinside-travel').'</p></td></tr>';
        echo '<tr><th>'.esc_html__('Main menu on smaller screens', 'seeinside-travel').'</th><td><label><input type="checkbox" name="menu_more" value="1"'.checked(($s['menu_more'] ?? '1') === '1', true, false).'> '
            .esc_html__('Never break into two rows: items that do not fit go under "⋯" at the end (click shows them)', 'seeinside-travel').'</label>'
            .'<p class="description">'.esc_html__('Also: at most 10 px between the buttons in the header.', 'seeinside-travel').'</p></td></tr>';
        echo '<tr><th>'.esc_html__('Offers per page', 'seeinside-travel').'</th><td><input type="number" min="3" max="60" name="per_page" value="'.esc_attr($s['per_page'] ?? 12).'"></td></tr>';
        echo '<tr><th>'.esc_html__('Colours', 'seeinside-travel').'</th><td><label>'.esc_html__('Main', 'seeinside-travel').' <input type="color" name="color_primary" value="'.esc_attr($s['color_primary'] ?? '#0ea5e9').'"></label> &nbsp; <label>'.esc_html__('Accent (badges)', 'seeinside-travel').' <input type="color" name="color_accent" value="'.esc_attr($s['color_accent'] ?? '#f97316').'"></label><p class="description">'.esc_html__('Elementor widgets also have their own colour settings.', 'seeinside-travel').'</p></td></tr>';
        $gl = $s['gallery_layout'] ?? 'mosaic';
        $gopts = ['mosaic' => __('Mosaic – big photo + 4 small', 'seeinside-travel'), 'mosaic3' => __('Mosaic – big photo + 2 small', 'seeinside-travel'), 'slider' => __('Slider – one photo with arrows and thumbnails', 'seeinside-travel'), 'grid' => __('Grid – all photos the same size', 'seeinside-travel')];
        echo '<tr><th>'.esc_html__('Photo gallery', 'seeinside-travel').'</th><td><select name="gallery_layout">';
        foreach ($gopts as $k => $l) {
            echo '<option value="'.esc_attr($k).'"'.selected($gl, $k, false).'>'.esc_html($l).'</option>';
        }
        echo '</select><p><label>'.esc_html__('Height (computer)', 'seeinside-travel').' <input type="number" min="150" max="900" step="10" name="gallery_height" value="'.esc_attr($s['gallery_height'] ?? 420).'" style="width:90px"> px</label> &nbsp; '
            .'<label>'.esc_html__('Height (phone)', 'seeinside-travel').' <input type="number" min="150" max="700" step="10" name="gallery_height_mobile" value="'.esc_attr($s['gallery_height_mobile'] ?? 260).'" style="width:90px"> px</label></p>'
            .'<p class="description">'.esc_html__('For all offers. In an Elementor template the "Travel: section" widget (Gallery) can set its own look and height.', 'seeinside-travel').'</p></td></tr>';
        echo '<tr><th>'.esc_html__('General terms of travel', 'seeinside-travel').'</th><td>'
            .'<input type="url" class="regular-text" name="terms_url" value="'.esc_attr($s['terms_url'] ?? '').'" placeholder="'.esc_attr(sta_terms_url() ?: home_url('/opsti-uslovi-za-patuvanje/')).'"><br>'
            .'<input type="text" class="regular-text" name="terms_label" value="'.esc_attr($s['terms_label'] ?? '').'" placeholder="'.esc_attr__('General terms of travel of NATA', 'seeinside-travel').'" style="margin-top:6px">'
            .'<p class="description">'.esc_html__('Every booking form has a required box "I declare that I am familiar with the General terms of travel of NATA and accept them in full" with this link. Empty link = the page "opsti-uslovi-za-patuvanje".', 'seeinside-travel').'</p></td></tr>';
        echo '</table></div>';

        echo '<div class="sta-card"><h2>'.esc_html__('Addresses (URL)', 'seeinside-travel').'</h2><table class="form-table">';
        foreach (STA_Schema::post_types() as $type => $pt) {
            echo '<tr><th>'.esc_html($pt['plural']).'</th><td><code>'.esc_html(home_url('/')).'</code><input name="slugs['.esc_attr($type).']" value="'.esc_attr(STA_Post_Types::slug($type)).'"><code>/…</code></td></tr>';
        }
        // the listing pages: hotels (booking system) and apartments are separate pages
        $pages = get_option('sta_pages', []);
        foreach (['hoteli' => __('Hotels (page with the search)', 'seeinside-travel'), 'apartmani' => __('Apartments (page with filters)', 'seeinside-travel')] as $key => $label) {
            if (! empty($pages[$key]) && ($pg = get_post($pages[$key])) && $pg->post_status === 'publish') {
                echo '<tr><th>'.esc_html($label).'</th><td><code>'.esc_html(home_url('/')).'</code><input name="page_slugs['.esc_attr($key).']" value="'.esc_attr(rawurldecode($pg->post_name)).'"><code>/</code></td></tr>';
            }
        }
        $del = $s['delete_mode'] ?? 'trash';
        echo '<tr><th>'.esc_html__('Deleted in SeeInside', 'seeinside-travel').'</th><td><label><input type="radio" name="delete_mode" value="trash"'.checked($del, 'trash', false).'> '.esc_html__('Move to trash', 'seeinside-travel').'</label> &nbsp; <label><input type="radio" name="delete_mode" value="delete"'.checked($del, 'delete', false).'> '.esc_html__('Delete permanently', 'seeinside-travel').'</label></td></tr>';
        echo '</table>';
        submit_button(__('Save', 'seeinside-travel'));
        echo '</div></form>';
    }

    /** Header: top bar with links and the phone layout of the theme header */
    protected static function tab_header(array $s): void
    {
        $o = STA_Header::opt();
        $d = STA_Header::defaults();
        $chk = fn ($k, $label) => '<label style="display:block;margin:2px 0"><input type="checkbox" name="'.$k.'" value="1"'.checked($o[$k], '1', false).'> '.esc_html($label).'</label>';
        $num = fn ($k, $label, $help = '') => '<tr><th><label for="hd-'.$k.'">'.esc_html($label).'</label></th><td><input type="number" min="0" class="small-text" id="hd-'.$k.'" name="'.$k.'" value="'.esc_attr((string) $o[$k]).'"> px <span class="description">'.esc_html(sprintf(__('default: %s', 'seeinside-travel'), $d[$k])).($help ? ' · '.esc_html($help) : '').'</span></td></tr>';
        $col = fn ($k, $label, $help = '') => '<tr><th>'.esc_html($label).'</th><td><input type="text" class="sta-color" name="'.$k.'" value="'.esc_attr($o[$k]).'" data-default-color="'.esc_attr($d[$k]).'">'.($help ? '<p class="description">'.esc_html($help).'</p>' : '').'</td></tr>';
        $sel = function ($k, $label, array $opts) use ($o) {
            $h = '<tr><th>'.esc_html($label).'</th><td><select name="'.$k.'">';
            foreach ($opts as $v => $l) {
                $h .= '<option value="'.esc_attr($v).'"'.selected($o[$k], $v, false).'>'.esc_html($l).'</option>';
            }

            return $h.'</select></td></tr>';
        };
        self::form_start('header');
        echo '<div class="sta-grid2"><div>';
        echo '<div class="sta-card"><h2>'.esc_html__('Top bar', 'seeinside-travel').'</h2><p class="description">'.esc_html__('A thin bar above the header of the theme: phone and e-mail of the agency on the left, links to pages on the right.', 'seeinside-travel').'</p><table class="form-table">'
            .'<tr><th>'.esc_html__('Show', 'seeinside-travel').'</th><td>'.$chk('tb_on', __('Top bar above the header', 'seeinside-travel')).'</td></tr>'
            .$sel('tb_where', __('Where', 'seeinside-travel'), ['all' => __('Computer and phone', 'seeinside-travel'), 'mobile' => __('Only phones and tablets', 'seeinside-travel'), 'desktop' => __('Only computers', 'seeinside-travel')])
            .'<tr><th><label for="hd-links">'.esc_html__('Links', 'seeinside-travel').'</label></th><td><textarea class="large-text code" rows="5" id="hd-links" name="tb_links">'.esc_textarea($o['tb_links']).'</textarea><p class="description">'.esc_html__('One per line: Text|/page/  (e.g. Visas|/vizi/ or Facebook|https://facebook.com/…)', 'seeinside-travel').'</p></td></tr>'
            .'<tr><th>'.esc_html__('Contact', 'seeinside-travel').'</th><td>'.$chk('tb_phone', __('Phone of the agency', 'seeinside-travel')).$chk('tb_email', __('E-mail of the agency (on phones only the icon)', 'seeinside-travel')).'</td></tr>'
            .'<tr><th><label for="hd-text">'.esc_html__('Short text', 'seeinside-travel').'</label></th><td><input type="text" class="large-text" id="hd-text" name="tb_text" value="'.esc_attr($o['tb_text']).'" placeholder="'.esc_attr__('e.g. Working hours: Mon–Fri 9–17', 'seeinside-travel').'"><p class="description">'.esc_html__('Only on computers.', 'seeinside-travel').'</p></td></tr>'
            .$col('tb_bg', __('Background', 'seeinside-travel')).$col('tb_color', __('Text', 'seeinside-travel'))
            .$num('tb_size', __('Text size', 'seeinside-travel'))
            .'</table></div>';
        echo '<div class="sta-card"><h2>'.esc_html__('Logo while loading', 'seeinside-travel').'</h2><p class="description">'.esc_html__('Stops the logo from showing huge for a moment while the page loads.', 'seeinside-travel').'</p><table class="form-table">'
            .'<tr><th>'.esc_html__('Show', 'seeinside-travel').'</th><td>'.$chk('early_on', __('Keep the logo small from the first moment', 'seeinside-travel')).'</td></tr>'
            .$num('logo_max', __('Widest logo on computers', 'seeinside-travel'))
            .'</table></div>';
        echo '</div><div>';
        echo '<div class="sta-card"><h2>'.esc_html__('Header on phones', 'seeinside-travel').'</h2><p class="description">'.esc_html__('Screens up to 1199px: the logo, the magnifier and the menu button in one row.', 'seeinside-travel').'</p><table class="form-table">'
            .'<tr><th>'.esc_html__('Arrange', 'seeinside-travel').'</th><td>'.$chk('m_on', __('Arrange the header on phones', 'seeinside-travel')).'</td></tr>'
            .$num('m_logo', __('Logo width', 'seeinside-travel'))
            .$num('m_pad', __('Space above and below', 'seeinside-travel'))
            .$col('m_bg', __('Header background', 'seeinside-travel'), __('Empty = as the theme', 'seeinside-travel'))
            .$num('m_btn_size', __('Menu button size', 'seeinside-travel'))
            .$col('m_btn_bg', __('Menu button – background', 'seeinside-travel'), __('Empty = as the theme', 'seeinside-travel'))
            .$col('m_btn_color', __('Menu button – icon', 'seeinside-travel'), __('Empty = as the theme', 'seeinside-travel'))
            .'<tr><th>'.esc_html__('Phone menu', 'seeinside-travel').'</th><td>'.$chk('m_menu_contact', __('Phone and e-mail of the agency at the end of the phone menu', 'seeinside-travel')).'</td></tr>'
            .$sel('m_sticky', __('Sticky header (stays on top while scrolling)', 'seeinside-travel'), ['theme' => __('As the theme', 'seeinside-travel'), 'off' => __('Off on phones', 'seeinside-travel'), 'on' => __('Always on top', 'seeinside-travel')])
            .'<tr><th><label for="hd-hide">'.esc_html__('Hide on phones', 'seeinside-travel').'</label></th><td><textarea class="large-text code" rows="3" id="hd-hide" name="m_hide" placeholder=".header-button .th-btn">'.esc_textarea($o['m_hide']).'</textarea><p class="description">'.esc_html__('CSS selectors of elements of the header that should not show on phones, one per line.', 'seeinside-travel').'</p></td></tr>'
            .'<tr><th><label for="hd-css">'.esc_html__('Own CSS', 'seeinside-travel').'</label></th><td><textarea class="large-text code" rows="4" id="hd-css" name="css" placeholder="@media (max-width:767px){ .sta-topbar { font-size:12px } }">'.esc_textarea($o['css']).'</textarea></td></tr>'
            .'</table></div>';
        echo '</div></div>';
        submit_button(__('Save', 'seeinside-travel'));
        echo '</form>';
    }

    /** Photo stories: texts, buttons and design of [sta_photo_stories_slider] / [sta_photo_stories] */
    protected static function tab_photos(array $s): void
    {
        $o = STA_Photo_Stories::opt();
        $d = STA_Photo_Stories::defaults();
        $txt = fn ($k, $label, $help = '') => '<tr><th><label for="ps-'.$k.'">'.esc_html($label).'</label></th><td><input type="text" class="large-text" id="ps-'.$k.'" name="'.$k.'" value="'.esc_attr($o[$k]).'">'.($help ? '<p class="description">'.esc_html($help).'</p>' : '').'</td></tr>';
        $num = fn ($k, $label, $unit = 'px', $help = '') => '<tr><th><label for="ps-'.$k.'">'.esc_html($label).'</label></th><td><input type="number" min="0" class="small-text" id="ps-'.$k.'" name="'.$k.'" value="'.esc_attr((string) $o[$k]).'"> '.esc_html($unit)
            .' <span class="description">'.esc_html(sprintf(__('default: %s', 'seeinside-travel'), $d[$k] ?: '—')).($help ? ' · '.esc_html($help) : '').'</span></td></tr>';
        $chk = fn ($k, $label) => '<label style="display:block;margin:2px 0"><input type="checkbox" name="'.$k.'" value="1"'.checked($o[$k], '1', false).'> '.esc_html($label).'</label>';
        $col = fn ($k, $label, $help = '') => '<tr><th>'.esc_html($label).'</th><td><input type="text" class="sta-color" name="'.$k.'" value="'.esc_attr($o[$k]).'" data-default-color="'.esc_attr($d[$k]).'">'.($help ? '<p class="description">'.esc_html($help).'</p>' : '').'</td></tr>';
        $font = function ($k, $label) use ($o) {
            $h = '<tr><th><label for="ps-'.$k.'">'.esc_html($label).'</label></th><td><select id="ps-'.$k.'" name="'.$k.'">';
            foreach (STA_Photo_Stories::FONTS as $slug => $name) {
                $h .= '<option value="'.esc_attr($slug).'"'.selected($o[$k], $slug, false).($name ? ' style="font-family:\''.esc_attr($name).'\'"' : '').'>'.esc_html($name ?: __('Font of the theme', 'seeinside-travel')).'</option>';
            }

            return $h.'</select></td></tr>';
        };
        $page = STA_Photo_Stories::page_url();
        echo '<link rel="stylesheet" href="https://fonts.bunny.net/css?family='.esc_attr(implode('|', array_filter(array_keys(STA_Photo_Stories::FONTS), fn ($f) => $f !== 'theme'))).'&display=swap">';
        echo '<div class="sta-card"><p>'.wp_kses_post(__('Homepage: <code>[sta_photo_stories_slider]</code> · Page with all stories: <code>[sta_photo_stories]</code> · Only the button: <code>[sta_photo_story_share]</code>. The settings below apply everywhere; an attribute in a shortcode (e.g. <code>title="…"</code>) still overrides them for that place.', 'seeinside-travel')).'</p>'
            .'<p><a class="button" href="'.esc_url(home_url('/')).'" target="_blank">'.esc_html__('Open the homepage', 'seeinside-travel').'</a> '.($page ? '<a class="button" href="'.esc_url($page).'" target="_blank">'.esc_html__('Open the photo stories page', 'seeinside-travel').'</a>' : '').'</p></div>';
        self::form_start('photos');
        echo '<div class="sta-grid2"><div>';
        echo '<div class="sta-card"><h2>'.esc_html__('Texts and buttons', 'seeinside-travel').'</h2><table class="form-table">'
            .$txt('eyebrow', __('Small title (handwritten)', 'seeinside-travel'), __('Empty = not shown', 'seeinside-travel'))
            .$txt('title', __('Title', 'seeinside-travel'))
            .'<tr><th><label for="ps-text">'.esc_html__('Text', 'seeinside-travel').'</label></th><td><textarea class="large-text" rows="4" id="ps-text" name="text">'.esc_textarea($o['text']).'</textarea></td></tr>'
            .$txt('share_label', __('Share button', 'seeinside-travel'))
            .$txt('all_label', __('Button to all stories', 'seeinside-travel'))
            .$txt('all_link', __('Link of that button', 'seeinside-travel'), __('Empty = the page with [sta_photo_stories] is found automatically', 'seeinside-travel'))
            .'<tr><th>'.esc_html__('Show', 'seeinside-travel').'</th><td>'
            .$chk('all_show', __('Button to all stories under the slider', 'seeinside-travel'))
            .$chk('page_head', __('Small title, title and text also on the photo stories page', 'seeinside-travel'))
            .$chk('page_share', __('Share button on the photo stories page', 'seeinside-travel'))
            .'</td></tr></table></div>';
        echo '<div class="sta-card"><h2>'.esc_html__('Slider and page', 'seeinside-travel').'</h2><table class="form-table">'
            .$num('count', __('Clients in the slider', 'seeinside-travel'), '', __('1–10; the ones marked "Homepage" in the app first', 'seeinside-travel'))
            .$num('autoplay', __('Change every', 'seeinside-travel'), __('seconds', 'seeinside-travel'), __('0 = only with the arrows', 'seeinside-travel'))
            .$num('max_photos', __('Photos in the mosaic', 'seeinside-travel'), '', '3–7')
            .'<tr><th>'.esc_html__('Slider', 'seeinside-travel').'</th><td>'
            .$chk('show_caption', __('Name and destination under the photos', 'seeinside-travel'))
            .$chk('show_all_photos', __('Link "All N photos" next to the name', 'seeinside-travel'))
            .$chk('show_dots', __('Dots and arrows', 'seeinside-travel'))
            .'</td></tr>'
            .$num('per_page', __('Clients per page', 'seeinside-travel'), '', __('then "Show more"', 'seeinside-travel'))
            .$num('card_min', __('Minimal card width', 'seeinside-travel'), 'px', __('smaller = more cards in a row', 'seeinside-travel'))
            .'<tr><th>'.esc_html__('Cards', 'seeinside-travel').'</th><td>'.$chk('card_story', __('Show the text of the client on the card', 'seeinside-travel')).'</td></tr>'
            .'</table></div>';
        echo '</div><div>';
        echo '<div class="sta-card"><h2>'.esc_html__('Colours', 'seeinside-travel').'</h2><table class="form-table">'
            .$col('c_main', __('Main colour', 'seeinside-travel'), __('Titles, buttons, dots – when not set separately', 'seeinside-travel'))
            .$col('c_eyebrow', __('Small title', 'seeinside-travel'))
            .$col('c_title', __('Title', 'seeinside-travel'))
            .$col('c_text', __('Text', 'seeinside-travel'))
            .$col('c_btn_bg', __('Share button – background', 'seeinside-travel'))
            .$col('c_btn_text', __('Share button – text', 'seeinside-travel'))
            .$col('c_all', __('Button to all stories (outline)', 'seeinside-travel'))
            .$col('c_link', __('Links and destinations', 'seeinside-travel'))
            .$col('c_bg', __('Background of the block', 'seeinside-travel'), __('Empty = transparent', 'seeinside-travel'))
            .'</table></div>';
        echo '<div class="sta-card"><h2>'.esc_html__('Fonts and sizes', 'seeinside-travel').'</h2><table class="form-table">'
            .$font('f_eyebrow', __('Font of the small title', 'seeinside-travel'))
            .$font('f_title', __('Font of the title', 'seeinside-travel'))
            .$num('s_eyebrow', __('Small title size', 'seeinside-travel'))
            .$num('s_title', __('Title size', 'seeinside-travel'))
            .$num('w_title', __('Title thickness', 'seeinside-travel'), '', '400 / 500 / 600 / 700 / 800')
            .$num('s_text', __('Text size', 'seeinside-travel'))
            .$num('s_btn', __('Button text size', 'seeinside-travel'))
            .$num('btn_radius', __('Button rounding', 'seeinside-travel'), 'px', __('999 = pill, 0 = square', 'seeinside-travel'))
            .'<tr><th>'.esc_html__('Buttons', 'seeinside-travel').'</th><td>'.$chk('btn_upper', __('CAPITAL LETTERS', 'seeinside-travel')).$chk('btn_shadow', __('Shadow', 'seeinside-travel')).'</td></tr>'
            .'</table></div>';
        echo '<div class="sta-card"><h2>'.esc_html__('Photos and layout', 'seeinside-travel').'</h2><table class="form-table">'
            .$num('mosaic_h', __('Mosaic height', 'seeinside-travel'), 'px', __('on the phone 64% of it', 'seeinside-travel'))
            .$num('gap', __('Space between photos', 'seeinside-travel'))
            .$num('photo_radius', __('Photo rounding', 'seeinside-travel'))
            .$num('width', __('Maximal width', 'seeinside-travel'), 'px', __('0 = full width', 'seeinside-travel'))
            .'<tr><th>'.esc_html__('Alignment', 'seeinside-travel').'</th><td><label><input type="radio" name="align" value="center"'.checked($o['align'], 'center', false).'> '.esc_html__('Centre', 'seeinside-travel').'</label> &nbsp; <label><input type="radio" name="align" value="left"'.checked($o['align'], 'left', false).'> '.esc_html__('Left', 'seeinside-travel').'</label></td></tr>'
            .'<tr><th><label for="ps-css">'.esc_html__('Own CSS', 'seeinside-travel').'</label></th><td><textarea class="large-text code" rows="5" id="ps-css" name="css" placeholder=".sta-ps-title { letter-spacing: -1px; }">'.esc_textarea($o['css']).'</textarea>'
            .'<p class="description">'.esc_html__('Classes: .sta-ps (slider), .sta-ps-page (page), .sta-ps-eyebrow, .sta-ps-title, .sta-ps-text, .sta-ps-share, .sta-ps-allbtn, .sta-ps-ph (photo), .sta-ps-card', 'seeinside-travel').'</p></td></tr>'
            .'</table></div>';
        echo '</div></div>';
        submit_button(__('Save', 'seeinside-travel'), 'primary', 'submit', false);
        echo ' <button type="submit" name="sta_ps_reset" value="1" class="button" onclick="return confirm(\''.esc_js(__('Bring back the default texts and design?', 'seeinside-travel')).'\')">'.esc_html__('Default design', 'seeinside-travel').'</button></form>';
    }

    protected static function tab_help(array $s): void
    {
        $sections = implode(', ', array_map(fn ($k) => '<code>'.$k.'</code>', array_keys(STA_Render::SECTIONS)));
        $rows = [
            ['[sta_slider height="560"]', __('Homepage slider (supertitle, title, subtitle, button)', 'seeinside-travel')],
            ['[sta_search types="tour,accommodation,event"]', __('Search: destination, month, budget, last minute', 'seeinside-travel')],
            ['[sta_list type="tour" limit="6" columns="3" destination="grcija" orderby="departure"]', __('Grid of offers. type: tour, hotel, event, visa, all · last_minute="1" · featured="1" · orderby: date, departure, price, title, random · pagination="1" · search="1"', 'seeinside-travel')],
            ['[sta_last_minute]', __('All last minute offers', 'seeinside-travel')],
            ['[sta_first_minute]', __('Offers marked for "First minute" in the app', 'seeinside-travel')],
            ['[sta_destinations taxonomy="sta_destination" limit="8"]', __('Destination tiles', 'seeinside-travel')],
            ['[sta_charters]', __('Charter seats with booking', 'seeinside-travel')],
            ['[sta_reviews limit="6" layout="grid" form="yes"]', __('Reviews. type: tour, event, accommodation, visa, agency · layout: grid, slider, list · featured="yes" · on an offer page: that offer', 'seeinside-travel')],
            ['[sta_reviews layout="testimonials" subtitle="Рецензии" title="Што велат нашите патници" limit="9" min_rating="4"]', __('Testimonials carousel: the middle card is raised; per_view="3", autoplay="6" (0 = off), role="Patnik" (text under the name), featured="yes"', 'seeinside-travel')],
            ['[sta_photo_stories_slider]', __('Photo stories of clients on the homepage: mosaic, up to 5 clients change. Texts and design: tab Photo stories', 'seeinside-travel')],
            ['[sta_photo_stories]', __('Page with all photo stories – a click opens all photos of a client', 'seeinside-travel')],
            ['[sta_photo_story_share label="…"]', __('Only the button "Share your trip" (booking number + e-mail, then the photos)', 'seeinside-travel')],
            ['[sta_review_form]', __('Only the form for a new review', 'seeinside-travel')],
            ['[sta_flight_search]', __('Flight search: the official "GOL IBE Search Form" plugin when installed, otherwise the GOL IBE code / link from SeeInside', 'seeinside-travel')],
            ['[sta_hotels]', __('Hotels with live prices from the booking system (Hotelbeds): results with filters, hotel page with rooms, booking', 'seeinside-travel')],
            ['[sta_hotel_finder exclude_type="hotel"]', __('Own apartments and villas with filters (without hotels)', 'seeinside-travel')],
            ['[sta_search_box]', __('Big search box with tabs (hotels, tours, flights) for the homepage. tabs="hotels,tours,flights" active="tours" title="…" subtitle="…"', 'seeinside-travel')],
            ['[sta_hotel_finder]', __('Hotels, apartments and villas with filters on the left (type, destination, beach, transport, free dates, stars, board, amenities, price)', 'seeinside-travel')],
            ['[sta_contacts]', __('All phones (office, mobile with Viber / WhatsApp, emergency), e-mail, address, social networks. show="main" or show="emergency" for one group', 'seeinside-travel')],
            ['[sta_phone type="mobile"]', __('One phone with Viber / WhatsApp buttons – for the header or footer', 'seeinside-travel')],
            ['[sta_social]', __('Social network icons (Facebook, Instagram, YouTube…)', 'seeinside-travel')],
            ['[sta_newsletter]', __('Newsletter (Mailchimp) sign-up button', 'seeinside-travel')],
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
            $s['mode'] = ($_POST['mode'] ?? '') === 'push' ? 'push' : 'pull';
            $ip = trim(sanitize_text_field(wp_unslash($_POST['server_ip'] ?? '')));
            $s['server_ip'] = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
            foreach (['api_key', 'webhook_secret'] as $k) {
                $v = trim(sanitize_text_field(wp_unslash($_POST[$k] ?? '')));
                if ($v !== '') {
                    $s[$k] = $v;
                }
            }
        } elseif ($tab === 'header') {
            STA_Header::save($_POST);
            self::back($tab, __('Saved.', 'seeinside-travel'));
        } elseif ($tab === 'photos') {
            if (! empty($_POST['sta_ps_reset'])) {
                delete_option('sta_ps');
                self::back($tab, __('The default design is back.', 'seeinside-travel'));
            }
            STA_Photo_Stories::save_settings($_POST);
            self::back($tab, __('Saved.', 'seeinside-travel'));
        } elseif ($tab === 'display') {
            $old = (array) ($s['slugs'] ?? []);
            $s['layout_mode'] = in_array($_POST['layout_mode'] ?? '', ['auto', 'off'], true) ? $_POST['layout_mode'] : 'auto';
            $s['archive_mode'] = in_array($_POST['archive_mode'] ?? '', ['auto', 'theme'], true) ? $_POST['archive_mode'] : 'auto';
            $s['qs_header'] = ($_POST['qs_header'] ?? 'auto') === 'off' ? 'off' : 'auto';
            $s['menu_more'] = ! empty($_POST['menu_more']) ? '1' : '0';
            $s['per_page'] = max(3, min(60, (int) ($_POST['per_page'] ?? 12)));
            $s['color_primary'] = sanitize_hex_color(wp_unslash($_POST['color_primary'] ?? '')) ?: '';
            $s['color_accent'] = sanitize_hex_color(wp_unslash($_POST['color_accent'] ?? '')) ?: '';
            $s['delete_mode'] = ($_POST['delete_mode'] ?? '') === 'delete' ? 'delete' : 'trash';
            $s['chat_off'] = ! empty($_POST['chat_off']);
            $s['gallery_layout'] = in_array($_POST['gallery_layout'] ?? '', ['mosaic', 'mosaic3', 'slider', 'grid'], true) ? $_POST['gallery_layout'] : 'mosaic';
            $s['gallery_height'] = max(150, min(900, (int) ($_POST['gallery_height'] ?? 420)));
            $s['gallery_height_mobile'] = max(150, min(700, (int) ($_POST['gallery_height_mobile'] ?? 260)));
            $s['terms_url'] = esc_url_raw(trim((string) wp_unslash($_POST['terms_url'] ?? '')));
            $s['terms_label'] = sanitize_text_field(wp_unslash($_POST['terms_label'] ?? ''));
            $s['slugs'] = array_map('sanitize_title', array_intersect_key((array) wp_unslash($_POST['slugs'] ?? []), STA_Schema::post_types()));
            $pages = get_option('sta_pages', []);
            foreach (array_intersect_key((array) wp_unslash($_POST['page_slugs'] ?? []), ['hoteli' => 1, 'apartmani' => 1]) as $key => $slug) {
                $slug = sanitize_title($slug);
                if ($slug !== '' && ! in_array($slug, $s['slugs'], true) && ! empty($pages[$key]) && get_post_field('post_name', $pages[$key]) !== $slug) {
                    wp_update_post(['ID' => (int) $pages[$key], 'post_name' => $slug]);
                }
            }
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
                if (sta_push_mode()) {
                    $last = get_option('sta_last_webhook');
                    self::back('connection', $last
                        ? sprintf(__('Push mode: last message from the app %s. Use "Test webhook" in the app to check again.', 'seeinside-travel'), $last)
                        : __('Push mode: no message from the app yet. In the app click "Test webhook" (signal icon).', 'seeinside-travel'), (bool) $last);
                }
                [$code, $body] = STA_Api::get('meta');
                if ($code === 200) {
                    STA_Sync::sync_meta();
                    self::back('connection', sprintf(__('Connected: %s', 'seeinside-travel'), $body['agency']['name'] ?? ''));
                }
                $hint = $code === 0 ? ' '.__('This server cannot reach SeeInside. If the website is on the same server, enter 127.0.0.1 as Server IP; otherwise ask the hosting to allow outgoing HTTPS to the SeeInside address.', 'seeinside-travel') : '';
                self::back('connection', sprintf(__('Connection failed (HTTP %1$d): %2$s', 'seeinside-travel'), $code, $body['message'] ?? '').$hint, false);
                // no break
            case 'diagnose':
                set_transient('sta_diag', STA_Api::diagnose(), 300);
                self::back('connection', __('Diagnosis finished – see the table below.', 'seeinside-travel'));
                // no break
            case 'templates':
            case 'templates_replace':
                $r = STA_Templates::create_elementor_templates($_GET['do'] === 'templates_replace');
                self::back('display', isset($r['error']) ? $r['error'] : sprintf(__('Templates ready: %s', 'seeinside-travel'), $r['made'] ? implode(', ', $r['made']) : __('already existed', 'seeinside-travel')), ! isset($r['error']));
                // no break
            case 'menu':
            case 'menu_fresh':
                STA_Templates::create_pages();
                $r = STA_Site::build_menu($_GET['do'] === 'menu_fresh');
                self::back('display', isset($r['error']) ? $r['error'] : sprintf(__('Menu "%1$s": %2$d new, %3$d removed, %4$d items in total. Check it in Appearance → Menus.', 'seeinside-travel'), $r['name'], $r['added'], $r['removed'], $r['items']), ! isset($r['error']));
                // no break
            case 'unify':
                STA_Templates::create_pages();
                $r = STA_Templates::unify_design();
                self::back('display', isset($r['error']) ? $r['error'] : trim(sprintf(__('Same design on: %s', 'seeinside-travel'), $r['made'] ? implode(', ', $r['made']) : '—').' '.implode(' ', $r['notes'] ?? [])), ! isset($r['error']) && empty($r['notes']));
                // no break
            case 'restore_pages':
                $n = STA_Templates::restore_list_pages();
                self::back('display', sprintf(__('Pages restored: %d', 'seeinside-travel'), $n));
                // no break
            case 'pages':
                $r = STA_Templates::create_pages();
                $msg = sprintf(__('Pages ready: %s', 'seeinside-travel'), $r['made'] ? implode(', ', $r['made']) : __('already existed', 'seeinside-travel'));
                self::back('display', trim($msg.' '.implode(' ', $r['notes'] ?? [])), empty($r['notes']));
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
