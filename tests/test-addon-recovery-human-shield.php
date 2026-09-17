<?php
/**
 * Form Security & Spam Protection (AdnHSH) recovery regression tests.
 *
 * The add-on is a downloaded package (human-shield.zip) and is not part of the
 * plugin zip, so every plugin update removes it. Recovery used to treat it as
 * bundled: it recorded "This bundled add-on is missing from the plugin files"
 * without asking the add-on server, so the add-on never came back, every form
 * stayed blocked and the site owner was emailed after each retry cycle.
 *
 * The real downloader, recovery and background runner work on a throwaway
 * plugin directory; only the add-on server and the mail transport are stubbed.
 *
 * Run: php tests/test-addon-recovery-human-shield.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'EMSFB_PLUGIN_DIRECTORY', rtrim( str_replace( '\\', '/', sys_get_temp_dir() ), '/' ) . '/efb-hsh-recovery-' . getmypid() . '/' );
define( 'EMSFB_PLUGIN_VERSION', '4.2.1' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$test_options    = array();
$test_transients = array();
$test_cron       = array();
$test_settings   = (object) array(
	'AdnHSH'         => 1,
	'emailSupporter' => 'owner@example.test',
);

function get_option( $key, $default = false ) {
	global $test_options;
	return array_key_exists( $key, $test_options ) ? $test_options[ $key ] : $default;
}

function update_option( $key, $value, $autoload = null ) {
	global $test_options;
	$test_options[ $key ] = $value;
	return true;
}

function delete_option( $key ) {
	global $test_options;
	unset( $test_options[ $key ] );
	return true;
}

function get_transient( $key ) {
	global $test_transients;
	return array_key_exists( $key, $test_transients ) ? $test_transients[ $key ] : false;
}

function set_transient( $key, $value, $ttl = 0 ) {
	global $test_transients;
	$test_transients[ $key ] = $value;
	return true;
}

function delete_transient( $key ) {
	global $test_transients;
	unset( $test_transients[ $key ] );
	return true;
}

function wp_schedule_single_event( $timestamp, $hook, $args = array() ) {
	global $test_cron;
	$test_cron[] = array( 'at' => $timestamp, 'hook' => $hook, 'args' => $args );
	return true;
}

function get_setting_Emsfb( $mode = 'decoded' ) {
	global $test_settings;
	return $test_settings;
}

function emsfb_is_email_sending_enabled_efb( $settings = null ) { return true; }

function esc_html__( $text, $domain = '' ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return $url; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function get_bloginfo( $what = 'name' ) { return $what === 'version' ? '7.0' : 'Test Site'; }
function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
function wp_kses( $text, $allowed ) { return strip_tags( (string) $text, '<' . implode( '><', array_keys( $allowed ) ) . '>' ); }
function current_time( $type ) { return '2026-09-15 12:00:00'; }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_]/', '', strtolower( $v ) ); }
function sanitize_text_field( $v ) { return is_string( $v ) ? trim( strip_tags( $v ) ) : ''; }

require_once dirname( __DIR__ ) . '/includes/functions.php';

function efb_hsh_put_file( $relative_file ) {
	$path = EMSFB_PLUGIN_DIRECTORY . $relative_file;
	if ( ! is_dir( dirname( $path ) ) ) {
		mkdir( dirname( $path ), 0777, true );
	}
	file_put_contents( $path, "<?php\n" );
}

/** Deletes the throwaway plugin directory, and nothing outside it. */
function efb_hsh_remove_plugin_dir() {
	$root = EMSFB_PLUGIN_DIRECTORY;
	if ( false === strpos( $root, '/efb-hsh-recovery-' ) || ! is_dir( $root ) ) {
		return;
	}
	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $items as $item ) {
		$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
	}
	rmdir( $root );
}

/** A site right after a plugin update: empty plugin folder, no recovery state. */
function efb_hsh_reset_site() {
	global $test_options, $test_transients, $test_cron;
	efb_hsh_remove_plugin_dir();
	mkdir( EMSFB_PLUGIN_DIRECTORY, 0777, true );
	$test_options    = array();
	$test_transients = array();
	$test_cron       = array();
}

register_shutdown_function( 'efb_hsh_remove_plugin_dir' );

class Efb_Hsh_Recovery_Test_Function extends efbFunction {
	/** Add-on keys requested from the add-on server, in order. */
	public $server_requests = array();

	/** Whether the stubbed add-on server hands the package over. */
	public $server_delivers = true;

	/** Problem reports that would have been emailed. */
	public $reports = array();

	public function __construct() {}

	/**
	 * Stands in for the add-on server and the zip extraction: a delivered
	 * package lands exactly where the local health check looks for it. The
	 * current human-shield.zip also carries its translations, which the
	 * Create/Panel scope (the Recover button) checks as well.
	 */
	public function addon_add_efb( $value ) {
		$this->server_requests[] = $value;
		if ( ! $this->server_delivers ) {
			return array( 'status' => false, 'message' => 'Error: server (whitestudio.team) responded with an invalid request. responded code: 503' );
		}
		$requirements = $this->get_addon_required_files_efb();
		$i18n_files   = $this->get_addon_i18n_required_files_efb();
		foreach ( array_merge( $requirements[ $value ], isset( $i18n_files[ $value ] ) ? $i18n_files[ $value ] : array() ) as $relative_file ) {
			efb_hsh_put_file( $relative_file );
		}
		return array( 'status' => true, 'message' => 'installed' );
	}

