<?php
/**
 * Site control for the e2e scenario "omise" (PAY04), next to ctl.php:
 *
 *   wp eval-file tests/e2e-wp/ctl-omise.php <command> [args]
 *
 *   install                   copy mu-plugins/efb-e2e-omise.php into the site's mu-plugins folder
 *   fake <0|1>                switch the fake api.omise.co on / off (option efb_e2e_omise_fake)
 *   keys <test|live> [nosecret]   store the fake Omise keys (and webhook secrets) and the mode;
 *                             prints the mode's webhook secret so the scenario can sign like Omise
 *   charges                   the fake's charges (newest last)
 *   set <charge_id> <status> [amount]   Omise-side status (successful | failed | expired | pending)
 *   event <charge_id> <key> <event_id>  the event JSON Omise would POST (data = the current charge)
 *
 * Every command prints one JSON document on its last line. Local test site only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through WP-CLI: wp eval-file tests/e2e-wp/ctl-omise.php <command>\n" );
	exit( 1 );
}

$efb_om_args = isset( $args ) && is_array( $args ) ? array_values( $args ) : array();
$efb_om_cmd  = isset( $efb_om_args[0] ) ? $efb_om_args[0] : '';
$efb_om_rest = array_slice( $efb_om_args, 1 );

function efb_om_out( $data ) {
	echo "\n" . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
	exit( 0 );
}

require_once dirname( __DIR__ ) . '/omise-fake-provider.php';

switch ( $efb_om_cmd ) {
	case 'install':
		$dir = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
		if ( ! is_dir( $dir ) ) wp_mkdir_p( $dir );
		$ok = copy( __DIR__ . '/mu-plugins/efb-e2e-omise.php', $dir . '/efb-e2e-omise.php' );
		efb_om_out( array( 'ok' => $ok ) );
		break;

	case 'fake':
		update_option( 'efb_e2e_omise_fake', ( isset( $efb_om_rest[0] ) && '1' === $efb_om_rest[0] ) ? 1 : 0, false );
		if ( isset( $efb_om_rest[0] ) && '1' === $efb_om_rest[0] ) update_option( Efb_Test_Omise_Fake::OPTION, array(), false );
		efb_om_out( array( 'ok' => true ) );
		break;

	case 'keys':
		$g = class_exists( '\Emsfb\Payments\Registry' ) ? \Emsfb\Payments\Registry::get( 'omise' ) : null;
		if ( ! $g ) efb_om_out( array( 'error' => 'omise gateway not registered (addon-flag AdnOMS 1, vendor/omise present)' ) );
		$k = Efb_Test_Omise_Fake::keys();
		$mode = isset( $efb_om_rest[0] ) && 'live' === $efb_om_rest[0] ? 'live' : 'test';
		$in = array( 'secret_key__test' => $k['skey_test'], 'secret_key__live' => $k['skey_live'], 'public_key__test' => $k['pkey_test'], 'public_key__live' => $k['pkey_live'] );
		$nosecret = isset( $efb_om_rest[1] ) && 'nosecret' === $efb_om_rest[1];
		update_option( \Emsfb\Payments\Settings::option_name( 'omise' ), array(), false );
		if ( ! $nosecret ) {
			$in['webhook_secret__test'] = $k['whsec_test'];
			$in['webhook_secret__live'] = $k['whsec_live'];
		}
		$refused = \Emsfb\Payments\Settings::load( 'omise' )->apply( $g->settings_schema(), $in, $mode );
		efb_om_out( array( 'ok' => ! $refused, 'mode' => $mode, 'secret' => $nosecret ? '' : $k[ 'whsec_' . $mode ], 'key_tail' => substr( $k[ 'skey_' . $mode ], -4 ) ) );
		break;

	case 'charges':
		efb_om_out( array_values( Efb_Test_Omise_Fake::all() ) );
		break;

	case 'set':
		efb_om_out( array( 'ok' => Efb_Test_Omise_Fake::set_status( $efb_om_rest[0], $efb_om_rest[1], isset( $efb_om_rest[2] ) ? $efb_om_rest[2] : null ) ) );
		break;

	case 'event':
		$all = Efb_Test_Omise_Fake::all();
		$c = isset( $all[ $efb_om_rest[0] ] ) ? $all[ $efb_om_rest[0] ] : array( 'object' => 'charge', 'id' => $efb_om_rest[0] );
		efb_om_out( array( 'object' => 'event', 'id' => $efb_om_rest[2], 'livemode' => ! empty( $c['livemode'] ), 'location' => '/events/' . $efb_om_rest[2], 'webhook_deliveries' => array(), 'data' => $c, 'key' => $efb_om_rest[1], 'created_at' => gmdate( 'Y-m-d\TH:i:s\Z' ) ) );
		break;

	default:
		efb_om_out( array( 'error' => 'unknown command ' . $efb_om_cmd ) );
}
