<?php
/**
 * The add-on install gate, on every plan.
 *
 * Reported 2026-09-23: a Free site could install a paid add-on from the
 * Add-ons page. Three things had to be true at once for that:
 *
 *   1. The card's lock was decoration. createCardAddoneEfb() drew a gem and an
 *      orange button, but funBTNAddOnsEFB() never looked at that state and
 *      posted the install anyway.
 *   2. add_addons_Emsfb() had no plan rule of its own. It asked the licensing
 *      server and installed whatever the answer allowed.
 *   3. That server only refuses add-ons it sells. It hands Conditional Logic
 *      to every domain (verified against example.com) and hands everything to
 *      127.0.0.1 and localhost, which is why the bug was visible on a dev box
 *      for every add-on and on real sites for Conditional Logic.
 *
 * The plan now lives in one table - addon_required_package_efb() - which the
 * install endpoint enforces, the downgrade path reads, and admin-efb.js
 * mirrors. This test pins that table, the gate over every plan/add-on pair,
 * the licence override that protects a Pro site whose plan option was reset,
 * and the wiring in the JS that must not drift from it.
 *
 * Run: C:\xampp\php\php.exe tests\test-addon-plan-gate.php
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}
define( 'WP_USE_THEMES', false );
require_once $wp_load;

$pass = 0;
$fail = 0;
function t( $label, $ok, $detail = '' ) {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "[PASS] {$label}\n"; }
	else { $fail++; echo "[FAIL] {$label}\n"; }
	if ( '' !== $detail ) { echo "       {$detail}\n"; }
}

$fn = get_efbFunction();

/* Everything this test writes is restored on the way out, pass or fail.
 * The activation code lives in two places - the option and the settings row -
 * and the gate reads the row when the option is empty, exactly as is_efb_pro()
 * does. A test that only cleared the option would be answered by whatever this
 * dev site happens to have stored and would pass on any code at all. */
global $wpdb;
$setting_table = $wpdb->prefix . 'emsfb_setting';
$original_pro  = get_option( 'emsfb_pro' );
$original_code = get_option( 'emsfb_pro_activeCode' );
$original_row  = $wpdb->get_row( "SELECT id, setting FROM `{$setting_table}` ORDER BY id DESC LIMIT 1" );

register_shutdown_function( function () use ( $original_pro, $original_code, $original_row, $setting_table ) {
	global $wpdb;
	if ( $original_row ) {
		$wpdb->update( $setting_table, array( 'setting' => $original_row->setting ), array( 'id' => (int) $original_row->id ) );
		update_option( 'emsfb_settings', $original_row->setting );
		set_transient( 'emsfb_settings_transient', $original_row->setting, 1800 );
	}
	if ( false === $original_pro ) { delete_option( 'emsfb_pro' ); }
	else { update_option( 'emsfb_pro', $original_pro ); }
	if ( false === $original_code ) { delete_option( 'emsfb_pro_activeCode' ); }
	else { update_option( 'emsfb_pro_activeCode', $original_code ); }
	delete_transient( 'emsfb_settings_transient' );
	if ( $original_row ) { set_transient( 'emsfb_settings_transient', $original_row->setting, 1800 ); }
	wp_cache_delete( 'settings:decoded', 'emsfb' );
	if ( function_exists( 'get_setting_Emsfb' ) ) { get_setting_Emsfb( '_clear_cache' ); }
	echo "\n[cleanup] emsfb_pro restored to " . var_export( get_option( 'emsfb_pro' ), true )
		. ', activation code restored to ' . ( '' === (string) get_option( 'emsfb_pro_activeCode', '' ) ? '(empty)' : '(set)' ) . "\n";
} );

/**
 * Put the site on a plan.
 *
 * The settings row carries package_type too, and get_setting_Emsfb('decoded')
 * heals emsfb_pro from it whenever the option reads -1 or 10 (a fresh install
 * that has not chosen yet). Leaving the row behind therefore un-does the two
 * "no plan chosen" rows below mid-test, so both copies are written together.
 */
function set_plan( $package_type ) {
	global $wpdb, $setting_table;
	update_option( 'emsfb_pro', (string) $package_type );

	$row = $wpdb->get_row( "SELECT id, setting FROM `{$setting_table}` ORDER BY id DESC LIMIT 1" );
	if ( $row ) {
		$decoded = json_decode( $row->setting );
		if ( is_object( $decoded ) ) {
			if ( in_array( (int) $package_type, array( 0, 1, 2, 3 ), true ) ) {
				$decoded->package_type = (string) $package_type;
			} else {
				unset( $decoded->package_type );
			}
			$json = wp_json_encode( $decoded, JSON_UNESCAPED_UNICODE );
			$wpdb->update( $setting_table, array( 'setting' => $json ), array( 'id' => (int) $row->id ) );
			update_option( 'emsfb_settings', $json );
			set_transient( 'emsfb_settings_transient', $json, 1800 );
		}
	}

	wp_cache_delete( 'settings:decoded', 'emsfb' );
	if ( function_exists( 'get_setting_Emsfb' ) ) { get_setting_Emsfb( '_clear_cache' ); }
	// The heal above runs on the first decode; re-assert the option after it.
	update_option( 'emsfb_pro', (string) $package_type );
}

