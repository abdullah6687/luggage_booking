<?php
/**
 * Plugin Name: Luggage Storage Booking
 * Description: A WooCommerce-integrated plugin for luggage storage booking.
 * Version: 1.0.0
 * Author: Maham
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Add settings menu
function luggage_storage_add_admin_menu() {
    add_options_page('Luggage Storage Settings', 'Luggage Storage', 'manage_options', 'luggage_storage', 'luggage_storage_settings_page');
}
add_action('admin_menu', 'luggage_storage_add_admin_menu');

// Register settings
function luggage_storage_register_settings() {
    register_setting('luggage_storage_settings', 'per_day_charge');
    register_setting('luggage_storage_settings', 'insurance_per_bag');
    register_setting('luggage_storage_settings', 'luggage_product_id');
}
add_action('admin_init', 'luggage_storage_register_settings');

// Settings page
function luggage_storage_settings_page() {
    ?>
    <div class="wrap">
        <h1>Luggage Storage Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('luggage_storage_settings');
            do_settings_sections('luggage_storage_settings');
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Luggage Product ID</th>
                    <td><input type="number" name="luggage_product_id" value="<?php echo esc_attr(get_option('luggage_product_id', 0)); ?>" min="1"></td>
                </tr>
                <tr>
                    <th scope="row">Per Day Charge (£)</th>
                    <td><input type="number" name="per_day_charge" value="<?php echo esc_attr(get_option('per_day_charge', 10)); ?>" step="0.01"></td>
                </tr>
                <tr>
                    <th scope="row">Insurance Per Bag (£)</th>
                    <td><input type="number" name="insurance_per_bag" value="<?php echo esc_attr(get_option('insurance_per_bag', 5)); ?>" step="0.01"></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Add booking fields to a specific product page
function luggage_storage_add_booking_fields() {
    if (!is_product()) return;
    global $product;
    $luggage_product_id = get_option('luggage_product_id', 0);
    if ($product->get_id() != $luggage_product_id) return;
    ?>
    <div id="luggage-booking">
        <label>Start Date: <input type="date" id="start_date" required></label>
        <label>End Date: <input type="date" id="end_date" required></label>
        <label>Number of Bags: <input type="number" id="num_bags" min="1" value="1" required></label>
        <button type="button" id="book_now" class="button alt">Book Now</button>
    </div>
    <script>
        document.getElementById('book_now').addEventListener('click', function() {
            let startDate = document.getElementById('start_date').value;
            let endDate = document.getElementById('end_date').value;
            let numBags = parseInt(document.getElementById('num_bags').value);
            let perDayCharge = <?php echo get_option('per_day_charge', 10); ?>;
            let insurancePerBag = <?php echo get_option('insurance_per_bag', 5); ?>;

            if (!startDate || !endDate || numBags < 1) {
                alert('Please enter valid dates and number of bags.');
                return;
            }

            let days = Math.max(1, (new Date(endDate) - new Date(startDate)) / (1000 * 60 * 60 * 24));
            let totalCost = days * ((perDayCharge * numBags) + (insurancePerBag * numBags));

            let url = new URL('<?php echo esc_url(wc_get_cart_url()); ?>');
            url.searchParams.append('add-to-cart', '<?php echo get_option('luggage_product_id', 0); ?>');
            url.searchParams.append('quantity', 1);
            url.searchParams.append('start_date', startDate);
            url.searchParams.append('end_date', endDate);
            url.searchParams.append('num_bags', numBags);
            url.searchParams.append('total_cost', totalCost);
            window.location.href = url;
        });
    </script>
    <style>
        #luggage-booking { margin-top: 20px; }
        #luggage-booking label { display: block; margin-bottom: 10px; }
    </style>
    <?php
}
add_action('woocommerce_before_add_to_cart_button', 'luggage_storage_add_booking_fields');

// Add custom data to cart item
function luggage_storage_add_cart_item_data($cart_item_data, $product_id, $variation_id) {
    if (isset($_GET['start_date'])) {
        $cart_item_data['start_date'] = sanitize_text_field($_GET['start_date']);
        $cart_item_data['end_date'] = sanitize_text_field($_GET['end_date']);
        $cart_item_data['num_bags'] = intval($_GET['num_bags']);
        $cart_item_data['total_cost'] = floatval($_GET['total_cost']);
    }
    return $cart_item_data;
}
add_filter('woocommerce_add_cart_item_data', 'luggage_storage_add_cart_item_data', 10, 3);

// Display custom data in cart
function luggage_storage_display_cart_item_data($item_data, $cart_item) {
    if (isset($cart_item['start_date'])) {
        $item_data[] = ['name' => 'Start Date', 'value' => $cart_item['start_date']];
        $item_data[] = ['name' => 'End Date', 'value' => $cart_item['end_date']];
        $item_data[] = ['name' => 'Number of Bags', 'value' => $cart_item['num_bags']];
    }
    return $item_data;
}
add_filter('woocommerce_get_item_data', 'luggage_storage_display_cart_item_data', 10, 2);

// Adjust cart item price
function luggage_storage_calculate_price($cart_object) {
    foreach ($cart_object->get_cart() as $cart_item) {
        if (isset($cart_item['total_cost'])) {
            $cart_item['data']->set_price($cart_item['total_cost']);
        }
    }
}
add_action('woocommerce_before_calculate_totals', 'luggage_storage_calculate_price');
