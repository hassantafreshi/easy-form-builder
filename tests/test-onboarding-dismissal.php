<?php
/**
 * Regression test: closing the first-run email step is a lasting "not now".
 *
 * After a plan was chosen, the email-delivery step of the setup guide reopened
 * on every Panel and Create load until a delivery test had been run - closing
 * it with x or Escape only hid it for that one page view. The x button and
 * Escape now call efb_setup_overlay_user_close_efb(), which posts
 * efb_dismiss_onboarding while the email step is on screen.
 *
 *   A. Admin::efb_dismiss_onboarding() - what it writes, and what it must not:
 *      completion (emsfb_onboarding_completed_at) keeps meaning a test ran.
 *   B. Who may call it.
 *   C. Wiring checked against the source: a new install restarts the guide,
 *      and only the admin's own x / Escape - not the code's own closes after a
 *      plan is saved or Finish is pressed - counts as a dismissal.
 *
 * Every option touched is restored, including on failure.
 *
 * Run: php tests/test-onboarding-dismissal.php
 *      EFB_WP_LOAD=c:/xampp/htdocs/efb-divi/wp-load.php php tests/test-onboarding-dismissal.php
 */

$wp_load = getenv( 'EFB_WP_LOAD' ) ?: dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}

if ( ! defined( 'DOING_AJAX' ) ) {
	// wp_send_json_*() only reaches wp_die() - which this test catches - on AJAX requests.
	define( 'DOING_AJAX', true );
}

require_once $wp_load;

if ( ! defined( 'EMSFB_PLUGIN_DIRECTORY' ) || ! function_exists( 'emsfb_onboarding_pending_efb' ) ) {
	echo "[SKIP] Easy Form Builder is not active in this WordPress installation.\n";
	exit( 0 );
}

if ( ! class_exists( '\\Emsfb\\Admin' ) ) {
	require_once EMSFB_PLUGIN_DIRECTORY . 'includes/admin/class-Emsfb-admin.php';
}

$passed = 0;
$failed = 0;

function efb_dismiss_check( $label, $condition, $detail = '' ) {
	global $passed, $failed;
	if ( $condition ) {
		$passed++;
		echo "[PASS] {$label}\n";
		return;
	}
	$failed++;
	echo "[FAIL] {$label}" . ( '' !== $detail ? " -- {$detail}" : '' ) . "\n";
}

/** Calls the real handler as admin-ajax would and returns the decoded JSON. */
function efb_dismiss_call( $nonce = null ) {
	$_POST = array(
		'action' => 'efb_dismiss_onboarding',
		'nonce'  => null === $nonce ? wp_create_nonce( 'wp_rest' ) : $nonce,
	);
	$_REQUEST   = $_POST;
	$reflection = new ReflectionClass( '\\Emsfb\\Admin' );
	$admin      = $reflection->newInstanceWithoutConstructor();
	ob_start();
	try {
		$admin->efb_dismiss_onboarding();
	} catch ( Exception $e ) {
		// wp_die() from wp_send_json_*().
	}
	$decoded = json_decode( ob_get_clean(), true );
	return is_array( $decoded ) ? $decoded : array();
}

function efb_dismiss_state( $pending, $completed_at = null, $dismissed_at = null ) {
	update_option( 'emsfb_onboarding_initial_install', 1, false );
	update_option( 'emsfb_onboarding_pending', $pending, false );
	null === $completed_at ? delete_option( 'emsfb_onboarding_completed_at' ) : update_option( 'emsfb_onboarding_completed_at', $completed_at, false );
	null === $dismissed_at ? delete_option( 'emsfb_onboarding_dismissed_at' ) : update_option( 'emsfb_onboarding_dismissed_at', $dismissed_at, false );
}

$option_names = array( 'emsfb_onboarding_pending', 'emsfb_onboarding_initial_install', 'emsfb_onboarding_completed_at', 'emsfb_onboarding_dismissed_at' );
$restore      = array();
foreach ( $option_names as $name ) {
	$restore[ $name ] = get_option( $name, null );
}
$restore_user = get_current_user_id();

$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( empty( $admins ) ) {
	echo "[SKIP] No administrator account to run the handler as.\n";
	exit( 0 );
}

$thrower = function () {
	return function () { throw new Exception( 'wp_die' ); };
};
add_filter( 'wp_die_ajax_handler', $thrower );
add_filter( 'wp_die_handler', $thrower );

