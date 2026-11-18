<?php
/**
 * Plugin Name: EFB e2e F17
 * Description: Test only (tests/e2e-wp/scenario-f17.js). A Nominatim stand-in for the Address & Postal add-on's search:
 * every outbound request to a Nominatim address is answered here (never sent), counted in the option efb_e2e_f17_calls
 * (url, user-agent, referer), and answers a fixed Tehran / Riyadh / London result, or a postcode result. Requests to any
 * other OpenStreetMap host are counted as "leak" (the test fails on one).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	if ( strpos( $host, 'nominatim' ) === false && strpos( $host, 'geo.e2e.test' ) === false ) {
		if ( strpos( $host, 'openstreetmap' ) !== false ) { $c = (array) get_option( 'efb_e2e_f17_calls', array() ); $c[] = array( 'leak' => $url ); update_option( 'efb_e2e_f17_calls', $c, false ); }
		return $pre;
	}
	$c = (array) get_option( 'efb_e2e_f17_calls', array() );
	$c[] = array( 'url' => $url, 'ua' => isset( $args['user-agent'] ) ? $args['user-agent'] : '', 'referer' => isset( $args['headers']['Referer'] ) ? $args['headers']['Referer'] : '' );
	update_option( 'efb_e2e_f17_calls', $c, false );
	parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $q );
	$cc = isset( $q['countrycodes'] ) ? $q['countrycodes'] : '';
	if ( isset( $q['postalcode'] ) ) {
		$a = $cc === 'us' ? array( 'city' => 'Beverly Hills', 'state' => 'California', 'postcode' => $q['postalcode'], 'country' => 'United States' ) : array( 'city' => 'Tehran', 'state' => 'Tehran', 'postcode' => $q['postalcode'] );
		$body = array( array( 'display_name' => 'Postcode ' . $q['postalcode'], 'lat' => '1', 'lon' => '1', 'address' => $a ) );
	} elseif ( $cc === 'ir' ) {
		$body = array(
			array( 'display_name' => 'خیابان ولیعصر، تهران <b>x</b>', 'lat' => '35.7', 'lon' => '51.4', 'address' => array( 'house_number' => '12', 'road' => 'خیابان ولیعصر', 'suburb' => 'یوسف‌آباد', 'city' => 'Tehran', 'state' => 'Tehran', 'postcode' => '1234567890', 'country' => 'Iran', 'secret' => 'dropped' ), 'extra' => 'dropped' ),
			array( 'display_name' => 'Isfahan', 'lat' => '32.6', 'lon' => '51.6', 'address' => array( 'city' => 'Isfahan', 'state' => 'Isfahan' ) ),
		);
	} elseif ( isset( $q['q'] ) && strpos( $q['q'], 'nothing' ) !== false ) {
		$body = array();
	} else {
		$body = array( array( 'display_name' => '10 Downing Street, London', 'lat' => '51.5', 'lon' => '-0.12', 'address' => array( 'house_number' => '10', 'road' => 'Downing Street', 'city' => 'London', 'state' => 'England', 'postcode' => 'SW1A 2AA', 'country' => 'United Kingdom' ) ) );
	}
	return array( 'headers' => array(), 'body' => wp_json_encode( $body ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
}, 10, 3 );
