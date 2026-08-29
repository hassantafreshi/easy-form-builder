<?php
/**
 * Stripe REST route registration for Easy Form Builder.
 *
 * Loaded conditionally by class-Emsfb.php only when the Stripe addon (AdnSPF) is active.
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

	// Create Stripe PaymentIntent or Subscription
	register_rest_route( 'Emsfb/v1', 'forms/payment/stripe/card/add', [
		'methods'             => 'POST',
		'callback'            => [ $public, 'pay_stripe_sub_Emsfb_api' ],
		'permission_callback' => $perm,
	] );

	// Confirm a one-time Stripe payment (update status pending → completed)
	register_rest_route( 'Emsfb/v1', 'forms/payment/stripe/confirm', [
		'methods'             => 'POST',
		'callback'            => [ $public, 'pay_stripe_confirm_Emsfb_api' ],
		'permission_callback' => $perm,
	] );

	// Publishable-key fallback: pages served from a stale HTML cache may carry
	// paymentKey:"null"; the frontend re-asks for the current key here. The
	// publishable key is public by design (it is embedded in the page HTML),
	// so no nonce is required — a cached page would hold a stale nonce anyway.
	register_rest_route( 'Emsfb/v1', 'forms/payment/stripe/pkey', [
		'methods'             => 'GET',
		'callback'            => [ $public, 'get_stripe_public_key_Emsfb_api' ],
		'permission_callback' => '__return_true',
	] );

} );
