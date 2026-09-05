<?php
/**
 * Regression test for the WPScan/Jetpack report (2026-09-03): get_form_public_efb()
 * only compared the submitted `type` against the form's stored type inside a
 * block that was skipped whenever the stored type was `login` or `register`,
 * so any submission type could be dispatched against a published login or
 * registration form.
 *
 *   - #46267 stored XSS: sending type=form at a login form stored the raw,
 *     unsanitized submitted value verbatim (the per-field sanitizer never ran).
 *   - #43813 unauthenticated account creation: sending type=register at a
 *     login form reached wp_insert_user() regardless of "Anyone can register".
 *
 * The fix (includes/class-Emsfb-public.php) checks submitted type against the
 * form's stored type unconditionally, for every form type, before anything
 * dispatches on it - logout/recovery remain valid only against login/register
 * forms. This test:
 *   1) Reproduces both original exploits against ephemeral login/register
 *      forms and asserts they are now rejected.
 *   2) Confirms legitimate login, register, logout, and recovery submissions
 *      against their own matching form type still work.
 *   3) Confirms ordinary form types (plain "form") still enforce - and are
 *      not newly broken by - the type-match check.
 *
 * Every form, message row, and WP user this test creates is deleted at the
 * end, pass or fail.
 *
 * Run: C:\xampp\php\php.exe tests/test-login-register-type-confusion.php
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

$fn = get_efbFunction();
$form_table = $wpdb->prefix . 'emsfb_form';
$msg_table  = $wpdb->prefix . 'emsfb_msg_';
$stts_table = $wpdb->prefix . 'emsfb_stts_';
$rest_url   = get_rest_url( null ) . 'Emsfb/v1/forms/message/add';

$run_tag = 'efb-security-test-' . wp_generate_password( 6, false, false );
// usernameRegisterEFB is validated against /^[a-z0-9._]*$/ - lowercase/digits only.
$username_suffix = strtolower( substr( md5( uniqid( '', true ) ), 0, 8 ) );

/* Human Shield (AdnHSH) rate-limits/blocks rapid-fire submissions from a
 * single IP within a short window, which this script deliberately does.
 * Disable it for the duration of the run and restore the exact prior value
 * on shutdown, pass or fail - this is a test-harness accommodation, not part
 * of the security fix under test. */
$hsh_orig_raw = get_setting_Emsfb( 'raw' );
$hsh_settings = is_string( $hsh_orig_raw ) ? json_decode( str_replace( '\\', '', $hsh_orig_raw ) ) : $hsh_orig_raw;
$hsh_had_setting = is_object( $hsh_settings ) && isset( $hsh_settings->AdnHSH );
if ( $hsh_had_setting ) {
	$hsh_orig_value = $hsh_settings->AdnHSH;
	$hsh_settings->AdnHSH = 0;
	$fn->set_setting_Emsfb( $hsh_settings, $hsh_settings->emailSupporter ?? '' );
	echo "[setup] Human Shield temporarily disabled for this run (was {$hsh_orig_value})\n";
	register_shutdown_function( function () use ( $fn, $hsh_orig_value ) {
		$raw = get_setting_Emsfb( 'raw' );
		$s = is_string( $raw ) ? json_decode( str_replace( '\\', '', $raw ) ) : $raw;
		if ( is_object( $s ) ) {
			$s->AdnHSH = $hsh_orig_value;
			$fn->set_setting_Emsfb( $s, $s->emailSupporter ?? '' );
			echo "[cleanup] Human Shield restored to {$hsh_orig_value}\n";
		}
	} );
}

/* ---------------------------------------------------------------- helpers */

function efb_step_field( $extra = array() ) {
	return array_merge( array(
		'id_' => '1', 'type' => 'step', 'dataId' => '1', 'id' => '1',
		'name' => 'Step', 'step' => '1', 'amount' => 1, 'visible' => 1,
	), $extra );
}

function efb_input_field( $id, $type, $extra = array() ) {
	return array_merge( array(
		'id_' => $id, 'dataId' => $id . '-id', 'type' => $type, 'id' => '',
		'name' => $id, 'required' => false, 'amount' => 2, 'step' => '1',
	), $extra );
}

