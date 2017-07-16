<?php
/*** load classes ***/
require_once SUPERLIST_SITEF_ROOT."classes/superlist-sitef-base.php";
require_once SUPERLIST_SITEF_ROOT."classes/superlist-sitef-settings.php";
require_once SUPERLIST_SITEF_ROOT."classes/superlist-sitef-setup.php";
require_once SUPERLIST_SITEF_ROOT."classes/superlist-sitef-credit-card.php";
require_once SUPERLIST_SITEF_ROOT."classes/superlist-sitef-shortcodes.php";
//require_once SUPERLIST_PAYU_ROOT."classes/superlist-payu-autoship-payment-gateway.php";

/*** enqueue styles and scripts ***/
function superlist_sitef_styles_and_scripts() {
	wp_enqueue_style( 'superlist-sitef-css', SUPERLIST_SITEF_ROOT_URL."assets/css/style.css", false );
}
add_action( 'wp_enqueue_scripts', 'superlist_sitef_styles_and_scripts' );

