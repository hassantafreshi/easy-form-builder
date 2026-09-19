<?php
/**
 * Regression test: "Start with Free" must be saved on a fresh install.
 *
 * A fresh install stores emsfb_pro = -1 until a plan is chosen. The plan
 * handler (Admin::efb_save_plan_selection) coerced every value outside 0-3 to
 * 2 before comparing it with the chosen plan, so choosing Free (2) on a fresh
 * install hit the "unchanged" early return and wrote nothing. The page kept
 * package_type -1, getSelectedPlan_efb() kept answering 'null', and the plan
 * chooser reopened on every Panel and Create load, forever. Free Plus and Pro
 * were saved because 3 and 1 never equalled the coerced 2. Shipped in 4.1.3,
 * 4.2.0 and 4.2.1; two German users deactivated the plugin the day they
 * installed it.
 *
 * The existing browser-state test (test-setup-plan-selection-browser-state.js)
 * mocks this handler's response, so the server side had no coverage at all.
 * This calls the real handler the way admin-ajax does and reads back what the
 * next page load reads.
 *
 * Every option, the settings transient and the emsfb_setting table are
 * restored, including on failure.
 *
 * Run: php tests/test-plan-selection-first-run.php
 *      EFB_WP_LOAD=c:/xampp/htdocs/efb-divi/wp-load.php php tests/test-plan-selection-first-run.php
 */

$wp_load = getenv( 'EFB_WP_LOAD' ) ?: dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}

if ( ! defined( 'DOING_AJAX' ) ) {
	// wp_send_json_*() only reaches wp_die() - which this test catches - when
	// the request is an AJAX one. Otherwise it calls die() and ends the run.
	define( 'DOING_AJAX', true );
}

require_once $wp_load;

if ( ! defined( 'EMSFB_PLUGIN_DIRECTORY' ) || ! function_exists( 'get_setting_Emsfb' ) ) {
	echo "[SKIP] Easy Form Builder is not active in this WordPress installation.\n";
	exit( 0 );
}

if ( ! class_exists( '\\Emsfb\\Admin' ) ) {
	// Admin screens only: the plugin skips it when is_admin() is false, as it is here.
	require_once EMSFB_PLUGIN_DIRECTORY . 'includes/admin/class-Emsfb-admin.php';
}

$passed = 0;
$failed = 0;

function efb_plan_check( $label, $condition, $detail = '' ) {
	global $passed, $failed;
	if ( $condition ) {
		$passed++;
		echo "[PASS] {$label}\n";
		return;
	}
	$failed++;
	echo "[FAIL] {$label}" . ( '' !== $detail ? " -- {$detail}" : '' ) . "\n";
}

global $wpdb;
$settings_table = $wpdb->prefix . 'emsfb_setting';

$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( empty( $admins ) ) {
	echo "[SKIP] No administrator account to run the handler as.\n";
	exit( 0 );
}
wp_set_current_user( (int) $admins[0] );

// ----------------------------------------------------------------------
// Snapshot everything the handler (and set_setting_Emsfb) can write.
// ----------------------------------------------------------------------
$option_names = array(
	'emsfb_pro', 'emsfb_settings', 'emsfb_pro_activeCode', 'emsfb_pro_ac_date',
	'emsfb_license_failed_since', 'emsfb_license_fail_reason', 'emsfb_license_suspend_notified',
	'emsfb_onboarding_pending', 'emsfb_onboarding_initial_install', 'emsfb_onboarding_completed_at',
);
foreach ( $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'emsfb\\_addon\\_%'" ) as $addon_option ) {
	$option_names[] = $addon_option;
}
$restore_options = array();
foreach ( $option_names as $name ) {
	$restore_options[ $name ] = get_option( $name, null );
}
$restore_transient = get_transient( 'emsfb_settings_transient' );
$table_exists      = $settings_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $settings_table ) );
$restore_rows      = $table_exists ? $wpdb->get_results( "SELECT * FROM `{$settings_table}` ORDER BY id ASC", ARRAY_A ) : array();

function efb_plan_clear_caches() {
	foreach ( array( 'settings:decoded', 'settings:pub', 'settings:raw', 'emsfb_settings' ) as $key ) {
		wp_cache_delete( $key, 'emsfb' );
	}
	get_setting_Emsfb( '_clear_cache' );
}

