<?php

defined('ABSPATH') || exit;

/**
 * Detects the page builder and prepares templates for it:
 *  - Elementor Pro: Theme Builder "Single" and "Archive" templates per post type, made of our widgets
 *  - Elementor (free) / Gutenberg / Divi / Bricks / WPBakery / classic theme: automatic layout + shortcodes / blocks
 */
class STA_Templates
{
    public static function init(): void
    {
        add_action('elementor/widgets/register', [self::class, 'elementor_widgets']);
        add_action('elementor/elements/categories_registered', function ($manager) {
            $manager->add_category('seeinside-travel', ['title' => 'SeeInside Travel', 'icon' => 'fa fa-plane']);
        });
        add_action('init', [self::class, 'blocks']);
    }

    /** elementor_pro | elementor | divi | bricks | wpbakery | oxygen | beaver | block_theme | classic */
    public static function builder(): string
    {
        if (defined('ELEMENTOR_PRO_VERSION')) {
            return 'elementor_pro';
        }
        if (defined('ELEMENTOR_VERSION')) {
            return 'elementor';
        }
        if (defined('ET_BUILDER_VERSION')) {
            return 'divi';
        }
        if (defined('BRICKS_VERSION')) {
            return 'bricks';
        }
        if (defined('WPB_VC_VERSION')) {
            return 'wpbakery';
        }
        if (defined('CT_VERSION')) {
            return 'oxygen';
        }
        if (class_exists('FLBuilder')) {
            return 'beaver';
        }

        return function_exists('wp_is_block_theme') && wp_is_block_theme() ? 'block_theme' : 'classic';
    }

    public static function builder_label(): string
    {
        return [
            'elementor_pro' => 'Elementor Pro',
            'elementor' => 'Elementor',
            'divi' => 'Divi',
            'bricks' => 'Bricks',
            'wpbakery' => 'WPBakery',
            'oxygen' => 'Oxygen',
            'beaver' => 'Beaver Builder',
            'block_theme' => __('Block theme (Gutenberg)', 'seeinside-travel'),
            'classic' => __('Classic theme', 'seeinside-travel'),
        ][self::builder()];
    }

