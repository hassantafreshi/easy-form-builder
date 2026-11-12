<?php
/**
 * Site control for scenario "paytabs" of tests/e2e-wp/run.js (PAY05, the PayTabs add-on):
 *
 *   wp eval-file tests/e2e-wp/ctl-paytabs.php <command> [args]
 *
 *   install                 copy the mu-plugin efb-e2e-paytabs.php into the site
 *   fake <0|1>              answer the PayTabs hosts with the fake and reset its state
 *   keys [test|live] [framed|-] [methods]  store the fake Test (Saudi Arabia, 10001) and Live
 *                           (UAE, 10002) region, profile and server key (built at run time, never a
 *                           real key) through the gateway's schema, the mode, framed on or off and
 *                           the owner's method list (comma separated, default: empty = all)
 *   state                   the fake's transactions (ref, status, amount as sent, currency, request
 *                           fields without customer data) and its request log (no key)
 *   set <tran_ref> <A|D|C…> [amount]   PayTabs' side of a transaction
 *   callback <tran_ref> [key-mode]     the callback PayTabs would post: JSON body + Signature,
 *                           signed with the fake key of that mode (default: test)
 *   clear-settings          remove the gateway's settings option
 *
 * Every command prints one JSON document on its last line. Local test site only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through WP-CLI\n" );
	exit( 1 );
}

$efb_p_args = isset( $args ) && is_array( $args ) ? array_values( $args ) : array();
$efb_p_cmd  = isset( $efb_p_args[0] ) ? $efb_p_args[0] : 'state';
$efb_p_rest = array_slice( $efb_p_args, 1 );

function efb_p_out( $d ) {
	echo "\n" . wp_json_encode( $d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ) . "\n";
	exit( 0 );
}

require_once WP_PLUGIN_DIR . '/easy-form-builder/tests/paytabs-fake-provider.php';
if ( class_exists( '\Emsfb\Payments\Registry' ) ) \Emsfb\Payments\Registry::all(); // registers the add-on's gateway when AdnPTB is on

switch ( $efb_p_cmd ) {
	case 'install':
		$dir = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
		if ( ! is_dir( $dir ) ) wp_mkdir_p( $dir );
		$ok = copy( WP_PLUGIN_DIR . '/easy-form-builder/tests/e2e-wp/mu-plugins/efb-e2e-paytabs.php', $dir . '/efb-e2e-paytabs.php' );
		efb_p_out( array( 'ok' => $ok ) );
		break;

	case 'fake':
		$on = isset( $efb_p_rest[0] ) && '1' === $efb_p_rest[0];
		update_option( 'efb_e2e_ptb_fake', $on ? 1 : 0, false );
		Efb_Test_Paytabs_Fake::reset();
		efb_p_out( array( 'ok' => true, 'fake' => $on ) );
		break;

	case 'keys':
		if ( ! class_exists( '\Emsfb\Paytabs\Paytabs_Gateway' ) ) efb_p_out( array( 'error' => 'the PayTabs add-on is not loaded (addon-flag AdnPTB 1 first)' ) );
		$gw = new \Emsfb\Paytabs\Paytabs_Gateway();
		$st = \Emsfb\Payments\Settings::load( 'paytabs' );
		$in = array( 'framed' => isset( $efb_p_rest[1] ) && 'framed' === $efb_p_rest[1] ? '1' : '', 'methods' => isset( $efb_p_rest[2] ) ? (string) $efb_p_rest[2] : '' );
		foreach ( array( 'test', 'live' ) as $m ) {
			$in[ 'region__' . $m ] = Efb_Test_Paytabs_Fake::region( $m );
			$in[ 'profile_id__' . $m ] = Efb_Test_Paytabs_Fake::profile( $m );
			$in[ 'server_key__' . $m ] = Efb_Test_Paytabs_Fake::key( $m );
		}
		$refused = $st->apply( $gw->settings_schema(), $in, isset( $efb_p_rest[0] ) && 'live' === $efb_p_rest[0] ? 'live' : 'test' );
		$st = \Emsfb\Payments\Settings::load( 'paytabs' );
		efb_p_out( array( 'ok' => ! $refused, 'refused' => $refused, 'mode' => $st->mode(), 'framed' => $st->get( 'framed' ), 'key_tail' => substr( Efb_Test_Paytabs_Fake::key( 'test' ), -4 ) ) );
		break;

	case 'state':
		$s = Efb_Test_Paytabs_Fake::state();
		$tx = array();
		foreach ( $s['tx'] as $ref => $t ) {
			$q = isset( $t['_request'] ) ? $t['_request'] : array();
			unset( $q['customer_details'] );
			$tx[] = array( 'ref' => $ref, 'status' => $t['_status'], 'mode' => $t['_mode'], 'amount' => $t['cart_amount'], 'currency' => $t['cart_currency'], 'cart_id' => $t['cart_id'], 'refund' => ! empty( $t['_refund'] ), 'previous' => isset( $t['previous_tran_ref'] ) ? $t['previous_tran_ref'] : '', 'request' => $q, 'customer' => isset( $t['_request']['customer_details'] ) ? array_keys( $t['_request']['customer_details'] ) : array() );
		}
		$log = array();
		foreach ( $s['log'] as $l ) {
			$b = json_decode( $l['raw'], true );
			$log[] = array( 'path' => $l['path'], 'host' => $l['host'], 'key_mode' => $l['key_mode'], 'tran_type' => is_array( $b ) && isset( $b['tran_type'] ) ? $b['tran_type'] : '', 'tran_ref' => is_array( $b ) && isset( $b['tran_ref'] ) ? $b['tran_ref'] : '', 'amount' => preg_match( '/"cart_amount":([0-9.]+)/', $l['raw'], $m ) ? $m[1] : '' );
		}
		efb_p_out( array( 'tx' => $tx, 'log' => $log ) );
		break;

	case 'set':
		efb_p_out( array( 'ok' => Efb_Test_Paytabs_Fake::set( $efb_p_rest[0], $efb_p_rest[1], isset( $efb_p_rest[2] ) ? $efb_p_rest[2] : null ) ) );
		break;

	case 'callback':
		efb_p_out( Efb_Test_Paytabs_Fake::callback( $efb_p_rest[0], Efb_Test_Paytabs_Fake::key( isset( $efb_p_rest[1] ) ? $efb_p_rest[1] : 'test' ) ) );
		break;

	case 'clear-settings':
		delete_option( 'emsfb_pgw_paytabs' );
		efb_p_out( array( 'ok' => true ) );
		break;

	default:
		efb_p_out( array( 'error' => 'unknown command ' . $efb_p_cmd ) );
}
