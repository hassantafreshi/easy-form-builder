<?php
/**
 * Site control for scenario "tappay" of tests/e2e-wp/run.js (PAY03, the Tap Payments add-on):
 *
 *   wp eval-file tests/e2e-wp/ctl-tappay.php <command> [args]
 *
 *   fake <0|1>              answer api.tap.company with the fake (mu-plugin efb-e2e-tappay.php)
 *                           and reset its state
 *   keys [test|live]        store fake Test and Live secret keys (built at run time, never a real
 *                           key) through the gateway's schema, and the mode
 *   state                   the fake's charges (id, status, amount as sent, currency, source,
 *                           customer, reference) and refunds, and its request log (no key)
 *   set <chg_id> <STATUS> [amount]   Tap's side of a charge (CAPTURED, DECLINED, …)
 *   webhook <chg_id> [key-mode]      the webhook Tap would post: JSON body + hashstring header,
 *                           signed with the fake key of that mode (default: test)
 *   clear-settings          remove the gateway's settings option
 *
 * Every command prints one JSON document on its last line. Local test site only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through WP-CLI\n" );
	exit( 1 );
}

$efb_t_args = isset( $args ) && is_array( $args ) ? array_values( $args ) : array();
$efb_t_cmd  = isset( $efb_t_args[0] ) ? $efb_t_args[0] : 'state';
$efb_t_rest = array_slice( $efb_t_args, 1 );

function efb_t_out( $d ) {
	echo "\n" . wp_json_encode( $d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ) . "\n";
	exit( 0 );
}

require_once WP_PLUGIN_DIR . '/easy-form-builder/tests/tappay-fake-provider.php';
if ( class_exists( '\Emsfb\Payments\Registry' ) ) \Emsfb\Payments\Registry::all(); // registers the add-on's gateway when AdnTAP is on

switch ( $efb_t_cmd ) {
	case 'fake':
		$on = isset( $efb_t_rest[0] ) && '1' === $efb_t_rest[0];
		update_option( 'efb_e2e_tap_fake', $on ? 1 : 0, false );
		Efb_Test_Tap_Fake::reset();
		efb_t_out( array( 'ok' => true, 'fake' => $on ) );
		break;

	case 'keys':
		if ( ! class_exists( '\Emsfb\Tappay\Tap_Gateway' ) ) efb_t_out( array( 'error' => 'the Tap add-on is not loaded (addon-flag AdnTAP 1 first)' ) );
		$gw = new \Emsfb\Tappay\Tap_Gateway();
		$st = \Emsfb\Payments\Settings::load( 'tappay' );
		$refused = $st->apply( $gw->settings_schema(), array( 'secret_key__test' => Efb_Test_Tap_Fake::key( 'test' ), 'secret_key__live' => Efb_Test_Tap_Fake::key( 'live' ) ), isset( $efb_t_rest[0] ) && 'live' === $efb_t_rest[0] ? 'live' : 'test' );
		efb_t_out( array( 'ok' => ! $refused, 'mode' => \Emsfb\Payments\Settings::load( 'tappay' )->mode(), 'key_tail' => substr( Efb_Test_Tap_Fake::key( 'test' ), -4 ) ) );
		break;

	case 'state':
		$s = Efb_Test_Tap_Fake::state();
		$ch = array();
		foreach ( $s['charges'] as $id => $c ) {
			$ch[] = array( 'id' => $id, 'status' => $c['status'], 'amount' => $c['_raw_amount'], 'currency' => $c['currency'], 'source' => $c['source']['id'], 'customer' => $c['customer'], 'reference' => $c['reference'], 'metadata' => $c['metadata'], 'live_mode' => $c['live_mode'], 'post' => $c['post']['url'] );
		}
		$rf = array();
		foreach ( $s['refunds'] as $id => $r ) $rf[] = array( 'id' => $id, 'charge_id' => $r['charge_id'], 'amount' => $r['_raw_amount'], 'currency' => $r['currency'], 'reference' => $r['reference'] );
		$log = array();
		foreach ( $s['log'] as $l ) $log[] = array( 'method' => $l['method'], 'path' => $l['path'], 'key_mode' => $l['key_mode'] );
		efb_t_out( array( 'charges' => $ch, 'refunds' => $rf, 'log' => $log ) );
		break;

	case 'set':
		efb_t_out( array( 'ok' => Efb_Test_Tap_Fake::set( $efb_t_rest[0], $efb_t_rest[1], isset( $efb_t_rest[2] ) ? $efb_t_rest[2] : null ) ) );
		break;

	case 'webhook':
		efb_t_out( Efb_Test_Tap_Fake::webhook( $efb_t_rest[0], Efb_Test_Tap_Fake::key( isset( $efb_t_rest[1] ) ? $efb_t_rest[1] : 'test' ) ) );
		break;

	case 'clear-settings':
		delete_option( 'emsfb_pgw_tappay' );
		efb_t_out( array( 'ok' => true ) );
		break;

	default:
		efb_t_out( array( 'error' => 'unknown command ' . $efb_t_cmd ) );
}
