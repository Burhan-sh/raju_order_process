<?php
class RJ_Admin_Order_Form {
    public function __construct() {
        add_action('init', array($this, 'handle_form_submission'));
        add_action('init', array($this, 'start_session'));
    }

    public function start_session() {
        if (!session_id()) {
            session_start();
        }
    }

    public function handle_form_submission() {
        if (!isset($_POST['rj_admin_order_submit'])) {
            return;
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have permission to perform this action.', 'rj-admin-order'));
        }

        // Verify nonce
        if (!isset($_POST['rj_admin_order_nonce']) || 
            !wp_verify_nonce($_POST['rj_admin_order_nonce'], 'rj_admin_order_form')) {
            wp_die(__('Security check failed.', 'rj-admin-order'));
        }

        $errors = $this->validate_form_data($_POST);
        
        if (!empty($errors)) {
            $this->store_errors($errors);
            wp_redirect($_SERVER['HTTP_REFERER']);
            exit;
        }

        try {
            $order_id = $this->create_order($_POST);
            if ($order_id) {
                wp_redirect(add_query_arg('order_success', $order_id, $_SERVER['HTTP_REFERER']));
                exit;
            }
        } catch (Exception $e) {
            $this->store_errors(array($e->getMessage()));
            wp_redirect($_SERVER['HTTP_REFERER']);
            exit;
        }
    }

    private function validate_form_data($data) {
        $errors = array();

        // Validate phone number (10 digits only)
        $phone = preg_replace('/[^0-9]/', '', $data['phone']);
        if (strlen($phone) !== 10) {
            $errors[] = __('Phone number must be exactly 10 digits.', 'rj-admin-order');
        }

        // Required fields
        $required_fields = array(
            'fname' => __('First Name', 'rj-admin-order'),
            'lname' => __('Last Name', 'rj-admin-order'),
            'phone' => __('Phone Number', 'rj-admin-order'),
            'address_1' => __('Street Address', 'rj-admin-order'),
            'city' => __('City', 'rj-admin-order'),
            'state' => __('State', 'rj-admin-order'),
            'postcode' => __('Postcode', 'rj-admin-order'),
            'country' => __('Country', 'rj-admin-order')
        );

        foreach ($required_fields as $field => $label) {
            if (empty($data[$field])) {
                $errors[] = sprintf(__('%s is required.', 'rj-admin-order'), $label);
            }
        }

        // Validate products
        if (empty($data['products'])) {
            $errors[] = __('Please select at least one product.', 'rj-admin-order');
        }

        // Validate email if provided
        if (!empty($data['email']) && !is_email($data['email'])) {
            $errors[] = __('Please enter a valid email address.', 'rj-admin-order');
        }

        return $errors;
    }

    private function create_order($data) {
        // Check if user exists with phone number
        $user_id = $this->get_user_by_phone($data['phone']);
        
        // Create order
        $order = wc_create_order(array(
            'customer_id' => $user_id
        ));

        // Add products
        $products = array();
        if (isset($data['products']) && is_array($data['products'])) {
            foreach ($data['products'] as $product_json) {
                $product_data = json_decode($product_json, true);
                if (is_array($product_data)) {
                    $products[] = $product_data;
                }
            }
        }

        if (!empty($products)) {
            foreach ($products as $product) {
                $product_id = absint($product['id']);
                $variation_id = isset($product['variation_id']) ? absint($product['variation_id']) : 0;
                $quantity = isset($product['quantity']) ? absint($product['quantity']) : 1;
                
                if ($variation_id) {
                    $product_obj = wc_get_product($variation_id);
                    $order->add_product($product_obj, $quantity, array(
                        'subtotal' => $product_obj->get_price() * $quantity,
                        'total' => $product_obj->get_price() * $quantity
                    ));
                } else {
                    $product_obj = wc_get_product($product_id);
                    $order->add_product($product_obj, $quantity, array(
                        'subtotal' => $product_obj->get_price() * $quantity,
                        'total' => $product_obj->get_price() * $quantity
                    ));
                }
            }
        }

        // Set addresses
        $address = array(
            'first_name' => sanitize_text_field($data['fname']),
            'last_name'  => sanitize_text_field($data['lname']),
            'company'    => sanitize_text_field($data['company']),
            'address_1'  => sanitize_text_field($data['address_1']),
            'address_2'  => sanitize_text_field($data['address_2']),
            'city'       => sanitize_text_field($data['city']),
            'state'      => sanitize_text_field($data['state']),
            'postcode'   => sanitize_text_field($data['postcode']),
            'country'    => sanitize_text_field($data['country']),
            'phone'      => sanitize_text_field($data['phone'])
        );

        if (!empty($data['email'])) {
            $address['email'] = sanitize_email($data['email']);
        }

        $order->set_address($address, 'billing');
        $order->set_address($address, 'shipping');

        // Set payment method
        $order->set_payment_method('cod');
        $order->set_payment_method_title('Cash on Delivery');

        // Calculate totals
        $order->calculate_totals();

        // Set order status
        $order->update_status('processing', __('Order placed by admin.', 'rj-admin-order'));

        return $order->get_id();
    }

    private function get_user_by_phone($phone) {
        global $wpdb;
        
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
            WHERE meta_key = 'billing_phone' AND meta_value = %s",
            $phone
        ));

        return $user_id ? $user_id : 0;
    }

    private function store_errors($errors) {
        if (!session_id()) {
            session_start();
        }
        $_SESSION['rj_admin_order_errors'] = $errors;
    }

    public function get_success_message() {
        if (isset($_SESSION['rj_admin_order_success'])) {
            $message = $_SESSION['rj_admin_order_success'];
            unset($_SESSION['rj_admin_order_success']);
            return $message;
        }
        return '';
    }

    public function get_errors() {
        if (isset($_SESSION['rj_admin_order_errors'])) {
            $errors = $_SESSION['rj_admin_order_errors'];
            unset($_SESSION['rj_admin_order_errors']);
            return $errors;
        }
        return array();
    }
} 