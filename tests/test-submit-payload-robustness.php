<?php
/**
 * The public submit endpoint must answer, not fatal, on a malformed payload.
 *
 * `value` is documented as a JSON string and the bundled client always sends
 * one (core-efb.js: value: JSON.stringify(...)). Nothing enforces that on the
 * wire though, and a request carrying an array reached json_decode() with a
 * non-string: an uncaught TypeError, so the route answered HTTP 500 and wrote a
 * stack trace to the error log. Form Security & Spam Protection absorbs these
 * before they land, but it ships disabled, so a stock install was exposed.
 *
 * Also covers the WP-CLI/WP-Cron server globals, where REMOTE_ADDR and
 * HTTP_USER_AGENT are simply absent.
 *
 * Run: php tests/test-submit-payload-robustness.php
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}
define( 'WP_USE_THEMES', false );
require_once $wp_load;

global $wpdb;

$pass = 0;
$fail = 0;
function t( $label, $ok, $detail = '' ) {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "[PASS] {$label}\n"; }
	else { $fail++; echo "[FAIL] {$label}\n"; }
	if ( '' !== $detail ) { echo "       {$detail}\n"; }
}

$fn  = get_efbFunction();
$fid = (int) $wpdb->get_var( "SELECT form_id FROM {$wpdb->prefix}emsfb_form WHERE form_type='form' ORDER BY form_id ASC LIMIT 1" );
if ( ! $fid ) { echo "[SKIP] no form of type 'form' exists\n"; exit( 0 ); }

$url = get_rest_url( null ) . 'Emsfb/v1/forms/message/add';

/**
 * @param mixed $value Whatever to put in the payload's `value` field.
 * @return array{0:int,1:string} HTTP status and body.
 */
function efb_submit_with_value( $value, $fid, $fn, $url ) {
	$sid = $fn->efb_code_validate_create( $fid, 0, 'visit', 0 );
	$res = wp_remote_post( $url, array(
		'timeout' => 30, 'sslverify' => false,
		'headers' => array(
			'Content-Type' => 'application/json',
			'X-WP-Nonce'   => wp_create_nonce( 'wp_rest' ),
			'sid'          => $sid,
			'form-id'      => (string) $fid,
		),
		'body' => wp_json_encode( array(
			'id' => (string) $fid, 'sid' => $sid, 'page_id' => '0',
			'url' => home_url( '/' ), 'value' => $value, 'type' => 'form',
		) ),
	) );

	global $wpdb;
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}emsfb_stts_ WHERE sid = %s", $sid ) );

	if ( is_wp_error( $res ) ) { return array( 0, $res->get_error_message() ); }
	return array( (int) wp_remote_retrieve_response_code( $res ), (string) wp_remote_retrieve_body( $res ) );
}

echo "=== malformed `value` payloads must not fatal ===\n";

foreach ( array(
	'array'        => array( 'a' => 1 ),
	'empty array'  => array(),
	'nested array' => array( array( 'id_' => 1 ) ),
	'integer'      => 12345,
	'boolean'      => true,
	'null'         => null,
	'empty string' => '',
	'not json'     => 'definitely-not-json',
) as $label => $value ) {
	list( $code, $body ) = efb_submit_with_value( $value, $fid, $fn, $url );
	$fatal = ( 500 === $code ) || false !== stripos( $body, 'critical error' ) || false !== stripos( $body, 'Uncaught' );
	t( "value as {$label} does not fatal", ! $fatal, "HTTP {$code}: " . substr( trim( $body ), 0, 110 ) );
}

echo "\n=== the documented shape still works ===\n";
list( $code_ok, $body_ok ) = efb_submit_with_value(
	wp_json_encode( array( array( 'id_' => 1, 'name' => 'test', 'value' => 'hello', 'type' => 'text' ) ) ),
	$fid, $fn, $url
);
t( 'a JSON-string value is still accepted', 500 !== $code_ok, "HTTP {$code_ok}: " . substr( trim( $body_ok ), 0, 110 ) );

echo "\n=== server globals absent (WP-CLI / WP-Cron) ===\n";
$saved = array();
foreach ( array( 'REMOTE_ADDR', 'HTTP_USER_AGENT', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR' ) as $k ) {
	$saved[ $k ] = isset( $_SERVER[ $k ] ) ? $_SERVER[ $k ] : null;
	unset( $_SERVER[ $k ] );
}

$warnings = array();
set_error_handler( function ( $no, $str ) use ( &$warnings ) { $warnings[] = $str; return true; }, E_ALL );
$ip = $fn->get_ip_address();
$os = $fn->getVisitorOS();
$br = $fn->getVisitorBrowser();
restore_error_handler();

foreach ( $saved as $k => $v ) { if ( null !== $v ) { $_SERVER[ $k ] = $v; } }

t( 'get_ip_address() returns a string with no REMOTE_ADDR', is_string( $ip ) && '' !== $ip, "ip: {$ip}" );
t( 'getVisitorOS() survives a missing user agent', is_string( $os ), "os: {$os}" );
t( 'getVisitorBrowser() survives a missing user agent', is_string( $br ), "browser: {$br}" );
t( 'no warning or deprecation was raised', empty( $warnings ),
	empty( $warnings ) ? 'clean' : implode( ' | ', array_slice( $warnings, 0, 3 ) ) );

echo "\n========================================\n";
echo "RESULTS: {$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
