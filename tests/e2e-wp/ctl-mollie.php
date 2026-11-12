<?php
/**
 * Site control for scenario "mollie" of tests/e2e-wp/run.js (PAY01, the Mollie add-on):
 *
 *   wp eval-file tests/e2e-wp/ctl-mollie.php <command> [args]
 *
 *   fake <0|1>              answer api.mollie.com with the fake (mu-plugin efb-e2e-mollie.php)
 *                           and reset its state; the checkout page is the site's own fake page
 *   keys [test|live]        store fake Test and Live keys of the right shape (built here at run
 *                           time, never a real key) through the gateway's schema, and the mode
 *   state                   the fake's payments (id, status, amount, method, mode, refunded) and
 *                           its request log (method, path, key mode, Idempotency-Key, body)
 *   set <tr_id> <status>    the fake's state of a payment (paid, failed, canceled, expired, pending)
 *   token <uid>             the webhook token t of a payment (what the webhook URL carries)
 *   clear-settings          remove the gateway's settings option
 *
 * Every command prints one JSON document on its last line. Local test site only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through WP-CLI\n" );
	exit( 1 );
}

$efb_m_args = isset( $args ) && is_array( $args ) ? array_values( $args ) : array();
$efb_m_cmd  = isset( $efb_m_args[0] ) ? $efb_m_args[0] : 'state';
$efb_m_rest = array_slice( $efb_m_args, 1 );

function efb_m_out( $d ) {
	echo "\n" . wp_json_encode( $d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ) . "\n";
	exit( 0 );
}

require_once WP_PLUGIN_DIR . '/easy-form-builder/tests/mollie-fake-provider.php';
if ( class_exists( '\Emsfb\Payments\Registry' ) ) \Emsfb\Payments\Registry::all(); // registers the add-on's gateway when AdnMOL is on

switch ( $efb_m_cmd ) {
	case 'fake':
		$on = isset( $efb_m_rest[0] ) && '1' === $efb_m_rest[0];
		update_option( 'efb_e2e_mollie_fake', $on ? 1 : 0, false );
		Efb_Test_Mollie_Fake::reset( array( 'checkout_base' => rest_url( 'efb-e2e/v1/mollie-checkout' ) . '?x=1' ) );
		efb_m_out( array( 'ok' => true, 'fake' => $on ) );
		break;

	case 'keys':
		if ( ! class_exists( '\Emsfb\Payments\Mollie_Gateway' ) ) efb_m_out( array( 'error' => 'the Mollie add-on is not loaded (addon-flag AdnMOL 1 first)' ) );
		$gw = new \Emsfb\Payments\Mollie_Gateway();
		$st = \Emsfb\Payments\Settings::load( 'mollie' );
		$refused = $st->apply( $gw->settings_schema(), array( 'test_api_key' => 'test_' . str_repeat( 'Fake', 6 ), 'live_api_key' => 'live_' . str_repeat( 'Fake', 6 ) ), isset( $efb_m_rest[0] ) && 'live' === $efb_m_rest[0] ? 'live' : 'test' );
		efb_m_out( array( 'ok' => ! $refused, 'mode' => \Emsfb\Payments\Settings::load( 'mollie' )->mode(), 'key_tail' => 'Fake' ) );
		break;

	case 'state':
		$s = Efb_Test_Mollie_Fake::state();
		$pay = array();
		foreach ( $s['payments'] as $id => $p ) {
			$pay[] = array( 'id' => $id, 'status' => $p['status'], 'mode' => $p['mode'], 'amount' => $p['amount'], 'method' => $p['request']['method'] ?? null, 'locale' => $p['request']['locale'] ?? null, 'webhookUrl' => $p['request']['webhookUrl'] ?? null, 'description' => $p['description'], 'metadata' => $p['metadata'], 'refunded' => $p['refunded'] ?? 0 );
		}
		efb_m_out( array( 'payments' => $pay, 'log' => $s['log'] ) );
		break;

	case 'set':
		efb_m_out( array( 'ok' => Efb_Test_Mollie_Fake::update( $efb_m_rest[0], array( 'status' => $efb_m_rest[1] ) ) ) );
		break;

	case 'token':
		efb_m_out( array( 't' => class_exists( '\Emsfb\Payments\Mollie_Gateway' ) ? \Emsfb\Payments\Mollie_Gateway::webhook_token( $efb_m_rest[0] ) : '' ) );
		break;

	case 'clear-settings':
		delete_option( 'emsfb_pgw_mollie' );
		efb_m_out( array( 'ok' => true ) );
		break;

	default:
		efb_m_out( array( 'error' => 'unknown command ' . $efb_m_cmd ) );
}
