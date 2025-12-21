<?php
if (!defined('ABSPATH')) {
    exit;
}

// Include WooCommerce dependencies
if (!class_exists('WooCommerce')) {
    return;
}

require_once WC_ABSPATH . 'includes/wc-order-functions.php';
require_once WC_ABSPATH . 'includes/wc-product-functions.php';

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

        // Validate phone number (10 digits only, no leading zeros)
        $phone = preg_replace('/[^0-9]/', '', $data['phone']);
        if (strlen($phone) !== 10 || $phone[0] === '0') {
            $errors[] = __('Phone number must be exactly 10 digits without any leading zeros or country code.', 'rj-admin-order');
        }

        // Required fields
        $required_fields = array(
            'fname' => __('First Name', 'rj-admin-order'),
            'lname' => __('Last Name', 'rj-admin-order'),
            'phone' => __('Phone Number', 'rj-admin-order'),
            'address_1' => __('Street Address', 'rj-admin-order'),
            'landmark' => __('Landmark / Area', 'rj-admin-order'),
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
        global $woocommerce;

        // Check if WooCommerce functions are available
        if (!function_exists('wc_create_order') || !function_exists('wc_get_product')) {
            throw new Exception(__('WooCommerce functions are not available.', 'rj-admin-order'));
        }

        // Check if user exists with phone number
        $user_id = $this->get_user_by_phone($data['phone']);
        
        // Create order
        $order = wc_create_order(array(
            'customer_id' => $user_id,
            'status' => 'processing',
            'created_via' => 'admin'
        ));

        if (!$order) {
            throw new Exception(__('Failed to create order.', 'rj-admin-order'));
        }

        // Store order ID for cleanup if needed
        $order_id = $order->get_id();
        $products_added = 0;

        try {
            // Set order source meta
            $order->update_meta_data('_wc_order_attribution_utm_source', 'Admin Order Form');
            $order->update_meta_data('_wc_order_attribution_utm_medium', 'plugin');
            $order->update_meta_data('_wc_order_attribution_utm_campaign', 'custom_order');

            // Add products
            $products = array();
            $order_total = 0;

            if (isset($data['products']) && is_array($data['products'])) {
                foreach ($data['products'] as $product_json) {
                    $product_data = json_decode(stripslashes($product_json), true);
                    if (!is_array($product_data)) {
                        continue;
                    }

                    $product_id = absint($product_data['id']);
                    $variation_id = isset($product_data['variation_id']) ? absint($product_data['variation_id']) : 0;
                    $quantity = isset($product_data['quantity']) ? absint($product_data['quantity']) : 1;
                    
                    try {
                        if ($variation_id > 0) {
                            $product = wc_get_product($variation_id);
                            $parent_product = wc_get_product($product_id);
                            
                            if (!$product || !$parent_product) {
                                continue;
                            }

                            // Get the current price
                            $price = $product->get_price();
                            $subtotal = $price * $quantity;
                            $total = $subtotal;

                            // Get variation attributes
                            $variation_data = array();
                            $attributes = $product->get_variation_attributes();
                            
                            // Format variation data for order
                            foreach ($attributes as $attribute_taxonomy => $term_slug) {
                                $taxonomy = str_replace('attribute_', '', $attribute_taxonomy);
                                $variation_data[$taxonomy] = $term_slug;
                            }

                            // Add variation product to order
                            $item_id = $order->add_product(
                                $parent_product, 
                                $quantity,
                                array(
                                    'variation_id' => $variation_id,
                                    'variation' => $variation_data,
                                    'subtotal' => $subtotal,
                                    'total' => $total
                                )
                            );

                            // Store variation data as item meta
                            if ($item_id) {
                                $products_added++;
                                $item = $order->get_item($item_id);
                                if ($item) {
                                    foreach ($variation_data as $meta_key => $meta_value) {
                                        $item->add_meta_data($meta_key, $meta_value);
                                    }
                                    $item->save();
                                }
                            }
                        } else {
                            $product = wc_get_product($product_id);
                            if (!$product) {
                                continue;
                            }

                            // Add simple product to order
                            $price = $product->get_price();
                            $subtotal = $price * $quantity;
                            $total = $subtotal;

                            $item_id = $order->add_product($product, $quantity, array(
                                'subtotal' => $subtotal,
                                'total' => $total
                            ));

                            if ($item_id) {
                                $products_added++;
                            }
                        }

                        if ($item_id) {
                            $order_total += $total;
                        }
                    } catch (Exception $e) {
                        error_log('RJ Admin Order - Product add error: ' . $e->getMessage());
                        continue;
                    }
                }
            }

            // Check if at least one product was added
            if ($products_added === 0) {
                throw new Exception(__('No products could be added to the order. Please check product availability.', 'rj-admin-order'));
            }

            // Set addresses
            $address = array(
                'first_name' => sanitize_text_field($data['fname']),
                'last_name'  => sanitize_text_field($data['lname']),
                'company'    => sanitize_text_field($data['company']),
                'address_1'  => sanitize_text_field($data['address_1']),
                'landmark'   => sanitize_text_field($data['landmark']),
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

            // Set billing meta data explicitly
            foreach ($address as $key => $value) {
                if (!empty($value)) {
                    $order->update_meta_data('_billing_' . $key, $value);
                    $order->update_meta_data('_shipping_' . $key, $value);
                }
            }

            // Set custom landmark meta
            $order->update_meta_data('_billing_landmark', sanitize_text_field($data['landmark']));

            // Set payment method
            $order->set_payment_method('cod');
            $order->set_payment_method_title('Cash on Delivery');

            // Calculate and set totals
            $order->calculate_totals(false); // false to not trigger taxes recalculation
            $order->set_total($order_total);

            // Mark this as a WhatsApp Order (Admin Order Form)
            $order->update_meta_data('_order_source', 'whatsapp_order');
            $order->update_meta_data('_created_via_admin_form', 'yes');

            // Add order note for WhatsApp Order
            $order->add_order_note(__('📱 WhatsApp Order - Placed by admin through custom order form.', 'rj-admin-order'));

            // Check if it's a repeat order and add note (but keep status as processing)
            if ($this->is_repeat_order($data['phone'])) {
                $order->update_meta_data('_is_repeat_order', 'yes');
                $order->add_order_note(__('🔄 Repeat Customer - This customer has ordered before (based on phone number).', 'rj-admin-order'));
            }

            // Save the order (status remains 'processing')
            $order->save();

            return $order->get_id();

        } catch (Exception $e) {
            // If any error occurs, delete the incomplete order to avoid empty orders in database
            if ($order_id) {
                // Delete the order safely
                $order_to_delete = wc_get_order($order_id);
                if ($order_to_delete) {
                    $order_to_delete->delete(true); // true = force delete (bypass trash)
                }
                error_log('RJ Admin Order - Order #' . $order_id . ' deleted due to error: ' . $e->getMessage());
            }
            // Re-throw the exception so it's handled by the calling function
            throw $e;
        }
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

    private function normalize_phone_number($phone) {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Remove +91 prefix (which would be just '91' after removing non-numeric chars)
        if (substr($phone, 0, 2) === '91' && strlen($phone) > 10) {
            $phone = substr($phone, 2);
        }
        
        // Remove leading 0 if it's an 11-digit number starting with 0
        if (substr($phone, 0, 1) === '0' && strlen($phone) === 11) {
            $phone = substr($phone, 1);
        }
        
        // If the phone number is longer than 10 digits, take only the last 10
        if (strlen($phone) > 10) {
            $phone = substr($phone, -10);
        }
        
        return $phone;
    }

    private function is_repeat_order($phone_number) {
        if (empty($phone_number)) {
            return false;
        }

        // Normalize the phone number
        $current_phone = $this->normalize_phone_number($phone_number);
        if (empty($current_phone) || strlen($current_phone) !== 10) {
            return false;
        }
        
        global $wpdb;
        
        // Get the current date
        $current_date = current_time('mysql');
        
        // Query to check for existing orders with the same phone number
        $query = $wpdb->prepare(
            "SELECT COUNT(DISTINCT postmeta.post_id) 
            FROM {$wpdb->postmeta} as postmeta 
            JOIN {$wpdb->posts} as posts ON postmeta.post_id = posts.ID
            WHERE posts.post_type = 'shop_order' 
            AND posts.post_date < DATE_SUB(%s, INTERVAL 1 MINUTE)
            AND postmeta.meta_key = '_billing_phone'
            AND (
                postmeta.meta_value = %s 
                OR postmeta.meta_value = %s
                OR postmeta.meta_value = CONCAT('91', %s)
                OR postmeta.meta_value = CONCAT('+91', %s)
            )",
            $current_date,
            $current_phone,
            '0' . $current_phone,
            $current_phone,
            $current_phone
        );
        
        $order_count = (int) $wpdb->get_var($query);
        return $order_count > 0;
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