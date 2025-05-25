<?php
class RJ_Admin_Order {
    private $version;
    private $form;

    public function __construct() {
        $this->version = RJ_ADMIN_ORDER_VERSION;
        $this->load_dependencies();
        $this->form = new RJ_Admin_Order_Form();
    }

    private function load_dependencies() {
        // Load required files
        require_once RJ_ADMIN_ORDER_PLUGIN_DIR . 'includes/class-rj-admin-order-form.php';
        require_once RJ_ADMIN_ORDER_PLUGIN_DIR . 'includes/class-rj-admin-order-ajax.php';
    }

    public function init() {
        // Initialize components
        $this->init_hooks();
        new RJ_Admin_Order_Ajax();
    }

    private function init_hooks() {
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Add menu items
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register shortcode
        add_shortcode('raju_order_process', array($this, 'render_order_form'));
    }

    public function enqueue_scripts() {
        if (is_page('order-process-admin') || has_shortcode(get_post()->post_content, 'raju_order_process')) {
            // Enqueue CSS
            wp_enqueue_style(
                'rj-admin-order-style',
                RJ_ADMIN_ORDER_PLUGIN_URL . 'assets/css/order-form.css',
                array(),
                $this->version
            );

            // Enqueue JavaScript
            wp_enqueue_script(
                'rj-admin-order-script',
                RJ_ADMIN_ORDER_PLUGIN_URL . 'assets/js/order-form.js',
                array('jquery'),
                $this->version,
                true
            );

            // Localize script
            wp_localize_script(
                'rj-admin-order-script',
                'rj_admin_order_data',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('rj-admin-order-nonce')
                )
            );
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            __('RJ Admin Order', 'rj-admin-order'),
            __('RJ Admin Order', 'rj-admin-order'),
            'manage_woocommerce',
            'rj-admin-order',
            array($this, 'render_admin_page'),
            'dashicons-cart',
            56
        );
    }

    public function render_admin_page() {
        // Admin page content
        include RJ_ADMIN_ORDER_PLUGIN_DIR . 'admin/admin-page.php';
    }

    public function render_order_form() {
        if (!current_user_can('manage_woocommerce')) {
            return __('You do not have permission to access this page.', 'rj-admin-order');
        }

        // Make the form instance available to the template
        $rj_admin_order_form = $this->form;
        
        ob_start();
        include RJ_ADMIN_ORDER_PLUGIN_DIR . 'templates/order-form.php';
        return ob_get_clean();
    }
} 