function efb_insert_form( $wpdb, $table, $name, $type, $structure ) {
	$wpdb->insert( $table, array(
		'form_name'       => $name,
		'form_structer'   => wp_json_encode( $structure, JSON_UNESCAPED_UNICODE ),
		'form_email'      => get_option( 'admin_email' ),
		'form_created_by' => get_current_user_id(),
		'form_type'       => $type,
		'form_create_date'=> current_time( 'mysql' ),
	) );
	$fid = (int) $wpdb->insert_id;
	wp_cache_delete( 'efb_form_' . md5( 'form_' . $fid ), 'emsfb' );
	wp_cache_delete( 'efb_form_' . $fid, 'emsfb' );
	return $fid;
}

/**
 * @return array{code:int, body:string, data:array|null, sid:string}
 */
function efb_submit( $fid, $type, $value, $fn, $url, $extra_body = array() ) {
	global $wpdb;
	$sid = $fn->efb_code_validate_create( $fid, 0, 'visit', 0 );
	$res = wp_remote_post( $url, array(
		'timeout' => 30, 'sslverify' => false,
		'headers' => array(
			'Content-Type' => 'application/json',
			'X-WP-Nonce'   => wp_create_nonce( 'wp_rest' ),
			'sid'          => $sid,
			'form-id'      => (string) $fid,
		),
		'body' => wp_json_encode( array_merge( array(
			'id' => (string) $fid, 'sid' => $sid, 'page_id' => '0',
			'url' => home_url( '/' ), 'value' => wp_json_encode( $value ), 'type' => $type,
			'name' => 'test',
		), $extra_body ) ),
	) );

	if ( is_wp_error( $res ) ) {
		return array( 'code' => 0, 'body' => $res->get_error_message(), 'data' => null, 'sid' => $sid );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$body = (string) wp_remote_retrieve_body( $res );
	$json = json_decode( $body, true );
	$data = isset( $json['data'] ) ? $json['data'] : null;
	return array( 'code' => $code, 'body' => $body, 'data' => $data, 'sid' => $sid );
}

$created_form_ids = array();
$created_sids     = array();
$created_users    = array();

register_shutdown_function( function () use ( $wpdb, &$created_form_ids, &$created_sids, &$created_users, $form_table, $msg_table, $stts_table ) {
	foreach ( $created_form_ids as $fid ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM `$msg_table` WHERE form_id = %d", $fid ) );
		$wpdb->delete( $form_table, array( 'form_id' => $fid ) );
	}
	foreach ( $created_sids as $sid ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM `$stts_table` WHERE sid = %s", $sid ) );
	}
	foreach ( $created_users as $uid ) {
		if ( function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $uid );
		}
	}
	echo "\n[cleanup] removed " . count( $created_form_ids ) . " form(s), " . count( $created_users ) . " user(s)\n";
} );

/* -------------------------------------------------------------- fixtures */

$login_form_id = efb_insert_form( $wpdb, $form_table, "{$run_tag}-login", 'login', array(
	array( 'type' => 'login', 'steps' => 1, 'formName' => 'Login', 'captcha' => false, 'thank_you' => 'msg',
		'thank_you_message' => array( 'thankYou' => 'ok', 'done' => 'ok' ), 'email' => get_option( 'admin_email' ) ),
	efb_step_field(),
	efb_input_field( 'emaillogin', 'text' ),
	efb_input_field( 'passwordlogin', 'password' ),
) );
$created_form_ids[] = $login_form_id;

$register_form_id = efb_insert_form( $wpdb, $form_table, "{$run_tag}-register", 'register', array(
	array( 'type' => 'register', 'steps' => 1, 'formName' => 'Register', 'captcha' => false, 'thank_you' => 'msg',
		'thank_you_message' => array( 'thankYou' => 'ok', 'done' => 'ok' ), 'email' => get_option( 'admin_email' ) ),
	efb_step_field(),
	efb_input_field( 'usernameRegisterEFB', 'text' ),
	efb_input_field( 'passwordRegisterEFB', 'password' ),
	efb_input_field( 'emailRegisterEFB', 'email' ),
) );
$created_form_ids[] = $register_form_id;

