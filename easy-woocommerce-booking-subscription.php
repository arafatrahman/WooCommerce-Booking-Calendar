<?php
/**
 * Plugin Name: WooCommerce Booking Subscription
 * Plugin URI:  https://yourwebsite.com/
 * Description: Adds a booking date selection for products with customizable subscription durations.
 * Version:     1.1
 * Author:      Your Name
 * Author URI:  https://yourwebsite.com/
 * Text Domain: wc-booking-subscription
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add booking date and expiry duration fields to the product settings page.
 */
add_action('woocommerce_product_options_general_product_data', 'wcbs_add_booking_and_expiry_options');
function wcbs_add_booking_and_expiry_options() {
    // Enable booking date checkbox
    woocommerce_wp_checkbox([
        'id'    => '_enable_booking_date',
        'label' => __('Enable Booking Date', 'wc-booking-subscription'),
        'desc_tip' => 'true',
        'description' => __('Allow customers to select a booking start date for this product.', 'wc-booking-subscription'),
    ]);

    // Expiry duration dropdown
    woocommerce_wp_select([
        'id'          => '_expiry_duration',
        'label'       => __('Expiry Duration', 'wc-booking-subscription'),
        'options'     => [
            '4_weeks'   => __('4 Weeks', 'wc-booking-subscription'),
            '6_months'  => __('6 Months', 'wc-booking-subscription'),
            '1_year'    => __('1 Year', 'wc-booking-subscription'),
            'unlimited' => __('Unlimited', 'wc-booking-subscription'),
        ],
        'description' => __('Set the subscription expiry duration.', 'wc-booking-subscription'),
        'desc_tip'    => true,
    ]);

    // Add a custom checkbox field to enable/disable promo code generation
    woocommerce_wp_checkbox( array(
        'id'            => '_enable_promo_code',
        'label'         => __( 'Enable Promo Code for this Product', 'wc-booking-subscription' ),
        'description'   => __( 'Check this box if you want to enable promo code generation for this product.', 'woocommerce' ),
        'desc_tip'      => true,
        'value'         => get_post_meta( get_the_ID(), '_enable_promo_code', true ) ? 'yes' : 'no',
    ) );

//  /   $usage_limit = get_post_meta( get_the_ID(), '_promo_code_usage_limit', true );
    woocommerce_wp_text_input( array(
        'id'            => '_promo_code_usage_limit',
        'label'         => __( 'Promo Code Usage Limit', 'wc-booking-subscription' ),
        'desc_tip'      => true,
        'description'   => __( 'Enter how many times the promo code can be used. Leave empty for unlimited use.', 'woocommerce' ),
        'type'          => 'number',
        'value'         => get_post_meta( get_the_ID(), '_promo_code_usage_limit', true ),
    ) );
}

add_action('woocommerce_process_product_meta', 'wcbs_save_booking_and_expiry_options');
function wcbs_save_booking_and_expiry_options($post_id) {
    $enable_booking_date = isset($_POST['_enable_booking_date']) ? 'yes' : 'no';
    update_post_meta($post_id, '_enable_booking_date', $enable_booking_date);

    $expiry_duration = isset($_POST['_expiry_duration']) ? sanitize_text_field($_POST['_expiry_duration']) : '6_months';
    update_post_meta($post_id, '_expiry_duration', $expiry_duration);

    $enable_promo_code = isset($_POST['_enable_promo_code']) ? sanitize_text_field($_POST['_enable_promo_code']) : '6_months';
    update_post_meta($post_id, '_enable_promo_code', $expiry_duration);

    $promo_code_usage_limit = isset($_POST['_promo_code_usage_limit']) ? sanitize_text_field($_POST['_promo_code_usage_limit']) : '6_months';
    update_post_meta($post_id, '_promo_code_usage_limit', $promo_code_usage_limit);
}

/**
 * Add booking date field on the product page if enabled.
 */