try {
	wp_set_current_user( (int) $admins[0] );

	// ------------------------------------------------------------------
	// A. What a dismissal writes
	// ------------------------------------------------------------------
	efb_dismiss_state( 1 );
	efb_dismiss_check( 'A0 a fresh install has the guide pending', true === emsfb_onboarding_pending_efb() );

	$r = efb_dismiss_call();
	efb_dismiss_check( 'A1 closing the email step is accepted', ! empty( $r['success'] ) && ! empty( $r['data']['dismissed'] ), wp_json_encode( $r ) );
	efb_dismiss_check( 'A2 the guide is no longer pending, so Panel/Create will not reopen it', false === emsfb_onboarding_pending_efb() );
	$dismissed_at = get_option( 'emsfb_onboarding_dismissed_at', '' );
	efb_dismiss_check( 'A3 the dismissal is recorded with a date', '' !== $dismissed_at );
	efb_dismiss_check( 'A4 it is not recorded as a completed delivery test', null === get_option( 'emsfb_onboarding_completed_at', null ) );

	$r = efb_dismiss_call();
	efb_dismiss_check( 'A5 a second close changes nothing', ! empty( $r['success'] ) && empty( $r['data']['dismissed'] ) && get_option( 'emsfb_onboarding_dismissed_at', '' ) === $dismissed_at, wp_json_encode( $r ) );

	efb_dismiss_state( 0, '2026-09-01 10:00:00' );
	$r = efb_dismiss_call();
	efb_dismiss_check( 'A6 after a real test, closing does not overwrite completion', empty( $r['data']['dismissed'] ) && '2026-09-01 10:00:00' === get_option( 'emsfb_onboarding_completed_at' ) && null === get_option( 'emsfb_onboarding_dismissed_at', null ), wp_json_encode( $r ) );

	// A delivery test run after a dismissal still has nothing left to end.
	efb_dismiss_state( 1 );
	efb_dismiss_call();
	efb_dismiss_check( 'A7 the delivery-test writer treats a dismissed guide as already ended', false === emsfb_complete_onboarding_efb() && false === emsfb_onboarding_pending_efb() );

	// ------------------------------------------------------------------
	// B. Who may call it
	// ------------------------------------------------------------------
	efb_dismiss_state( 1 );
	$r = efb_dismiss_call( 'not-a-valid-nonce' );
	efb_dismiss_check( 'B1 a bad nonce is refused', empty( $r['success'] ), wp_json_encode( $r ) );
	efb_dismiss_check( 'B2 ... and the guide stays pending', true === emsfb_onboarding_pending_efb() );

	wp_set_current_user( 0 );
	$r = efb_dismiss_call();
	efb_dismiss_check( 'B3 a visitor without admin rights is refused', empty( $r['success'] ), wp_json_encode( $r ) );
	efb_dismiss_check( 'B4 ... and the guide stays pending', true === emsfb_onboarding_pending_efb() );
	wp_set_current_user( (int) $admins[0] );

	// ------------------------------------------------------------------
	// C. Wiring
	// ------------------------------------------------------------------
	$admin_src   = (string) file_get_contents( EMSFB_PLUGIN_DIRECTORY . 'includes/admin/class-Emsfb-admin.php' );
	$install_src = (string) file_get_contents( EMSFB_PLUGIN_DIRECTORY . 'includes/class-Emsfb-install.php' );
	$js_src      = (string) file_get_contents( EMSFB_PLUGIN_DIRECTORY . 'includes/admin/assets/js/val-efb.js' );

	efb_dismiss_check( 'C1 the AJAX action is registered', false !== strpos( $admin_src, "add_action('wp_ajax_efb_dismiss_onboarding', [\$this, 'efb_dismiss_onboarding'])" ) );

	$reset_block = preg_match( "/update_option\('emsfb_onboarding_pending', 1, false\);(.{0,400})/s", $install_src, $m ) ? $m[1] : '';
	efb_dismiss_check( 'C2 a genuinely new install forgets an old dismissal', false !== strpos( $reset_block, "delete_option('emsfb_onboarding_dismissed_at')" ) );

	efb_dismiss_check( 'C3 the x button is a user close', false !== strpos( $js_src, 'class="efb-overlay-close" onclick="efb_setup_overlay_user_close_efb()"' ) );
	$escape = preg_match( '/function handleOverlayEscape_efb\(event\) \{(.*?)\n\}/s', $js_src, $m ) ? $m[1] : '';
	efb_dismiss_check( 'C4 Escape is a user close', false !== strpos( $escape, 'efb_setup_overlay_user_close_efb()' ) && false === strpos( $escape, 'closeSetupOverlay_efb()' ) );
	$user_close = preg_match( '/function efb_setup_overlay_user_close_efb\(\) \{(.*?)\n\}/s', $js_src, $m ) ? $m[1] : '';
	efb_dismiss_check( 'C5 only the email step posts the dismissal', false !== strpos( $user_close, "querySelector('#efb-onboarding-test-email')" ) && false !== strpos( $user_close, "action: 'efb_dismiss_onboarding'" ) );
	$finish = preg_match( '/function efb_onboarding_finish_efb\(\) \{(.*?)\n\}/s', $js_src, $m ) ? $m[1] : '';
	efb_dismiss_check( 'C6 Finish closes without posting a dismissal', false !== strpos( $finish, 'closeSetupOverlay_efb()' ) && false === strpos( $finish, 'efb_setup_overlay_user_close_efb' ) );
	$call_sites = substr_count( $js_src, 'efb_setup_overlay_user_close_efb()' ) - substr_count( $js_src, 'function efb_setup_overlay_user_close_efb()' );
	efb_dismiss_check( 'C7 only x and Escape route through the user close', 2 === $call_sites, "{$call_sites} call sites" );
} catch ( Throwable $e ) {
	$failed++;
	echo '[FAIL] unexpected ' . get_class( $e ) . ': ' . $e->getMessage() . "\n";
} finally {
	remove_filter( 'wp_die_ajax_handler', $thrower );
	remove_filter( 'wp_die_handler', $thrower );
	foreach ( $restore as $name => $value ) {
		null === $value ? delete_option( $name ) : update_option( $name, $value, false );
	}
	wp_set_current_user( $restore_user );
	$restored = true;
	foreach ( $restore as $name => $value ) {
		$restored = $restored && ( null === $value ? null === get_option( $name, null ) : get_option( $name ) == $value );
	}
	efb_dismiss_check( 'Z onboarding options restored', $restored );
}

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
