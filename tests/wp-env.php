<?php
/**
 * Not a test. Where the WordPress-backed tests find WordPress, its address and its administrator, so
 * the same test runs on the owner's XAMPP machine and on any other box. PHP twin of tests/wp-env.js.
 *
 * WordPress (the folder that holds wp-load.php), first match wins:
 *   1. EFB_WP_ROOT, else EFB_E2E_SITE_DIR (the e2e suite's variable), else the folder of EFB_WP_LOAD.
 *      EFB_WP_ROOT=none turns WordPress off: the tests then print [SKIP].
 *   2. The local e2e site (EFB_TEST_E2E_SITE below), when it exists.
 *   3. The owner's layout: this plugin inside <wordpress>/wp-content/plugins/, then C:/xampp/htdocs/wp.
 * Address: EFB_WP_URL, else EFB_E2E_URL, else the e2e site's http://127.0.0.1:8088 or the owner's
 * http://127.0.0.1/wp (whichever WordPress was found). Administrator: EFB_WP_USER / EFB_WP_PASS, else
 * EFB_E2E_USER / EFB_E2E_PASS, else that site's own (admin / admin-pass-123 on the e2e site, admin /
 * admin on the owner's).
 *
 * These tests write to that site: run them one at a time, under the e2e lock when other runs share it.
 */

if ( ! defined( 'EFB_TEST_E2E_SITE' ) ) {
	define( 'EFB_TEST_E2E_SITE', '/tmp/claude-0/-home-user/6470513e-3182-5834-893e-38f1388be77b/scratchpad/wpsite/site' );
}

