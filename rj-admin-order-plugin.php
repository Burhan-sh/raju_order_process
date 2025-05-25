<?php
/**
 * Plugin Name: RJ Admin Order Plugin
 * Description: Custom order processing plugin for administrators
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: rj-admin-order
 * Requires at least: 5.8
 * Requires PHP: 7.2
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('RJ_ADMIN_ORDER_VERSION', '1.0.0');
define('RJ_ADMIN_ORDER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RJ_ADMIN_ORDER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Check if WooCommerce is active
function rj_admin_order_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php _e('RJ Admin Order Plugin requires WooCommerce to be installed and activated.', 'rj-admin-order'); ?></p>
            </div>
            <?php
        });
        return false;
    }
    return true;
}

// Plugin activation hook
function rj_admin_order_activate() {
    if (!rj_admin_order_check_woocommerce()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('Please install and activate WooCommerce before activating this plugin.', 'rj-admin-order'));
    }

    // Create the Order Process admin page
    $page_data = array(
        'post_title'    => 'Order Process Admin',
        'post_content'  => '[raju_order_process]',
        'post_status'   => 'publish',
        'post_type'     => 'page'
    );

    // Check if page already exists
    $existing_page = get_page_by_title('Order Process Admin');
    if (!$existing_page) {
        wp_insert_post($page_data);
    }

    // Create required directories
    $upload_dir = wp_upload_dir();
    $plugin_upload_dir = $upload_dir['basedir'] . '/rj-admin-order';
    if (!file_exists($plugin_upload_dir)) {
        wp_mkdir_p($plugin_upload_dir);
    }
}
register_activation_hook(__FILE__, 'rj_admin_order_activate');

// Plugin deactivation hook
function rj_admin_order_deactivate() {
    // Cleanup if needed
}
register_deactivation_hook(__FILE__, 'rj_admin_order_deactivate');

// Load plugin files
require_once RJ_ADMIN_ORDER_PLUGIN_DIR . 'includes/class-rj-admin-order.php';

// Initialize the plugin
function rj_admin_order_init() {
    if (rj_admin_order_check_woocommerce()) {
        $plugin = new RJ_Admin_Order();
        $plugin->init();
    }
}
add_action('plugins_loaded', 'rj_admin_order_init'); 