/** The settings of the site under test, minus any licence and saved package. */
function efb_plan_base_settings() {
	global $restore_rows, $restore_transient;
	$json = '';
	if ( ! empty( $restore_rows ) ) {
		$last = end( $restore_rows );
		$json = $last['setting'];
	} elseif ( ! empty( $restore_transient ) ) {
		$json = $restore_transient;
	}
	$settings = json_decode( (string) $json );
	if ( ! is_object( $settings ) ) {
		$settings = json_decode( stripslashes( (string) $json ) );
	}
	if ( ! is_object( $settings ) ) {
		$settings = new stdClass();
	}
	$settings->activeCode = '';
	unset( $settings->package_type );
	return $settings;
}

/**
 * Puts the site in a given plan state. $pro null deletes emsfb_pro (never written).
 */
function efb_plan_set_state( $pro ) {
	global $wpdb, $settings_table, $table_exists;
	$json = wp_json_encode( efb_plan_base_settings(), JSON_UNESCAPED_UNICODE );
	if ( $table_exists ) {
		$last_id = (int) $wpdb->get_var( "SELECT MAX(id) FROM `{$settings_table}`" );
		if ( $last_id > 0 ) {
			$wpdb->update( $settings_table, array( 'setting' => $json ), array( 'id' => $last_id ), array( '%s' ), array( '%d' ) );
		}
	}
	update_option( 'emsfb_settings', $json );
	set_transient( 'emsfb_settings_transient', $json, 1800 );
	delete_option( 'emsfb_pro_activeCode' );
	if ( null === $pro ) {
		delete_option( 'emsfb_pro' );
	} else {
		update_option( 'emsfb_pro', $pro );
	}
	efb_plan_clear_caches();
}

/** Calls the real handler as admin-ajax would and returns the decoded JSON. */
function efb_plan_select( $plan ) {
	$_POST = array(
		'action'    => 'efb_save_plan_selection',
		'nonce'     => wp_create_nonce( 'wp_rest' ),
		'plan_data' => wp_json_encode( array( 'selected_plan' => $plan, 'timestamp' => time() ) ),
	);
	$reflection = new ReflectionClass( '\\Emsfb\\Admin' );
	$admin      = $reflection->newInstanceWithoutConstructor();
	ob_start();
	try {
		$admin->efb_save_plan_selection();
	} catch ( Exception $e ) {
		// wp_die() from wp_send_json_*().
	}
	$out = ob_get_clean();
	efb_plan_clear_caches();
	$decoded = json_decode( $out, true );
	return is_array( $decoded ) ? $decoded : array( 'raw' => $out );
}

/** The package_type the next Panel/Create page load hands to getSelectedPlan_efb(). */
function efb_plan_next_page_package_type() {
	efb_plan_clear_caches();
	$settings = get_setting_Emsfb( 'decoded' );
	return is_object( $settings ) && isset( $settings->package_type ) ? (string) $settings->package_type : '(none)';
}

$thrower = function () {
	return function () { throw new Exception( 'wp_die' ); };
};
add_filter( 'wp_die_ajax_handler', $thrower );
add_filter( 'wp_die_handler', $thrower );

