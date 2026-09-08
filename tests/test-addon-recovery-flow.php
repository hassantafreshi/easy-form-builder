<?php
/**
 * Recovery state-machine regression tests.
 * Run: php tests/test-addon-recovery-flow.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'EMSFB_PLUGIN_DIRECTORY', dirname( __DIR__ ) . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$test_options = array();

function get_option( $key, $default = false ) {
	global $test_options;
	return array_key_exists( $key, $test_options ) ? $test_options[ $key ] : $default;
}

function update_option( $key, $value ) {
	global $test_options;
	$test_options[ $key ] = $value;
	return true;
}

function delete_option( $key ) {
	global $test_options;
	unset( $test_options[ $key ] );
	return true;
}

function delete_transient( $key ) {
	return true;
}

function current_time( $type ) {
	return '2026-07-12 12:00:00';
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_]/', '', strtolower( $value ) );
}

require_once dirname( __DIR__ ) . '/includes/functions.php';

class Efb_Recovery_Test_Function extends efbFunction {
	public $missing = array();
	public $downloads = 0;

	public function __construct() {}

	public function get_addon_local_health_efb( $settings = null ) {
		return array( 'missing' => $this->missing, 'checked' => array_keys( $this->missing ) );
	}

	public function download_all_addons_efb( $return_details = false ) {
		$this->downloads++;
		$this->missing = array();
		return array(
			'success' => true,
			'errors' => array(),
			'missing' => array(),
			'renew_required' => false,
		);
	}
}

$passed = 0;
$failed = 0;
function efb_recovery_assert( $name, $actual, $expected ) {
	global $passed, $failed;
	if ( $actual === $expected ) {
		echo "[PASS] {$name}\n";
		$passed++;
		return;
	}
	echo "[FAIL] {$name}: expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . "\n";
	$failed++;
}

$helper = new Efb_Recovery_Test_Function();
$helper->missing = array( 'AdnSPF' => array( 'vendor/stripe/class-Emsfb-stripe-payment.php' ) );

$test_options = array( 'emsfb_addons_reinstall_required' => time() );
efb_recovery_assert( 'post-update missing file blocks the admin UI', $helper->addon_recovery_state_efb(), 'block' );

$test_options = array();
efb_recovery_assert( 'ordinary missing file uses inline recovery UI', $helper->addon_recovery_state_efb(), 'inline' );

$test_options = array( 'emsfb_addons_reinstall_required' => time() );
$helper->missing = array();
efb_recovery_assert( 'healthy files return none', $helper->addon_recovery_state_efb(), 'none' );
efb_recovery_assert( 'healthy state clears the post-update gate', isset( $test_options['emsfb_addons_reinstall_required'] ), false );

$helper->missing = array( 'AdnSPF' => array( 'vendor/stripe/class-Emsfb-stripe-payment.php' ) );
$test_options = array( 'emsfb_addons_reinstall_required' => time() );
$result = $helper->recover_missing_addons_efb( null, 'create' );
efb_recovery_assert( 'missing files trigger immediate recovery', $result['recovered'], true );
efb_recovery_assert( 'recovery runs once in the request', $helper->downloads, 1 );
efb_recovery_assert( 'successful recovery clears the update gate', isset( $test_options['emsfb_addons_reinstall_required'] ), false );

echo "\n========================================\n";
echo "RESULTS: {$passed} passed, {$failed} failed\n";
echo "========================================\n";
exit( $failed > 0 ? 1 : 0 );
