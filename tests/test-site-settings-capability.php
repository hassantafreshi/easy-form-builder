<?php
/**
 * QA4-002: a user who holds only the Emsfb capability (a staff role an owner made with a role editor: read, Emsfb,
 * Emsfb_panel, Emsfb_create) keeps forms and entries, but the site-wide settings, credentials, add-ons,
 * Development Mode, the file cleanup and the Telegram / Google Sheets / onboarding-email settings are the
 * administrator's (manage_options). The plan and closing the first-run guide stay with the plugin's own admin permission
 * (owner decision, F18). remove_addons_Emsfb takes an add-on code of the catalogue only; it used to set
 * ANY settings key to 0 (emailSupporter, siteKey, activeCode...), even for an administrator.
 *
 *   A. Core handlers, called as admin-ajax would call them, as the staff user: each is refused and changes nothing.
 *   B. The same user keeps the builder: heartbeat and the forms list answer.
 *   C. remove_addons_Emsfb as an administrator: a settings key or an unknown code is refused, a real add-on code works.
 *   D. Telegram and Google Sheets (vendor/): a read action is refused to the staff user; every settings action of the
 *      two add-ons goes through the manage_options gate (source check).
 *
 * Every option and setting touched is restored, and the temporary role and user are removed, also on failure.
 * Nothing leaves the machine (pre_http_request answers every request with an error) and the file cleanup sees an
 * empty uploads folder.
 *
 * Run: php tests/test-site-settings-capability.php        (needs WordPress: tests/wp-env.php)
 */

require_once __DIR__ . '/wp-env.php';
$wp_load = efb_test_wp_load();
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}
if ( ! defined( 'DOING_AJAX' ) ) define( 'DOING_AJAX', true );
if ( ! defined( 'WP_ADMIN' ) ) define( 'WP_ADMIN', true );
require_once $wp_load;
require_once ABSPATH . 'wp-admin/includes/file.php'; // list_files() for the file cleanup handler
if ( ! defined( 'EMSFB_PLUGIN_DIRECTORY' ) || ! function_exists( 'get_efbFunction' ) ) {
	echo "[SKIP] Easy Form Builder is not active in this WordPress installation.\n";
	exit( 0 );
}
if ( ! class_exists( '\\Emsfb\\Admin' ) ) require_once EMSFB_PLUGIN_DIRECTORY . 'includes/admin/class-Emsfb-admin.php';
if ( ! class_exists( '\\Emsfb\\Admin_Bar_Development_Mode' ) ) require_once EMSFB_PLUGIN_DIRECTORY . 'includes/class-Emsfb-admin-bar.php';

$passed = 0;
$failed = 0;
function efb_ssc_check( $label, $ok, $detail = '' ) {
	global $passed, $failed;
	if ( $ok ) { $passed++; echo "[PASS] {$label}\n"; return; }
	$failed++;
	echo "[FAIL] {$label}" . ( $detail !== '' ? " -- {$detail}" : '' ) . "\n";
}

/** Calls $callable as admin-ajax would, with $post as the request; returns the decoded JSON (array) or the raw text. */
function efb_ssc_call( $callable, array $post ) {
	$_POST    = $post;
	$_REQUEST = $post;
	ob_start();
	try {
		call_user_func( $callable );
	} catch ( Exception $e ) {
		// wp_die() from wp_send_json_*().
	}
	$out = ob_get_clean();
	$j   = json_decode( $out, true );
	return is_array( $j ) ? $j : array( '_raw' => $out );
}

/** A refusal in any of the shapes these handlers use: HTTP-style error, or success:true wrapping success:false. */
function efb_ssc_refused( $r ) {
	if ( isset( $r['_raw'] ) ) return $r['_raw'] === '-1' || $r['_raw'] === '' || stripos( $r['_raw'], 'secure' ) !== false;
	if ( empty( $r['success'] ) ) return true;
	return isset( $r['data']['success'] ) && $r['data']['success'] === false;
}

