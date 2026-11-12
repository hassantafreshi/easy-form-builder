<?php
/**
 * Site control for scenario "xendit" of tests/e2e-wp/run.js (PAY07, the Xendit add-on):
 *
 *   wp eval-file tests/e2e-wp/ctl-xendit.php <command> [args]
 *
 *   install                 copy the mu-plugin efb-e2e-xendit.php into the site
 *   fake <0|1>              answer api.xendit.co with the fake (return URLs may be http on this
 *                           local site) and reset its state
 *   keys [test|live]        store fake Test and Live secret keys and webhook tokens (built at run time,
 *                           never real) through the gateway's schema, and the mode
 *   token [test|live]       the fake webhook verification token of a mode
 *   state                   the fake's sessions (id, status, amount as sent, currency, channels,
 *                           reference, metadata, customer reference, return URLs), refunds and its
 *                           request log (no key)
 *   set <ps_id> <STATUS> [CHANNEL]   Xendit's side of a session (COMPLETED, EXPIRED, CANCELED …)
 *   webhook <ps_id> <event>          the body Xendit would post (payment_session.completed / .expired)
 *   refund-webhook <rfd_id> <event>  the body of refund.succeeded / refund.failed
 *   clear-settings          remove the gateway's settings option
 *
 * Every command prints one JSON document on its last line. Local test site only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through WP-CLI\n" );
	exit( 1 );
}

$efb_x_args = isset( $args ) && is_array( $args ) ? array_values( $args ) : array();
$efb_x_cmd  = isset( $efb_x_args[0] ) ? $efb_x_args[0] : 'state';
$efb_x_rest = array_slice( $efb_x_args, 1 );

function efb_x_out( $d ) {
	echo "\n" . wp_json_encode( $d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ) . "\n";
	exit( 0 );
}

require_once dirname( __DIR__ ) . '/xendit-fake-provider.php';
if ( class_exists( '\Emsfb\Payments\Registry' ) ) \Emsfb\Payments\Registry::all(); // registers the add-on's gateway when AdnXND is on

switch ( $efb_x_cmd ) {
	case 'install':
		$dir = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
		if ( ! is_dir( $dir ) ) wp_mkdir_p( $dir );
		efb_x_out( array( 'ok' => copy( __DIR__ . '/mu-plugins/efb-e2e-xendit.php', $dir . '/efb-e2e-xendit.php' ) ) );
		break;

	case 'fake':
		$on = isset( $efb_x_rest[0] ) && '1' === $efb_x_rest[0];
		update_option( 'efb_e2e_xendit_fake', $on ? 1 : 0, false );
		Efb_Test_Xendit_Fake::reset();
		if ( $on ) Efb_Test_Xendit_Fake::set_opt( 'allow_http', true );
		efb_x_out( array( 'ok' => true, 'fake' => $on ) );
		break;

	case 'keys':
		$g = class_exists( '\Emsfb\Payments\Registry' ) ? \Emsfb\Payments\Registry::get( 'xendit' ) : null;
		if ( ! $g ) efb_x_out( array( 'error' => 'the Xendit add-on is not loaded (addon-flag AdnXND 1, vendor/xendit present)' ) );
		$mode = isset( $efb_x_rest[0] ) && 'live' === $efb_x_rest[0] ? 'live' : 'test';
		update_option( \Emsfb\Payments\Settings::option_name( 'xendit' ), array(), false );
		$refused = \Emsfb\Payments\Settings::load( 'xendit' )->apply( $g->settings_schema(), array(
			'secret_key__test'     => Efb_Test_Xendit_Fake::key( 'test' ),
			'secret_key__live'     => Efb_Test_Xendit_Fake::key( 'live' ),
			'callback_token__test' => Efb_Test_Xendit_Fake::token( 'test' ),
			'callback_token__live' => Efb_Test_Xendit_Fake::token( 'live' ),
		), $mode );
		efb_x_out( array( 'ok' => ! $refused, 'mode' => \Emsfb\Payments\Settings::load( 'xendit' )->mode(), 'key_tail' => substr( Efb_Test_Xendit_Fake::key( $mode ), -4 ) ) );
		break;

	case 'token':
		efb_x_out( array( 'token' => Efb_Test_Xendit_Fake::token( isset( $efb_x_rest[0] ) && 'live' === $efb_x_rest[0] ? 'live' : 'test' ) ) );
		break;

	case 'state':
		$s = Efb_Test_Xendit_Fake::state();
		$ss = array();
		foreach ( $s['sessions'] as $id => $x ) {
			$ss[] = array( 'id' => $id, 'status' => $x['status'], 'amount' => $x['_raw_amount'], 'currency' => $x['currency'], 'country' => $x['country'], 'channels' => $x['allowed_payment_channels'],
				'reference_id' => $x['reference_id'], 'metadata' => $x['metadata'], 'customer_ref' => isset( $x['_customer']['reference_id'] ) ? $x['_customer']['reference_id'] : '', 'locale' => $x['locale'],
				'success' => $x['success_return_url'], 'cancel' => $x['cancel_return_url'], 'mode' => $x['_mode'], 'payment_id' => isset( $x['payment_id'] ) ? $x['payment_id'] : null );
		}
		$rf = array();
		foreach ( $s['refunds'] as $id => $r ) $rf[] = array( 'id' => $id, 'payment_id' => $r['payment_id'], 'amount' => $r['_raw_amount'], 'currency' => $r['currency'], 'reference_id' => $r['reference_id'], 'status' => $r['status'], 'metadata' => $r['metadata'] );
		$log = array();
		foreach ( $s['log'] as $l ) $log[] = array( 'method' => $l['method'], 'path' => $l['path'], 'key_mode' => $l['key_mode'], 'api_version' => $l['api_version'] );
		efb_x_out( array( 'sessions' => $ss, 'refunds' => $rf, 'log' => $log ) );
		break;

	case 'set':
		efb_x_out( array( 'ok' => Efb_Test_Xendit_Fake::set( $efb_x_rest[0], $efb_x_rest[1], isset( $efb_x_rest[2] ) ? $efb_x_rest[2] : 'QRIS' ) ) );
		break;

	case 'webhook':
		efb_x_out( array( 'body' => Efb_Test_Xendit_Fake::webhook_body( $efb_x_rest[0], $efb_x_rest[1] ) ) );
		break;

	case 'refund-webhook':
		efb_x_out( array( 'body' => Efb_Test_Xendit_Fake::refund_webhook_body( $efb_x_rest[0], $efb_x_rest[1] ) ) );
		break;

	case 'clear-settings':
		delete_option( 'emsfb_pgw_xendit' );
		efb_x_out( array( 'ok' => true ) );
		break;

	default:
		efb_x_out( array( 'error' => 'unknown command ' . $efb_x_cmd ) );
}
