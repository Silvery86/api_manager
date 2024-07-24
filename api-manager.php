<?php
/*
Plugin Name: Plugin Manager API
Description: Create custom API
Version: 1.5
Author: Ablue-Dev
Update URI: https://b-commerce.xyz/api-manager/
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
require 'plugin-update-checker-5.4/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/Silvery86/api_manager',
    __FILE__,
    'api_manager'
);

//Set the branch that contains the stable release.
$myUpdateChecker->setBranch('stable-version');

function get_orders_custom_by_date_api($data) {
    // Get custom day from GET request
    $custom_day = isset($_GET['custom_day']) ? intval($_GET['custom_day']) : 0;

    if ($custom_day <= 0) {
        echo 'Please provide a valid custom day value.';
        return;
    }
    $custom_days_ago = new \DateTime("-$custom_day days");
    $today = new \DateTime();
    $args = array(
        'date_created' => $custom_days_ago->format('Y-m-d') . '...' . $today->format('Y-m-d'),
        'limit' => -1,
    );
    $orders = wc_get_orders($args);
    if ($orders) {
        $order_data = array();
        foreach ($orders as $order) {
            try {
                if ($order->get_total() > 0) {
                    // Extracting product details
                    $order_products = array();
                    foreach ($order->get_items() as $item_id => $item) {
                        // Get product attributes
                        $meta_datas = $item->get_meta_data();
                        $pa_size = array();
                        foreach ($meta_datas as $meta_data) {
                            $pa_size[] = array(
                                $meta_data->key => $meta_data->value,
                            );
                        }
                        // Get product id
                        $product_id = $item->get_product_id();
                        // Get product sku
                        $product_sku = get_post_meta($product_id, '_sku', true);
                        // Get product image url
                        $image_url = get_the_post_thumbnail_url($product_id, 'full');
                        $order_products[] = array(
                            'product_sku' => $product_sku,
                            'item_name' => $item->get_name(),
                            'attribute' => $pa_size,
                            'meta_datas' => $meta_datas,
                            'quantity' => $item->get_quantity(),
                            'subtotal' => $item->get_subtotal(),
                            'variation_id' => $item->get_variation_id(),
                            'product_type' => $image_url,
                        );
                    }
                    // Combine first name and last name to full name
                    $first_name = $order->get_shipping_first_name();
                    $last_name = $order->get_shipping_last_name();
                    $full_name = $first_name . ' ' . $last_name;
                    // Get shipping address
                    $address_line_1 = $order->get_shipping_address_1();
                    $address_line_2 = $order->get_shipping_address_2();
                    $city = $order->get_shipping_city();
                    $state = $order->get_shipping_state();
                    $country = $order->get_shipping_country();
                    // Construct the complete address
                    $address_parts = array_filter([$address_line_1, $address_line_2]); // Remove empty parts
                    $complete_address = implode(', ', $address_parts);
                    // get domain name
                    $full_url = home_url();
                    $parsed_url = parse_url($full_url);
                    $domain = '';
                    if (isset($parsed_url['host'])) {
                        $domain = $parsed_url['host']; // Retrieve the domain name
                    }
                    // Get the order date and set to store's timezone
                    $store_timezone = new \DateTimeZone(wc_timezone_string());
                    $order_date = $order->get_date_created()->setTimezone($store_timezone);
					// Get the customer's billing email from the current order
					$billing_email = $order->get_billing_email();
					// Initialize the first and last order dates
					$first_order_date = '';
					$last_order_date = '';
					// Get orders with the matching billing email
					$orders = wc_get_orders(array(
						'limit' => -1,
						'orderby' => 'date',
						'order' => 'ASC', // Get orders sorted by date in ascending order
						'billing_email' => $billing_email,
						'status' => array('processing', 'completed'),
					));
					// Check if orders are found
					if (!empty($orders)) {
						// The first order is the first element in the sorted orders array
						$first_order = reset($orders);
						$first_order_date = $first_order->get_date_created()->setTimezone($store_timezone);
						// The last order is the last element in the sorted orders array
						$last_order = end($orders);
						$last_order_date = $last_order->get_date_created()->setTimezone($store_timezone);
					}
					// If no previous orders, set first and last order dates to the current order date
					if (!$first_order_date) {
						$first_order_date = $order->get_date_created()->setTimezone($store_timezone);
					}
					if (!$last_order_date) {
						$last_order_date = $order->get_date_created()->setTimezone($store_timezone);
					}
                    $order_meta_data = $order->get_meta_data();
                    $cs_paypal_payout = 0;
                    $cs_paypal_fee = 0;
                    $cs_stripe_fee = 0;
                    $cs_stripe_payout = 0;
                    $shield_pp = '';
                    foreach ($order_meta_data as $meta) {
                        if ($meta->key === '_cs_paypal_payout') {
                            $cs_paypal_payout = $meta->value;
                        }
                        if ($meta->key === '_cs_paypal_fee') {
                            $cs_paypal_fee = $meta->value;
                        }
                        if ($meta->key === '_mecom_paypal_proxy_url') {
                            $shield_pp = $meta->value;
                        }
                        if ($meta->key === '_cs_stripe_fee') {
                            $cs_stripe_fee = $meta->value;
                        }
                        if ($meta->key === '_cs_stripe_payout') {
                            $cs_stripe_payout = $meta->value;
                        }
                    }
                    $state_code = $order->get_shipping_state();
                    $country_code = $order->get_shipping_country();
                    $country_name = WC()->countries->get_countries()[$country_code];
                    if ($country_code) {
                        $state_name = WC()->countries->get_states($country_code)[$state_code];
                    }
                    $order_data[] = array(
                        'domain_name' => $domain,
                        'order_number' => $order->get_order_number(),
                        'revenue' => ($order->get_total() !== null) ? $order->get_total() : 0,
                        'paypal_fee' => $cs_paypal_fee,
                        'rev_paypal' => $cs_paypal_payout,
                        'stripe_fee' => $cs_stripe_fee,
                        'rev_stripe' => $cs_stripe_payout,
                        'quantity' => count($order->get_items()),
                        'base_cost' => '',
                        'tn' => '',
                        'carrier' => '',
                        'tn_status' => '',
                        'design' => '',
                        'order_id' => $order->get_id(),
                        'order_status' => $order->get_status(),
                        'order_date' => $order_date->format('Y-m-d H:i:s'),
                        'first_order_date' => $first_order_date->format('Y-m-d H:i:s'),
                        'last_order_date' => $last_order_date->format('Y-m-d H:i:s'),
                        'shipping_full_name' => $full_name,
                        'shipping_address' => $complete_address,
                        'company' => $order->get_shipping_company(),
                        'shipping_city' => $order->get_shipping_city(),
                        'shipping_state' => $state_name,
                        'shipping_postcode' => $order->get_shipping_postcode(),
                        'shipping_country' => $country_name,
                        'country_code' => $country_code,
                        'customer_note' => $order->get_customer_note(),
                        'billing_phone' => $order->get_billing_phone(),
                        'billing_email' => $order->get_billing_email(),
                        'shipping_amount' => $order->get_shipping_total(),
                        'shield_pp' => $shield_pp,
                        'payment_method' => $order->get_payment_method(),
                        'transaction_id' => $order->get_transaction_id(),
                        'products' => $order_products,
                        'meta_data' => $order_meta_data,
                    );
                }
            } catch (Exception $e) {
                echo "Error";
            }
        }
        header('Content-Type: application/json');
        echo json_encode($order_data);
        exit;
    } else {
        echo 'No orders found.';
    }
}

add_action('rest_api_init', function () {
    register_rest_route(
        'wc/v3',
        'get-orders',
        array(
            'methods' => 'GET',
            'callback' => 'get_orders_custom_by_date_api',
            'permission_callback' => function ($request) {
                return current_user_can('manage_options');
            },
        )
    );
});

// API check
// Hook into WooCommerce REST API request
add_action('rest_api_init', 'ab_custom_api_logger_hook', 10, 1);
function ab_custom_api_logger_hook()
{
    add_action('rest_pre_dispatch', 'wc_api_logger_log_request', 10, 3);
}

if (!function_exists('wc_api_logger_log_request')) {
    function wc_api_logger_log_request($result, $server, $request)
    {
        // Get the current user
        $current_user = wp_get_current_user();
        $user         = $current_user->user_login;
        $domain       = get_site_url();
        $ip           = $_SERVER['REMOTE_ADDR'];
        // Get the request URL
        $url = $request->get_route();
        // Get the request method
        $request_method = $request->get_method();
        if ($request_method != 'OPTIONS' && $url != '/wc-analytics/reports') {
            $data = [
                'domain'         => $domain,
                'url'            => $url,
                'user'           => $user,
                'request_method' => $request_method,
                'ip'             => $ip
            ];
            $api_endpoint
                  = 'https://blue-dashboard.com/api_logger'; // Replace with your endpoint URL
            wp_remote_post($api_endpoint, [
                'method'  => 'POST',
                'body'    => json_encode($data),
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]);
        }

        return $result;
    }
}

