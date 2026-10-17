<?php
/**
 * Plugin Name:       SeeInside Travel – Sync
 * Plugin URI:        https://ta.seeinsidesolution.com
 * Description:       Real-time sync of tours, events, hotels/apartments and visas from the SeeInside Travel system (ta.seeinsidesolution.com) into custom post types with ACF fields, plus booking & visa forms, listings, slider and page-builder templates (Elementor / Gutenberg / any theme).
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Author:            SeeInside Solution
 * Text Domain:       seeinside-travel
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 */

defined('ABSPATH') || exit;

define('STA_VERSION', '1.0.0');
define('STA_FILE', __FILE__);
define('STA_DIR', plugin_dir_path(__FILE__));
define('STA_URL', plugin_dir_url(__FILE__));

require_once STA_DIR.'includes/helpers.php';
require_once STA_DIR.'includes/class-schema.php';
require_once STA_DIR.'includes/class-api.php';
require_once STA_DIR.'includes/class-post-types.php';
require_once STA_DIR.'includes/class-fields.php';
require_once STA_DIR.'includes/class-sync.php';
require_once STA_DIR.'includes/class-webhook.php';
require_once STA_DIR.'includes/class-render.php';
require_once STA_DIR.'includes/class-frontend.php';
require_once STA_DIR.'includes/class-templates.php';
require_once STA_DIR.'includes/class-admin.php';

add_action('plugins_loaded', function () {
    load_plugin_textdomain('seeinside-travel', false, dirname(plugin_basename(STA_FILE)).'/languages');

    STA_Post_Types::init();
    STA_Fields::init();
    STA_Sync::init();
    STA_Webhook::init();
    STA_Frontend::init();
    STA_Templates::init();
    if (is_admin()) {
        STA_Admin::init();
    }
});

register_activation_hook(__FILE__, function () {
    STA_Post_Types::register();
    flush_rewrite_rules();
    STA_Sync::schedule();
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('sta_cron_changes');
    wp_clear_scheduled_hook('sta_process_queue');
    flush_rewrite_rules();
});