    /** Elementor Pro has a Theme Builder template for the current page in this location? */
    protected static function elementor_location_has(string $location): bool
    {
        if (! class_exists('\ElementorPro\Modules\ThemeBuilder\Module')) {
            return false;
        }
        try {
            $docs = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()->get_documents_for_location($location);

            return ! empty($docs);
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Should the plugin print the whole page into the content? */
    public static function use_auto_layout(string $postType, int $postId): bool
    {
        if (sta_setting('layout_mode', 'auto') === 'off') {
            return false;
        }
        if (get_post_meta($postId, '_elementor_edit_mode', true) === 'builder') {
            return false;
        }

        return ! self::elementor_location_has('single');
    }

    public static function use_auto_archive(): bool
    {
        return sta_setting('layout_mode', 'auto') !== 'off' && sta_setting('archive_mode', 'auto') !== 'theme' && ! self::elementor_location_has('archive');
    }

    /* ======================== Elementor ======================== */

    public static function elementor_widgets($manager): void
    {
        require_once STA_DIR.'includes/elementor/widgets.php';
        foreach (sta_elementor_widget_classes() as $class) {
            $manager->register(new $class());
        }
    }

    protected static function eid(): string
    {
        return substr(md5(wp_generate_uuid4()), 0, 7);
    }

    protected static function widget(string $type, array $settings = []): array
    {
        return ['id' => self::eid(), 'elType' => 'widget', 'widgetType' => $type, 'settings' => (object) $settings, 'elements' => []];
    }

    protected static function section(array $columns, array $settings = []): array
    {
        return [
            'id' => self::eid(), 'elType' => 'section', 'isInner' => false,
            'settings' => (object) ($settings + ['gap' => 'extended']),
            'elements' => array_map(fn ($c) => [
                'id' => self::eid(), 'elType' => 'column', 'isInner' => false,
                'settings' => (object) (['_column_size' => $c['size'], '_inline_size' => null] + ($c['settings'] ?? [])),
                'elements' => $c['widgets'],
            ], $columns),
        ];
    }

    protected static function single_layout(string $postType): array
    {
        $type = STA_Schema::api_type($postType);
        $main = ['gallery', 'facts', 'description', 'info_cards', 'highlights', 'program', 'departures', 'rooms', 'availability', 'tickets', 'requirements', 'included', 'extras', 'amenities', 'info', 'videos', 'map', 'booking', 'reviews'];
        $skip = [
            'tour' => ['rooms', 'availability', 'tickets', 'requirements', 'amenities'],
            'event' => ['highlights', 'program', 'departures', 'rooms', 'availability', 'requirements', 'extras', 'amenities'],
            'accommodation' => ['highlights', 'program', 'departures', 'tickets', 'requirements', 'extras', 'included'],
            'visa' => ['highlights', 'program', 'departures', 'rooms', 'availability', 'tickets', 'included', 'extras', 'amenities', 'map', 'videos'],
        ][$type] ?? [];
        $main = array_values(array_diff($main, $skip));

        return [
            self::section([['size' => 100, 'widgets' => [self::widget('theme-post-title', ['header_size' => 'h1'])]]]),
            self::section([
                ['size' => 66, 'widgets' => array_map(fn ($s) => self::widget('sta-section', ['section' => $s]), $main)],
                ['size' => 33, 'widgets' => [self::widget('sta-section', ['section' => 'price', 'sticky' => 'top', '_css_classes' => 'sta-sticky'])]],
            ]),
        ];
    }

    protected static function archive_layout(string $postType): array
    {
        $search = ['sta_tour' => 'tour', 'sta_hotel' => 'accommodation', 'sta_event' => 'event', 'sta_visa' => 'visa'][$postType];

        return [
            self::section([['size' => 100, 'widgets' => [
                self::widget('theme-archive-title', ['header_size' => 'h1']),
                self::widget('sta-search', ['types' => $search]),
                self::widget('sta-list', ['query' => 'current', 'columns' => '3', 'pagination' => 'yes']),
            ]]]),
        ];
    }

    /**
     * Elementor Pro: create (or re-create) the Theme Builder templates. Existing templates made by the plugin
     * are left alone unless $replace is true, so the user's own design changes are kept.
     */
    public static function create_elementor_templates(bool $replace = false): array
    {
        if (! defined('ELEMENTOR_VERSION')) {
            return ['error' => __('Elementor is not active.', 'seeinside-travel')];
        }
        $made = [];
        $known = get_option('sta_elementor_templates', []);

        foreach (STA_Schema::post_types() as $postType => $pt) {
            foreach (['single-post' => self::single_layout($postType), 'archive' => self::archive_layout($postType)] as $docType => $layout) {
                $key = $postType.':'.$docType;
                $existing = (int) ($known[$key] ?? 0);
                if ($existing && get_post_status($existing) && ! $replace) {
                    continue;
                }
                $title = ($docType === 'archive' ? __('List', 'seeinside-travel').': ' : '').$pt['plural'].' – SeeInside';
                $id = $existing && get_post_status($existing) ? $existing : wp_insert_post([
                    'post_type' => 'elementor_library',
                    'post_status' => 'publish',
                    'post_title' => $title,
                ]);
                if (is_wp_error($id) || ! $id) {
                    continue;
                }
                wp_set_object_terms($id, $docType, 'elementor_library_type');
                update_post_meta($id, '_elementor_template_type', $docType);
                update_post_meta($id, '_elementor_edit_mode', 'builder');
                update_post_meta($id, '_elementor_version', ELEMENTOR_VERSION);
                if (defined('ELEMENTOR_PRO_VERSION')) {
                    update_post_meta($id, '_elementor_pro_version', ELEMENTOR_PRO_VERSION);
                }
                update_post_meta($id, '_elementor_data', wp_slash(wp_json_encode($layout)));
                update_post_meta($id, '_elementor_page_settings', []);
                $condition = $docType === 'archive' ? 'include/archive/'.$postType.'_archive' : 'include/singular/'.$postType;
                update_post_meta($id, '_elementor_conditions', [$condition]);
                if ($docType === 'archive') {
                    // destination / group pages use the tour list
                    if ($postType === 'sta_tour') {
                        update_post_meta($id, '_elementor_conditions', [$condition, 'include/archive/sta_destination', 'include/archive/sta_group', 'include/archive/sta_tour_type']);
                    }
                }
                delete_post_meta($id, '_elementor_css');
                $known[$key] = $id;
                $made[] = $title;
            }
        }
        update_option('sta_elementor_templates', $known, false);

        if (class_exists('\ElementorPro\Modules\ThemeBuilder\Module')) {
            try {
                \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()->get_cache()->regenerate();
            } catch (Throwable $e) {
                sta_log('elementor conditions: '.$e->getMessage(), 'error');
            }
        }
        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
        sta_log('elementor templates: '.implode(', ', $made));

        return ['made' => $made];
    }

    /* ======================== pages ======================== */

    /** Ready pages with listings (shortcodes work in every builder / editor) */
    public static function create_pages(): array
    {
        $pages = [
            'aranzmani' => [__('Organized tours', 'seeinside-travel'), "[sta_search types=\"tour\"]\n[sta_list type=\"tour\" limit=\"12\" columns=\"3\" search=\"1\" pagination=\"1\" orderby=\"departure\"]"],
            'hoteli' => [__('Hotels & apartments', 'seeinside-travel'), "[sta_search types=\"accommodation\"]\n[sta_list type=\"hotel\" limit=\"12\" columns=\"3\" search=\"1\" pagination=\"1\"]"],
            'nastani' => [__('Events', 'seeinside-travel'), '[sta_list type="event" limit="12" columns="3" orderby="departure" pagination="1"]'],
            'vizi' => [__('Visas', 'seeinside-travel'), '[sta_list type="visa" limit="24" columns="4" orderby="title"]'],
            'last-minute' => ['Last minute', '[sta_last_minute limit="24" columns="3"]'],
            'carteri' => [__('Charter seats', 'seeinside-travel'), '[sta_charters]'],
            'avio-karti' => [__('Flights', 'seeinside-travel'), '[sta_flight_search]'],
            'proveri-rezervacija' => [__('Check your booking', 'seeinside-travel'), '[sta_booking_status]'],
        ];
        $made = [];
        $known = get_option('sta_pages', []);
        foreach ($pages as $slug => [$title, $content]) {
            if (! empty($known[$slug]) && get_post_status($known[$slug])) {
                continue;
            }
            $existing = get_page_by_path($slug);
            if ($existing) {
                $known[$slug] = $existing->ID;
                continue;
            }
            $id = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => $content]);
            if ($id && ! is_wp_error($id)) {
                $known[$slug] = $id;
                $made[] = $title;
            }
        }
        update_option('sta_pages', $known, false);

        return ['made' => $made];
    }

