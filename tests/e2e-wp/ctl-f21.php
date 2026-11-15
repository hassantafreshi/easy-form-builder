<?php
/**
 * Site control for the F21 scenarios (tests/e2e-wp/scenario-f21.js, the Pro renewal reminder):
 *
 *   wp eval-file tests/e2e-wp/ctl-f21.php <command> [args]
 *
 *   mu                       copy mu-plugins/efb-e2e-f21.php into the site's mu-plugins
 *   pro                      the site on Pro with a placeholder activation code made for this host (never a real key)
 *   license <days> [k=v&…]   the stand-in licence server: the subscription ends <days> from now (fractions allowed);
 *                            keys: state (active|deactive), auto_renew (0|1), lapse_effect, mode (v1|missing|garbled|down)
 *   advance <days>           the server's clock moves <days> ahead (the same end date): time passing
 *   set k=v&…                change the stand-in's keys only (state, mode, …)
 *   check                    the licence check now (as the daily event emsfb_revalidate_license does, forced)
 *   cron                     the event emsfb_revalidate_license as it runs (not forced: the 20 h limit applies)
 *   state                    Renewal_Reminder::state(), capability(), the stored options, emsfb_pro
 *   mails [clear]            the e-mails the recording mailer saw (clear also switches it on)
 *   calls [clear]            the licence requests the stand-in saw (caps, whether a key was sent; never the key)
 *   recheck-reset            forget the 5-minute limit of "Check again"
 *   reset                    forget everything F21 stored (field, marks, locks, cause, dismissals), the stand-in off
 *   code                     the placeholder code (to check that it is never shown)
 *   off                      the stand-in and the recording mailer off; back to Free Plus
 *
 * Every command prints one JSON document on its last line. Local test site only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through WP-CLI\n" );
	exit( 1 );
}

$f21_args = isset( $args ) && is_array( $args ) ? array_values( $args ) : array();
$f21_cmd  = isset( $f21_args[0] ) ? $f21_args[0] : 'state';
$f21_rest = array_slice( $f21_args, 1 );

function efb_f21_out( $d ) {
	echo "\n" . wp_json_encode( $d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ) . "\n";
	exit( 0 );
}
function efb_f21_code() {
	$host = wp_parse_url( get_option( 'siteurl' ), PHP_URL_HOST );
	return md5( (string) $host ) . '@E2EF21PLACEHOLDER';
}
function efb_f21_kv( $s, array $into ) {
	foreach ( array_filter( explode( '&', (string) $s ) ) as $kv ) {
		list( $k, $v ) = array_pad( explode( '=', $kv, 2 ), 2, '' );
		$into[ $k ] = 'auto_renew' === $k ? (bool) (int) $v : urldecode( $v );
	}
	return $into;
}

global $wpdb;

switch ( $f21_cmd ) {
	case 'mu':
		$dir = WPMU_PLUGIN_DIR;
		if ( ! is_dir( $dir ) ) wp_mkdir_p( $dir );
		$src = __DIR__ . '/mu-plugins/efb-e2e-f21.php';
		$dst = $dir . '/efb-e2e-f21.php';
		$same = is_readable( $dst ) && md5_file( $dst ) === md5_file( $src );
		$ok = $same || copy( $src, $dst );
		if ( ! $same ) sleep( 3 ); // the server's opcache
		efb_f21_out( array( 'ok' => $ok, 'changed' => ! $same ) );
		break;

	case 'pro':
		update_option( 'emsfb_pro', 1 );
		update_option( 'emsfb_pro_activeCode', efb_f21_code() );
		update_option( 'emsfb_pro_ac_date', gmdate( 'Y-m-d H:i:s' ) );
		$st = get_setting_Emsfb( 'decoded' );
		if ( is_object( $st ) ) {
			$st->activeCode = efb_f21_code();
			$st->package_type = 1;
			get_efbFunction()->set_setting_Emsfb( wp_json_encode( $st, JSON_UNESCAPED_UNICODE ), isset( $st->emailSupporter ) ? $st->emailSupporter : '' );
		}
		efb_f21_out( array( 'emsfb_pro' => (int) get_option( 'emsfb_pro' ) ) );
		break;

	case 'license':
		$days = isset( $f21_rest[0] ) ? (float) $f21_rest[0] : 7;
		$cfg = efb_f21_kv( isset( $f21_rest[1] ) ? $f21_rest[1] : '', array( 'mode' => 'v1', 'state' => 'active', 'auto_renew' => false, 'lapse_effect' => 'pause_pro', 'shift' => 0 ) );
		$cfg['expires_at'] = time() + (int) round( $days * DAY_IN_SECONDS );
		update_option( 'efb_e2e_f21_license', $cfg, false );
		efb_f21_out( $cfg );
		break;

	case 'advance':
		$cfg = get_option( 'efb_e2e_f21_license', array() );
		$cfg['shift'] = (int) ( isset( $cfg['shift'] ) ? $cfg['shift'] : 0 ) + (int) round( (float) ( isset( $f21_rest[0] ) ? $f21_rest[0] : 1 ) * DAY_IN_SECONDS );
		update_option( 'efb_e2e_f21_license', $cfg, false );
		efb_f21_out( $cfg );
		break;

	case 'set':
		$cfg = efb_f21_kv( isset( $f21_rest[0] ) ? $f21_rest[0] : '', (array) get_option( 'efb_e2e_f21_license', array() ) );
		update_option( 'efb_e2e_f21_license', $cfg, false );
		efb_f21_out( $cfg );
		break;

	case 'check':
		delete_option( 'emsfb_pro_ac_date' ); // forced: the last check is "long ago"
		do_action( 'emsfb_revalidate_license' );
		efb_f21_out( array( 'emsfb_pro' => (int) get_option( 'emsfb_pro' ), 'stage' => class_exists( '\Emsfb\Renewal_Reminder' ) ? \Emsfb\Renewal_Reminder::state()['stage'] : null ) );
		break;

	case 'cron':
		do_action( 'emsfb_revalidate_license' );
		efb_f21_out( array( 'emsfb_pro' => (int) get_option( 'emsfb_pro' ) ) );
		break;

	case 'state':
		wp_set_current_user( 1 ); // what an administrator's screen gets (efb_var.renewal)
		$locks = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'emsfb_renew_lock_' ) . '%' ) );
		$out = array(
			'emsfb_pro' => (int) get_option( 'emsfb_pro', 2 ),
			'info'      => get_option( 'emsfb_license_info', null ),
			'sent'      => get_option( 'emsfb_renew_reminder', null ),
			'locks'     => array_values( (array) $locks ),
			'cause'     => get_option( 'emsfb_license_lapse_cause', null ),
			'locale'    => get_locale(),
		);
		if ( class_exists( '\Emsfb\Renewal_Reminder' ) ) {
			$out['state'] = \Emsfb\Renewal_Reminder::state();
			$out['capability'] = \Emsfb\Renewal_Reminder::capability();
			$out['renewal_vars'] = \Emsfb\Renewal_Reminder::localize_vars( array() );
		}
		efb_f21_out( $out );
		break;

	case 'mails':
		if ( isset( $f21_rest[0] ) && 'clear' === $f21_rest[0] ) {
			update_option( 'efb_e2e_f21_mails', array(), false );
			update_option( 'efb_e2e_f21_mailer', 1, false );
			update_option( 'efb_e2e_capture_mail', 0, false ); // the suite's own capture would stop wp_mail() early
		}
		efb_f21_out( get_option( 'efb_e2e_f21_mails', array() ) );
		break;

	case 'calls':
		if ( isset( $f21_rest[0] ) && 'clear' === $f21_rest[0] ) update_option( 'efb_e2e_f21_calls', array(), false );
		efb_f21_out( get_option( 'efb_e2e_f21_calls', array() ) );
		break;

	case 'recheck-reset':
		delete_transient( 'emsfb_license_recheck' );
		efb_f21_out( array( 'ok' => true ) );
		break;

	case 'reset':
		if ( class_exists( '\Emsfb\Renewal_Reminder' ) ) \Emsfb\Renewal_Reminder::forget();
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'emsfb_renew_lock_' ) . '%' ) ) as $o ) delete_option( $o );
		delete_metadata( 'user', 0, 'efb_renew_notice', '', true );
		delete_transient( 'emsfb_license_recheck' );
		delete_option( 'efb_e2e_f21_license' );
		update_option( 'efb_e2e_f21_mails', array(), false );
		update_option( 'efb_e2e_f21_calls', array(), false );
		// The settings save keeps a key that is absent (its wipe guard), so "back to the default" is '1'.
		$st = get_setting_Emsfb( 'decoded' );
		if ( is_object( $st ) && isset( $st->renewMail ) && '1' !== $st->renewMail ) {
			$st->renewMail = '1';
			get_efbFunction()->set_setting_Emsfb( wp_json_encode( $st, JSON_UNESCAPED_UNICODE ), isset( $st->emailSupporter ) ? $st->emailSupporter : '' );
		}
		efb_f21_out( array( 'ok' => true ) );
		break;

	case 'code':
		efb_f21_out( array( 'code' => efb_f21_code(), 'secret' => 'E2EF21PLACEHOLDER' ) );
		break;

	case 'off':
		delete_option( 'efb_e2e_f21_license' );
		delete_option( 'efb_e2e_f21_mailer' );
		if ( class_exists( '\Emsfb\Renewal_Reminder' ) ) \Emsfb\Renewal_Reminder::forget();
		delete_option( 'emsfb_pro_activeCode' );
		delete_option( 'emsfb_pro_ac_date' );
		update_option( 'emsfb_pro', 3 );
		$st = get_setting_Emsfb( 'decoded' );
		if ( is_object( $st ) ) {
			$st->activeCode = '';
			$st->package_type = 3;
			if ( isset( $st->renewMail ) ) $st->renewMail = '1';
			get_efbFunction()->set_setting_Emsfb( wp_json_encode( $st, JSON_UNESCAPED_UNICODE ), isset( $st->emailSupporter ) ? $st->emailSupporter : '' );
		}
		efb_f21_out( array( 'emsfb_pro' => (int) get_option( 'emsfb_pro' ) ) );
		break;

	default:
		efb_f21_out( array( 'error' => 'unknown command ' . $f21_cmd ) );
}
