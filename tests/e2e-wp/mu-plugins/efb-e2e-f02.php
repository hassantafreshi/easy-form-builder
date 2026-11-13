<?php
/**
 * Plugin Name: EFB e2e F02 helpers (local test site only)
 * Description: For the e2e scenarios "f02*" (one-time codes). Copied by `ctl.php f02-mu`.
 *  (1) A stand-in of the WP SMS plugin while the option efb_e2e_wpsms is set: wp_sms_send() records
 *      each message in efb_e2e_sms instead of reaching a gateway (no network), and answers true, a
 *      WP_Error or false (option efb_e2e_wpsms_mode = ok | error | false) like the real plugin
 *      (https://wp-sms-pro.com/resources/wp_sms_send/). The code is read back from the recorded text.
 *  (2) SMS codes are Pro; on this test site a Pro plan without a licence falls back to Free Plus, so the
 *      scenarios open the SMS code through the plugin's own filter efb_verify_plan (what a site owner
 *      could do) while the option efb_e2e_f02_plan is set.
 */
if ( get_option( 'efb_e2e_wpsms' ) && ! function_exists( 'wp_sms_send' ) ) {
	function wp_sms_send( $to, $msg, $is_flash = false, $from = null, $media_urls = array() ) {
		$log   = get_option( 'efb_e2e_sms', array() );
		$log   = is_array( $log ) ? $log : array();
		$log[] = array( 'to' => $to, 'msg' => (string) $msg, 'time' => time() );
		update_option( 'efb_e2e_sms', array_slice( $log, -30 ), false );
		$mode = get_option( 'efb_e2e_wpsms_mode', 'ok' );
		if ( 'error' === $mode ) {
			return new WP_Error( 'gateway', 'The gateway refused the request (credit 0, key 1234).' );
		}
		if ( 'false' === $mode ) {
			return false;
		}
		return true;
	}
}
add_filter(
	'efb_verify_plan',
	static function ( $need, $part ) {
		if ( 'sms' === $part && get_option( 'efb_e2e_f02_plan' ) ) {
			return 'free_plus';
		}
		return $need;
	},
	10,
	2
);
