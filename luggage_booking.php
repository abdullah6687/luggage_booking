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

// Add booking fields to product page
function luggage_storage_add_booking_fields() {
    if (!is_product()) return;
    ?>
    <div id="luggage-booking">
        <label>Start Date: <input type="date" id="start_date" required></label>
        <label>End Date: <input type="date" id="end_date" required></label>
        <label>Number of Bags: <input type="number" id="num_bags" min="1" value="1" required></label>
        <button type="button" id="book_now" class="button alt">Book Now</button>
    </div>
    <script>
        document.getElementById('book_now').addEventListener('click', function() {
            let startDate = new Date(document.getElementById('start_date').value);
            let endDate = new Date(document.getElementById('end_date').value);
            let numBags = parseInt(document.getElementById('num_bags').value);
            let perDayCharge = <?php echo get_option('per_day_charge', 10); ?>;
            let insurancePerBag = <?php echo get_option('insurance_per_bag', 5); ?>;

            if (isNaN(startDate) || isNaN(endDate) || numBags < 1) {
                alert('Please enter valid dates and number of bags.');
                return;
            }

            let days = Math.max(1, Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24)));
            let totalCost = days * ((perDayCharge * numBags) + (insurancePerBag * numBags));

            let form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo esc_url(wc_get_checkout_url()); ?>';

            let input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'luggage_booking';
            input.value = JSON.stringify({ startDate, endDate, numBags, totalCost });
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        });
    </script>
    <style>
        #luggage-booking { margin-top: 20px; }
        #luggage-booking label { display: block; margin-bottom: 10px; }
    </style>
    <?php
}
add_action('woocommerce_before_add_to_cart_button', 'luggage_storage_add_booking_fields');

// Process the booking at checkout
function luggage_storage_process_checkout($cart_object) {
    if (isset($_POST['luggage_booking'])) {
        $bookingData = json_decode(stripslashes($_POST['luggage_booking']), true);
        $cart_object->add_to_cart( get_the_ID(), 1, 0, [], [
            'start_date' => $bookingData['startDate'],
            'end_date' => $bookingData['endDate'],
            'num_bags' => $bookingData['numBags'],
            'total_cost' => $bookingData['totalCost']
        ]);
    }
}
add_action('woocommerce_before_calculate_totals', 'luggage_storage_process_checkout');

// Customize the cart item name
function luggage_storage_customize_cart_item_name($item_name, $cart_item, $cart_item_key) {
    if (isset($cart_item['start_date'])) {
        $item_name .= '<br>Start Date: ' . esc_html($cart_item['start_date']) .
                      '<br>End Date: ' . esc_html($cart_item['end_date']) .
                      '<br>Number of Bags: ' . esc_html($cart_item['num_bags']);
    }
    return $item_name;
}
add_filter('woocommerce_cart_item_name', 'luggage_storage_customize_cart_item_name', 10, 3);

// Override cart total price
function luggage_storage_override_cart_price($cart_object) {
    foreach ($cart_object->cart_contents as $cart_item_key => $cart_item) {
        if (isset($cart_item['total_cost'])) {
            $cart_item['data']->set_price($cart_item['total_cost']);
        }
    }
}
add_action('woocommerce_cart_calculate_fees', 'luggage_storage_override_cart_price');
