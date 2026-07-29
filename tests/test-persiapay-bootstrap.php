<?php
/**
 * Regression test for the PersiaPay script bootstrap.
 * Run: php tests/test-persiapay-bootstrap.php
 */

define( 'ABSPATH', __DIR__ . '/' );

$registered_actions = array();

function add_action( $hook, $callback ) {
	global $registered_actions;
	$registered_actions[ $hook ][] = $callback;
}

/*
 * load_persiapay_addon() is invoked directly below rather than through
 * Emsfb::includes(), so the add-on compatibility helper that includes() would
 * normally have loaded is not defined. Stand in for it here; the real
 * implementation lives in includes/class-Emsfb-addon-compatibility.php.
 */
if ( ! function_exists( 'emsfb_is_addon_compatible_efb' ) ) {
	function emsfb_is_addon_compatible_efb( $addon_key ) {
		return true;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-Emsfb.php';

$reflection = new ReflectionClass( 'Emsfb' );
$efb        = $reflection->newInstanceWithoutConstructor();
$efb->plugin_path = dirname( __DIR__ ) . DIRECTORY_SEPARATOR;

$method = new ReflectionMethod( 'Emsfb', 'load_persiapay_addon' );
$method->setAccessible( true );
$method->invoke( $efb );

if ( ! class_exists( '\\Emsfb\\persiapayEFB', false ) || empty( $registered_actions['efb_enqueue_persia'] ) ) {
	echo "[FAIL] PersiaPay did not register its script hook\n";
	exit( 1 );
}

echo "[PASS] PersiaPay bootstrap registers its editor/front-end script hook\n";
