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
		// Only an INSERT into the entries table is looked at, and the flag is read once per request
		// with the filter guarded: get_option() runs a query itself, which would come back here.
		static $busy = false, $flag = null;
		if ( $busy || ! is_string( $q ) || stripos( $q, 'emsfb_msg_' ) === false || ! preg_match( '/^\s*INSERT/i', $q ) ) {
			return $q;
		}
		if ( null === $flag ) {
			$busy = true;
			$flag = (bool) get_option( 'efb_e2e_f11_fail_insert' );
			$busy = false;
		}
		if ( ! $flag ) {
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
/*
 * (3) Save-and-continue and partial answers are Pro. On this test site a Pro plan without a licence
 * falls back to Free Plus on the next page view, so the tests open the two parts through the plugin's
 * own filter efb_f11_plan (what a site owner could do) while the option efb_e2e_f11_plan is set.
 */
add_filter(
	'efb_f11_plan',
	static function ( $need, $part ) {
		if ( in_array( $part, array( 'drafts', 'partials' ), true ) && get_option( 'efb_e2e_f11_plan' ) ) {
			return 'free';
		}
		return $need;
	},
	10,
	2
);
/*
 * (4) A form in a widget, footer or popup leaves the page cacheable (Cache_Compat). The e2e scenario
 * shows such a page by switching the bypass off through the plugin's own filter emsfb_cache_bypass
 * while the option efb_e2e_f11_cacheable is set: the gate then prints the card and the form, and
 * the browser asks forms/session/prepare which one to show.
 */
add_filter(
	'emsfb_cache_bypass',
	static function ( $bypass ) {
		return get_option( 'efb_e2e_f11_cacheable' ) ? false : $bypass;
	},
	99
);
/*
 * (4) F03: the per-minute limit of refused unique values (efb_unique_fail_per_minute) while the option
 * efb_e2e_unique_fail_limit is set, so a test can reach it without sending dozens of requests.
 */
add_filter(
	'efb_unique_fail_per_minute',
	static function ( $n ) {
		$v = (int) get_option( 'efb_e2e_unique_fail_limit', 0 );
		return $v > 0 ? $v : $n;
	}
);
