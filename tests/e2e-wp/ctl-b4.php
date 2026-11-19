<?php
/**
 * Site control for batch B4's scenarios (tests/e2e-wp/scenario-b4.js):
 *
 *   wp eval-file tests/e2e-wp/ctl-b4.php <command> [args]
 *
 *   rh-seed [form|payment]   a request failure the server recorded now (Request_Health: a 500 on that operation), so the
 *                            "request failures detected" notice shows; administrator 1's acknowledgement is cleared
 *   rh-clear <key>           removes that row again
 *   snooze [clear]           administrator 1's "Remind me in 7 days" (user meta emsfb_notice_snooze), or clears it
 *   sources                  the notice manager's sources (id, priority, severity, screens)
 *
 * Every command prints one JSON document on its last line. Local test site only.
 */
if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through WP-CLI\n" );
	exit( 1 );
}
$b4_args = isset( $args ) && is_array( $args ) ? array_values( $args ) : array();
$b4_cmd  = isset( $b4_args[0] ) ? $b4_args[0] : 'sources';
$b4_rest = array_slice( $b4_args, 1 );

function efb_b4_out( $d ) {
	echo "\n" . wp_json_encode( $d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ) . "\n";
	exit( 0 );
}

switch ( $b4_cmd ) {
	case 'rh-seed':
		$op = isset( $b4_rest[0] ) && 'payment' === $b4_rest[0] ? 'payment' : 'form';
		$rows = get_option( 'emsfb_request_health_v1', array() );
		if ( ! is_array( $rows ) ) $rows = array();
		$key = 'server:' . $op . ':500:server_fatal';
		$now = time();
		$rows[ $key ] = array( 'operation' => $op, 'status' => 500, 'source' => 'server', 'reason' => 'server_fatal', 'first' => $now, 'last' => $now, 'count' => 1 );
		update_option( 'emsfb_request_health_v1', $rows, false );
		delete_user_option( 1, 'emsfb_request_health_ack' );
		efb_b4_out( array( 'key' => $key, 'rows' => count( $rows ) ) );
		break;

	case 'rh-clear':
		$rows = get_option( 'emsfb_request_health_v1', array() );
		if ( is_array( $rows ) && isset( $b4_rest[0] ) ) unset( $rows[ $b4_rest[0] ] );
		update_option( 'emsfb_request_health_v1', is_array( $rows ) ? $rows : array(), false );
		efb_b4_out( array( 'ok' => true ) );
		break;

	case 'snooze':
		if ( isset( $b4_rest[0] ) && 'clear' === $b4_rest[0] ) delete_user_meta( 1, 'emsfb_notice_snooze' );
		$m = get_user_meta( 1, 'emsfb_notice_snooze', true );
		efb_b4_out( array( 'snooze' => is_array( $m ) ? $m : array(), 'now' => time() ) );
		break;

	case 'sources':
		$out = array();
		if ( class_exists( '\Emsfb\Notices' ) ) {
			foreach ( \Emsfb\Notices::sources() as $id => $s ) $out[ $id ] = array( 'priority' => is_callable( $s['priority'] ) ? 'callable' : $s['priority'], 'severity' => $s['severity'], 'screens' => is_string( $s['screens'] ) ? $s['screens'] : 'callable', 'snooze' => $s['snooze'] );
		}
		efb_b4_out( $out );
		break;

	default:
		efb_b4_out( array( 'error' => 'unknown command' ) );
}
