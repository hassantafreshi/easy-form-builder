<?php
/**
 * A Response Box reply must survive any character a person can type.
 *
 * The reply composer on a ?track= page sends its text through
 * JSON.stringify without converting quotes, so a straight double quote
 * reaches forms/response/add as \". The handler stripped every backslash
 * before json_decode(), the JSON broke, and array_values(null) ended the
 * request with a PHP fatal: the browser showed "Network response was not ok
 * (HTTP 500)" and the reply was lost (whitestudio.team support, 4.3.0).
 *
 * Covers, for an administrator and for a visitor:
 *   - every awkward character class is accepted (no fatal, success:true);
 *   - the stored row still decodes the way every reader decodes it (all
 *     backslashes stripped, then json_decode) - a raw quote saved there
 *     would make the whole conversation unreadable;
 *   - the text round-trips, with " carried as @efb@dq#;
 *   - malformed payloads answer with a message instead of a fatal.
 *
 * The administrator runs in-process (a REST request with the current user
 * set); wp_send_json() is captured instead of ending the process. Every row
 * this test creates is deleted at the end, pass or fail.
 *
 * Run: C:\xampp\php\php.exe tests/test-response-reply-special-chars.php
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}
$_SERVER['REQUEST_METHOD'] = 'POST';
define( 'WP_USE_THEMES', false );
require_once $wp_load;

global $wpdb;

$pass = 0;
$fail = 0;
function t( $label, $ok, $detail = '' ) {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "[PASS] {$label}\n"; }
	else { $fail++; echo "[FAIL] {$label}\n"; }
	if ( '' !== $detail && ! $ok ) { echo "       {$detail}\n"; }
}

class Efb_Test_Json_Sent extends Exception {}

/* No integration may leave this machine while the test runs. */
add_filter( 'pre_http_request', function () {
	return array( 'headers' => array(), 'body' => '{}', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() );
}, 1 );

/* wp_send_json() dies; in "ajax" mode it goes through wp_die(), which can be
 * turned into an exception so one process can send many requests. */
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', function () {
	return function () { throw new Efb_Test_Json_Sent(); };
} );

$form_id = (int) $wpdb->get_var( "SELECT form_id FROM {$wpdb->prefix}emsfb_form WHERE form_type='form' ORDER BY form_id ASC LIMIT 1" );
if ( ! $form_id ) { echo "[SKIP] no form of type 'form' exists\n"; exit( 0 ); }

