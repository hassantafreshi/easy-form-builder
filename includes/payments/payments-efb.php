<?php
/**
 * PAY00: loads the payment framework (docs/payments/PAY00-payment-framework.fa.md) on every
 * request. The classes are small and only do work when a gateway is registered.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'EMSFB_PAYMENTS_API' ) ) {
	define( 'EMSFB_PAYMENTS_API', 1 );
}

foreach ( array(
	'class-Emsfb-payment-http.php',
	'class-Emsfb-payment-store.php',
	'class-Emsfb-payment-money.php',
	'class-Emsfb-payment-secrets.php',
	'class-Emsfb-payment-gateway.php',
	'class-Emsfb-payment-registry.php',
	'payment-field-functions-efb.php',
	'class-Emsfb-payment-pricing.php',
	'class-Emsfb-payment-service.php',
	'class-Emsfb-payment-frontend.php',
) as $efb_pay_file ) {
	require_once __DIR__ . '/' . $efb_pay_file;
}
unset( $efb_pay_file );

if ( is_admin() && is_readable( __DIR__ . '/class-Emsfb-payment-admin.php' ) ) {
	require_once __DIR__ . '/class-Emsfb-payment-admin.php';
	\Emsfb\Payments\Admin::register();
}

// The test gateway: tests and the e2e site only (define EFB_PAYMENTS_MOCK in wp-config.php or a mu-plugin).
if ( defined( 'EFB_PAYMENTS_MOCK' ) && EFB_PAYMENTS_MOCK ) {
	require_once __DIR__ . '/gateways/class-Emsfb-payment-mock.php';
	\Emsfb\Payments\Mock_Provider::register();
	add_action( 'efb_register_payment_gateways', static function ( $registry ) {
		$registry->register( new \Emsfb\Payments\Mock_Gateway() );
	} );
}

add_action( 'emsfb_pmt_reconcile', array( '\Emsfb\Payments\Service', 'reconcile' ) );
add_action( 'emsfb_pmt_finalize', array( '\Emsfb\Payments\Service', 'finalize_by_id' ) );
// Back-up run of the reconcile job, once a day, also when no single event is queued.
add_action( 'emsfb_daily_maintenance', array( '\Emsfb\Payments\Service', 'reconcile' ) );
// F13: entries deleted or anonymized → their payment rows are anonymized (amount and reference kept).
add_action( 'efb_entries_purged', array( '\Emsfb\Payments\Store', 'on_entries_purged' ), 10, 3 );
// Builder screens: efb_var.pay (gateways without secrets, default texts, panel labels).
add_filter( 'efb_admin_localize_vars', array( '\Emsfb\Payments\Frontend', 'localize_vars' ), 30, 2 );