try {
	// ------------------------------------------------------------------
	// A. The bug: a fresh install (-1) chooses Free.
	// ------------------------------------------------------------------
	efb_plan_set_state( -1 );
	efb_plan_check( 'A0 a fresh install opens the chooser (package_type -1)', '-1' === efb_plan_next_page_package_type(), efb_plan_next_page_package_type() );

	$r = efb_plan_select( 'free' );
	efb_plan_check( 'A1 choosing Free succeeds', ! empty( $r['success'] ), wp_json_encode( $r ) );
	efb_plan_check( 'A2 it is not answered as "unchanged"', empty( $r['data']['unchanged'] ), wp_json_encode( $r ) );
	efb_plan_check( 'A3 it reports a real plan change to package 2', ! empty( $r['data']['plan_changed'] ) && 2 === (int) $r['data']['package_type'], wp_json_encode( $r ) );
	efb_plan_check( 'A4 emsfb_pro is saved as 2', '2' === (string) get_option( 'emsfb_pro' ), var_export( get_option( 'emsfb_pro' ), true ) );
	efb_plan_check( 'A5 the next page load reads Free, so the chooser stays closed', '2' === efb_plan_next_page_package_type(), efb_plan_next_page_package_type() );

	$again = efb_plan_select( 'free' );
	efb_plan_check( 'A6 choosing Free again is now the harmless "unchanged"', ! empty( $again['data']['unchanged'] ) && '2' === (string) get_option( 'emsfb_pro' ), wp_json_encode( $again ) );

	// ------------------------------------------------------------------
	// B. The other "no plan yet" states behave the same.
	// ------------------------------------------------------------------
	efb_plan_set_state( null );
	$r = efb_plan_select( 'free' );
	efb_plan_check( 'B1 emsfb_pro never written: Free is saved as 2', empty( $r['data']['unchanged'] ) && '2' === (string) get_option( 'emsfb_pro' ), wp_json_encode( $r ) . ' pro=' . var_export( get_option( 'emsfb_pro' ), true ) );

	efb_plan_set_state( 10 );
	$r = efb_plan_select( 'free' );
	efb_plan_check( 'B2 legacy 10: Free is saved as 2', empty( $r['data']['unchanged'] ) && '2' === (string) get_option( 'emsfb_pro' ), wp_json_encode( $r ) . ' pro=' . var_export( get_option( 'emsfb_pro' ), true ) );

	// ------------------------------------------------------------------
	// C. Behaviour that must not change.
	// ------------------------------------------------------------------
	efb_plan_set_state( -1 );
	$r = efb_plan_select( 'free_plus' );
	efb_plan_check( 'C1 fresh install -> Free Plus is still saved as 3', '3' === (string) get_option( 'emsfb_pro' ) && '3' === efb_plan_next_page_package_type(), wp_json_encode( $r ) );

	efb_plan_set_state( -1 );
	$r = efb_plan_select( 'pro' );
	efb_plan_check( 'C2 fresh install -> Pro without a licence only opens the pricing page', isset( $r['data']['plan_changed'] ) && false === $r['data']['plan_changed'] && ! empty( $r['data']['redirect_url'] ), wp_json_encode( $r ) );
	efb_plan_check( 'C3 ... and does not mark the site as Pro', '1' !== (string) get_option( 'emsfb_pro' ), var_export( get_option( 'emsfb_pro' ), true ) );

	efb_plan_set_state( 3 );
	$r = efb_plan_select( 'free' );
	efb_plan_check( 'C4 Free Plus -> Free still asks for downgrade confirmation', empty( $r['success'] ) && isset( $r['data']['code'] ) && 'downgrade_confirmation_required' === $r['data']['code'], wp_json_encode( $r ) );
	efb_plan_check( 'C5 ... and keeps Free Plus until confirmed', '3' === (string) get_option( 'emsfb_pro' ), var_export( get_option( 'emsfb_pro' ), true ) );

	efb_plan_set_state( 3 );
	$r = efb_plan_select( 'free_plus' );
	efb_plan_check( 'C6 a saved Free Plus choosing Free Plus is "unchanged"', ! empty( $r['data']['unchanged'] ), wp_json_encode( $r ) );
} catch ( Throwable $e ) {
	$failed++;
	echo '[FAIL] unexpected ' . get_class( $e ) . ': ' . $e->getMessage() . "\n";
} finally {
	remove_filter( 'wp_die_ajax_handler', $thrower );
	remove_filter( 'wp_die_handler', $thrower );

	foreach ( $restore_options as $name => $value ) {
		if ( null === $value ) {
			delete_option( $name );
		} else {
			update_option( $name, $value );
		}
	}
	if ( false === $restore_transient ) {
		delete_transient( 'emsfb_settings_transient' );
	} else {
		set_transient( 'emsfb_settings_transient', $restore_transient, 1800 );
	}
	if ( $table_exists ) {
		$kept_ids = array_map( 'intval', wp_list_pluck( $restore_rows, 'id' ) );
		foreach ( $wpdb->get_col( "SELECT id FROM `{$settings_table}`" ) as $id ) {
			if ( ! in_array( (int) $id, $kept_ids, true ) ) {
				$wpdb->delete( $settings_table, array( 'id' => (int) $id ), array( '%d' ) );
			}
		}
		foreach ( $restore_rows as $row ) {
			$id = (int) $row['id'];
			unset( $row['id'] );
			$wpdb->update( $settings_table, $row, array( 'id' => $id ) );
		}
	}
	efb_plan_clear_caches();

	$restored_ok = (string) get_option( 'emsfb_pro', '(none)' ) === (string) ( null === $restore_options['emsfb_pro'] ? '(none)' : $restore_options['emsfb_pro'] )
		&& ( $table_exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$settings_table}`" ) === count( $restore_rows ) : true );
	efb_plan_check( 'Z site state restored (emsfb_pro, settings table)', $restored_ok );
}

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
