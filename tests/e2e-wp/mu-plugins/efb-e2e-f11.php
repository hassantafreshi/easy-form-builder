<?php
/**
 * Plugin Name: EFB e2e F11 helpers (local test site only)
 * Description: For tests/test-form-limits-http.php, tests/test-drafts-http.php and the e2e scenario "f11":
 *              (1) while the option efb_e2e_f11_fail_insert is set, the next INSERT into the entries
 *              table fails (a database error injected through the core "query" filter), to prove that
 *              a failed store gives the places of the form limits back; (2) while e-mails are captured
 *              (efb_e2e_capture_mail), their bodies are kept too (efb_e2e_mail_bodies), so a test can
 *              open the resume link of a "save and continue" e-mail. Copied by those tests.
 */
add_filter(
	'query',
	static function ( $q ) {
		global $wpdb;
		if ( ! is_string( $q ) || ! get_option( 'efb_e2e_f11_fail_insert' ) ) {
			return $q;
		}
		if ( preg_match( '/^\s*INSERT INTO `?' . preg_quote( $wpdb->prefix . 'emsfb_msg_', '/' ) . '`?\s/i', $q ) ) {
			return 'SELECT efb_e2e_injected_failure FROM efb_e2e_no_such_table';
		}
		return $q;
	}
);
add_filter(
	'pre_wp_mail',
	static function ( $pre, $atts ) {
		if ( get_option( 'efb_e2e_capture_mail' ) ) {
			$log   = get_option( 'efb_e2e_mail_bodies', array() );
			$log   = is_array( $log ) ? $log : array();
			$log[] = array( 'to' => $atts['to'], 'subject' => (string) $atts['subject'], 'message' => substr( (string) $atts['message'], 0, 20000 ) );
			update_option( 'efb_e2e_mail_bodies', array_slice( $log, -20 ), false );
		}
		return $pre;
	},
	5,
	2
);