/** Write the activation code to both places the gate can read it. */
function set_activation_code( $code ) {
	global $wpdb, $setting_table;
	update_option( 'emsfb_pro_activeCode', $code );
	$row = $wpdb->get_row( "SELECT id, setting FROM `{$setting_table}` ORDER BY id DESC LIMIT 1" );
	if ( ! $row ) { return; }
	$decoded = json_decode( $row->setting );
	if ( ! is_object( $decoded ) ) { return; }
	$decoded->activeCode = $code;
	$json = wp_json_encode( $decoded, JSON_UNESCAPED_UNICODE );
	$wpdb->update( $setting_table, array( 'setting' => $json ), array( 'id' => (int) $row->id ) );
	update_option( 'emsfb_settings', $json );
	set_transient( 'emsfb_settings_transient', $json, 1800 );
	wp_cache_delete( 'settings:decoded', 'emsfb' );
	if ( function_exists( 'get_setting_Emsfb' ) ) { get_setting_Emsfb( '_clear_cache' ); }
}

/* ---------------------------------------------------------------- the table */

echo "=== which plan each add-on needs ===\n";
t( 'Offline Forms is free on every plan', 0 === $fn->addon_required_package_efb( 'AdnOF' ) );
t( 'Conditional Logic is the Free Plus tier', 3 === $fn->addon_required_package_efb( 'AdnSMF' ) );
foreach ( array( 'AdnSPF', 'AdnPAP', 'AdnPPF', 'AdnSS', 'AdnTLG', 'AdnGoS', 'AdnHSH', 'AdnATF' ) as $paid ) {
	t( "{$paid} is Pro", 1 === $fn->addon_required_package_efb( $paid ) );
}
t( 'an unknown key is treated as Pro, never as free',
	1 === $fn->addon_required_package_efb( 'AdnSomethingNew' ) );

/* ------------------------------------------------------------- the gate */

echo "\n=== the gate, plan by plan (no activation code) ===\n";
set_activation_code( '' );

$matrix = array(
	// plan => array( addon => expected allowed )
	2  => array( 'AdnOF' => true, 'AdnSMF' => false, 'AdnSPF' => false ), // Free
	3  => array( 'AdnOF' => true, 'AdnSMF' => true,  'AdnSPF' => false ), // Free Plus
	1  => array( 'AdnOF' => true, 'AdnSMF' => true,  'AdnSPF' => true ),  // Pro
	0  => array( 'AdnOF' => true, 'AdnSMF' => false, 'AdnSPF' => false ), // expired
	-1 => array( 'AdnOF' => true, 'AdnSMF' => false, 'AdnSPF' => false ), // never chosen
	10 => array( 'AdnOF' => true, 'AdnSMF' => false, 'AdnSPF' => false ), // legacy unset
);
$plan_name = array( 2 => 'Free', 3 => 'Free Plus', 1 => 'Pro', 0 => 'expired', -1 => 'unchosen', 10 => 'legacy' );

foreach ( $matrix as $plan => $expectations ) {
	set_plan( $plan );
	foreach ( $expectations as $addon => $expected ) {
		$actual = $fn->addon_plan_allows_install_efb( $addon );
		t( sprintf( '%s: %s is %s', $plan_name[ $plan ], $addon, $expected ? 'allowed' : 'blocked' ),
			$expected === $actual,
			'got ' . var_export( $actual, true ) );
	}
}

/* ------------------------------------------- the licence override */

echo "\n=== a validated licence outranks a reset plan option ===\n";
/* The licence churn incident: a network blip reset emsfb_pro on a paying
 * site. The stored code still proves the purchase, so the gate must not send
 * that customer to the upgrade dialog. */
$hosts = $fn->license_domain_candidates_efb();
t( 'this site resolves at least one licence domain candidate', ! empty( $hosts ), implode( ', ', (array) $hosts ) );
$valid_code = md5( (string) reset( $hosts ) ) . '@whatever';

set_plan( 0 );
set_activation_code( $valid_code );
t( 'a Pro add-on is allowed when the code still validates, even at plan 0',
	true === $fn->addon_plan_allows_install_efb( 'AdnSPF' ) );

set_activation_code( md5( 'some-other-domain.example' ) . '@whatever' );
t( 'a code minted for another domain does not unlock anything',
	false === $fn->addon_plan_allows_install_efb( 'AdnSPF' ) );

