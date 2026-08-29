<?php
/**
 * PersiaPay (Zarinpal) REST route registration for Easy Form Builder.
 *
 * Loaded conditionally by class-Emsfb.php only when the PersiaPay addon (AdnPPF) is active.
 * Hooks into 'efb_register_payment_rest_routes' which fires inside rest_api_init.
 *
 * @package Emsfb
 * @since   4.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'efb_register_payment_rest_routes', function ( $public ) {

	register_rest_route( 'Emsfb/v1', 'forms/payment/persia/add', [
		'methods'             => 'POST',
		'callback'            => [ $public, 'pay_persia_sub_Emsfb_api' ],
		'permission_callback' => [ $public, 'check_nonce_permission_efb' ],
	] );

} );