function efb_ssc_settings() {
	\Emsfb::get_setting_Emsfb( '_clear_cache' );
	$s = get_setting_Emsfb( 'decoded' );
	return is_object( $s ) ? $s : new stdClass();
}

$admin_ids = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( empty( $admin_ids ) ) { echo "[SKIP] No administrator account.\n"; exit( 0 ); }
$admin_id = (int) $admin_ids[0];

$role_key   = 'efb_ssc_staff_test';
$restore_user = get_current_user_id();
$opt_names  = array( 'emsfb_dev_mode', 'emsfb_onboarding_pending', 'emsfb_onboarding_initial_install', 'emsfb_onboarding_completed_at', 'emsfb_onboarding_dismissed_at', 'emsfb_pro', 'emsfb_email_status' );
$opt_before = array();
foreach ( $opt_names as $n ) $opt_before[ $n ] = get_option( $n, null );
$fn = get_efbFunction();
$s0 = efb_ssc_settings();
$keep = array();
foreach ( array( 'emailSupporter', 'siteKey', 'activeCode', 'AdnSPF', 'smtp' ) as $k ) $keep[ $k ] = property_exists( $s0, $k ) ? $s0->$k : '__unset__';

$http_calls = 0;
$no_http = function () use ( &$http_calls ) { $http_calls++; return new WP_Error( 'efb_test_offline', 'offline (test)' ); };
$thrower = function () { return function () { throw new Exception( 'wp_die' ); }; };
$tmp_up = sys_get_temp_dir() . '/efb-ssc-uploads-' . getmypid();
@mkdir( $tmp_up );
$empty_uploads = function ( $u ) use ( $tmp_up ) { $u['basedir'] = $tmp_up; $u['path'] = $tmp_up; return $u; };
add_filter( 'pre_http_request', $no_http, 1 );
add_filter( 'wp_die_ajax_handler', $thrower );
add_filter( 'wp_die_handler', $thrower );
add_filter( 'upload_dir', $empty_uploads, 999 );