add_action('woocommerce_before_add_to_cart_button', 'wcbs_display_booking_date_field');
function wcbs_display_booking_date_field() {
    global $product;

    $is_booking_enabled = get_post_meta($product->get_id(), '_enable_booking_date', true);

    if ('yes' === $is_booking_enabled) {
        echo '<div class="wc-booking-date-field">';
        echo '<label for="wc_booking_date">' . __('Select Booking Start Date:', 'wc-booking-subscription') . '</label>';
        echo '<input type="date" id="wc_booking_date" name="wc_booking_date" required />';
        echo '</div>';
    }
}

/**
 * Validate booking date on add to cart.
 */
add_filter('woocommerce_add_to_cart_validation', 'wcbs_validate_booking_date', 10, 3);
function wcbs_validate_booking_date($passed, $product_id, $quantity) {
    if (isset($_POST['wc_booking_date']) && empty($_POST['wc_booking_date'])) {
        wc_add_notice(__('Please select a booking start date.', 'wc-booking-subscription'), 'error');
        return false;
    }
    return $passed;
}

/**
 * Save booking date and expiry duration to cart item.
 */
add_filter('woocommerce_add_cart_item_data', 'wcbs_add_booking_date_to_cart', 10, 2);
function wcbs_add_booking_date_to_cart($cart_item_data, $product_id) {
    if (isset($_POST['wc_booking_date'])) {
        $cart_item_data['wc_booking_date'] = sanitize_text_field($_POST['wc_booking_date']);
    }

    // Fetch expiry duration
    $expiry_duration = get_post_meta($product_id, '_expiry_duration', true);
    $cart_item_data['wc_expiry_duration'] = $expiry_duration;

    // Fetch expiry duration
    $expiry_duration = get_post_meta($product_id, '_promo_code_usage_limit', true);
    $cart_item_data['wc_promo_code_usage_limit'] = $expiry_duration;

    return $cart_item_data;
}

/**
 * Display booking and expiry dates in the cart and checkout pages.
 */
add_filter('woocommerce_get_item_data', 'wcbs_display_booking_and_expiry_cart', 10, 2);
function wcbs_display_booking_and_expiry_cart($item_data, $cart_item) {
    if (isset($cart_item['wc_booking_date'])) {
        $booking_date = $cart_item['wc_booking_date'];
        $expiry_duration = $cart_item['wc_expiry_duration'];
        $promo_code_usage_limit = $cart_item['wc_promo_code_usage_limit'];


        // Calculate expiration date based on selected duration
        if ($expiry_duration === '4_weeks') {
            $expiration_date = date('Y-m-d', strtotime($booking_date . ' +4 weeks'));
        } elseif ($expiry_duration === '6_months') {
            $expiration_date = date('Y-m-d', strtotime($booking_date . ' +6 months'));
        } elseif ($expiry_duration === '1_year') {
            $expiration_date = date('Y-m-d', strtotime($booking_date . ' +1 year'));
        } else {
            $expiration_date = __('Unlimited', 'wc-booking-subscription');
        }

        $item_data[] = [
            'name'  => __('Booking Start Date', 'wc-booking-subscription'),
            'value' => $booking_date,
        ];
        $item_data[] = [
            'name'  => __('Booking End Date', 'wc-booking-subscription'),
            'value' => $expiration_date,
        ];

        $item_data[] = [
            'name'  => __('Promo code usage limit', 'wc-booking-subscription'),
            'value' => $promo_code_usage_limit,
        ];
    }
    return $item_data;
}

/**
 * Save booking and expiration dates to order metadata.
 */
