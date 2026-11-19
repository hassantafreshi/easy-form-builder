<?php
/**
 * SEC-04 (owner decision 6, BUGS-FOUND 21): the answer to a password field of an ordinary form is used for its checks
 * only. It was stored as typed in emsfb_msg_.content, so it showed in entries, CSV and the notification e-mail.
 *
 * On the real site, through the live REST route the browser uses (forms/message/add), a throwaway form with a text
 * field and a required password field (at least 8 characters):
 *  - a password that is too short is still refused (the checks see the password);
 *  - a good one is accepted, and the stored entry holds the mask (••••), never the password, anywhere in its content;
 *  - the notification e-mail's table (email_get_content_efb) shows the mask, also for an entry saved before 4.3.2
 *    that still holds a password as typed;
 *  - the other answer is stored as typed.
 * Register and login forms take their own branch (tests/test-login-register-type-confusion.php, test-ap-b4-auth-save.php).
 * Everything it creates is removed at the end.
 *
 * Run (under the e2e lock): php tests/test-password-never-stored-http.php
 */
require_once __DIR__ . '/wp-env.php';
$wp_load = efb_test_wp_load();
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
add_filter( 'pre_http_request', function () { return array( 'headers' => array(), 'body' => '{}', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() ); }, 1 );
wp_set_current_user( 0 );

$pass = 0; $fail = 0;
function t( $label, $ok, $detail = '' ) { global $pass, $fail; if ( $ok ) { $pass++; echo "[PASS] {$label}\n"; } else { $fail++; echo "[FAIL] {$label}" . ( '' !== $detail ? "\n       {$detail}" : '' ) . "\n"; } }

$fn = get_efbFunction();
$MASK = "\u{2022}\u{2022}\u{2022}\u{2022}";
$row0 = array( 'type' => 'form', 'steps' => 1, 'formName' => 'sec04 password e2e', 'email' => '', 'trackingCode' => '1', 'EfbVersion' => 2, 'sendEmail' => '0', 'captcha' => '', 'stateForm' => 0,
	'thank_you' => 'msg', 'button_single_text' => 'Send', 'button_color' => 'btn-primary', 'el_text_color' => 'text-white', 'icon' => 'bi-send', 'show_icon' => '1' );
$structure = array(
	$row0,
	array( 'id_' => '1', 'type' => 'step', 'step' => 1, 'name' => 'Step 1', 'amount' => 1 ),
	array( 'id_' => 'nm1', 'dataId' => 'nm1-id', 'type' => 'text', 'name' => 'Name', 'step' => 1, 'amount' => 2, 'required' => '0' ),
	array( 'id_' => 'pw1', 'dataId' => 'pw1-id', 'type' => 'password', 'name' => 'Password', 'step' => 1, 'amount' => 3, 'required' => '1', 'milen' => '8' ),
);
$wpdb->insert( $wpdb->prefix . 'emsfb_form', array(
	'form_name' => 'sec04 password e2e', 'form_structer' => str_replace( '"', '\\"', wp_json_encode( $structure, JSON_UNESCAPED_UNICODE ) ),
	'form_email' => '', 'form_type' => 'form', 'form_created_by' => '1', 'form_access_by' => '',
) );
$form_id = (int) $wpdb->insert_id;
if ( ! $form_id ) { echo "[SKIP] could not create the throwaway form\n"; exit( 0 ); }

function efb_sec04_submit( $rows, $form_id, $fn ) {
	global $wpdb;
	$sid  = $fn->efb_code_validate_create( $form_id, 0, 'visit', 0 );
	$body = wp_json_encode( array( 'action' => 'get_form_Emsfb', 'name' => 'sec04 password e2e', 'id' => (string) $form_id, 'sid' => $sid, 'page_id' => '0',
		'url' => home_url( '/' ), 'value' => json_encode( $rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), 'type' => 'form' ) );
	$base = efb_test_wp_url();
	$ch   = curl_init( $base . '/wp-json/Emsfb/v1/forms/message/add' );
	curl_setopt_array( $ch, array( CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60,
		CURLOPT_HTTPHEADER => array( 'Content-Type: application/json', 'X-WP-Nonce: ' . wp_create_nonce( 'wp_rest' ), 'sid: ' . $sid, 'form-id: ' . $form_id, 'Origin: ' . $base, 'Referer: ' . $base . '/' ) ) );
	$raw = curl_exec( $ch );
	$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	curl_close( $ch );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}emsfb_stts_ WHERE sid = %s", $sid ) );
	$data = json_decode( (string) $raw, true );
	return array( $code, is_array( $data ) ? $data : array( 'raw' => substr( (string) $raw, 0, 300 ) ) );
}
$entries = function () use ( $wpdb, $form_id ) { return $wpdb->get_col( $wpdb->prepare( "SELECT content FROM {$wpdb->prefix}emsfb_msg_ WHERE form_id = %d ORDER BY msg_id", $form_id ) ); };