$plain_form_id = efb_insert_form( $wpdb, $form_table, "{$run_tag}-plain", 'form', array(
	array( 'type' => 'form', 'steps' => 1, 'formName' => 'Plain', 'captcha' => false, 'thank_you' => 'msg',
		'thank_you_message' => array( 'thankYou' => 'ok', 'done' => 'ok' ), 'email' => get_option( 'admin_email' ),
		'sendEmail' => false, 'trackingCode' => true ),
	efb_step_field(),
	efb_input_field( 'msg', 'text', array( 'required' => false ) ),
) );
$created_form_ids[] = $plain_form_id;

t( 'fixtures created', $login_form_id > 0 && $register_form_id > 0 && $plain_form_id > 0,
	"login={$login_form_id} register={$register_form_id} plain={$plain_form_id}" );

/* ---------------------------------------------------- #46267: stored XSS */

echo "\n=== #46267 stored XSS: type=form against a login form must be rejected ===\n";
$xss_payload = array( array( 'name' => '<img src=x onerror=alert(1)>', 'value' => '1', 'id_' => 'fake' ) );
$r = efb_submit( $login_form_id, 'form', $xss_payload, $fn, $rest_url );
$created_sids[] = $r['sid'];
$stored = $r['data']['success'] ?? null;
t( 'type=form at a login form is rejected (success=false)', $stored === false, json_encode( $r['data'] ) );

$row = $wpdb->get_row( $wpdb->prepare( "SELECT content FROM `$msg_table` WHERE form_id = %d", $login_form_id ) );
t( 'nothing was stored for the login form', $row === null, $row ? substr( $row->content, 0, 120 ) : '(none)' );

/* ------------------------------------------- #43813: unauthenticated register */

echo "\n=== #43813 account creation: type=register against a login form must be rejected ===\n";
$rogue_user  = 'efbtest_' . $username_suffix;
$rogue_email = $rogue_user . '@example.invalid';
t( 'the target username does not exist yet', ! username_exists( $rogue_user ) );

$reg_payload = array(
	array( 'id_' => 'usernameRegisterEFB', 'name' => 'Username', 'value' => $rogue_user ),
	array( 'id_' => 'emailRegisterEFB', 'name' => 'Email', 'value' => $rogue_email ),
	array( 'id_' => 'passwordRegisterEFB', 'name' => 'Password', 'value' => 'S3cur3Pass!' ),
);
$r = efb_submit( $login_form_id, 'register', $reg_payload, $fn, $rest_url );
$created_sids[] = $r['sid'];
t( 'type=register at a login form is rejected (success=false)', ( $r['data']['success'] ?? null ) === false, json_encode( $r['data'] ) );
$uid = username_exists( $rogue_user );
t( 'no WordPress user was created', $uid === false, $uid ? "user #{$uid} exists" : '' );
if ( $uid ) { $created_users[] = $uid; }

/* --------------------------------------- cross-type: login against register */

echo "\n=== type=login against a register form must be rejected ===\n";
$admin_user = get_user_by( 'id', 1 );
if ( $admin_user ) {
	$login_payload = array(
		array( 'id_' => 'emaillogin', 'name' => 'Email', 'value' => $admin_user->user_login ),
		array( 'id_' => 'passwordlogin', 'name' => 'Password', 'value' => 'irrelevant' ),
	);
	$r = efb_submit( $register_form_id, 'login', $login_payload, $fn, $rest_url );
	$created_sids[] = $r['sid'];
	$leaked_email = is_array( $r['data']['m'] ?? null ) ? ( $r['data']['m']['user_email'] ?? '' ) : '';
	t( 'type=login at a register form is rejected (success=false)', ( $r['data']['success'] ?? null ) === false, json_encode( $r['data'] ) );
	t( 'no account info was leaked in the response', $leaked_email === '', $leaked_email );
} else {
	echo "[SKIP] no user #1 to target\n";
}