add_action('woocommerce_checkout_create_order_line_item', 'wcbs_save_booking_date_to_order', 10, 4);
function wcbs_save_booking_date_to_order($item, $cart_item_key, $values, $order) {
    if (isset($values['wc_booking_date'])) {
        $booking_date = $values['wc_booking_date'];
        $expiry_duration = $values['wc_expiry_duration'];
        $promo_code_usage_limit = $values['wc_promo_code_usage_limit'];

        // Calculate expiration date
        if ($expiry_duration === '4_weeks') {
            $expiration_date = date('Y-m-d', strtotime($booking_date . ' +4 weeks'));
        } elseif ($expiry_duration === '6_months') {
            $expiration_date = date('Y-m-d', strtotime($booking_date . ' +6 months'));
        } elseif ($expiry_duration === '1_year') {
            $expiration_date = date('Y-m-d', strtotime($booking_date . ' +1 year'));
        } else {
            $expiration_date = __('Unlimited', 'wc-booking-subscription');
        }

        $item->add_meta_data(__('WP_Booking_Start_Date', 'wc-booking-subscription'), $booking_date);
        $item->add_meta_data(__('WP_Booking_End_Date', 'wc-booking-subscription'), $expiration_date);
        $item->add_meta_data(__('WP_Promo_Code_Usage_Limit', 'wc-booking-subscription'), $promo_code_usage_limit);

    }
}

/**
 * Display booking dates in the order details page.
 */
add_action('woocommerce_order_item_meta_end', 'wcbs_display_booking_date_order', 10, 4);
function wcbs_display_booking_date_order($item_id, $item, $order, $plain_text) {
    $booking_date = wc_get_order_item_meta($item_id, __('WP_Booking_Start Date', 'wc-booking-subscription'));
    $end_date = wc_get_order_item_meta($item_id, __('WP_Booking_End_Date', 'wc-booking-subscription'));
    $WP_Promo_Code_Usage_Limit = wc_get_order_item_meta($item_id, __('WP_Promo_Code_Usage_Limit', 'wc-booking-subscription'));


    $WP_Promo_Code = wc_get_order_item_meta($item_id, __('WP_Promo_Code', 'wc-booking-subscription'));


    if ($booking_date) {
        echo '<p><strong>' . __('Booking Start Date:', 'wc-booking-subscription') . '</strong> ' . esc_html($booking_date) . '</p>';
    }
    if ($end_date) {
        echo '<p><strong>' . __('Booking End Date:', 'wc-booking-subscription') . '</strong> ' . esc_html($end_date) . '</p>';
    }
    if ($WP_Promo_Code_Usage_Limit) {
        echo '<p><strong>' . __('Promo Code Usage Limit:', 'wc-booking-subscription') . '</strong> ' . esc_html($WP_Promo_Code_Usage_Limit) . '</p>';
    }
    if ($WP_Promo_Code) {
        echo '<p><strong>' . __('Your Promo Code:', 'wc-booking-subscription') . '</strong> ' . esc_html($WP_Promo_Code) . '</p>';
    }
}
add_action('admin_menu', 'wcbs_register_booking_calendar_menu');
function wcbs_register_booking_calendar_menu() {
    add_menu_page(
        __('Booking Calendar', 'wc-booking-subscription'), // Page title
        __('Booking Calendar', 'wc-booking-subscription'), // Menu title
        'manage_woocommerce', // Capability
        'wc-booking-calendar', // Menu slug
        'wcbs_render_booking_calendar', // Callback function
        'dashicons-calendar-alt', // Icon
        25 // Position
    );
}
function wcbs_render_booking_calendar() {
    ?>
    <div class="wrap">
        <h1><?php _e('Booking Calendar', 'wc-booking-subscription'); ?></h1>
        <div id="booking-calendar"></div>
    </div>

    <!-- Include FullCalendar CSS and JS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('booking-calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            events: <?php echo json_encode(wcbs_get_bookings_for_calendar()); ?>,
            eventDidMount: function(info) {
                // On hover, show additional information in a tooltip
                var clientInfo = info.event.extendedProps.description;

                // Attach the description to the tooltip
                info.el.setAttribute('title', clientInfo);
                info.el.style.cursor = 'pointer'; // Change cursor to pointer to indicate it's interactive
            }
        });
        calendar.render();
    });
    </script>

    <style>
        #booking-calendar {
            max-width: 900px;
            margin: 20px auto;
        }
    </style>
    <?php
}