/* The code is only in the settings row - the option is where is_efb_pro()
 * copies it to, and a site that never went through that path still has to be
 * recognised. */
update_option( 'emsfb_pro_activeCode', '' );
set_plan( 0 );
$row_only = $wpdb->get_row( "SELECT id, setting FROM `{$setting_table}` ORDER BY id DESC LIMIT 1" );
$decoded_row = json_decode( $row_only->setting );
$decoded_row->activeCode = $valid_code;
$json_row = wp_json_encode( $decoded_row, JSON_UNESCAPED_UNICODE );
$wpdb->update( $setting_table, array( 'setting' => $json_row ), array( 'id' => (int) $row_only->id ) );
update_option( 'emsfb_settings', $json_row );
set_transient( 'emsfb_settings_transient', $json_row, 1800 );
wp_cache_delete( 'settings:decoded', 'emsfb' );
get_setting_Emsfb( '_clear_cache' );
t( 'a code held only in the settings row still counts',
	true === $fn->addon_plan_allows_install_efb( 'AdnSPF' ) );

set_activation_code( '' );
set_plan( 2 );
t( 'an empty code leaves a Free site blocked',
	false === $fn->addon_plan_allows_install_efb( 'AdnSPF' ) );

/* --------------------------------------- what a downgrade keeps */

echo "\n=== what each plan keeps on a downgrade ===\n";
$keeps_free_plus = $fn->addon_keys_allowed_by_package_efb( 3 );
sort( $keeps_free_plus );
t( 'Free Plus keeps exactly Offline Forms and Conditional Logic',
	array( 'AdnOF', 'AdnSMF' ) === $keeps_free_plus, implode( ', ', $keeps_free_plus ) );

$keeps_free = $fn->addon_keys_allowed_by_package_efb( 2 );
t( 'Free keeps only Offline Forms', array( 'AdnOF' ) === array_values( $keeps_free ), implode( ', ', $keeps_free ) );

$keeps_pro = $fn->addon_keys_allowed_by_package_efb( 1 );
t( 'Pro keeps every add-on', count( $keeps_pro ) === count( $fn->get_all_addon_keys_efb() ), count( $keeps_pro ) . ' keys' );

/* ------------------------------------------ the endpoint enforces it */

echo "\n=== the install endpoint carries the gate, not just the card ===\n";
$admin_src = file_get_contents( __DIR__ . '/../includes/admin/class-Emsfb-admin.php' );
t( 'add_addons_Emsfb() blocks before it asks the licensing server',
	strpos( $admin_src, 'addon_plan_allows_install_efb($post_value)' ) !== false
	&& strpos( $admin_src, "'code' => 'addon_plan_required'" ) !== false );
$gate_pos   = strpos( $admin_src, 'addon_plan_allows_install_efb($post_value)' );
$remote_pos = strpos( $admin_src, 'remote_request_prepared' );
t( 'and it blocks before the remote request is even prepared',
	$gate_pos !== false && $remote_pos !== false && $gate_pos < $remote_pos );
t( 'a refusal carrying only `error` is read as a plan refusal',
	strpos( $admin_src, "stripos( \$remote_error, 'permission' )" ) !== false
	|| strpos( $admin_src, "stripos(\$remote_error, 'permission')" ) !== false );
t( 'the downgrade path reads the same table',
	strpos( $admin_src, 'addon_keys_allowed_by_package_efb($package_type_efb)' ) !== false
	&& strpos( $admin_src, "array_diff(\$addon_keys, array('AdnOF', 'AdnSMF'))" ) === false );

/* ------------------------------------------------ the card agrees */

echo "\n=== the Add-ons page mirrors the same table ===\n";
$js = file_get_contents( __DIR__ . '/../includes/admin/assets/js/admin-efb.js' );
t( 'the JS declares the same tiers',
	strpos( $js, "if (name === 'AdnOF') return 0;" ) !== false
	&& strpos( $js, "if (name === 'AdnSMF') return 3;" ) !== false );
t( 'the card and the click share one gate',
	substr_count( $js, 'efb_addon_can_install_efb(' ) >= 3 );
t( 'the click refuses a locked add-on instead of posting the install',
	strpos( $js, 'pro_show_efb(efb_addon_required_package_efb(val) === 3 ? 3 : 1);' ) !== false );
t( 'a locked card no longer offers the word Install',
	strpos( $js, "nameNtn = requiredPackage === 3" ) !== false );
t( 'the old special case is gone',
	strpos( $js, "i.name === 'AdnSMF' && [1, 3].includes(packageType)" ) === false );

echo "\n========================================\n";
echo "RESULTS: {$pass} passed, {$fail} failed\n";
echo "========================================\n";
exit( $fail > 0 ? 1 : 0 );
