<?php
/**
 * Site control for the e2e scenario "paymob" (PAY06), next to ctl.php:
 *
 *   wp eval-file tests/e2e-wp/ctl-paymob.php <command> [args]
 *
 *   install                   copy mu-plugins/efb-e2e-paymob.php into the site's mu-plugins folder
 *   fake <0|1>                switch the fake Paymob on / off (option efb_e2e_pmb_fake) and reset it
 *   keys [test|live] [region] store the fake Paymob settings (region, keys of both modes built at run
 *                             time, API key, HMAC secret, integration IDs) and the mode
 *   state                     the fake's intentions (request bodies), transactions and request log (no key)
 *   pay <order> <paid|failed|kiosk> [method]   the visitor on Unified Checkout: a transaction on the order
 *   callback <txn_id> [wrong] the Transaction Processed callback Paymob would post: JSON body and the
 *                             hmac query value (signed with the fake HMAC secret, or another one)
 *   clear-settings            remove the gateway's settings option
 *
 * Every command prints one JSON document on its last line. Local test site only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through WP-CLI: wp eval-file tests/e2e-wp/ctl-paymob.php <command>\n" );
	exit( 1 );
}

$efb_pm_args = isset( $args ) && is_array( $args ) ? array_values( $args ) : array();
$efb_pm_cmd  = isset( $efb_pm_args[0] ) ? $efb_pm_args[0] : 'state';
$efb_pm_rest = array_slice( $efb_pm_args, 1 );

function efb_pm_out( $data ) {
	echo "\n" . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ) . "\n";
	exit( 0 );
}

require_once dirname( __DIR__ ) . '/paymob-fake-provider.php';
if ( class_exists( '\Emsfb\Payments\Registry' ) ) \Emsfb\Payments\Registry::all(); // registers the add-on's gateway when AdnPMB is on

switch ( $efb_pm_cmd ) {
	case 'install':
		$dir = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
		if ( ! is_dir( $dir ) ) wp_mkdir_p( $dir );
		efb_pm_out( array( 'ok' => copy( __DIR__ . '/mu-plugins/efb-e2e-paymob.php', $dir . '/efb-e2e-paymob.php' ) ) );
		break;

	case 'fake':
		$on = isset( $efb_pm_rest[0] ) && '1' === $efb_pm_rest[0];
		update_option( 'efb_e2e_pmb_fake', $on ? 1 : 0, false );
		Efb_Test_Paymob_Fake::reset();
		efb_pm_out( array( 'ok' => true, 'fake' => $on ) );
		break;

	case 'keys':
		$g = class_exists( '\Emsfb\Payments\Registry' ) ? \Emsfb\Payments\Registry::get( 'paymob' ) : null;
		if ( ! $g ) efb_pm_out( array( 'error' => 'paymob gateway not registered (addon-flag AdnPMB 1, vendor/paymob present)' ) );
		$mode = isset( $efb_pm_rest[0] ) && 'live' === $efb_pm_rest[0] ? 'live' : 'test';
		$region = isset( $efb_pm_rest[1] ) ? $efb_pm_rest[1] : 'eg';
		update_option( \Emsfb\Payments\Settings::option_name( 'paymob' ), array(), false );
		$refused = \Emsfb\Payments\Settings::load( 'paymob' )->apply( $g->settings_schema(), Efb_Test_Paymob_Fake::settings( $region ), $mode );
		efb_pm_out( array( 'ok' => ! $refused, 'refused' => $refused, 'mode' => $mode, 'region' => $region, 'key_tail' => substr( Efb_Test_Paymob_Fake::key( 'sk', 'test' ), -4 ), 'public_test' => Efb_Test_Paymob_Fake::key( 'pk', 'test' ) ) );
		break;

	case 'state':
		$s = Efb_Test_Paymob_Fake::state();
		$in = array();
		foreach ( $s['intentions'] as $id => $i ) {
			$in[] = array( 'id' => $id, 'order' => $i['body']['intention_order_id'], 'mode' => $i['mode'], 'region' => $i['region'], 'body' => $i['raw'] );
		}
		$tx = array();
		foreach ( $s['txns'] as $id => $t ) {
			$tx[] = array( 'id' => $t['id'], 'order' => $t['order']['id'], 'success' => $t['success'], 'pending' => $t['pending'], 'amount_cents' => $t['amount_cents'], 'currency' => $t['currency'], 'refund' => ! empty( $t['is_refund'] ), 'parent' => $t['parent_transaction'], 'refunded_amount_cents' => $t['refunded_amount_cents'], 'type' => $t['source_data']['type'] );
		}
		efb_pm_out( array( 'intentions' => $in, 'txns' => $tx, 'log' => $s['log'] ) );
		break;

	case 'pay':
		efb_pm_out( Efb_Test_Paymob_Fake::pay( $efb_pm_rest[0], isset( $efb_pm_rest[1] ) ? $efb_pm_rest[1] : 'paid', array(), isset( $efb_pm_rest[2] ) ? $efb_pm_rest[2] : 'card' ) );
		break;

	case 'callback':
		efb_pm_out( Efb_Test_Paymob_Fake::callback( $efb_pm_rest[0], isset( $efb_pm_rest[1] ) && 'wrong' === $efb_pm_rest[1] ? 'Wrong' . 'HmacSecret0001' : null ) );
		break;

	case 'clear-settings':
		delete_option( 'emsfb_pgw_paymob' );
		efb_pm_out( array( 'ok' => true ) );
		break;

	default:
		efb_pm_out( array( 'error' => 'unknown command ' . $efb_pm_cmd ) );
}
