<?php
/**
 * Site control for the FX-ADMIN scenarios (tests/e2e-wp/scenario-qa1.js):
 *
 *   wp eval-file tests/e2e-wp/ctl-qa1.php <command> [args]
 *
 *   mail-old         keeps the stored e-mail template and plugin version aside (option efb_e2e_qa1_mail), then names an
 *                    old host for this plugin's address in the template (as after a site move) and sets the version to
 *                    4.3.1, so the next admin load runs the update
 *   mail-state       whether the stored template names old.example, has {efb_plugin_url}, names this site's plugin address
 *   mail-restore     puts the kept template and version back
 *
 * Every command prints one JSON document on its last line. Local test site only.
 */
if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through WP-CLI\n" );
	exit( 1 );
}
$q1_args = isset( $args ) && is_array( $args ) ? array_values( $args ) : array();
$q1_cmd  = isset( $q1_args[0] ) ? $q1_args[0] : 'mail-state';
function efb_q1_out( $d ) {
	echo "\n" . wp_json_encode( $d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
	exit( 0 );
}
$q1_s = get_setting_Emsfb( 'decoded' );
switch ( $q1_cmd ) {
	case 'mail-old':
		update_option( 'efb_e2e_qa1_mail', array( 't' => $q1_s->emailTemp, 'v' => get_option( 'emsfb_version' ) ), false );
		$q1_s->emailTemp = str_replace( '{efb_plugin_url}', 'http:@efb@old.example@efb@wp-content@efb@plugins@efb@easy-form-builder@efb@', \Emsfb::email_template_placeholder_efb( $q1_s->emailTemp ) );
		get_efbFunction()->set_setting_Emsfb( $q1_s, $q1_s->emailSupporter );
		update_option( 'emsfb_version', '4.3.1' );
		efb_q1_out( array( 'ok' => true ) );
	case 'mail-state':
		efb_q1_out( array( 'old' => strpos( $q1_s->emailTemp, 'old.example' ) !== false, 'placeholder' => strpos( $q1_s->emailTemp, '{efb_plugin_url}' ) !== false, 'current' => strpos( $q1_s->emailTemp, str_replace( '/', '@efb@', preg_replace( '#^https?://#', '', EMSFB_PLUGIN_URL ) ) ) !== false ) );
	case 'mail-restore':
		$k = get_option( 'efb_e2e_qa1_mail' );
		if ( is_array( $k ) ) {
			$q1_s->emailTemp = $k['t'];
			get_efbFunction()->set_setting_Emsfb( $q1_s, $q1_s->emailSupporter );
			update_option( 'emsfb_version', $k['v'] );
			delete_option( 'efb_e2e_qa1_mail' );
		}
		efb_q1_out( array( 'ok' => true ) );
}
efb_q1_out( array( 'error' => 'unknown command' ) );
