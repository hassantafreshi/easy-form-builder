<?php
/**
 * Plugin Name: EFB e2e add-on server
 * Description: Answers Easy Form Builder's add-on API from local zip fixtures and keeps the test site offline. Test site only.
 *
 * The URLs Easy Form Builder builds stay the real ones (whitestudio.team), so
 * its host allow-list and request code run unchanged; only the transport is
 * answered here. The mode lives in the efb_e2e_server_mode option:
 *   ok      - the add-on link and the zip are served
 *   down    - every add-on request fails like a network timeout
 *   expired - the link request answers subscription_expired
 */

defined( 'ABSPATH' ) || exit;

function efb_e2e_addon_zip( $addon_key ) {
	$zips = array(
		'AdnHSH' => 'human-shield.zip',
		'AdnSMF' => 'logic.zip',
		'AdnSPF' => 'stripe.zip',
		'AdnPAP' => 'paypal.zip',
		'AdnPDP' => 'persiadatepicker.zip',
		'AdnADP' => 'arabicdatepicker.zip',
		'AdnATF' => 'autofill.zip',
		'AdnTLG' => 'telegram.zip',
		'AdnGoS' => 'googlesheet.zip',
		'AdnOF'  => 'offline.zip',
		'AdnPPF' => 'persiapay.zip',
	);
	return isset( $zips[ $addon_key ] ) ? $zips[ $addon_key ] : '';
}

function efb_e2e_http_response( $code, $body = '', $filename = null ) {
	return array(
		'headers'  => array(),
		'body'     => $body,
		'response' => array( 'code' => $code, 'message' => 200 === $code ? 'OK' : 'Error' ),
		'cookies'  => array(),
		'filename' => $filename,
	);
}

add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	if ( in_array( $host, array( (string) wp_parse_url( home_url(), PHP_URL_HOST ), 'localhost', '127.0.0.1' ), true ) ) {
		return $pre; // Loopback: WP-Cron spawning, site health.
	}

	$mode = get_option( 'efb_e2e_server_mode', 'ok' );
	$log  = get_option( 'efb_e2e_http_log', array() );
	$log  = is_array( $log ) ? $log : array();
	$log[] = array( 'at' => time(), 'url' => $url, 'mode' => $mode );
	update_option( 'efb_e2e_http_log', array_slice( $log, -300 ), false );

	$timeout = new WP_Error( 'http_request_failed', 'cURL error 28: Connection timed out after 15001 milliseconds' );

	if ( preg_match( '#/wp-json/wl/v1/addons-link/[^/]+/(Adn[A-Za-z]+)/#', $url, $match ) ) {
		if ( 'down' === $mode ) {
			return $timeout;
		}
		if ( 'expired' === $mode ) {
			return efb_e2e_http_response( 200, wp_json_encode( array(
				'status' => false,
				'error'  => 'subscription_expired',
				'reason' => 'expired',
				'renew'  => 'https://whitestudio.team/checkout?renew=e2e',
			) ) );
		}
		$zip = efb_e2e_addon_zip( $match[1] );
		if ( '' === $zip ) {
			return efb_e2e_http_response( 200, wp_json_encode( array( 'status' => false, 'error' => 'addon_not_found' ) ) );
		}
		return efb_e2e_http_response( 200, wp_json_encode( array(
			'link'     => 'https://whitestudio.team/source/files/zip/v4/' . $zip,
			'download' => true,
			'status'   => true,
			'name'     => $match[1],
			'v'        => '4.0.0',
			'wp'       => '6.0',
			'package'  => 1,
		) ) );
	}

	if ( preg_match( '#^https://whitestudio\.team/source/files/zip/v4/([a-z0-9-]+\.zip)$#', $url, $match ) ) {
		if ( 'down' === $mode ) {
			return $timeout;
		}
		$source = WP_CONTENT_DIR . '/efb-e2e-zips/' . $match[1];
		if ( empty( $args['filename'] ) || ! is_file( $source ) ) {
			return efb_e2e_http_response( 404 );
		}
		copy( $source, $args['filename'] );
		return efb_e2e_http_response( 200, '', $args['filename'] );
	}

	return new WP_Error( 'http_request_failed', 'External requests are blocked on the EFB e2e site.' );
}, 10, 3 );
