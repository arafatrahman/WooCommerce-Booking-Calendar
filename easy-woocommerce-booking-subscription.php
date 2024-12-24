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



/**
 * Replace "Add to Cart" button with "Book Now" for booking-enabled products.
 */
add_filter('woocommerce_product_single_add_to_cart_text', 'wcbs_change_add_to_cart_button_text');
function wcbs_change_add_to_cart_button_text($button_text) {
    global $product;

    // Check if the booking feature is enabled for this product
    $is_booking_enabled = get_post_meta($product->get_id(), '_enable_booking_date', true);

    if ('yes' === $is_booking_enabled) {
        $button_text = __('Book Now', 'wc-booking-subscription');
    }

    return $button_text;
}

/**
 * Replace the "Add to Cart" button functionality with booking logic.
 */
add_action('woocommerce_before_add_to_cart_form', 'wcbs_replace_add_to_cart_button_logic');
function wcbs_replace_add_to_cart_button_logic() {
    global $product;

    $is_booking_enabled = get_post_meta($product->get_id(), '_enable_booking_date', true);

    if ('yes' === $is_booking_enabled) {
        // Remove the default WooCommerce add to cart button
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);

        // Add custom booking button
        add_action('woocommerce_single_product_summary', 'wcbs_custom_booking_button', 30);
    }
}

/**
 * Display custom "Book Now" button.
 */
function wcbs_custom_booking_button() {
    global $product;

    $booking_page_url = esc_url(add_query_arg(['product_id' => $product->get_id()], get_permalink())); // Customize the booking page URL if needed

    echo '<a href="' . $booking_page_url . '" class="button wc-book-now-button">' . __('Book Now', 'wc-booking-subscription') . '</a>';
}
/**
 * Replace "Add to Cart" button with "Book Now" button on the shop page.
 */
add_filter('woocommerce_loop_add_to_cart_link', 'wcbs_replace_shop_add_to_cart_button', 10, 2);
function wcbs_replace_shop_add_to_cart_button($button, $product) {
    // Check if the booking feature is enabled for this product
    $is_booking_enabled = get_post_meta($product->get_id(), '_enable_booking_date', true);

    if ('yes' === $is_booking_enabled) {
        $product_url = get_permalink($product->get_id()); // Get single product page URL
        $button = '<a href="' . esc_url($product_url) . '" class="button wc-book-now-button">' . __('Book Now', 'wc-booking-subscription') . '</a>';
    }

    return $button;
}




/**
 * Helper function to calculate the coupon expiration date based on the product's expiry duration setting.
 *
 * @param string $expiry_duration The expiry duration ('4_weeks', '6_months', '1_year', 'unlimited').
 * @return string Expiration date in 'Y-m-d' format or null for unlimited.
 */
function calculate_expiry_date($expiry_duration) {
    $current_date = current_time('Y-m-d');
    switch ($expiry_duration) {
        case '4_weeks':
            return date('Y-m-d', strtotime($current_date . ' +4 weeks'));
        case '6_months':
            return date('Y-m-d', strtotime($current_date . ' +6 months'));
        case '1_year':
            return date('Y-m-d', strtotime($current_date . ' +1 year'));
        case 'unlimited':
            return null; // No expiration
        default:
            return date('Y-m-d', strtotime($current_date . ' +6 months')); // Default to 6 months
    }
}




/**
 * Generate coupon and display details in order emails using woocommerce_order_item_meta_end.
 */
add_action('woocommerce_order_item_meta_end', 'wcbs_generate_and_display_coupon_details', 10, 4);
function wcbs_generate_and_display_coupon_details($item_id, $item, $order, $plain_text) {
    // Check if a coupon code has already been generated for this item
   
        $generated_coupon_code = 'BOOKING-' . strtoupper(uniqid()) . '-' . substr($item->get_name(), 0, 3);
        
        // Define coupon details
        $discount_type = 'percent'; // 50% discount
        $coupon_amount = 50; 
        $usage_limit = 1; // Single-use by default
        $expiry_duration = $order->get_meta('_expiry_duration', true); // Fetch expiry duration set for the product
        
        // Calculate expiry date based on settings
        if ($expiry_duration === '4_weeks') {
            $expiry_date = date('Y-m-d', strtotime('+4 weeks'));
        } elseif ($expiry_duration === '6_months') {
            $expiry_date = date('Y-m-d', strtotime('+6 months'));
        } elseif ($expiry_duration === '1_year') {
            $expiry_date = date('Y-m-d', strtotime('+1 year'));
        } else {
            $expiry_date = null; // Unlimited expiry
        }
        
        // Create the coupon
        $coupon = new WC_Coupon();
        $coupon->set_code($generated_coupon_code);
        $coupon->set_discount_type($discount_type);
        $coupon->set_amount($coupon_amount);
        $coupon->set_individual_use(true); // Cannot combine with other coupons
        $coupon->set_usage_limit($usage_limit); 
        if ($expiry_date) {
            $coupon->set_date_expires(strtotime($expiry_date));
        }
        $coupon->save();    

    // Display the coupon details in the email
    if ($generated_coupon_code) {
        // Fetch expiry date and usage limit
        $expiry_date = $coupon->get_date_expires() ? date('F j, Y', strtotime($coupon->get_date_expires())) : 'Unlimited';
        $usage_limit = $coupon->get_usage_limit() ? $coupon->get_usage_limit() : 'No limit';

        echo '<br><strong>Promo Code:</strong> ' . esc_html($generated_coupon_code);
        echo '<br><strong>Expiry Date:</strong> ' . esc_html($expiry_date);
        echo '<br><strong>Usage Limit:</strong> ' . esc_html($usage_limit);
    }
}







