<?php
/**
 * Regression tests for add-on state normalization.
 * Run: php tests/test-addon-settings.php
 */

define( 'ABSPATH', __DIR__ . '/' );

$test_options = [
	'emsfb_addon_AdnGoS' => 2,
];

function get_option( $key, $default = false ) {
	global $test_options;
	return array_key_exists( $key, $test_options ) ? $test_options[$key] : $default;
}

function absint( $value ) {
	return abs( (int) $value );
}

require_once dirname( __DIR__ ) . '/includes/class-Emsfb.php';
require_once dirname( __DIR__ ) . '/includes/functions.php';

$passed = 0;
$failed = 0;

function efb_addon_test( $name, $actual, $expected ) {
	global $passed, $failed;

	if ( $actual === $expected ) {
		echo "[PASS] {$name}\n";
		$passed++;
		return;
	}

	echo "[FAIL] {$name}: expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . "\n";
	$failed++;
}

$get_addons = new ReflectionMethod( 'Emsfb', 'get_addons_list_efb' );
$get_addons->setAccessible( true );

$settings = (object) [
	'AdnSMF' => 1,
	'AdnGoS' => 0,
];
$public_addons = $get_addons->invoke( null, $settings );

efb_addon_test( 'A1 settings activate AdnSMF without a separate option', isset( $public_addons['AdnSMF'] ), true );
efb_addon_test( 'A2 existing add-ons preserve option-based activation', isset( $public_addons['AdnGoS'] ), true );

$legacy_addons = $get_addons->invoke( null, new stdClass() );
efb_addon_test( 'A3 legacy option is used when setting is absent', isset( $legacy_addons['AdnGoS'] ), true );
efb_addon_test( 'A4 legacy option version is preserved', $legacy_addons['AdnGoS']['version'], 2 );

$function_reflection = new ReflectionClass( 'efbFunction' );
$helper = $function_reflection->newInstanceWithoutConstructor();
$admin_addons = $helper->fun_get_addons_list_efb( (object) [ 'AdnSMF' => 1 ] );

efb_addon_test( 'A5 admin helper does not depend on AdnSPF', $admin_addons['AdnSMF'], 1 );
efb_addon_test( 'A6 admin helper preserves numeric output', is_int( $admin_addons['AdnSMF'] ), true );
efb_addon_test( 'A7 public helper preserves metadata output', is_array( $public_addons['AdnSMF'] ), true );
efb_addon_test( 'A8 public helper exposes active flag', $public_addons['AdnSMF']['active'], true );
efb_addon_test( 'A9 public helper preserves version', $public_addons['AdnSMF']['version'], 1 );

$public_keys = [
	'AdnSS',
	'AdnATF',
	'AdnGoS',
	'AdnTLG',
	'AdnPAP',
	'AdnSPF',
	'AdnPPF',
	'AdnOF',
	'AdnSMF',
];
$test_options = [
	'emsfb_addon_AdnSS' => 1,
	'emsfb_addon_AdnATF' => 1,
	'emsfb_addon_AdnGoS' => 2,
	'emsfb_addon_AdnTLG' => 1,
	'emsfb_addon_AdnPAP' => 1,
	'emsfb_addon_AdnSPF' => 1,
	'emsfb_addon_AdnPPF' => 1,
	'emsfb_addon_AdnOF' => 1,
];
$all_active_settings = new stdClass();
foreach ( $public_keys as $addon_key ) {
	$all_active_settings->{$addon_key} = 1;
}
$all_public_addons = $get_addons->invoke( null, $all_active_settings );
$actual_public_keys = array_keys( $all_public_addons );
sort( $public_keys );
sort( $actual_public_keys );
efb_addon_test( 'A10 existing public add-on keys are preserved', $actual_public_keys, $public_keys );

unset( $test_options['emsfb_addon_AdnSPF'] );
$settings_only_stripe = $get_addons->invoke( null, (object) [ 'AdnSPF' => 1 ] );
efb_addon_test(
	'A11 existing add-ons are not activated by settings alone',
	isset( $settings_only_stripe['AdnSPF'] ),
	false
);

$admin_keys = [
	'AdnSPF',
	'AdnOF',
	'AdnPPF',
	'AdnATC',
	'AdnSS',
	'AdnCPF',
	'AdnESZ',
	'AdnSE',
	'AdnPDP',
	'AdnADP',
	'AdnPAP',
	'AdnTLG',
	'AdnATF',
	'AdnGoS',
	'AdnWHS',
	'AdnWSP',
	'AdnSMF',
	'AdnPLF',
	'AdnMSF',
	'AdnBEF',
];
$missing_admin_keys = array_values( array_diff( $admin_keys, array_keys( $admin_addons ) ) );
efb_addon_test( 'A12 admin and vendor add-on keys are preserved', $missing_admin_keys, [] );

echo "\n========================================\n";
echo "RESULTS: {$passed} passed, {$failed} failed\n";
echo "========================================\n";

exit( $failed > 0 ? 1 : 0 );
