<?php
/*
Plugin Name: Woocommerce Superlist Sitef Payments
Plugin URI: http://github.com/jscarton/woocomerce-autoship-sitef-latam-recurring-payments
Description: Add sitef recurring payments as autoship payment gateway
Version: 1.0.0
Author: Juan Scarton
Author URI: http://github.com/jscarton
License: GPLV3
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'SUPERLIST_SITEF_PAYMENT_METHOD_VERSION', '1.0.0' );
define( 'SUPERLIST_SITEF_ROOT', plugin_dir_path( __FILE__ ) );
define( 'SUPERLIST_SITEF_ROOT_URL', plugin_dir_url( __FILE__ ) );
define( 'SUPERLIST_SITEF_ROOT_FILE', __FILE__) ;
include_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once SUPERLIST_SITEF_ROOT."includes/superlist-sitef-loader.php";

if ( is_plugin_active( 'woocommerce/woocommerce.php' ) &&  is_plugin_active( 'woocommerce-autoship/woocommerce-autoship.php' )) {
	
	function superlist_sitef_activate() {
		SuperlistSitefSetup::activate();
	}
	register_activation_hook( __FILE__, 'superlist_sitef_activate' );
	function superlist_sitef_deactivate() {
		SuperlistSitefSetup::deactivate();
	}
	register_deactivation_hook( __FILE__, 'superlist_sitef_deactivate' );
	function superlist_sitef_uninstall() {
		SuperlistSitefSetup::uninstall();
	}
	register_uninstall_hook( __FILE__, 'superlist_sitef_uninstall' );
	
	//register shortcodes
	$shortcodes=new SuperlistSitefShortcodes();
	$shortcodes->register();

	function superlist_sitef_load_gateway_class() {
		// Initialize WooCommerce
		if ( is_plugin_active( 'woocommerce/woocommerce.php' ) && function_exists( 'WC' ) ) {
			WC();
			// Include gateway class
			require_once SUPERLIST_SITEF_ROOT."classes/superlist-sitef-payment-gateway.php";
		}
	}
	add_action( 'plugins_loaded', 'superlist_sitef_load_gateway_class' );

	function superlist_sitef_payments_register_gateway( $methods ) {
		if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			$methods[] = 'SuperlistSitefPaymentGateway';
		}
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'superlist_sitef_payments_register_gateway' );

	function superlist_sitef_payments_load_for_functions()
	{
		require_once SUPERLIST_SITEF_ROOT."includes/superlist-sitef-loader.php";		
	}
	add_action ("superlist_sitef_payments_functions_init",'superlist_sitef_payments_load_for_functions');
	
	if ( is_admin() ) {
		// Register admin settings
		$settings = new SuperlistSitefSettings();
		$settings->register();
	}
}