$staff_id = 0;
try {
	remove_role( $role_key );
	add_role( $role_key, 'EFB staff (test)', array( 'read' => true, 'Emsfb' => true, 'Emsfb_panel' => true, 'Emsfb_create' => true ) );
	$staff_id = wp_insert_user( array( 'user_login' => 'efb_ssc_staff_' . getmypid(), 'user_pass' => wp_generate_password( 20 ), 'user_email' => 'efb-ssc-' . getmypid() . '@example.test', 'role' => $role_key ) );
	if ( is_wp_error( $staff_id ) ) throw new RuntimeException( 'cannot create the staff user: ' . $staff_id->get_error_message() );
	wp_set_current_user( $staff_id );
	efb_ssc_check( 'A0 the staff user holds Emsfb and not manage_options', current_user_can( 'Emsfb' ) && ! current_user_can( 'manage_options' ) );

	$ref   = new ReflectionClass( '\\Emsfb\\Admin' );
	$admin = $ref->newInstanceWithoutConstructor();
	$rest  = wp_create_nonce( 'wp_rest' );

	// A. core handlers as the staff user.
	$r = efb_ssc_call( array( $admin, 'remove_addons_Emsfb' ), array( 'action' => 'remove_addons_Emsfb', 'nonce' => $rest, 'value' => 'emailSupporter' ) );
	$s = efb_ssc_settings();
	efb_ssc_check( 'A1 remove_addons_Emsfb value=emailSupporter is refused', efb_ssc_refused( $r ), wp_json_encode( $r ) );
	efb_ssc_check( 'A2 ... and emailSupporter is unchanged', ( property_exists( $s, 'emailSupporter' ) ? $s->emailSupporter : '__unset__' ) === $keep['emailSupporter'] );
	$r = efb_ssc_call( array( $admin, 'remove_addons_Emsfb' ), array( 'action' => 'remove_addons_Emsfb', 'nonce' => $rest, 'value' => 'AdnSPF' ) );
	efb_ssc_check( 'A3 remove_addons_Emsfb of a real add-on is refused to the staff user', efb_ssc_refused( $r ), wp_json_encode( $r ) );

	$http_calls = 0;
	$r = efb_ssc_call( array( $admin, 'add_addons_Emsfb' ), array( 'action' => 'add_addons_Emsfb', 'nonce' => $rest, 'value' => 'AdnSPF' ) );
	efb_ssc_check( 'A4 add_addons_Emsfb is refused to the staff user before any download request', efb_ssc_refused( $r ) && $http_calls === 0, wp_json_encode( $r ) . " http={$http_calls}" );

	$r = efb_ssc_call( array( $admin, 'clear_garbeg_admin' ), array( 'action' => 'clear_garbeg_Emsfb', 'nonce' => $rest ) );
	efb_ssc_check( 'A5 clear_garbeg_Emsfb (the uploads cleanup) is refused', efb_ssc_refused( $r ), wp_json_encode( $r ) );

	$http_calls = 0;
	$r = efb_ssc_call( array( $admin, 'check_email_server_admin' ), array( 'action' => 'check_email_server_efb', 'nonce' => $rest, 'email' => 'efb-ssc@example.test' ) );
	efb_ssc_check( 'A6 check_email_server_efb (writes the e-mail settings) is refused', efb_ssc_refused( $r ) && $http_calls === 0, wp_json_encode( $r ) );

	$cur_mail = is_string( $keep['emailSupporter'] ) && is_email( $keep['emailSupporter'] ) ? $keep['emailSupporter'] : 'efb-ssc@example.test';
	$r = efb_ssc_call( array( $admin, 'efb_save_onboarding_email' ), array( 'action' => 'efb_save_onboarding_email', 'nonce' => $rest, 'email' => $cur_mail ) );
	efb_ssc_check( 'A7 efb_save_onboarding_email is refused', efb_ssc_refused( $r ), wp_json_encode( $r ) );

	// Owner decisions kept: closing the first-run guide is a UI flag of the plugin's admin screens, and the plan may be
	// changed by the plugin's own admin permission (F18 open question 2): both stay open to the Emsfb capability.
	update_option( 'emsfb_onboarding_initial_install', 1, false );
	update_option( 'emsfb_onboarding_pending', 1, false );
	$r = efb_ssc_call( array( $admin, 'efb_dismiss_onboarding' ), array( 'action' => 'efb_dismiss_onboarding', 'nonce' => $rest ) );
	efb_ssc_check( 'A8 the staff user may still close the first-run guide (a UI flag, owner decision)', ! efb_ssc_refused( $r ), wp_json_encode( $r ) );
	efb_ssc_check( 'A9 the staff user may still change the plan (F18 owner decision: efb_can_change_plan_efb())', function_exists( 'efb_can_change_plan_efb' ) && efb_can_change_plan_efb() );

	$dev_before = (string) get_option( 'emsfb_dev_mode', '0' );
	$bar = new \Emsfb\Admin_Bar_Development_Mode();
	$r = efb_ssc_call( array( $bar, 'toggle_development_mode' ), array( 'action' => 'efb_toggle_development_mode', 'nonce' => wp_create_nonce( 'efb_toggle_development_mode' ) ) );
	efb_ssc_check( 'A12 efb_toggle_development_mode is refused and the mode is unchanged', efb_ssc_refused( $r ) && (string) get_option( 'emsfb_dev_mode', '0' ) === $dev_before, wp_json_encode( $r ) );
	efb_ssc_check( 'A13 the user helper user_permission_efb_site_settings() is false for the staff user', method_exists( $fn, 'user_permission_efb_site_settings' ) && ! $fn->user_permission_efb_site_settings() );

	// B. the builder stays theirs.
	$r = efb_ssc_call( array( $admin, 'heartbeat_Emsfb' ), array( 'action' => 'heartbeat_Emsfb', 'nonce' => $rest ) );
	efb_ssc_check( 'B1 heartbeat_Emsfb still answers the staff user', ! efb_ssc_refused( $r ), wp_json_encode( $r ) );
	efb_ssc_check( 'B2 user_permission_efb_admin_dashboard() (forms and entries) is still true for them', $fn->user_permission_efb_admin_dashboard() );

	// C. remove_addons_Emsfb as an administrator.
	wp_set_current_user( $admin_id );
	$rest = wp_create_nonce( 'wp_rest' );
	$r = efb_ssc_call( array( $admin, 'remove_addons_Emsfb' ), array( 'action' => 'remove_addons_Emsfb', 'nonce' => $rest, 'value' => 'emailSupporter' ) );
	$s = efb_ssc_settings();
	efb_ssc_check( 'C1 value=emailSupporter is refused even for an administrator', efb_ssc_refused( $r ) && ( property_exists( $s, 'emailSupporter' ) ? $s->emailSupporter : '__unset__' ) === $keep['emailSupporter'], wp_json_encode( $r ) );
	$r = efb_ssc_call( array( $admin, 'remove_addons_Emsfb' ), array( 'action' => 'remove_addons_Emsfb', 'nonce' => $rest, 'value' => 'siteKey' ) );
	$s = efb_ssc_settings();
	efb_ssc_check( 'C2 value=siteKey is refused', efb_ssc_refused( $r ) && ( property_exists( $s, 'siteKey' ) ? $s->siteKey : '__unset__' ) === $keep['siteKey'], wp_json_encode( $r ) );
	$r = efb_ssc_call( array( $admin, 'remove_addons_Emsfb' ), array( 'action' => 'remove_addons_Emsfb', 'nonce' => $rest, 'value' => 'AdnZZZ' ) );
	efb_ssc_check( 'C3 an add-on code outside the catalogue is refused', efb_ssc_refused( $r ), wp_json_encode( $r ) );
	// A real code is accepted (AdnATC has no files and no side effect beyond its flag, which is put back below).
	$atc = property_exists( $s, 'AdnATC' ) ? $s->AdnATC : '__unset__';
	$r = efb_ssc_call( array( $admin, 'remove_addons_Emsfb' ), array( 'action' => 'remove_addons_Emsfb', 'nonce' => $rest, 'value' => 'AdnATC' ) );
	efb_ssc_check( 'C4 an add-on code of the catalogue is accepted for an administrator', ! efb_ssc_refused( $r ), wp_json_encode( $r ) );
	if ( $atc !== '__unset__' && (int) $atc !== 0 ) { $s = efb_ssc_settings(); $s->AdnATC = $atc; $fn->set_setting_Emsfb( $s, isset( $s->emailSupporter ) ? $s->emailSupporter : '' ); }

	// D. Telegram and Google Sheets.
	wp_set_current_user( $staff_id );
	$tlg = EMSFB_PLUGIN_DIRECTORY . 'vendor/telegram/class-Emsfb-telegram.php';
	if ( is_readable( $tlg ) ) {
		if ( ! class_exists( '\\Emsfb\\telegramlistefb' ) ) require_once $tlg;
		$t = ( new ReflectionClass( '\\Emsfb\\telegramlistefb' ) )->newInstanceWithoutConstructor();
		$r = efb_ssc_call( array( $t, 'ajax_load_telegram_activity_efb' ), array( 'action' => 'load_telegram_activity_efb', 'nonce' => wp_create_nonce( 'telegram_efb_actions' ) ) );
		efb_ssc_check( 'D1 Telegram: the activity log is refused to the staff user', efb_ssc_refused( $r ), wp_json_encode( $r ) );
		$src = (string) file_get_contents( $tlg );
		foreach ( array( 'ajax_test_telegram_connection_efb', 'ajax_send_telegram_test_efb', 'ajax_save_telegram_settings_efb', 'ajax_load_telegram_activity_efb', 'ajax_clear_telegram_activity_efb', 'verify_telegram_bot_ajax_efb' ) as $m ) {
			$body = preg_match( '/function ' . $m . '\s*\(\s*\)\s*\{(.{0,1600})/s', $src, $mm ) ? $mm[1] : '';
			efb_ssc_check( "D2 Telegram {$m} checks manage_options (can_manage_settings_efb)", strpos( $body, 'self::can_manage_settings_efb()' ) !== false && strpos( $body, 'user_permission_efb_admin_dashboard' ) === false );
		}
		efb_ssc_check( 'D3 Telegram: can_manage_settings_efb() is manage_options', method_exists( '\\Emsfb\\telegramlistefb', 'can_manage_settings_efb' ) && ! \Emsfb\telegramlistefb::can_manage_settings_efb() );
	} else {
		echo "[SKIP] D1-D3 the Telegram add-on is not in vendor/\n";
	}
	$gs = EMSFB_PLUGIN_DIRECTORY . 'vendor/googlesheet/class-Emsfb-googlesheet.php';
	if ( is_readable( $gs ) ) {
		if ( ! class_exists( '\\Emsfb\\GoogleSheetAddon' ) ) require_once $gs;
		$g = ( new ReflectionClass( '\\Emsfb\\GoogleSheetAddon' ) )->newInstanceWithoutConstructor();
		$forms = $GLOBALS['wpdb']->get_var( "SELECT form_id FROM {$GLOBALS['wpdb']->prefix}emsfb_form ORDER BY form_id ASC LIMIT 1" );
		$r = efb_ssc_call( array( $g, 'ajax_get_form_fields' ), array( 'action' => 'efb_gs_get_form_fields', 'nonce' => wp_create_nonce( 'wp_rest' ), 'form_id' => (int) $forms ) );
		efb_ssc_check( 'D4 Google Sheets: a form binding screen action is refused to the staff user', efb_ssc_refused( $r ) && isset( $r['data']['message'] ), wp_json_encode( $r ) );
		$src  = (string) file_get_contents( $gs );
		$body = preg_match( '/function can_manage_page\(\): bool\s*\{(.*?)\n    \}/s', $src, $mm ) ? $mm[1] : '';
		efb_ssc_check( 'D5 Google Sheets: the screens and every action need manage_options', strpos( $body, "current_user_can('manage_options')" ) !== false && strpos( $body, "'Emsfb'" ) === false && strpos( $src, "'manage_options', // QA4-002\n            'Emsfb_googlesheet_efb'" ) !== false );
	} else {
		echo "[SKIP] D4-D5 the Google Sheets add-on is not in vendor/\n";
	}
} catch ( Throwable $e ) {
	$failed++;
	echo '[FAIL] unexpected ' . get_class( $e ) . ': ' . $e->getMessage() . ' @' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n";
} finally {
	remove_filter( 'pre_http_request', $no_http, 1 );
	remove_filter( 'wp_die_ajax_handler', $thrower );
	remove_filter( 'wp_die_handler', $thrower );
	remove_filter( 'upload_dir', $empty_uploads, 999 );
	@rmdir( $tmp_up );
	wp_set_current_user( $admin_id );
	// The settings row: put back any key this run may have changed (only on a failing, older handler).
	$s = efb_ssc_settings();
	$dirty = false;
	foreach ( $keep as $k => $v ) {
		$now = property_exists( $s, $k ) ? $s->$k : '__unset__';
		if ( $now === $v ) continue;
		if ( $v === '__unset__' ) unset( $s->$k ); else $s->$k = $v;
		$dirty = true;
	}
	if ( $dirty ) $fn->set_setting_Emsfb( $s, isset( $s->emailSupporter ) ? $s->emailSupporter : '' );
	foreach ( $opt_before as $n => $v ) {
		if ( $v === null ) delete_option( $n ); else update_option( $n, $v, false );
	}
	if ( $staff_id && ! is_wp_error( $staff_id ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $staff_id );
	}
	remove_role( $role_key );
	wp_set_current_user( $restore_user );
	$s = efb_ssc_settings();
	$ok = true;
	foreach ( $keep as $k => $v ) $ok = $ok && ( ( property_exists( $s, $k ) ? $s->$k : '__unset__' ) === $v );
	efb_ssc_check( 'Z settings, options, role and user restored', $ok && get_role( $role_key ) === null );
}

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
