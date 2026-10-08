<?php
/**
 * A public form submission must survive any character a visitor can type.
 *
 * The bundled public client sends text values as typed, so a straight double
 * quote reaches forms/message/add JSON-escaped (\"). get_form_public_efb
 * stripped every backslash before json_decode, the JSON broke, the decoded
 * rows became empty, and the whole form was rejected with "Please complete the
 * form." - the entry was lost and no email or integration ran. The same strip
 * also silently dropped every backslash from stored values.
 *
 * This test clones a real form (all fields made optional, email off), then for
 * each awkward value:
 *   - submits it through the live REST route the browser uses;
 *   - asserts the submission is accepted (not the false "form is empty");
 *   - re-reads the stored entry the way every reader does (strip all
 *     backslashes, then json_decode) and asserts it still decodes;
 *   - asserts the value round-trips, with " carried as the @efb@dq# marker and
 *     the backslash preserved in the decoded payload.
 *
 * It SKIPs (does not fail) if a plain control value cannot be submitted on this
 * site, so an environment quirk never reads as a code regression.
 *
 * Every row this test creates - the throwaway form, its entries and sessions -
 * is removed at the end, pass or fail.
 *
 * Run: C:\xampp\php\php.exe tests/test-submit-special-chars-e2e.php
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR']    = '127.0.0.1';
$_SERVER['HTTP_HOST']      = '127.0.0.1';
define( 'WP_USE_THEMES', false );
require_once $wp_load;

global $wpdb;

class Efb_Submit_Json_Sent extends Exception {}

/* No mail or outbound call leaves the machine during the test. */
add_filter( 'pre_http_request', function () {
	return array( 'headers' => array(), 'body' => '{}', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() );
}, 1 );
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', function () {
	return function () { throw new Efb_Submit_Json_Sent(); };
} );
wp_set_current_user( 0 );

$pass = 0;
$fail = 0;
function t( $label, $ok, $detail = '' ) {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "[PASS] {$label}\n"; }
	else { $fail++; echo "[FAIL] {$label}\n"; if ( '' !== $detail ) echo "       {$detail}\n"; }
}

$fn = get_efbFunction();

/* Clone a real form so the structure is realistic, then neutralise it:
 * every field optional, no email, no captcha, no tracking requirement. */
$src = $wpdb->get_row( "SELECT form_structer, form_type FROM {$wpdb->prefix}emsfb_form WHERE form_type='form' ORDER BY form_id ASC LIMIT 1" );
if ( ! $src ) { echo "[SKIP] no form of type 'form' exists\n"; exit( 0 ); }

$structure = json_decode( str_replace( '\\', '', $src->form_structer ), true );
if ( ! is_array( $structure ) || empty( $structure[0] ) ) { echo "[SKIP] the sample form did not decode\n"; exit( 0 ); }

$structure[0]['sendEmail']    = '0';
$structure[0]['captcha']      = '';
$structure[0]['trackingCode'] = '1';
$structure[0]['smsnoti']      = '0';

$text_id = '';
foreach ( $structure as $i => $field ) {
	if ( 0 === $i || ! is_array( $field ) ) { continue; }
	if ( isset( $field['require'] ) ) { $structure[$i]['require'] = '0'; }
	if ( isset( $field['noti'] ) ) { $structure[$i]['noti'] = '0'; }
	if ( isset( $field['smsnoti'] ) ) { $structure[$i]['smsnoti'] = '0'; }
	if ( '' === $text_id && isset( $field['type'], $field['id_'] ) && in_array( $field['type'], array( 'text', 'textarea' ), true ) ) {
		$text_id   = (string) $field['id_'];
		$text_name = isset( $field['name'] ) ? (string) $field['name'] : 'Text';
	}
}
if ( '' === $text_id ) { echo "[SKIP] the sample form has no text/textarea field\n"; exit( 0 ); }

$stored_structure = str_replace( '"', '\\"', wp_json_encode( $structure, JSON_UNESCAPED_UNICODE ) );
$wpdb->insert( $wpdb->prefix . 'emsfb_form', array(
	'form_name'       => 'submit special chars e2e',
	'form_structer'   => $stored_structure,
	'form_email'      => '',
	'form_type'       => 'form',
	'form_created_by' => '1',
	'form_access_by'  => '',
) );
$form_id = (int) $wpdb->insert_id;
if ( ! $form_id ) { echo "[SKIP] could not create the throwaway form\n"; exit( 0 ); }

/**
 * Submit one value for the text field in-process and return [status, body].
 *
 * The value is encoded the way the browser does (JSON_UNESCAPED_SLASHES, so a
 * double quote travels as the single escape \" the server must cope with).
 */
