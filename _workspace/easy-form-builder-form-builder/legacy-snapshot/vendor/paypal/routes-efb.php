<?php
/**
 * PayPal REST route registration for Easy Form Builder.
 *
 * Loaded conditionally by class-Emsfb.php only when the PayPal addon (AdnPAP) is active.
 * Hooks into 'efb_register_payment_rest_routes' which fires inside rest_api_init.
 *
 * @package Emsfb
 * @since   4.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'efb_register_payment_rest_routes', function ( $public ) {

	$perm = [ $public, 'check_nonce_permission_efb' ];

	// Create PayPal order or subscription plan
	register_rest_route( 'Emsfb/v1', 'forms/payment/paypal/card/add', [
		'methods'             => 'POST',
		'callback'            => [ $public, 'pay_paypal_sub_Emsfb_api' ],
		'permission_callback' => $perm,
	] );

	// Capture an approved PayPal order
	register_rest_route( 'Emsfb/v1', 'forms/payment/paypal/capture', [
		'methods'             => 'POST',
		'callback'            => [ $public, 'pay_paypal_capture_Emsfb_api' ],
		'permission_callback' => $perm,
	] );

	// Activate PayPal subscription after buyer approval
	register_rest_route( 'Emsfb/v1', 'forms/payment/paypal/subscription/activate', [
		'methods'             => 'POST',
		'callback'            => [ $public, 'pay_paypal_subscription_activate_Emsfb_api' ],
		'permission_callback' => $perm,
	] );

} );