if ( ! function_exists( 'efb_test_wp_root' ) ) {

	/** @return array{root:string, kind:string, from:string} kind: env | e2e | owner | none */
	function efb_test_wp_site() {
		static $site = null;
		if ( $site !== null ) return $site;
		foreach ( array( 'EFB_WP_ROOT', 'EFB_E2E_SITE_DIR' ) as $var ) {
			$v = getenv( $var );
			if ( $v === false || $v === '' ) continue;
			if ( strtolower( $v ) === 'none' ) return $site = array( 'root' => '', 'kind' => 'none', 'from' => $var . '=none' );
			return $site = array( 'root' => rtrim( str_replace( '\\', '/', $v ), '/' ), 'kind' => 'env', 'from' => $var );
		}
		$v = getenv( 'EFB_WP_LOAD' );
		if ( $v !== false && $v !== '' ) return $site = array( 'root' => rtrim( str_replace( '\\', '/', dirname( $v ) ), '/' ), 'kind' => 'env', 'from' => 'EFB_WP_LOAD' );
		if ( is_file( EFB_TEST_E2E_SITE . '/wp-load.php' ) ) return $site = array( 'root' => EFB_TEST_E2E_SITE, 'kind' => 'e2e', 'from' => 'the e2e site' );
		foreach ( array( dirname( __DIR__, 4 ), 'C:/xampp/htdocs/wp' ) as $dir ) {
			$dir = rtrim( str_replace( '\\', '/', $dir ), '/' );
			if ( is_file( $dir . '/wp-load.php' ) ) return $site = array( 'root' => $dir, 'kind' => 'owner', 'from' => 'the owner layout' );
		}
		return $site = array( 'root' => '', 'kind' => 'none', 'from' => 'nothing found (set EFB_WP_ROOT)' );
	}

	/** The WordPress folder, '' when there is none. */
	function efb_test_wp_root() {
		$s = efb_test_wp_site();
		return $s['root'];
	}

	/**
	 * The wp-load.php to require. When there is no WordPress it is a path that does not exist and says
	 * why, so a test's own "[SKIP] WordPress bootstrap was not found at ..." line stays readable.
	 */
	function efb_test_wp_load() {
		$s = efb_test_wp_site();
		if ( $s['root'] === '' ) return '(no WordPress: ' . $s['from'] . ')/wp-load.php';
		return $s['root'] . '/wp-load.php';
	}

	/**
	 * For tests that used to require wp-load.php without a check: the path when it is there; with no
	 * WordPress at all prints [SKIP] and ends; a WordPress named in the environment that is not
	 * there is a failure (a wrong setting must not pass as a skip).
	 */
	function efb_test_wp_load_or_skip() {
		$s    = efb_test_wp_site();
		$load = efb_test_wp_load();
		if ( is_readable( $load ) ) return $load;
		if ( $s['kind'] === 'env' ) {
			fwrite( STDERR, "FAIL: {$s['from']} names a WordPress that is not there: {$load}\n" );
			exit( 1 );
		}
		echo "[SKIP] no WordPress ({$s['from']}): set EFB_WP_ROOT=/path/to/wordpress (see tests/wp-env.php)\n";
		echo "RESULTS: 0 passed, 0 failed\n";
		exit( 0 );
	}

	/** The site's address without a trailing slash. */
	function efb_test_wp_url() {
		foreach ( array( 'EFB_WP_URL', 'EFB_E2E_URL' ) as $var ) {
			$v = getenv( $var );
			if ( $v !== false && $v !== '' ) return rtrim( $v, '/' );
		}
		$s = efb_test_wp_site();
		return $s['kind'] === 'owner' ? 'http://127.0.0.1/wp' : 'http://127.0.0.1:8088';
	}

	/** @return array{0:string, 1:string} user name and password of the site's administrator. */
	function efb_test_wp_admin() {
		$user = getenv( 'EFB_WP_USER' ) ?: ( getenv( 'EFB_E2E_USER' ) ?: 'admin' );
		$pass = getenv( 'EFB_WP_PASS' ) ?: getenv( 'EFB_E2E_PASS' );
		if ( ! $pass ) {
			$s    = efb_test_wp_site();
			$pass = $s['kind'] === 'owner' ? 'admin' : 'admin-pass-123';
		}
		return array( $user, $pass );
	}

	/**
	 * A test that needs some add-ons on (or off) when Easy Form Builder boots — EFB loads its add-ons once per request,
	 * from its own settings. Call it right after wp-load.php. When the site's switches already match, it returns and
	 * the test runs as it is. Otherwise it writes the switches through EFB's own settings function
	 * (efbFunction::set_setting_Emsfb, never the option itself), runs this same test again in a new PHP process (which
	 * boots with them), puts the site's own switches back the same way, and ends with that process's exit code.
	 *
	 * @param array<string,int> $addons add-on flag => 1 on, 0 off, e.g. array( 'AdnSMF' => 1 )
	 */
	function efb_test_require_addons( array $addons ) {
		if ( getenv( 'EFB_TEST_ADDONS_CHILD' ) ) return;
		if ( ! function_exists( 'get_setting_Emsfb' ) || ! class_exists( 'efbFunction' ) ) return;
		$read = static function () {
			get_setting_Emsfb( '_clear_cache' );
			foreach ( array( 'decoded', 'pub', 'raw' ) as $k ) wp_cache_delete( 'settings:' . $k, 'emsfb' );
			$s = get_setting_Emsfb( 'decoded' );
			return is_object( $s ) ? $s : new stdClass();
		};
		$s    = $read();
		$own  = array();
		$diff = false;
		foreach ( $addons as $code => $on ) {
			$own[ $code ] = isset( $s->$code ) ? $s->$code : null;
			$diff         = $diff || (int) ( $s->$code ?? 0 ) !== (int) $on;
		}
		if ( ! $diff ) return;
		$write = static function ( array $flags ) use ( $read ) {
			$s = $read();
			foreach ( $flags as $code => $v ) $s->$code = $v === null ? 0 : $v;
			return false !== \efbFunction::set_setting_Emsfb( $s );
		};
		echo '[setup] add-ons for this test (EFB settings, restored afterwards): ' . json_encode( $addons ) . "\n";
		$code = 1;
		if ( $write( $addons ) ) {
			global $argv;
			$cmd = 'EFB_TEST_ADDONS_CHILD=1 ' . escapeshellarg( efb_test_php_bin() );
			foreach ( (array) $argv as $a ) $cmd .= ' ' . escapeshellarg( (string) $a );
			passthru( $cmd, $code );
		} else {
			echo "[FAIL] the add-on switches could not be written through set_setting_Emsfb\n";
		}
		if ( ! $write( $own ) ) {
			echo "[FAIL] the site's own add-on switches could not be put back: " . json_encode( $own ) . "\n";
			$code = 1;
		}
		exit( (int) $code );
	}

	/**
	 * A test of an add-on's own code: [SKIP] when that add-on is not installed in vendor/<dir> (the add-ons repository
	 * installs it there, or a link to it).
	 */
	function efb_test_require_addon_dir( $dir ) {
		if ( is_dir( dirname( __DIR__ ) . '/vendor/' . $dir ) ) return;
		echo "[SKIP] the add-on in vendor/{$dir} is not installed: install it (or link it from the add-ons repository) to run this test\n";
		echo "RESULTS: 0 passed, 0 failed\n";
		exit( 0 );
	}

	/** The PHP binary for child processes: EFB_PHP, else the one running this test. */
	function efb_test_php_bin() {
		$v = getenv( 'EFB_PHP' );
		return ( $v !== false && $v !== '' ) ? $v : PHP_BINARY;
	}
}