/* Too short: refused, nothing stored. */
list( $c1, $b1 ) = efb_sec04_submit( array( array( 'id_' => 'nm1', 'name' => 'Name', 'value' => 'Jane', 'type' => 'text' ), array( 'id_' => 'pw1', 'name' => 'Password', 'value' => 'short', 'type' => 'password' ) ), $form_id, $fn );
t( 'a password under the minimum length is refused (its checks still see it)', 200 === $c1 && isset( $b1['data']['success'] ) && false === $b1['data']['success'] && ( $b1['data']['field_id'] ?? '' ) === 'pw1', wp_json_encode( $b1 ) );
t( 'nothing is stored for the refused answer', count( $entries() ) === 0 );

/* Good: accepted, the mask is stored. */
$secret = 'Zq7Wv-Kx"j9<tq>';
list( $c2, $b2 ) = efb_sec04_submit( array( array( 'id_' => 'nm1', 'name' => 'Name', 'value' => 'Jane Roe', 'type' => 'text' ), array( 'id_' => 'pw1', 'name' => 'Password', 'value' => $secret, 'type' => 'password' ) ), $form_id, $fn );
$ok2 = 200 === $c2 && ! empty( $b2['data']['success'] );
t( 'a good password: the form is accepted', $ok2, wp_json_encode( $b2 ) );
$all = $entries();
$content = $all ? (string) end( $all ) : '';
foreach ( array( $secret, 'Zq7Wv', 'Kx', 'j9', str_replace( '"', '@efb@dq#', $secret ) ) as $needle ) {
	t( 'the stored entry holds no part of the password (' . $needle . ')', $content !== '' && false === strpos( $content, $needle ) );
}
$rows = emsfb_json_decode_efb( $content, true );
$pw = null; $nm = null;
foreach ( (array) $rows as $r ) { if ( is_array( $r ) && ( $r['id_'] ?? '' ) === 'pw1' ) $pw = $r; if ( is_array( $r ) && ( $r['id_'] ?? '' ) === 'nm1' ) $nm = $r; }
t( 'the password row is kept with the mask (the entry shows the field was answered)', is_array( $pw ) && $pw['value'] === $MASK && $pw['type'] === 'password', wp_json_encode( $pw ) );
t( 'the other answer is stored as typed', is_array( $nm ) && $nm['value'] === 'Jane Roe' );

/* The e-mail's table: the new entry, and an entry from before 4.3.2 that still holds a password. */
require_once EMSFB_PLUGIN_DIRECTORY . 'includes/class-Emsfb-public.php';
$pub = ( new ReflectionClass( '\\Emsfb\\_Public' ) )->newInstanceWithoutConstructor();
$pub->efbFunction = $fn;
$mail_new = $pub->email_get_content_efb( (array) $rows, 'TRK1' );
t( 'the notification e-mail shows the mask for the new entry', false !== strpos( $mail_new, $MASK ) && false === strpos( $mail_new, 'Zq7Wv' ) );
$old = array( array( 'id_' => 'nm1', 'name' => 'Name', 'value' => 'Old Entry', 'type' => 'text', 'amount' => 2 ), array( 'id_' => 'pw1', 'name' => 'Password', 'value' => 'legacy-plain-123', 'type' => 'password', 'amount' => 3 ), array( 'type' => 'w_link', 'value' => home_url( '/' ), 'amount' => -1 ) );
$mail_old = $pub->email_get_content_efb( $old, 'TRK2' );
t( 'an entry saved before 4.3.2 (password as typed): the e-mail shows the mask, not the password', false !== strpos( $mail_old, $MASK ) && false === strpos( $mail_old, 'legacy-plain-123' ) && false !== strpos( $mail_old, 'Old Entry' ) );
t( 'the mask constant is four bullets', \Emsfb\_Public::PASSWORD_MASK_EFB === $MASK );

$wpdb->delete( $wpdb->prefix . 'emsfb_msg_', array( 'form_id' => $form_id ) );
$wpdb->delete( $wpdb->prefix . 'emsfb_form', array( 'form_id' => $form_id ) );
t( 'the throwaway form and its entries are removed', 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}emsfb_form WHERE form_id = %d", $form_id ) ) );

echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit( $fail ? 1 : 0 );
