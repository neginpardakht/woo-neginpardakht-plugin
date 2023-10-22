<?php
/**
 * Plugin Name: NeginPardakht internet payment gateway
 * Author: NeginPardakht Development Team
 * Description: <a href="https://neginpardakht.ir">NeginPardakht</a>, internet payment gateway for WooCommerce
 * Version: 1.0.0
 * Author URI: https://neginpardakht.ir
 * Author Email: dev@neginpardakht.com
 * Text Domain: woo-neginpardakht-gateway
 * Domain Path: /languages/
 *
 * WC requires at least: 3.0
 * WC tested up to: 6.6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load plugin textdomain.
 *
 * @since 1.0.0
 */
function woo_neginpardakht_gateway_load_textdomain() {
	load_plugin_textdomain( 'woo-neginpardakht-gateway', false, basename( dirname( __FILE__ ) ) . '/languages' );
}

add_action( 'init', 'woo_neginpardakht_gateway_load_textdomain' );

require_once( plugin_dir_path( __FILE__ ) . 'includes/wc-gateway-neginpardakht-helpers.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/wc-gateway-neginpardakht-init.php' );
