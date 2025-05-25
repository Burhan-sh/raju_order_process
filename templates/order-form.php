<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="rj-admin-order-container">
    <div class="rj-admin-order-title">
        <h2><?php _e('Create Order', 'rj-admin-order'); ?></h2>
    </div>
    
    <?php
    // Display errors if any
    if (isset($_SESSION['rj_admin_order_errors'])) {
        echo '<div class="rj-admin-order-errors">';
        echo '<ul>';
        foreach ($_SESSION['rj_admin_order_errors'] as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
        unset($_SESSION['rj_admin_order_errors']);
    }
    ?>

    <div class="rj-admin-order-form-container">
        <form method="post" class="rj-admin-order-form">
            <?php wp_nonce_field('rj_admin_order_form', 'rj_admin_order_nonce'); ?>
            
            <!-- Product Search Section -->
            <div class="rj-admin-order-section">
                <h3><?php _e('Products', 'rj-admin-order'); ?></h3>
                <div class="rj-admin-order-product-search">
                    <input type="text" 
                           id="rj-product-search" 
                           placeholder="<?php esc_attr_e('Search for products...', 'rj-admin-order'); ?>"
                           autocomplete="off">
                </div>
                <div id="rj-selected-products" class="rj-admin-order-selected-products"></div>
            </div>

            <!-- Customer Information Section -->
            <div class="rj-admin-order-section">
                <h3><?php _e('Customer Information', 'rj-admin-order'); ?></h3>
                
                <div class="rj-admin-order-row">
                    <div class="rj-admin-order-col">
                        <label for="fname"><?php _e('First Name', 'rj-admin-order'); ?> *</label>
                        <input type="text" name="fname" id="fname" required>
                    </div>
                    <div class="rj-admin-order-col">
                        <label for="lname"><?php _e('Last Name', 'rj-admin-order'); ?> *</label>
                        <input type="text" name="lname" id="lname" required>
                    </div>
                </div>

                <div class="rj-admin-order-row">
                    <div class="rj-admin-order-col">
                        <label for="phone"><?php _e('Phone Number', 'rj-admin-order'); ?> *</label>
                        <input type="tel" name="phone" id="phone" required pattern="[0-9]{10}" title="<?php esc_attr_e('Please enter exactly 10 digits', 'rj-admin-order'); ?>">
                    </div>
                    <div class="rj-admin-order-col">
                        <label for="email"><?php _e('Email Address', 'rj-admin-order'); ?></label>
                        <input type="email" name="email" id="email">
                    </div>
                </div>

                <div class="rj-admin-order-row">
                    <div class="rj-admin-order-col">
                        <label for="company"><?php _e('Company Name', 'rj-admin-order'); ?></label>
                        <input type="text" name="company" id="company">
                    </div>
                </div>
            </div>

            <!-- Address Section -->
            <div class="rj-admin-order-section">
                <h3><?php _e('Address', 'rj-admin-order'); ?></h3>
                
                <div class="rj-admin-order-row">
                    <div class="rj-admin-order-col">
                        <label for="address_1"><?php _e('Street Address', 'rj-admin-order'); ?> *</label>
                        <input type="text" name="address_1" id="address_1" required>
                    </div>
                    <div class="rj-admin-order-col">
                        <label for="address_2"><?php _e('Apartment, suite, etc.', 'rj-admin-order'); ?></label>
                        <input type="text" name="address_2" id="address_2">
                    </div>
                </div>

                <div class="rj-admin-order-row">
                    <div class="rj-admin-order-col">
                        <label for="city"><?php _e('City', 'rj-admin-order'); ?> *</label>
                        <input type="text" name="city" id="city" required>
                    </div>
                    <div class="rj-admin-order-col">
                        <label for="state"><?php _e('State', 'rj-admin-order'); ?> *</label>
                        <input type="text" name="state" id="state" required>
                    </div>
                </div>

                <div class="rj-admin-order-row">
                    <div class="rj-admin-order-col">
                        <label for="postcode"><?php _e('Postcode', 'rj-admin-order'); ?> *</label>
                        <input type="text" name="postcode" id="postcode" required>
                    </div>
                    <div class="rj-admin-order-col">
                        <label for="country"><?php _e('Country', 'rj-admin-order'); ?> *</label>
                        <select name="country" id="country" required>
                            <option value=""><?php _e('Select a country...', 'rj-admin-order'); ?></option>
                            <?php
                            $countries_obj = new WC_Countries();
                            $countries = $countries_obj->get_countries();
                            foreach ($countries as $code => $name) {
                                echo '<option value="' . esc_attr($code) . '">' . esc_html($name) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Order Summary Section -->
            <div class="rj-admin-order-section">
                <h3><?php _e('Order Summary', 'rj-admin-order'); ?></h3>
                <div id="rj-order-summary" class="rj-admin-order-summary">
                    <table>
                        <thead>
                            <tr>
                                <th><?php _e('Product', 'rj-admin-order'); ?></th>
                                <th><?php _e('Quantity', 'rj-admin-order'); ?></th>
                                <th><?php _e('Price', 'rj-admin-order'); ?></th>
                                <th><?php _e('Total', 'rj-admin-order'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="rj-order-items"></tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3"><?php _e('Subtotal', 'rj-admin-order'); ?></th>
                                <td id="rj-order-subtotal">₹0.00</td>
                            </tr>
                            <tr>
                                <th colspan="3"><?php _e('Shipping', 'rj-admin-order'); ?></th>
                                <td><?php _e('Free shipping', 'rj-admin-order'); ?></td>
                            </tr>
                            <tr>
                                <th colspan="3"><?php _e('Total', 'rj-admin-order'); ?></th>
                                <td id="rj-order-total">₹0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="rj-admin-order-actions">
                <input type="hidden" name="rj_admin_order_submit" value="1">
                <button type="submit" class="button button-primary">
                    <?php _e('Place Order', 'rj-admin-order'); ?>
                </button>
            </div>
        </form>
    </div>
</div> 