    /* ======================== Gutenberg blocks ======================== */

    public static function blocks(): void
    {
        if (! function_exists('register_block_type')) {
            return;
        }
        wp_register_script('sta-blocks', STA_URL.'assets/js/blocks.js', ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n'], STA_VERSION, true);
        wp_localize_script('sta-blocks', 'STA_BLOCKS', [
            'sections' => array_map(fn ($label) => __($label, 'seeinside-travel'), STA_Render::SECTIONS),
        ]);

        register_block_type('seeinside-travel/section', [
            'editor_script' => 'sta-blocks',
            'attributes' => ['section' => ['type' => 'string', 'default' => 'layout'], 'postId' => ['type' => 'number', 'default' => 0]],
            'render_callback' => fn ($a) => STA_Render::section($a['section'] ?? 'layout', ! empty($a['postId']) ? (int) $a['postId'] : null),
        ]);
        register_block_type('seeinside-travel/list', [
            'editor_script' => 'sta-blocks',
            'attributes' => [
                'type' => ['type' => 'string', 'default' => 'tour'], 'limit' => ['type' => 'number', 'default' => 6],
                'columns' => ['type' => 'number', 'default' => 3], 'lastMinute' => ['type' => 'boolean', 'default' => false],
                'destination' => ['type' => 'string', 'default' => ''], 'orderby' => ['type' => 'string', 'default' => 'date'],
            ],
            'render_callback' => fn ($a) => STA_Frontend::sc_list([
                'type' => $a['type'], 'limit' => $a['limit'], 'columns' => $a['columns'], 'last_minute' => $a['lastMinute'] ? '1' : '',
                'destination' => $a['destination'], 'orderby' => $a['orderby'],
            ]),
        ]);
        foreach (['slider' => 'sc_slider', 'search' => 'sc_search', 'flights' => 'sc_flights', 'charters' => 'sc_charters', 'status' => 'sc_status'] as $name => $cb) {
            register_block_type('seeinside-travel/'.$name, ['editor_script' => 'sta-blocks', 'render_callback' => fn () => STA_Frontend::$cb([])]);
        }
    }
}
