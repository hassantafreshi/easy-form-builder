<?php
/**
 * Regression test for the Auto-Populate submenu capability grants.
 * Run: php tests/test-autofill-menu-capabilities.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'EMSFB_PLUGIN_DIRECTORY', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
class Efb_Autofill_Test_Role {
	public $caps = array();

	public function has_cap( $capability ) {
		return ! empty( $this->caps[ $capability ] );
	}

	public function add_cap( $capability ) {
		$this->caps[ $capability ] = true;
	}
}

$test_role = new Efb_Autofill_Test_Role();

function get_role( $role ) {
	global $test_role;
	return 'administrator' === $role ? $test_role : null;
}

function wp_get_current_user() {
	return null;
}

function is_admin() {
	return false;
}

function add_action() {
	return true;
}

require_once dirname( __DIR__ ) . '/includes/admin/class-Emsfb-admin.php';

$expected = array( 'Emsfb_autofill_efb', 'Emsfb_autofill_api_efb' );
$actual   = array_values( array_intersect( \Emsfb\Admin::get_administrator_capabilities_efb(), $expected ) );

if ( $actual !== $expected ) {
	echo '[FAIL] Auto-Populate menu capabilities are incomplete: ' . var_export( $actual, true ) . "\n";
	exit( 1 );
}

$reflection = new ReflectionClass( '\\Emsfb\\Admin' );
$admin      = $reflection->newInstanceWithoutConstructor();
$admin->add_cap();

foreach ( $expected as $capability ) {
	if ( ! $test_role->has_cap( $capability ) ) {
		echo "[FAIL] Administrator was not granted {$capability}\n";
		exit( 1 );
	}
}

echo "[PASS] Auto-Populate submenu capabilities are granted by the versioned core\n";
