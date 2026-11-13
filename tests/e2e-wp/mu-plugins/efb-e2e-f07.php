<?php
/**
 * Plugin Name: EFB e2e F07 helpers (local test site only)
 * Description: For tests/test-upload-multi-submit-http.php and the e2e scenarios "f07*", through the
 *              plugin's own filters only (what a site owner could do): (1) efb_upload_plan_ok opens every
 *              F07 part (several files, resizing, Media Library, e-mail attachments) while the option
 *              efb_e2e_f07_plan is set; on this site a Pro plan without a licence falls back to Free
 *              Plus, so e-mail attachments (Pro) are reached this way; (2) emsfb_upload_quota_limit and
 *              emsfb_upload_ip_quota_limit from efb_e2e_f07_quota / efb_e2e_f07_ip_quota, so a test reaches
 *              the quota with a few uploads; (3) emsfb_upload_require_token from efb_e2e_f07_require;
 *              (4) the e-mails' attachments (names and sizes) while efb_e2e_f07_mail is set
 *              (option efb_e2e_f07_mails). Copied by ctl.php f07-mu.
 */
add_filter(
	'efb_upload_plan_ok',
	static function ( $ok, $part ) {
		return get_option( 'efb_e2e_f07_plan' ) ? true : $ok;
	},
	10,
	2
);
add_filter(
	'emsfb_upload_quota_limit',
	static function ( $limit ) {
		$q = (int) get_option( 'efb_e2e_f07_quota', 0 );
		return $q > 0 ? $q : $limit;
	},
	99
);
add_filter(
	'emsfb_upload_ip_quota_limit',
	static function ( $limit ) {
		$q = (int) get_option( 'efb_e2e_f07_ip_quota', 0 );
		return $q > 0 ? $q : $limit;
	},
	99
);
add_filter(
	'emsfb_upload_require_token',
	static function ( $req ) {
		return get_option( 'efb_e2e_f07_require' ) ? true : $req;
	},
	99
);
add_filter(
	'pre_wp_mail',
	static function ( $pre, $atts ) {
		if ( ! get_option( 'efb_e2e_f07_mail' ) ) {
			return $pre;
		}
		$log = get_option( 'efb_e2e_f07_mails', array() );
		$log = is_array( $log ) ? $log : array();
		$att = array();
		foreach ( (array) ( isset( $atts['attachments'] ) ? $atts['attachments'] : array() ) as $a ) {
			$att[] = array( 'name' => basename( (string) $a ), 'size' => is_file( $a ) ? (int) filesize( $a ) : -1 );
		}
		$log[] = array( 'to' => $atts['to'], 'subject' => (string) $atts['subject'], 'attachments' => $att, 'message' => substr( (string) $atts['message'], 0, 30000 ) );
		update_option( 'efb_e2e_f07_mails', array_slice( $log, -20 ), false );
		return true;
	},
	4,
	2
);
