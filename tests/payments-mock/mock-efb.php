<?php
/**
 * Not shipped. Registers the test gateway "efbmock" the way a gateway add-on's routes-efb.php does
 * (efb_register_payment_gateways), plus its fake provider (pre_http_request + a hosted checkout
 * page). Required by the e2e site's mu-plugin when the suite switches payments on.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( '\Emsfb\Payments\Gateway' ) ) {
	return; // core's payment framework is not loaded (older core): nothing to register
}
require_once __DIR__ . '/class-Emsfb-payment-mock.php';
\Emsfb\Payments\Mock_Provider::register();
add_action( 'efb_register_payment_gateways', static function ( $registry ) {
	$registry->register( new \Emsfb\Payments\Mock_Gateway() );
} );