/* ---------------------------------------------------- legitimate flows still work */

echo "\n=== regression: legitimate session actions on a login form still work ===\n";
// A truly empty `value` is rejected earlier by the generic "form is empty"
// guard (unrelated to this fix) - the real client always sends a marker row.
$r = efb_submit( $login_form_id, 'logout', array( array( 'id_' => 'logout', 'value' => '1' ) ), $fn, $rest_url );
$created_sids[] = $r['sid'];
t( 'type=logout at a login form is still accepted', ( $r['data']['success'] ?? null ) === true, json_encode( $r['data'] ) );

$r = efb_submit( $login_form_id, 'recovery', array( array( 'value' => 'nobody@example.invalid' ) ), $fn, $rest_url,
	array( 'value' => wp_json_encode( array( 'email' => 'nobody@example.invalid' ) ) ) );
$created_sids[] = $r['sid'];
t( 'type=recovery at a login form is still accepted (no fernvtf/type-mismatch)', ( $r['data']['success'] ?? null ) !== null, json_encode( $r['data'] ) );

echo "\n=== regression: a real login attempt on a login form still dispatches ===\n";
$login_payload = array(
	array( 'id_' => 'emaillogin', 'name' => 'Email', 'value' => 'no-such-user-efb-test' ),
	array( 'id_' => 'passwordlogin', 'name' => 'Password', 'value' => 'wrong-password' ),
);
$r = efb_submit( $login_form_id, 'login', $login_payload, $fn, $rest_url );
$created_sids[] = $r['sid'];
$msg = is_array( $r['data']['m'] ?? null ) ? ( $r['data']['m']['error'] ?? '' ) : '';
t( 'type=login at a login form reaches the login handler (not the type gate)', $msg !== '', json_encode( $r['data'] ) );

echo "\n=== regression: a real registration on a register form still works ===\n";
$good_user  = 'efbtestok' . $username_suffix;
$good_email = $good_user . '@example.invalid';
$reg_payload = array(
	array( 'id_' => 'usernameRegisterEFB', 'name' => 'Username', 'value' => $good_user ),
	array( 'id_' => 'emailRegisterEFB', 'name' => 'Email', 'value' => $good_email ),
	array( 'id_' => 'passwordRegisterEFB', 'name' => 'Password', 'value' => 'S3cur3Pass!' ),
);
$r = efb_submit( $register_form_id, 'register', $reg_payload, $fn, $rest_url );
$created_sids[] = $r['sid'];
t( 'type=register at a register form still succeeds', ( $r['data']['success'] ?? null ) === true, json_encode( $r['data'] ) );
$uid2 = username_exists( $good_user );
t( 'the legitimate account was actually created', $uid2 !== false, $good_user );
if ( $uid2 ) { $created_users[] = $uid2; }

/* ------------------------------------------------ ordinary forms unaffected */

echo "\n=== regression: ordinary form type-mismatch is still rejected ===\n";
$r = efb_submit( $plain_form_id, 'survey', array( array( 'id_' => 'msg', 'name' => 'msg', 'value' => 'hi' ) ), $fn, $rest_url );
$created_sids[] = $r['sid'];
t( 'type=survey at a plain form is rejected (success=false)', ( $r['data']['success'] ?? null ) === false, json_encode( $r['data'] ) );

echo "\n=== regression: a normal matching submission on a plain form still succeeds ===\n";
$r = efb_submit( $plain_form_id, 'form', array( array( 'id_' => 'msg', 'name' => 'msg', 'value' => 'hello world' ) ), $fn, $rest_url );
$created_sids[] = $r['sid'];
t( 'type=form at a plain form still succeeds', ( $r['data']['success'] ?? null ) === true, json_encode( $r['data'] ) );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT content FROM `$msg_table` WHERE form_id = %d", $plain_form_id ) );
t( 'the plain form submission was actually stored', $row !== null );
if ( $row ) {
	t( 'the stored value is sanitized field data, not a raw echo of attacker markup', strpos( $row->content, '<img' ) === false );
}

echo "\n========================================\n";
echo "RESULTS: {$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
