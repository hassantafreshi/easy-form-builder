<?php
/**
 * Regression test for the PersiaPay script bootstrap (persiapayefb.php: the efb_enqueue_persia hook that puts the
 * add-on's persia_pay-efb.js on the page).
 *
 * PAY-OUT step 4: core used to load the bootstrap itself (Emsfb::load_persiapay_addon()); the add-on loads it now from
 * its bridge (PersiaPay_Legacy_Efb::register()) with a core whose legacy_gateway contract is 2.1 or more, and core no
 * longer names its file. With an older core the core keeps loading it (the add-on then does not, so never twice).
 *
 * Run: php tests/test-persiapay-bootstrap.php
 */

define( 'ABSPATH', __DIR__ . '/' );

$registered_actions = array();
$pass = 0; $fail = 0;
function t( $name, $ok ) { global $pass, $fail; echo ( $ok ? '[PASS] ' : '[FAIL] ' ) . $name . "\n"; $ok ? $pass++ : $fail++; }

function add_action( $hook, $callback, $p = 10, $a = 1 ) {
	global $registered_actions;
	$registered_actions[ $hook ][] = $callback;
}
function add_filter() {}
function is_admin() { return false; }
$GLOBALS['efb_t_core'] = 2.1;
function efb_addon_contract_versions() { return array( 'legacy_gateway' => array( 'version' => $GLOBALS['efb_t_core'], 'min' => 2 ) ); }

$core = file_get_contents( dirname( __DIR__ ) . '/includes/class-Emsfb.php' );
t( 'core no longer loads the bootstrap itself (no load_persiapay_addon(), no persiapayefb.php)', strpos( $core, 'load_persiapay_addon' ) === false && strpos( $core, 'persiapayefb.php' ) === false );

$dir = getenv( 'EFB_PERSIAPAY_DIR' ) ?: ( is_dir( dirname( __DIR__ ) . '/vendor/persiapay' ) ? dirname( __DIR__ ) . '/vendor/persiapay' : '/home/user/vendores-efb/persiapay' );
if ( ! is_readable( $dir . '/class-Emsfb-persiapay-legacy.php' ) ) {
	echo "[SKIP] the Persia Pay add-on is not found\nRESULTS: {$pass} passed, {$fail} failed\n";
	exit( $fail ? 1 : 0 );
}
require_once $dir . '/class-Emsfb-persiapay-legacy.php';
\Emsfb\PersiaPay_Legacy_Efb::register();
t( 'a 2.1 core: the add-on\'s bridge loads its bootstrap, which registers the editor / front-end script hook', class_exists( '\\Emsfb\\persiapayEFB', false ) && ! empty( $registered_actions['efb_enqueue_persia'] ) );
$n = count( $registered_actions['efb_enqueue_persia'] );
$GLOBALS['efb_t_core'] = 2.0;
\Emsfb\PersiaPay_Legacy_Efb::register();
t( 'an older core (it still loads the bootstrap itself): the add-on does not add a second hook', count( $registered_actions['efb_enqueue_persia'] ) === $n );

echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit( $fail ? 1 : 0 );
