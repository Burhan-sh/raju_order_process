<?php
class RJ_Admin_Order_Ajax {
    public function __construct() {
        add_action('wp_ajax_rj_search_products', array($this, 'search_products'));
        add_action('wp_ajax_rj_get_variations', array($this, 'get_variations'));
    }

    public function search_products() {
        check_ajax_referer('rj-admin-order-nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied.', 'rj-admin-order'));
        }

        $search_term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';
        
        if (empty($search_term)) {
            wp_send_json_error(__('Search term is required.', 'rj-admin-order'));
        }

        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            's'              => $search_term,
            'orderby'        => 'title',
            'order'          => 'ASC'
        );

        $products = array();
        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());
                
                if (!$product) {
                    continue;
                }

                $product_data = array(
                    'id'    => $product->get_id(),
                    'text'  => $product->get_name(),
                    'price' => $product->get_price(),
                    'type'  => $product->get_type()
                );

                // Check if product has variations
                if ($product->is_type('variable')) {
                    $product_data['has_variations'] = true;
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
        check_ajax_referer('rj-admin-order-nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied.', 'rj-admin-order'));
        }

        $product_id = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
        
        if (!$product_id) {
            wp_send_json_error(__('Product ID is required.', 'rj-admin-order'));
        }

        $product = wc_get_product($product_id);
        
        if (!$product || !$product->is_type('variable')) {
            wp_send_json_error(__('Invalid product.', 'rj-admin-order'));
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
            foreach ($variation['attributes'] as $attribute => $value) {
                $taxonomy = str_replace('attribute_', '', $attribute);
                $term = get_term_by('slug', $value, $taxonomy);
                $attribute_string[] = $term ? $term->name : $value;
            }

            $variations[] = array(
                'variation_id' => $variation['variation_id'],
                'text'        => implode(' - ', $attribute_string),
                'price'       => $variation_obj->get_price(),
                'sku'         => $variation_obj->get_sku(),
                'stock'       => $variation_obj->get_stock_quantity()
            );
        }

        wp_send_json_success(array(
            'variations' => $variations
        ));
    }
} 