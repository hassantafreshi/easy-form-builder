<?php
/**
 * C5: emsfb_prune_addon_languages_efb().
 *
 * Kept vs deleted locales (exact site, user and refetched locales only), protected files,
 * look-alike names, empty kept set, a languages directory outside vendor/, the
 * vendor/.git skip, the local-host skip for localhost / 127.0.0.1 / ::1 (nothing is
 * deleted without the emsfb_prune_addon_languages_on_local_efb filter), the registry
 * path (slug null) and EMSFB_KEEP_ALL_ADDON_LOCALES.
 *
 * Standalone (no WordPress). Fixtures live under temp/addon-i18n/, never the real vendor/.
 * Run: php tests/addon-i18n/test-addon-i18n-prune.php
 */

$efb_plugin  = str_replace( '\\', '/', dirname( __DIR__, 2 ) );
$efb_fixture = $efb_plugin . '/temp/addon-i18n/prune-fixture-' . getmypid();

define( 'ABSPATH', $efb_fixture . '/' );
define( 'EMSFB_PLUGIN_DIRECTORY', $efb_fixture . '/' );

$efb_test = array(
	'home'      => 'https://example.com/wp',
	'site'      => null,
	'locale'    => 'en_US',
	'options'   => array(),
	'available' => array(),
	'filters'   => array(),
);