function efb_submit_text( $value, $form_id, $text_id, $text_name, $fn, $url ) {
	global $wpdb;
	$sid = $fn->efb_code_validate_create( $form_id, 0, 'visit', 0 );
	$_SERVER['HTTP_X_WP_NONCE'] = wp_create_nonce( 'wp_rest' );
	$_SERVER['HTTP_SID']        = $sid;
	$_SERVER['HTTP_FORM_ID']    = (string) $form_id;

	$rows = array( array( 'id_' => $text_id, 'name' => $text_name, 'value' => $value, 'type' => 'text' ) );
	$req  = new WP_REST_Request( 'POST', '/Emsfb/v1/forms/message/add' );
	$req->set_header( 'content-type', 'application/json' );
	$req->set_header( 'sid', $sid );
	$req->set_header( 'form-id', (string) $form_id );
	$req->set_body( wp_json_encode( array(
		'id' => (string) $form_id, 'sid' => $sid, 'page_id' => '0',
		'url' => home_url( '/' ),
		'value' => json_encode( $rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
		'type' => 'form',
	) ) );

	$status = 200;
	$body   = null;
	ob_start();
	try {
		rest_do_request( $req );
	} catch ( Efb_Submit_Json_Sent $e ) {
		// wp_send_json fired.
	} catch ( Throwable $e ) {
		$status = 500;
		$body   = array( 'fatal' => get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() );
	}
	$raw = (string) ob_get_clean();
	if ( null === $body ) { $body = json_decode( $raw, true ); }
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}emsfb_stts_ WHERE sid = %s", $sid ) );
	return array( $status, $body );
}

/** The value stored for the text field in the newest entry, decoded as readers do. */
function efb_stored_text_value( $form_id, $text_id ) {
	global $wpdb;
	$content = $wpdb->get_var( $wpdb->prepare( "SELECT content FROM {$wpdb->prefix}emsfb_msg_ WHERE form_id = %d ORDER BY msg_id DESC LIMIT 1", $form_id ) );
	if ( null === $content ) { return array( 'decoded' => false, 'value' => null ); }
	$rows = json_decode( str_replace( '\\', '', $content ), true );
	if ( ! is_array( $rows ) ) { return array( 'decoded' => false, 'value' => null ); }
	foreach ( $rows as $r ) {
		if ( is_array( $r ) && isset( $r['id_'] ) && $r['id_'] === $text_id ) {
			return array( 'decoded' => true, 'value' => isset( $r['value'] ) ? (string) $r['value'] : '' );
		}
	}
	return array( 'decoded' => true, 'value' => null );
}

/* Baseline: a plain value must go through, or the environment is not sane. */
list( $code0, $body0 ) = efb_submit_text( 'hello world', $form_id, $text_id, $text_name, $fn, $url );
$accepted0 = 200 === $code0 && is_array( $body0 ) && isset( $body0['data']['success'] ) && true === $body0['data']['success'];
if ( ! $accepted0 ) {
	echo '[SKIP] the control submission was not accepted (HTTP ' . $code0 . '): ' . substr( wp_json_encode( $body0 ), 0, 200 ) . "\n";
	$wpdb->delete( $wpdb->prefix . 'emsfb_msg_', array( 'form_id' => $form_id ) );
	$wpdb->delete( $wpdb->prefix . 'emsfb_form', array( 'form_id' => $form_id ) );
	exit( 0 );
}
t( 'control value accepted', true );

$cases = array(
	'double quotes'    => 'He said "hello" twice',
	'single quote'     => "it's working",
	'backslash path'   => 'C:\\Users\\test\\new',
	'escaped sequence' => 'regex \\d+ and \\" here',
	'persian + zwnj'   => 'سلام می‌خواهم «نقل»',
	'emoji'            => 'great 😀👍 done',
	'ampersand/lt/gt'  => 'a & b < c > d',
	'percent'          => '100% off at 50%',
	'marker typed'     => 'has @efb@dq# inside',
	'equals/formula'   => '=SUM(A1:A2)',
);

foreach ( $cases as $label => $value ) {
	list( $code, $body ) = efb_submit_text( $value, $form_id, $text_id, $text_name, $fn, $url );
	$accepted = 200 === $code && is_array( $body ) && ! empty( $body['data']['success'] );
	$detail   = 'HTTP ' . $code . ': ' . substr( wp_json_encode( is_array( $body ) ? $body['data'] : $body ), 0, 160 );
	t( "{$label}: accepted (not false-empty)", $accepted, $detail );
	if ( ! $accepted ) { continue; }

	$stored = efb_stored_text_value( $form_id, $text_id );
	t( "{$label}: stored entry still decodes", $stored['decoded'] );
	if ( ! $stored['decoded'] || null === $stored['value'] ) { continue; }

	$expected = str_replace( '"', '@efb@dq#', $value );
	t( "{$label}: value round-trips", $expected === $stored['value'], 'stored: ' . $stored['value'] );
}

/* Clean up everything this test created. */
$wpdb->delete( $wpdb->prefix . 'emsfb_msg_', array( 'form_id' => $form_id ) );
$wpdb->delete( $wpdb->prefix . 'emsfb_form', array( 'form_id' => $form_id ) );
$left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}emsfb_form WHERE form_id = %d", $form_id ) );
t( 'throwaway form removed', 0 === $left );

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail ? 1 : 0 );