	public function send_email_state_new( $to, $sub, $cont, $pro, $state, $link, $st = "null" ) {
		$this->reports[] = array( 'to' => $to, 'state' => $state );
		return true;
	}
}

$passed = 0;
$failed = 0;
function efb_hsh_assert( $name, $actual, $expected ) {
	global $passed, $failed;
	if ( $actual === $expected ) {
		echo "[PASS] {$name}\n";
		$passed++;
		return;
	}
	echo "[FAIL] {$name}: expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . "\n";
	$failed++;
}

// 1. A plugin update removed the add-on folder.
efb_hsh_reset_site();
$helper = new Efb_Hsh_Recovery_Test_Function();
efb_hsh_assert( 'setup: the update left Human Shield missing', $helper->is_addon_installed_locally_efb( 'AdnHSH' ), false );

$result = $helper->download_all_addons_efb( true );
efb_hsh_assert( 'a missing Human Shield is requested from the add-on server', $helper->server_requests, array( 'AdnHSH' ) );
efb_hsh_assert( 'the delivered package passes the local health check', $helper->is_addon_installed_locally_efb( 'AdnHSH' ), true );
efb_hsh_assert( 'recovery reports success', $result, true );
efb_hsh_assert( 'no problem report is emailed', count( $helper->reports ), 0 );
efb_hsh_assert( 'no download failure is counted', get_option( 'emsfb_addons_dl_failures', 0 ), 0 );

// 2. The add-on is already in place.
efb_hsh_reset_site();
efb_hsh_put_file( 'vendor/human-shield/human-shield-efb.php' );
$helper = new Efb_Hsh_Recovery_Test_Function();
$result = $helper->download_all_addons_efb( true );
efb_hsh_assert( 'an installed Human Shield is not requested again', $helper->server_requests, array() );
efb_hsh_assert( 'an installed Human Shield counts as healthy', $result, true );

// 3. The add-on server refuses the download.
efb_hsh_reset_site();
$helper = new Efb_Hsh_Recovery_Test_Function();
$helper->server_delivers = false;
$result = $helper->download_all_addons_efb( true );
$reason = is_array( $result ) && isset( $result['errors']['AdnHSH'] ) ? $result['errors']['AdnHSH'] : '';
efb_hsh_assert( 'a refused download still went to the add-on server', $helper->server_requests, array( 'AdnHSH' ) );
efb_hsh_assert( 'the refused add-on stays missing', is_array( $result ) ? $result['missing'] : $result, array( 'AdnHSH' ) );
efb_hsh_assert( 'the report quotes the server answer', false !== strpos( $reason, 'responded code: 503' ), true );
efb_hsh_assert( 'the report no longer blames missing plugin files', false === strpos( $reason, 'bundled add-on' ), true );
efb_hsh_assert( 'a real failure is still reported to the owner', count( $helper->reports ), 1 );

// 4. The Recover button on the post-update gate. recover_missing_addons_efb()
// caches its result for the rest of the process, so it is called only once.
efb_hsh_reset_site();
$test_options = array( 'emsfb_addons_reinstall_required' => time() );
$helper = new Efb_Hsh_Recovery_Test_Function();
efb_hsh_assert( 'setup: the post-update gate blocks the admin pages', $helper->addon_recovery_state_efb( $test_settings ), 'block' );
$recovery = $helper->recover_missing_addons_efb( $test_settings, 'manual' );
efb_hsh_assert( 'the Recover button restores Human Shield', $recovery['recovered'], true );
efb_hsh_assert( 'the admin pages open again', $helper->addon_recovery_state_efb( $test_settings ), 'none' );

// 5. The background runner's last attempt: the run that used to email the report.
efb_hsh_reset_site();
$test_options    = array( 'emsfb_addon_recovery_pending' => array( 'attempt' => 2, 'source' => 'public_form' ) );
$test_transients = array( 'emsfb_addon_recovery_lock' => 1 );
$helper = new Efb_Hsh_Recovery_Test_Function();
$helper->run_addon_recovery_event_efb( array( 'attempt' => 2 ) );
efb_hsh_assert( 'the background repair downloads Human Shield', $helper->server_requests, array( 'AdnHSH' ) );
efb_hsh_assert( 'the background repair emails no report', count( $helper->reports ), 0 );
efb_hsh_assert( 'the background repair clears its pending job', array_key_exists( 'emsfb_addon_recovery_pending', $test_options ), false );
efb_hsh_assert( 'no further retry is scheduled', $test_cron, array() );

echo "\n========================================\n";
echo "RESULTS: {$passed} passed, {$failed} failed\n";
echo "========================================\n";
exit( $failed > 0 ? 1 : 0 );