function home_url() { global $efb_test; return $efb_test['home']; }
function site_url() { global $efb_test; return null === $efb_test['site'] ? $efb_test['home'] : $efb_test['site']; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function get_locale() { global $efb_test; return $efb_test['locale']; }
function get_option( $key, $default = false ) { global $efb_test; return array_key_exists( $key, $efb_test['options'] ) ? $efb_test['options'][ $key ] : $default; }
function update_option( $key, $value ) { global $efb_test; $efb_test['options'][ $key ] = $value; return true; }
function get_available_languages() { global $efb_test; return $efb_test['available']; }
function is_multisite() { return false; }
function add_action() { return true; }
function add_filter() { return true; }
function apply_filters( $tag, $value ) {
	global $efb_test;
	$args = func_get_args();
	if ( isset( $efb_test['filters'][ $tag ] ) ) {
		return call_user_func_array( $efb_test['filters'][ $tag ], array_slice( $args, 1 ) );
	}
	return $value;
}
function emsfb_is_php_function_available_efb( $name ) { return function_exists( $name ); }
function esc_html__( $text ) { return $text; }

class Efb_Prune_Test_Wpdb {
	public $usermeta = 'wp_usermeta';
	public $locales  = array();
	public function get_col( $sql ) { return $this->locales; }
}
$GLOBALS['wpdb'] = new Efb_Prune_Test_Wpdb();

require_once $efb_plugin . '/includes/functions.php';

$passed = 0;
$failed = 0;
function efb_prune_assert( $name, $actual, $expected ) {
	global $passed, $failed;
	if ( $actual === $expected ) {
		echo "[PASS] {$name}\n";
		$passed++;
		return;
	}
	echo "[FAIL] {$name}: expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . "\n";
	$failed++;
}

function efb_prune_rmtree( $path ) {
	if ( is_link( $path ) || is_file( $path ) ) {
		unlink( $path );
		return;
	}
	if ( ! is_dir( $path ) ) {
		return;
	}
	foreach ( array_diff( scandir( $path ), array( '.', '..' ) ) as $entry ) {
		efb_prune_rmtree( $path . '/' . $entry );
	}
	rmdir( $path );
}
register_shutdown_function( function () use ( $efb_fixture ) {
	efb_prune_rmtree( $efb_fixture );
} );

$efb_locales = array( 'fa_IR', 'de_DE', 'ja', 'ar', 'es_ES', 'fr_FR', 'pt_PT' );

/** (Re)build vendor/telegram/languages and a second add-on with the same file set. */
function efb_prune_build() {
	global $efb_fixture, $efb_locales;
	efb_prune_rmtree( $efb_fixture );
	foreach ( array( 'telegram', 'other' ) as $slug ) {
		$dir = $efb_fixture . '/vendor/' . $slug . '/languages';
		mkdir( $dir, 0777, true );
		file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		file_put_contents( $dir . '/efb-' . $slug . '.pot', "msgid \"\"\n" );
		file_put_contents( $dir . '/locales.json', json_encode( array( 'domain' => 'efb-' . $slug, 'locales' => $efb_locales ) ) );
		foreach ( $efb_locales as $locale ) {
			foreach ( array( 'po', 'mo', 'l10n.php' ) as $ext ) {
				file_put_contents( $dir . '/efb-' . $slug . '-' . $locale . '.' . $ext, 'x' );
			}
			file_put_contents( $dir . '/efb-' . $slug . '-' . $locale . '-0123456789abcdef0123456789abcdef.json', '{}' );
		}
		// Look-alikes that must never match the delete pattern.
		file_put_contents( $dir . '/efb-' . $slug . '-ja.mo.bak', 'x' );
		file_put_contents( $dir . '/efb-' . $slug . '-ja-notahash.json', 'x' );
		file_put_contents( $dir . '/readme.txt', 'x' );
	}
	file_put_contents( $efb_fixture . '/vendor/telegram/languages/efb-other-ja.mo', 'x' );
}

function efb_prune_exists( $slug, $name ) {
	global $efb_fixture;
	return file_exists( $efb_fixture . '/vendor/' . $slug . '/languages/' . $name );
}

function efb_prune_locale_files_present( $slug, $locale ) {
	foreach ( array( '.po', '.mo', '.l10n.php', '-0123456789abcdef0123456789abcdef.json' ) as $suffix ) {
		if ( ! efb_prune_exists( $slug, 'efb-' . $slug . '-' . $locale . $suffix ) ) {
			return false;
		}
	}
	return true;
}

function efb_prune_reset_env() {
	global $efb_test, $wpdb;
	$efb_test['home']      = 'https://example.com/wp';
	$efb_test['site']      = null;
	$efb_test['locale']    = 'en_US';
	$efb_test['options']   = array();
	$efb_test['available'] = array();
	$efb_test['filters']   = array();
	$wpdb->locales         = array();
}

// ---------------------------------------------------------------------------
echo "--- A. kept vs deleted ---\n";
efb_prune_build();
efb_prune_reset_env();
$efb_test['available'] = array( 'fa_IR' );
$wpdb->locales         = array( 'de_CH', 'es_AR' );
$efb_test['options']['emsfb_addon_i18n_refetch_log'] = array( 'telegram|pt_PT' => time() - 3600 );
$result = emsfb_prune_addon_languages_efb( 'telegram' );

efb_prune_assert( 'A1 not skipped', $result['skipped'], '' );
efb_prune_assert( 'A2 site language pack (fa_IR) kept', efb_prune_locale_files_present( 'telegram', 'fa_IR' ), true );
// A locale is never kept for another locale's sake: de_CH and es_AR are their own languages.
efb_prune_assert( 'A3 user locale de_CH does not keep de_DE', efb_prune_locale_files_present( 'telegram', 'de_DE' ), false );
efb_prune_assert( 'A4 user locale es_AR does not keep es_ES', efb_prune_locale_files_present( 'telegram', 'es_ES' ), false );
efb_prune_assert( 'A5 locale refetched within 30 days (pt_PT) kept', efb_prune_locale_files_present( 'telegram', 'pt_PT' ), true );
foreach ( array( 'ja', 'ar', 'fr_FR', 'de_DE', 'es_ES' ) as $gone ) {
	foreach ( array( '.po', '.mo', '.l10n.php', '-0123456789abcdef0123456789abcdef.json' ) as $suffix ) {
		efb_prune_assert( "A6 unused efb-telegram-{$gone}{$suffix} deleted", efb_prune_exists( 'telegram', 'efb-telegram-' . $gone . $suffix ), false );
	}
}
foreach ( array( 'index.php', 'efb-telegram.pot', 'locales.json', 'readme.txt', 'efb-telegram-ja.mo.bak', 'efb-telegram-ja-notahash.json', 'efb-other-ja.mo' ) as $protected ) {
	efb_prune_assert( "A7 {$protected} kept", efb_prune_exists( 'telegram', $protected ), true );
}
efb_prune_assert( 'A8 another add-on untouched when one slug is pruned', efb_prune_locale_files_present( 'other', 'ja' ), true );
efb_prune_assert( 'A9 result lists 20 deleted files', count( $result['deleted'] ), 20 );

// ---------------------------------------------------------------------------
echo "\n--- B. empty kept set ---\n";
efb_prune_build();
efb_prune_reset_env();
$efb_test['filters']['emsfb_addon_kept_locales_efb'] = function () { return array(); };
$result = emsfb_prune_addon_languages_efb( 'telegram' );
efb_prune_assert( 'B1 skipped: no kept locales', $result['skipped'], 'no_kept_locales' );
efb_prune_assert( 'B2 nothing deleted', efb_prune_locale_files_present( 'telegram', 'ja' ) && efb_prune_locale_files_present( 'telegram', 'ar' ), true );

// ---------------------------------------------------------------------------
echo "\n--- C. languages directory outside vendor/ ---\n";
efb_prune_build();
efb_prune_reset_env();
$outside = $efb_fixture . '/outside/languages';
mkdir( $outside, 0777, true );
file_put_contents( $outside . '/efb-telegram-ja.mo', 'x' );
$efb_test['filters']['emsfb_addon_languages_dir_efb'] = function ( $dir, $slug ) use ( $outside ) { return $outside . '/'; };
$result = emsfb_prune_addon_languages_efb( 'telegram' );
efb_prune_assert( 'C1 skipped: outside vendor', $result['skipped'], 'outside_vendor' );
efb_prune_assert( 'C2 file outside vendor not deleted', file_exists( $outside . '/efb-telegram-ja.mo' ), true );
efb_prune_assert( 'C3 nothing deleted in vendor either', efb_prune_locale_files_present( 'telegram', 'ja' ), true );
$efb_test['filters']['emsfb_addon_languages_dir_efb'] = function ( $dir, $slug ) { return EMSFB_PLUGIN_DIRECTORY . 'vendor/'; };
$result = emsfb_prune_addon_languages_efb( 'telegram' );
efb_prune_assert( 'C4 vendor/ itself is refused', $result['skipped'], 'outside_vendor' );
efb_prune_assert( 'C5 slug with ../ is normalised and cannot escape', emsfb_addon_i18n_slug_efb( '../../etc' ), 'etc' );

// ---------------------------------------------------------------------------
echo "\n--- D. git checkout of vendor/ ---\n";
efb_prune_build();
efb_prune_reset_env();
mkdir( $efb_fixture . '/vendor/.git', 0777, true );
$result = emsfb_prune_addon_languages_efb( 'telegram' );
efb_prune_assert( 'D1 skipped: vendor/.git exists', $result['skipped'], 'git_checkout' );
efb_prune_assert( 'D2 nothing deleted', efb_prune_locale_files_present( 'telegram', 'ja' ), true );

// ---------------------------------------------------------------------------
echo "\n--- E. local sites ---\n";
$local_cases = array(
	'localhost home'      => array( 'http://localhost/wp', null ),
	'127.0.0.1 home'      => array( 'http://127.0.0.1/wp', null ),
	'::1 home'            => array( 'http://[::1]/wp', null ),
	'localhost site_url'  => array( 'https://example.com', 'http://localhost:8080/wp' ),
	'LOCALHOST uppercase' => array( 'http://LOCALHOST/wp', null ),
);
foreach ( $local_cases as $label => $urls ) {
	efb_prune_build();
	efb_prune_reset_env();
	$efb_test['home'] = $urls[0];
	$efb_test['site'] = $urls[1];
	$result = emsfb_prune_addon_languages_efb( 'telegram' );
	efb_prune_assert( "E1 {$label}: skipped as local", $result['skipped'], 'local_site' );
	efb_prune_assert( "E2 {$label}: nothing deleted", efb_prune_locale_files_present( 'telegram', 'ja' ) && efb_prune_locale_files_present( 'telegram', 'ar' ), true );
}
efb_prune_assert( 'E3 a public host is not local', ( function () { global $efb_test; efb_prune_reset_env(); return emsfb_is_local_site_efb(); } )(), false );

efb_prune_build();
efb_prune_reset_env();
$efb_test['home'] = 'http://127.0.0.1/wp';
$efb_test['filters']['emsfb_prune_addon_languages_on_local_efb'] = function () { return true; };
$result = emsfb_prune_addon_languages_efb( 'telegram' );
efb_prune_assert( 'E4 filter lifts the local skip', $result['skipped'], '' );
efb_prune_assert( 'E5 with the filter, unused locales are deleted', efb_prune_exists( 'telegram', 'efb-telegram-ja.mo' ), false );
efb_prune_assert( 'E6 with the filter, protected files stay', efb_prune_exists( 'telegram', 'locales.json' ) && efb_prune_exists( 'telegram', 'efb-telegram.pot' ), true );

// ---------------------------------------------------------------------------
echo "\n--- F. slug null prunes every registered add-on present ---\n";
require_once $efb_plugin . '/includes/phrases.php';
efb_prune_build();
efb_prune_reset_env();
$result = emsfb_prune_addon_languages_efb();
efb_prune_assert( 'F1 registered add-on (telegram) pruned', efb_prune_exists( 'telegram', 'efb-telegram-ja.mo' ), false );
efb_prune_assert( 'F2 unregistered add-on folder untouched', efb_prune_locale_files_present( 'other', 'ja' ), true );
efb_prune_assert( 'F3 missing registered add-on is skipped quietly', is_array( $result ) && '' === $result['skipped'], true );

// ---------------------------------------------------------------------------
echo "\n--- G. EMSFB_KEEP_ALL_ADDON_LOCALES ---\n";
efb_prune_build();
efb_prune_reset_env();
define( 'EMSFB_KEEP_ALL_ADDON_LOCALES', true );
$result = emsfb_prune_addon_languages_efb( 'telegram' );
efb_prune_assert( 'G1 skipped by the constant', $result['skipped'], 'keep_all_constant' );
efb_prune_assert( 'G2 nothing deleted', efb_prune_locale_files_present( 'telegram', 'ja' ), true );

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