$admin_id = (int) $wpdb->get_var( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '{$wpdb->prefix}capabilities' AND meta_value LIKE '%administrator%' ORDER BY user_id ASC LIMIT 1" );
if ( ! $admin_id ) { echo "[SKIP] no administrator exists\n"; exit( 0 ); }

/* A throwaway ticket of our own, so no real conversation is touched. */
$track = 'TQ' . strtoupper( substr( md5( uniqid( '', true ) ), 0, 9 ) );
$wpdb->insert( $wpdb->prefix . 'emsfb_msg_', array(
	'form_id'      => $form_id,
	'track'        => $track,
	'ip'           => '127.0.0.1',
	'form_title_x' => 'reply special chars test',
	'content'      => '[{\"id_\":\"t1\",\"name\":\"Name\",\"type\":\"text\",\"value\":\"x\",\"amount\":1}]',
	'read_'        => 0,
	'read_by'      => '',
) );
$msg_id = (int) $wpdb->insert_id;
if ( ! $msg_id ) { echo "[SKIP] could not create the test ticket\n"; exit( 0 ); }

/**
 * Send one reply the way the browser does and return the decoded answer.
 *
 * @param int         $user_id 0 for a visitor.
 * @param mixed       $message The `message` field (normally a JSON string).
 * @param string|null $by      Overrides the row's `by`.
 * @return array{fatal:string,data:array|null,raw:string}
 */
function efb_reply( $user_id, $message, $track, $msg_id ) {
	wp_set_current_user( $user_id );
	$_SERVER['HTTP_X_WP_NONCE'] = wp_create_nonce( 'wp_rest' );

	/* _Public reads the replier at rest_api_init; the server is built once,
	 * so keep its copy in step with the user of this request. */
	$server = rest_get_server();
	foreach ( $server->get_routes()['/Emsfb/v1/forms/response/add'] as $handler ) {
		if ( is_array( $handler['callback'] ) && is_object( $handler['callback'][0] ) ) {
			$prop = new ReflectionProperty( $handler['callback'][0], 'efb_uid' );
			$prop->setAccessible( true );
			$prop->setValue( $handler['callback'][0], $user_id );
		}
	}

	$req = new WP_REST_Request( 'POST', '/Emsfb/v1/forms/response/add' );
	$req->set_header( 'content-type', 'application/json' );
	$req->set_body( json_encode( array(
		'action' => 'set_rMessage_id_Emsfb', 'id' => (string) $msg_id, 'valid' => '',
		'message' => $message, 'type' => 'form', 'sid' => '',
		'user_type' => $user_id ? 'admin' : 'user', 'sc' => '', 'page_id' => '0', 'track' => $track,
	), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

	$fatal = '';
	ob_start();
	try {
		rest_do_request( $req );
	} catch ( Efb_Test_Json_Sent $e ) {
		// The answer was sent.
	} catch ( Throwable $e ) {
		$fatal = get_class( $e ) . ': ' . $e->getMessage() . ' at ' . basename( $e->getFile() ) . ':' . $e->getLine();
	}
	$raw = (string) ob_get_clean();
	$json = json_decode( $raw, true );

	return array( 'fatal' => $fatal, 'data' => is_array( $json ) && isset( $json['data'] ) ? $json['data'] : null, 'raw' => $raw );
}

/** The browser's payload for one typed reply. */
function efb_reply_payload( $text, $by = 'Support' ) {
	return json_encode( array( array(
		'id_' => 'message', 'name' => 'message', 'type' => 'text', 'amount' => 0,
		'value' => $text, 'by' => $by, 'session' => 'test', 'form_id' => -1,
	) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/** The newest stored reply, decoded exactly the way the readers decode it. */
function efb_last_stored_reply( $msg_id ) {
	global $wpdb;
	$content = (string) $wpdb->get_var( $wpdb->prepare( "SELECT content FROM {$wpdb->prefix}emsfb_rsp_ WHERE msg_id = %d ORDER BY rsp_id DESC LIMIT 1", $msg_id ) );
	return json_decode( str_replace( '\\', '', $content ), true );
}

$texts = array(
	'double quotes'        => 'Please click "Activate" and then "Save"',
	'lone double quote'    => 'it is 5" wide',
	'single quote'         => "it's done",
	'backtick'             => 'run `wp cache flush`',
	'backslash'            => 'path C:\\temp\\new',
	'escaped quote typed'  => 'literal \\" sequence',
	'persian with quotes'  => 'سلام «تست» "نقل قول" با نیم‌فاصله',
	'emoji'                => 'thanks 😀👍',
	'html-like'            => 'use 5 < 6 & 7 > 3',
	'percent sequences'    => 'discount 100% off %22 %5C',
	'marker typed'         => 'text with @efb@dq# inside',
	'json-looking'         => '{"a":[1,2]} and ]}',
	'long text'            => str_repeat( 'long "quoted" line ', 200 ),
);

foreach ( array( 'administrator' => $admin_id, 'visitor' => 0 ) as $who => $uid ) {
	echo "\n=== {$who} replies ===\n";
	foreach ( $texts as $label => $text ) {
		$r = efb_reply( $uid, efb_reply_payload( $text ), $track, $msg_id );
		$ok = '' === $r['fatal'] && is_array( $r['data'] ) && ! empty( $r['data']['success'] );
		t( "{$who}: {$label} is accepted", $ok, $r['fatal'] ? $r['fatal'] : substr( $r['raw'], 0, 200 ) );
		if ( ! $ok ) { continue; }

		$stored = efb_last_stored_reply( $msg_id );
		t( "{$who}: {$label} is stored decodable", is_array( $stored ) && isset( $stored[0]['value'] ) );
		if ( ! is_array( $stored ) || ! isset( $stored[0]['value'] ) ) { continue; }

		$value = (string) $stored[0]['value'];
		t( "{$who}: {$label} keeps no raw double quote", false === strpos( $value, '"' ), $value );
		$expected = sanitize_text_field( str_replace( array( '\\', '"' ), array( '', '@efb@dq#' ), $text ) );
		t( "{$who}: {$label} round-trips", $expected === $value, 'stored: ' . substr( $value, 0, 120 ) );
	}
}

echo "\n=== a quote in the replier's display name ===\n";
$r = efb_reply( $admin_id, efb_reply_payload( 'plain reply', 'Hassan "WS"' ), $track, $msg_id );
t( 'a quoted display name is accepted', '' === $r['fatal'] && ! empty( $r['data']['success'] ), $r['fatal'] ? $r['fatal'] : substr( $r['raw'], 0, 200 ) );
$stored = efb_last_stored_reply( $msg_id );
t( 'a quoted display name is stored decodable', is_array( $stored ) && isset( $stored[0]['by'] ) && false === strpos( $stored[0]['by'], '"' ) );

echo "\n=== malformed payloads answer instead of failing ===\n";
foreach ( array(
	'not json'         => 'definitely not json',
	'json object'      => '{"id_":"message","value":"x"}',
	'json scalar'      => '"just a string"',
	'empty json array' => '[]',
	'array, not text'  => array( array( 'id_' => 'message', 'value' => 'x' ) ),
	'broken quoting'   => '[{"id_":"message","value":"a"b"}]',
) as $label => $message ) {
	$before = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}emsfb_rsp_ WHERE msg_id = %d", $msg_id ) );
	$r = efb_reply( $admin_id, $message, $track, $msg_id );
	$after = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}emsfb_rsp_ WHERE msg_id = %d", $msg_id ) );
	t( "{$label}: no fatal", '' === $r['fatal'], $r['fatal'] );
	t( "{$label}: answered success:false", is_array( $r['data'] ) && empty( $r['data']['success'] ) && ! empty( $r['data']['m'] ), substr( $r['raw'], 0, 200 ) );
	t( "{$label}: nothing stored", $before === $after );
}

/* Clean up: the ticket and every reply written to it. */
$wpdb->delete( $wpdb->prefix . 'emsfb_rsp_', array( 'msg_id' => $msg_id ) );
$wpdb->delete( $wpdb->prefix . 'emsfb_msg_', array( 'msg_id' => $msg_id ) );
$left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}emsfb_msg_ WHERE msg_id = %d", $msg_id ) );
t( 'test ticket removed', 0 === $left );

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail ? 1 : 0 );
