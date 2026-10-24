<?php

defined('ABSPATH') || exit;

/** Custom post types and taxonomies (same structure as in SeeInside Travel) */
class STA_Post_Types
{
    public static function init(): void
    {
        add_action('init', [self::class, 'register']);
        add_action('add_meta_boxes', [self::class, 'meta_box']);
        add_filter('use_block_editor_for_post_type', fn ($use, $type) => isset(STA_Schema::post_types()[$type]) ? false : $use, 10, 2);
        add_action('admin_notices', [self::class, 'edit_notice']);
    }

    public static function slug(string $postType): string
    {
        $slugs = (array) sta_setting('slugs', []);

        return sanitize_title($slugs[$postType] ?? STA_Schema::post_types()[$postType]['slug']);
    }

    public static function register(): void
    {
        foreach (STA_Schema::taxonomies() as $tax => $t) {
            $objects = array_keys(array_filter(STA_Schema::post_types(), fn ($pt) => in_array($tax, $pt['taxonomies'], true)));
            register_taxonomy($tax, $objects, [
                'labels' => ['name' => $t['plural'], 'singular_name' => $t['singular'], 'menu_name' => $t['plural']],
                'hierarchical' => $t['hierarchical'],
                'public' => true,
                'show_in_rest' => true,
                'show_admin_column' => in_array($tax, ['sta_destination', 'sta_tour_type', 'sta_event_category', 'sta_hotel_type'], true),
                'rewrite' => ['slug' => sanitize_title(((array) sta_setting('tax_slugs', []))[$tax] ?? $t['slug']), 'with_front' => false, 'hierarchical' => $t['hierarchical']],
                // terms come from SeeInside
                'capabilities' => ['manage_terms' => 'manage_options', 'edit_terms' => 'manage_options', 'delete_terms' => 'manage_options', 'assign_terms' => 'edit_posts'],
            ]);
        }

        foreach (STA_Schema::post_types() as $type => $pt) {
            register_post_type($type, [
                'labels' => [
                    'name' => $pt['plural'],
                    'singular_name' => $pt['singular'],
                    'menu_name' => $pt['plural'],
                    'all_items' => $pt['plural'],
                    'edit_item' => $pt['singular'],
                    'view_item' => $pt['singular'],
                    'search_items' => __('Search', 'seeinside-travel'),
                    'not_found' => __('Nothing synced yet – SeeInside Travel → Sync', 'seeinside-travel'),
                ],
                'public' => true,
                'has_archive' => true,
                'show_in_rest' => true,
                'menu_icon' => $pt['icon'],
                'menu_position' => 25,
                'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'elementor'],
                'taxonomies' => $pt['taxonomies'],
                'rewrite' => ['slug' => self::slug($type), 'with_front' => false],
                // content is managed in SeeInside Travel: no "Add new" here
                'capability_type' => 'post',
                'capabilities' => ['create_posts' => 'do_not_allow'],
                'map_meta_cap' => true,
            ]);
            add_post_type_support($type, 'elementor');
        }
    }

    public static function meta_box(): void
    {
        foreach (array_keys(STA_Schema::post_types()) as $type) {
            add_meta_box('sta-source', 'SeeInside Travel', [self::class, 'render_meta_box'], $type, 'side', 'high');
        }
    }

    public static function render_meta_box(WP_Post $post): void
    {
        $id = (int) get_post_meta($post->ID, 'sta_id', true);
        $apiType = STA_Schema::api_type($post->post_type);
        $paths = ['tour' => 'tours', 'event' => 'events', 'accommodation' => 'accommodations', 'visa' => 'visa-types', 'custom' => 'other-offers'];
        $admin = rtrim((string) sta_setting('api_url', 'https://ta.seeinsidesolution.com'), '/').'/admin/'.$paths[$apiType].'/'.$id.'/edit';
        $synced = get_post_meta($post->ID, '_sta_synced_at', true);

        echo '<p>'.esc_html__('This content is managed in SeeInside Travel. Changes made here are overwritten at the next sync.', 'seeinside-travel').'</p>';
        if ($id) {
            echo '<p><a class="button button-primary" target="_blank" rel="noopener" href="'.esc_url($admin).'">'.esc_html__('Edit in SeeInside', 'seeinside-travel').' ↗</a></p>';
        }
        if ($synced) {
            echo '<p style="color:#646970">'.esc_html__('Last sync:', 'seeinside-travel').' '.esc_html(sta_date($synced, true)).'</p>';
        }
        $url = wp_nonce_url(admin_url('admin-post.php?action=sta_resync&post='.$post->ID), 'sta_resync_'.$post->ID);
        echo '<p><a class="button" href="'.esc_url($url).'">'.esc_html__('Sync this one now', 'seeinside-travel').'</a></p>';
    }

    public static function edit_notice(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen || ! isset(STA_Schema::post_types()[$screen->post_type ?? '']) || $screen->base !== 'edit') {
            return;
        }
        echo '<div class="notice notice-info"><p>'.esc_html__('Tours, events, hotels and visas are entered in SeeInside Travel and appear here automatically (real-time sync).', 'seeinside-travel').' <a href="'.esc_url(admin_url('admin.php?page=seeinside-travel')).'">'.esc_html__('Sync settings', 'seeinside-travel').'</a></p></div>';
    }
}