function wcbs_get_bookings_for_calendar() {
    $bookings = [];
    $orders = wc_get_orders(['limit' => -1]); // Fetch all orders

    foreach ($orders as $order) {
        foreach ($order->get_items() as $item_id => $item) {
            
            $booking_start_date = wc_get_order_item_meta($item_id, __('WP_Booking_Start_Date', 'wc-booking-subscription'));
            $booking_end_date = wc_get_order_item_meta($item_id, __('WP_Booking_End_Date', 'wc-booking-subscription'));
            $WP_Promo_Code_Usage_Limit = wc_get_order_item_meta($item_id, __('WP_Promo_Code_Usage_Limit', 'wc-booking-subscription'));

            $order_id = $order->get_id();
            $order_name = $item->get_name(); 

            if ($booking_start_date) {
                $client_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                $client_phone = $order->get_billing_phone();
                $bookings[] = [
                    'title' => sprintf(__(' #%s - %s', 'wc-booking-subscription'), $order_id, $order_name),
                    'start' => $booking_start_date,
                    'end'   => '',
                    'description' => sprintf(
                        __('Name: %s, Phone: %s, End Date: %s, Promocode Usage Limit: ', 'wc-booking-subscription'),
                        $client_name,
                        $client_phone,
                        $booking_end_date,
                        $WP_Promo_Code_Usage_Limit
                    ),
                ];
            }
        }
    }

    return $bookings;
}



function wcbs_display_promo_code_on_thankyou($thank_you_text, $order) {
    if (!$order instanceof WC_Order) {
        return $thank_you_text;
    }

    // Loop through order items to retrieve promo code details
    foreach ($order->get_items() as $item_id => $item) {
        $promo_code = 'BOOKING-' . strtoupper(uniqid()) . '-' . substr($item->get_name(), 0, 3);

        wc_add_order_item_meta($item_id, 'WP_Promo_Code', $promo_code);
        update_option($item_id.'_WP_Promo_Code', $promo_code);
    }


    return $thank_you_text;
}

function generate_woocommerce_promo_code() {
    // Define the coupon details
    $coupon_code = 'DISCOUNT2024'; // Coupon code (this can be generated dynamically)
    $discount_type = 'percent'; // Type of discount: 'fixed_cart', 'percent', 'fixed_product', 'percent_product'
    $coupon_amount = 20; // Discount amount (e.g., 20% off)
    $individual_use = true; // Set to true if the coupon cannot be used with other coupons
    $exclude_sale_items = false; // Set to true if you want to exclude sale items
    $minimum_spend = 50; // Minimum spend requirement for the coupon to be applied
    $maximum_spend = 500; // Maximum spend allowed for the coupon to be applied
    $expiry_date = '2024-12-31'; // Expiry date in Y-m-d format

    // Create the coupon
    $coupon = new WC_Coupon();
    $coupon->set_code($coupon_code); // Coupon code
    $coupon->set_discount_type($discount_type); // Discount type
    $coupon->set_amount($coupon_amount); // Discount amount
    $coupon->set_individual_use($individual_use); // If the coupon can be used with other coupons
    $coupon->set_exclude_sale_items($exclude_sale_items); // Whether sale items are excluded
    $coupon->set_minimum_amount($minimum_spend); // Minimum spend
    $coupon->set_maximum_amount($maximum_spend); // Maximum spend
    $coupon->set_date_expires(strtotime($expiry_date)); // Expiry date
    
    // Save the coupon
    $coupon->save();

    // Return the coupon code
    return $coupon_code;
}

// Call the function to generate the promo code
//$generated_coupon_code = generate_woocommerce_promo_code();
//echo 'New promo code generated: ' . $generated_coupon_code;




