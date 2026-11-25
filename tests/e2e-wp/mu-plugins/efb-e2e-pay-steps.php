<?php
/**
 * Plugin Name: EFB e2e pay-steps switch
 * Description: Test site only (tests/e2e-wp/ctl-pay-steps.php no-gateways 1): every payment gateway hidden from the
 * payment framework, as on a site whose gateway add-ons are all switched off (QA3-005).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
add_filter(
	'efb_payment_gateways',
	static function ( $all ) {
		return get_option( 'efb_e2e_no_gateways' ) ? array() : $all;
	},
	999
);
