<?php
class RJ_Admin_Order_Ajax {
    public function __construct() {
        // Add both admin and non-admin AJAX actions
        add_action('wp_ajax_rj_search_products', array($this, 'search_products'));
        add_action('wp_ajax_nopriv_rj_search_products', array($this, 'search_products'));
        add_action('wp_ajax_rj_get_variations', array($this, 'get_variations'));
        add_action('wp_ajax_nopriv_rj_get_variations', array($this, 'get_variations'));
    }

    public function search_products() {
        // Verify nonce
        if (!check_ajax_referer('rj-admin-order-nonce', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permission denied');
            return;
        }

        $search_term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';
        
        if (empty($search_term)) {
            wp_send_json_error('Search term is required');
            return;
        }

        $args = array(
            'post_type'      => array('product', 'product_variation'),
            'post_status'    => 'publish',
            'posts_per_page' => 15,
            's'              => $search_term,
            'orderby'        => 'title',
            'order'          => 'ASC'
        );

        $query = new WP_Query($args);
        $products = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);

                if (!$product) {
                    continue;
                }

                // Get product data
                $product_data = array(
                    'id'    => $product->get_id(),
                    'text'  => $product->get_name(),
                    'price' => $product->get_price(),
                    'type'  => $product->get_type(),
                    'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail')
                );

                // If it's a variable product, get variations
                if ($product->is_type('variable')) {
                    $variations = $product->get_available_variations();
                    $variation_data = array();
                    
                    foreach ($variations as $variation) {
                        $variation_obj = wc_get_product($variation['variation_id']);
                        if (!$variation_obj || !$variation_obj->is_purchasable()) {
                            continue;
                        }

                        // Format variation attributes
                        $attribute_string = array();
                        foreach ($variation['attributes'] as $attr => $value) {
                            $taxonomy = str_replace('attribute_', '', $attr);
                            if ($value) {
                                $term = get_term_by('slug', $value, $taxonomy);
                                $attribute_string[] = $term ? $term->name : $value;
                            }
                        }

                        $variation_data[] = array(
                            'id' => $variation['variation_id'],
                            'text' => implode(' - ', $attribute_string),
                            'price' => $variation_obj->get_price(),
                            'image' => wp_get_attachment_image_url($variation_obj->get_image_id(), 'thumbnail')
                        );
                    }
                    
                    if (!empty($variation_data)) {
                        $product_data['variations'] = $variation_data;
                    }
                }

                $products[] = $product_data;
            }
        }
        wp_reset_postdata();

        wp_send_json_success(array(
            'results' => $products
        ));
    }

    public function get_variations() {
        // Verify nonce
        if (!check_ajax_referer('rj-admin-order-nonce', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permission denied');
            return;
        }

        $product_id = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
        
        if (!$product_id) {
            wp_send_json_error('Product ID is required');
            return;
        }

        $product = wc_get_product($product_id);
        
        if (!$product || !$product->is_type('variable')) {
            wp_send_json_error('Invalid product');
            return;
        }

        $variations = array();
        $available_variations = $product->get_available_variations();

        foreach ($available_variations as $variation) {
            $variation_obj = wc_get_product($variation['variation_id']);
            
            if (!$variation_obj || !$variation_obj->is_purchasable()) {
                continue;
            }

            // Format variation attributes
            $attribute_string = array();
            foreach ($variation['attributes'] as $attr => $value) {
                $taxonomy = str_replace('attribute_', '', $attr);
                if ($value) {
                    $term = get_term_by('slug', $value, $taxonomy);
                    $attribute_string[] = $term ? $term->name : $value;
                }
            }

            $variations[] = array(
                'id' => $variation['variation_id'],
                'text' => implode(' - ', $attribute_string),
                'price' => $variation_obj->get_price(),
                'image' => wp_get_attachment_image_url($variation_obj->get_image_id(), 'thumbnail')
            );
        }

        wp_send_json_success(array(
            'variations' => $variations
        ));
    }
} 