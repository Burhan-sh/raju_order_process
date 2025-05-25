<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php _e('RJ Admin Order', 'rj-admin-order'); ?></h1>
    
    <div class="rj-admin-order-info">
        <h2><?php _e('Plugin Information', 'rj-admin-order'); ?></h2>
        <p><?php _e('This plugin allows administrators to create orders on behalf of customers in WooCommerce.', 'rj-admin-order'); ?></p>
        
        <h3><?php _e('How to Use', 'rj-admin-order'); ?></h3>
        <ol>
            <li><?php _e('Go to the page "Order Process Admin"', 'rj-admin-order'); ?></li>
            <li><?php _e('Search and select products (including variations if available)', 'rj-admin-order'); ?></li>
            <li><?php _e('Enter customer details including the 10-digit phone number', 'rj-admin-order'); ?></li>
            <li><?php _e('Fill in the shipping address', 'rj-admin-order'); ?></li>
            <li><?php _e('Review the order summary', 'rj-admin-order'); ?></li>
            <li><?php _e('Click "Place Order" to create the order', 'rj-admin-order'); ?></li>
        </ol>

        <h3><?php _e('Features', 'rj-admin-order'); ?></h3>
        <ul>
            <li><?php _e('Product search with variations support', 'rj-admin-order'); ?></li>
            <li><?php _e('Customer matching by phone number', 'rj-admin-order'); ?></li>
            <li><?php _e('Real-time order total calculation', 'rj-admin-order'); ?></li>
            <li><?php _e('Form validation and error handling', 'rj-admin-order'); ?></li>
            <li><?php _e('Mobile-responsive design', 'rj-admin-order'); ?></li>
        </ul>

        <h3><?php _e('Shortcode', 'rj-admin-order'); ?></h3>
        <p><?php _e('Use the following shortcode to display the order form:', 'rj-admin-order'); ?></p>
        <code>[raju_order_process]</code>
        
        <div class="rj-admin-order-version">
            <p><?php printf(__('Version: %s', 'rj-admin-order'), RJ_ADMIN_ORDER_VERSION); ?></p>
        </div>
    </div>
</div>

<style>
.rj-admin-order-info {
    max-width: 800px;
    margin: 20px 0;
    background: #fff;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.rj-admin-order-info h2 {
    color: #23282d;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.rj-admin-order-info h3 {
    margin: 1.5em 0 1em;
    color: #23282d;
}

.rj-admin-order-info ul,
.rj-admin-order-info ol {
    margin-left: 20px;
}

.rj-admin-order-info li {
    margin-bottom: 10px;
}

.rj-admin-order-info code {
    background: #f6f6f6;
    padding: 5px 10px;
    border-radius: 3px;
    font-size: 14px;
}

.rj-admin-order-version {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eee;
    color: #666;
}
</style> 