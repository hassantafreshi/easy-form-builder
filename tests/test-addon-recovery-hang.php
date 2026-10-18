<?php
/**
 * Add-on recovery when the add-on server hangs or answers slowly.
 *
 * A host that accepts the connection and never answers used to hold the first
 * admin page after an update for the full request timeout once per add-on
 * (twelve add-ons: three minutes). A request killed by a PHP, FPM or proxy time
 * limit never stored its backoff, so every admin page after it waited again.
 *
 * The real downloader (download_all_addons_efb, addon_add_efb), recovery and
 * background runner run on a throwaway plugin directory; only the HTTP
 * transport, the archive extraction and the settings writer are stubbed. Every
 * scenario runs in its own PHP process, because recover_missing_addons_efb()
 * caches its result for the rest of the process.
 *
 * Run: php tests/test-addon-recovery-hang.php
 */

$efb_scenarios = array(
	'hanging_server_costs_one_timeout',
	'quick_connection_error_still_asks_each_addon',
	'one_off_blip_does_not_fail_the_other_addons',
	'reported_timeout_skips_the_host',
	'persian_site_skips_a_dead_primary',
	'budget_still_counts_while_a_mirror_answers',
	'normal_path_installs_everything',
	'budget_defers_without_backoff',
	'partial_run_reloads_and_continues',
	'budget_per_source',
	'run_marker_lifecycle',
	'live_run_blocks_automatic_and_manual',
	'stale_run_blocks_only_automatic',
	'background_runner_waits_for_a_live_run',
	'manual_partial_answers_success',
	'visitor_without_cron_waits_one_short_timeout',
	'visitor_repair_starts_with_the_forms_addons',
);

if ( empty( $argv[1] ) ) {
	$passed = 0;
	$failed = 0;
	foreach ( $efb_scenarios as $efb_scenario ) {
		$output = array();
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' ' . escapeshellarg( $efb_scenario ) . ' 2>&1', $output );
		$finished = false;
		foreach ( $output as $line ) {
			if ( '[DONE]' === trim( $line ) ) {
				$finished = true;
				continue;
			}
			echo $line, "\n";
			if ( 0 === strpos( $line, '[PASS]' ) ) {
				$passed++;
			} elseif ( 0 === strpos( $line, '[FAIL]' ) ) {
				$failed++;
			}
		}
		if ( ! $finished ) {
			echo "[FAIL] {$efb_scenario}: the scenario did not finish\n";
			$failed++;
		}
	}
	echo "\n========================================\n";
	echo "RESULTS: {$passed} passed, {$failed} failed\n";
	echo "========================================\n";
	exit( $failed > 0 ? 1 : 0 );
}

$scenario = $argv[1];

define( 'ABSPATH', __DIR__ . '/' );
define( 'EMSFB_PLUGIN_DIRECTORY', rtrim( str_replace( '\\', '/', sys_get_temp_dir() ), '/' ) . '/efb-hang-recovery-' . getmypid() . '/' );
define( 'EMSFB_PLUGIN_VERSION', '4.3.1' );
define( 'EMSFB_SERVER_URL', 'https://whitestudio.team' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$test_options    = array();
$test_transients = array();
$test_cron       = array();
$test_locale     = 'en_US';
$test_settings   = (object) array(
	'AdnSPF'         => 0,
	'AdnSMF'         => 1,
	'AdnOF'          => 1,
	'AdnHSH'         => 1,
	'emailSupporter' => 'owner@example.test',
);

/**
 * The stubbed add-on servers, by host: 'up', 'hang' (answers nothing until the
 * timeout), 'refuse' (fails at once), 'refuse_once' (fails at once the first
 * time, then answers), 'timeout_fast' (reports a cURL timeout at once, as with a
 * timeout shortened by a filter) or 'slow' (answers after $test_slow_seconds).
 */
$test_hosts        = array( 'whitestudio.team' => 'up', 'easyformbuilder.ir' => 'up' );
$test_slow_seconds = 0.6;
/** Every request made: host, add-on key, outcome. */
$test_requests     = array();
/** Run-marker state seen while the add-on server was being asked. */
$test_marker_seen  = array();

class WP_Error {
	private $message;
	public function __construct( $code = '', $message = '' ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

function wp_remote_get( $url, $args = array() ) {
	global $test_hosts, $test_slow_seconds, $test_requests, $test_marker_seen;
	$host = parse_url( $url, PHP_URL_HOST );
	preg_match( '#/addons-link/[^/]+/([A-Za-z]+)/#', $url, $match );
	$key     = isset( $match[1] ) ? $match[1] : '?';
	$mode    = isset( $test_hosts[ $host ] ) ? $test_hosts[ $host ] : 'refuse';
	$timeout = isset( $args['timeout'] ) ? (float) $args['timeout'] : 5;
	$test_marker_seen[] = get_transient( 'emsfb_addon_recovery_running' );
	$test_requests[]    = array( $host, $key, $mode );
	if ( 'hang' === $mode ) {
		// Stands in for the whole timeout going by without a byte (cURL error 28).
		usleep( (int) ( $timeout * 1000000 ) );
		return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after ' . (int) ( $timeout * 1000 ) . ' milliseconds with 0 bytes received' );
	}
	if ( 'refuse_once' === $mode ) {
		$test_hosts[ $host ] = 'up';
		return new WP_Error( 'http_request_failed', 'cURL error 6: Could not resolve host: ' . $host );
	}
	if ( 'refuse' === $mode ) {
		return new WP_Error( 'http_request_failed', 'cURL error 7: Failed to connect' );
	}
	if ( 'timeout_fast' === $mode ) {
		return new WP_Error( 'http_request_failed', 'cURL error 28: Connection timed out after 2001 milliseconds' );
	}
	if ( 'slow' === $mode ) {
		usleep( (int) ( $test_slow_seconds * 1000000 ) );
	}
	return array(
		'response' => array( 'code' => 200 ),
		'body'     => json_encode( array( 'status' => true, 'download' => true, 'v' => '4.0.0', 'link' => 'https://' . $host . '/source/files/zip/v4/' . strtolower( $key ) . '.zip' ) ),
	);
}

function wp_remote_retrieve_response_code( $response ) { return is_array( $response ) ? $response['response']['code'] : ''; }
function wp_remote_retrieve_body( $response ) { return is_array( $response ) ? $response['body'] : ''; }

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

// A fresh copy per call, as decoding the stored row gives.
function get_setting_Emsfb( $mode = 'decoded' ) { return clone $GLOBALS['test_settings']; }
function get_locale() { return $GLOBALS['test_locale']; }
function emsfb_is_addon_install_ready_efb() { return true; }
function emsfb_is_email_sending_enabled_efb( $settings = null ) { return true; }
function untrailingslashit( $value ) { return rtrim( (string) $value, '/\\' ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
function esc_html__( $text, $domain = '' ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return $url; }
function esc_url_raw( $url ) { return $url; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function get_bloginfo( $what = 'name' ) { return 'version' === $what ? '7.1' : 'Test Site'; }
function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
function wp_kses( $text, $allowed ) { return strip_tags( (string) $text, '<' . implode( '><', array_keys( $allowed ) ) . '>' ); }
function current_time( $type ) { return '2026-10-18 12:00:00'; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return is_string( $value ) ? trim( strip_tags( $value ) ) : ''; }
function wp_unslash( $value ) { return $value; }
function check_ajax_referer( $action = -1, $query_arg = false, $die = true ) { return 1; }
class Efb_Test_Json_Sent extends Exception {}
function wp_send_json_success( $data = null, $status = null ) { $GLOBALS['test_json'] = array( 'success' => true, 'data' => $data ); throw new Efb_Test_Json_Sent(); }
function wp_send_json_error( $data = null, $status = null ) { $GLOBALS['test_json'] = array( 'success' => false, 'data' => $data ); throw new Efb_Test_Json_Sent(); }

require_once dirname( __DIR__ ) . '/includes/functions.php';

/** Deletes the throwaway plugin directory, and nothing outside it. */
function efb_hang_remove_plugin_dir() {
	$root = EMSFB_PLUGIN_DIRECTORY;
	if ( false === strpos( $root, '/efb-hang-recovery-' ) || ! is_dir( $root ) ) {
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
register_shutdown_function( 'efb_hang_remove_plugin_dir' );
efb_hang_remove_plugin_dir();
mkdir( EMSFB_PLUGIN_DIRECTORY, 0777, true );

class Efb_Hang_Test_Function extends efbFunction {
	/** Problem reports that would have been emailed. */
	public $reports = array();

	/** Budget the downloader ran with, per download_all_addons_efb() call. */
	public $budgets_seen = array();

	/** When set, download_all_addons_efb() returns this instead of downloading. */
	public $canned_download = null;

	public $cron_disabled = false;

	public function __construct() {}

	protected function wp_cron_disabled_efb() {
		return (bool) $this->cron_disabled;
	}

	public function download_all_addons_efb( $return_details = false ) {
		$this->budgets_seen[] = $this->addon_recovery_budget_efb;
		if ( null !== $this->canned_download ) {
			foreach ( $this->canned_download['installed'] as $key ) {
				$this->put_addon_files( $key );
			}
			return $this->canned_download;
		}
		return parent::download_all_addons_efb( $return_details );
	}

	/** Archive extraction: the package lands where the health check looks for it. */
	public function fun_addon_new( $url ) {
		$map = array( 'logic' => 'AdnSMF', 'offline' => 'AdnOF', 'human-shield' => 'AdnHSH', 'adnsmf' => 'AdnSMF', 'adnof' => 'AdnOF', 'adnhsh' => 'AdnHSH' );
		$name = substr( $url, strrpos( $url, '/' ) + 1, -4 );
		if ( isset( $map[ $name ] ) ) {
			$this->put_addon_files( $map[ $name ] );
		}
		return true;
	}

	public function put_addon_files( $key ) {
		$runtime = $this->get_addon_required_files_efb();
		$i18n    = $this->get_addon_i18n_required_files_efb();
		foreach ( array_merge( $runtime[ $key ], isset( $i18n[ $key ] ) ? $i18n[ $key ] : array() ) as $relative_file ) {
			$path = EMSFB_PLUGIN_DIRECTORY . $relative_file;
			if ( ! is_dir( dirname( $path ) ) ) {
				mkdir( dirname( $path ), 0777, true );
			}
			file_put_contents( $path, "<?php\n" );
		}
	}

	public static function set_setting_Emsfb( $newSettings, $email = "" ) { return true; }

	public function user_permission_efb_admin_dashboard() { return true; }

	public function send_email_state_new( $to, $sub, $cont, $pro, $state, $link, $st = "null" ) {
		$this->reports[] = $state;
		return true;
	}
}

function check( $label, $condition ) {
	echo ( $condition ? '[PASS] ' : '[FAIL] ' ) . $label . "\n";
}

function hosts_asked() {
	return array_map( function ( $request ) { return $request[0] . ':' . $request[1]; }, $GLOBALS['test_requests'] );
}

$connect_error = 'Cannot install add-ons of Easy Form Builder because the plugin is not able to connect to the whitestudio.team server';

switch ( $scenario ) {
	case 'hanging_server_costs_one_timeout':
		$test_hosts['whitestudio.team'] = 'hang';
		$fn = new Efb_Hang_Test_Function();
		$fn->addon_request_timeout_efb = 3; // 3 s instead of 15 s; the logic is the same.
		$started = microtime( true );
		$result  = $fn->recover_missing_addons_efb( null, 'panel' );
		$elapsed = microtime( true ) - $started;
		check( 'a hanging server is asked once, not once per add-on', 1 === count( $test_requests ) );
		check( 'the page waits one timeout (3 s here, 15 s live), not three', $elapsed < 4.5 );
		check( 'every missing add-on still shows the reason on the card', array( 'AdnOF', 'AdnSMF', 'AdnHSH' ) === array_keys( $result['errors'] ) );
		check( 'the reason is the usual "not able to connect" text', 0 === strpos( (string) $result['errors']['AdnHSH'], $connect_error ) );
		check( 'the failure starts the day-long backoff', false !== get_transient( 'emsfb_addons_dl_backoff' ) );
		check( 'the next page view is told why, from the stored result', array( 'AdnOF', 'AdnSMF', 'AdnHSH' ) === array_keys( get_option( 'emsfb_addon_recovery_result' )['errors'] ) );
		check( 'the run marker is gone once the run finished', false === get_transient( 'emsfb_addon_recovery_running' ) );
		check( 'the host is remembered as not answering for the rest of the request', ! empty( $fn->addon_unreachable_hosts_efb['https://whitestudio.team'] ) );
		check( 'an admin page view still sends no email', array() === $fn->reports );
		check( 'the budget is restored after the run', 0 === $fn->addon_recovery_budget_efb );
		break;

	case 'quick_connection_error_still_asks_each_addon':
		// A refused connection costs milliseconds, so each add-on still asks: as before.
		$test_hosts['whitestudio.team'] = 'refuse';
		$fn = new Efb_Hang_Test_Function();
		$fn->suppress_addon_report_efb = true;
		$details = $fn->download_all_addons_efb( true );
		check( 'a quick connection error is asked again for each add-on, as before', array( 'whitestudio.team:AdnOF', 'whitestudio.team:AdnSMF', 'whitestudio.team:AdnHSH' ) === hosts_asked() );
		check( 'all three add-ons fail with the connect reason', 3 === count( $details['errors'] ) && 0 === strpos( (string) $details['errors']['AdnSMF'], $connect_error ) );
		check( 'the host is not written off for the request', empty( $fn->addon_unreachable_hosts_efb ) );
		check( 'nothing is left as deferred', array() === $details['deferred'] );
		check( 'the backoff is stored', false !== get_transient( 'emsfb_addons_dl_backoff' ) );
		check( 'the run marker is gone', false === get_transient( 'emsfb_addon_recovery_running' ) );
		break;

	case 'one_off_blip_does_not_fail_the_other_addons':
		$test_hosts['whitestudio.team'] = 'refuse_once';
		$fn = new Efb_Hang_Test_Function();
		$fn->suppress_addon_report_efb = true;
		$details = $fn->download_all_addons_efb( true );
		check( 'after one quick failure the next add-on asks the server again', array( 'whitestudio.team:AdnOF', 'whitestudio.team:AdnSMF', 'whitestudio.team:AdnHSH' ) === hosts_asked() );
		check( 'the add-ons after the blip are installed', array( 'AdnSMF', 'AdnHSH' ) === $details['installed'] );
		check( 'only the add-on hit by the blip is missing and listed', array( 'AdnOF' ) === $details['missing'] && array( 'AdnOF' ) === array_keys( $details['errors'] ) );
		break;

	case 'reported_timeout_skips_the_host':
		// cURL reports a timeout sooner than the plugin's own (a filter shortened it): still no answer.
		$test_hosts['whitestudio.team'] = 'timeout_fast';
		$fn = new Efb_Hang_Test_Function();
		$fn->suppress_addon_report_efb = true;
		$details = $fn->download_all_addons_efb( true );
		check( 'a reported timeout is not repeated for every add-on', 1 === count( $test_requests ) );
		check( 'all three add-ons fail with the connect reason', 3 === count( $details['errors'] ) && 0 === strpos( (string) $details['errors']['AdnHSH'], $connect_error ) );
		check( 'the backoff is stored', false !== get_transient( 'emsfb_addons_dl_backoff' ) );
		$second = new Efb_Hang_Test_Function();
		$second->addon_add_efb( 'AdnSMF' );
		check( 'the next request (for example the Recover button) asks the server again', 2 === count( $test_requests ) );
		break;

	case 'persian_site_skips_a_dead_primary':
		$test_locale = 'fa_IR';
		$test_hosts['whitestudio.team'] = 'hang';
		$fn = new Efb_Hang_Test_Function();
		$fn->addon_request_timeout_efb = 3;
		$fn->suppress_addon_report_efb = true;
		$started = microtime( true );
		$result  = $fn->download_all_addons_efb( true );
		$elapsed = microtime( true ) - $started;
		check(
			'whitestudio.team is asked once, then every add-on goes to easyformbuilder.ir',
			array( 'whitestudio.team:AdnOF', 'easyformbuilder.ir:AdnOF', 'easyformbuilder.ir:AdnSMF', 'easyformbuilder.ir:AdnHSH' ) === hosts_asked()
		);
		check( 'a timeout is not retried three times against the same host', $elapsed < 4.5 );
		check( 'all add-ons are restored', true === $result );
		check( 'no backoff after a successful run', false === get_transient( 'emsfb_addons_dl_backoff' ) );
		break;

	case 'budget_still_counts_while_a_mirror_answers':
		// The primary timed out (reported at once here), the mirror answers slowly: the budget still applies.
		$test_locale = 'fa_IR';
		$test_hosts['whitestudio.team']   = 'timeout_fast';
		$test_hosts['easyformbuilder.ir'] = 'slow';
		$fn = new Efb_Hang_Test_Function();
		$fn->addon_recovery_budget_efb = 1; // Each add-on takes 0.6 s from the mirror.
		$details = $fn->download_all_addons_efb( true );
		check( 'the dead primary is asked once, the mirror serves the add-ons', array( 'whitestudio.team:AdnOF', 'easyformbuilder.ir:AdnOF', 'easyformbuilder.ir:AdnSMF' ) === hosts_asked() );
		check( 'while a server still answers, the budget leaves the rest for later', array( 'AdnOF', 'AdnSMF' ) === $details['installed'] && array( 'AdnHSH' ) === $details['deferred'] );
		check( 'and that is not a failure', false === get_transient( 'emsfb_addons_dl_backoff' ) && array() === $details['errors'] );
		break;

	case 'normal_path_installs_everything':
		$fn = new Efb_Hang_Test_Function();
		$result = $fn->recover_missing_addons_efb( null, 'panel' );
		check( 'every enabled add-on is requested once', array( 'whitestudio.team:AdnOF', 'whitestudio.team:AdnSMF', 'whitestudio.team:AdnHSH' ) === hosts_asked() );
		check( 'the first admin view restores all of them', ! empty( $result['success'] ) && ! empty( $result['recovered'] ) );
		check( 'nothing is deferred', array() === $result['deferred'] );
		check( 'the run marker was written before the first request', 1 <= (int) $test_marker_seen[0] );
		check( 'and refreshed before each further one', 3 === count( array_filter( $test_marker_seen ) ) );
		check( 'the run marker is gone afterwards', false === get_transient( 'emsfb_addon_recovery_running' ) );
		check( 'no backoff, no failure counted, no stored result', false === get_transient( 'emsfb_addons_dl_backoff' ) && false === get_option( 'emsfb_addons_dl_failures' ) && false === get_option( 'emsfb_addon_recovery_result' ) );
		break;

	case 'budget_defers_without_backoff':
		$test_hosts['whitestudio.team'] = 'slow';
		$fn = new Efb_Hang_Test_Function();
		$fn->addon_recovery_budget_efb = 1; // Each add-on takes 0.6 s here.
		$details = $fn->download_all_addons_efb( true );
		check( 'the run stops starting add-ons once the budget is used', array( 'AdnOF', 'AdnSMF' ) === $details['attempted'] );
		check( 'the rest is reported as deferred', array( 'AdnHSH' ) === $details['deferred'] );
		check( 'the deferred add-on is still missing', array( 'AdnHSH' ) === $details['missing'] );
		check( 'a deferral is not a failure: no backoff', false === get_transient( 'emsfb_addons_dl_backoff' ) );
		check( 'no failure counted', false === get_option( 'emsfb_addons_dl_failures' ) );
		check( 'no report email, even for an unattended run', array() === $fn->reports );
		check( 'the run marker is gone', false === get_transient( 'emsfb_addon_recovery_running' ) );

		$fn->addon_recovery_budget_efb = 0;
		$again = $fn->download_all_addons_efb( true );
		check( 'the next run installs what was left', true === $again && array( 'AdnOF', 'AdnSMF', 'AdnHSH' ) === array_column( $test_requests, 1 ) );

		$tiny = new Efb_Hang_Test_Function();
		efb_hang_remove_plugin_dir();
		mkdir( EMSFB_PLUGIN_DIRECTORY, 0777, true );
		$test_requests = array();
		$tiny->addon_recovery_budget_efb = 1;
		$test_slow_seconds = 1.2;
		$progress = $tiny->download_all_addons_efb( true );
		check( 'even a budget shorter than one add-on installs one add-on per run', array( 'AdnOF' ) === $progress['installed'] && 1 === count( $test_requests ) );
		break;

	case 'partial_run_reloads_and_continues':
		$fn = new Efb_Hang_Test_Function();
		$fn->canned_download = array( 'success' => false, 'attempted' => array( 'AdnOF', 'AdnSMF' ), 'installed' => array( 'AdnOF', 'AdnSMF' ), 'errors' => array(), 'missing' => array( 'AdnHSH' ), 'deferred' => array( 'AdnHSH' ), 'renew_required' => false );
		update_option( 'emsfb_addons_reinstall_required', time() );
		$result = $fn->recover_missing_addons_efb( null, 'panel' );
		check( 'a run that only ran out of time reloads the page like a repair', ! empty( $result['recovered'] ) && empty( $result['success'] ) );
		check( 'it reports what is left', array( 'AdnHSH' ) === $result['missing'] && array( 'AdnHSH' ) === $result['deferred'] );
		check( 'no failure is stored for the card', false === get_option( 'emsfb_addon_recovery_result' ) );
		check( 'the post-update gate stays until everything is back', false !== get_option( 'emsfb_addons_reinstall_required' ) );
		break;

	case 'budget_per_source':
		foreach ( array( 'panel' => 20, 'create' => 20, 'addons' => 20, 'manual' => 30, 'public_inline' => 5 ) as $source => $budget ) {
			$out = array();
			exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' budget_for:' . $source . ' 2>&1', $out );
			check( "{$source}: the run gets a {$budget} s budget", trim( implode( '', $out ) ) === (string) $budget );
		}
		break;

	case ( 0 === strpos( $scenario, 'budget_for:' ) ? $scenario : '' ):
		$fn = new Efb_Hang_Test_Function();
		$fn->canned_download = array( 'success' => false, 'installed' => array(), 'errors' => array(), 'missing' => array(), 'deferred' => array(), 'renew_required' => false );
		$fn->recover_missing_addons_efb( null, substr( $scenario, strlen( 'budget_for:' ) ) );
		echo $fn->budgets_seen[0], "\n";
		exit( 0 );

	case 'run_marker_lifecycle':
		$fn = new Efb_Hang_Test_Function();
		check( 'no marker: no run', 'none' === $fn->addon_recovery_run_state_efb() );
		set_transient( 'emsfb_addon_recovery_running', time() - 10, 600 );
		check( 'a marker refreshed seconds ago is a live run', 'active' === $fn->addon_recovery_run_state_efb() );
		set_transient( 'emsfb_addon_recovery_running', time() - 200, 600 );
		check( 'a marker nobody refreshed for minutes is a run that died', 'stale' === $fn->addon_recovery_run_state_efb() );
		set_transient( 'emsfb_addon_recovery_running', 1, 600 );
		check( 'an unexpected value counts as stale, never as live', 'stale' === $fn->addon_recovery_run_state_efb() );
		set_transient( 'emsfb_addon_recovery_running', time() + 3600, 600 );
		check( 'a time far in the future counts as stale too', 'stale' === $fn->addon_recovery_run_state_efb() );
		set_transient( 'emsfb_addon_recovery_running', (string) ( time() + 2 ), 600 );
		check( 'a few seconds of clock skew between servers is still a live run', 'active' === $fn->addon_recovery_run_state_efb() );
		$upgrade = file_get_contents( dirname( __DIR__ ) . '/includes/class-Emsfb.php' );
		$upgrade = substr( $upgrade, strpos( $upgrade, 'private function run_upgrade_tasks_efb' ), 1500 );
		check( 'a plugin update clears an old marker with the old backoff', false !== strpos( $upgrade, "delete_transient('emsfb_addon_recovery_running')" ) );
		break;

	case 'live_run_blocks_automatic_and_manual':
		set_transient( 'emsfb_addon_recovery_running', time() - 5, 600 );
		$fn = new Efb_Hang_Test_Function();
		$result = $fn->recover_missing_addons_efb( null, 'panel' );
		check( 'a page view during a live run does not ask the server', array() === $test_requests );
		check( 'it is told the run is in progress', 'in_progress' === $result['skipped'] && empty( $result['success'] ) );
		check( 'the add-ons are still listed as missing', array( 'AdnOF', 'AdnSMF', 'AdnHSH' ) === $result['missing'] );

		$out = array();
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' manual_during:live 2>&1', $out );
		check( 'the Recover button during a live run does not start a second one', 'requests=0' === ( $out[0] ?? '' ) );
		check( 'it says so instead of a misleading error', false !== strpos( (string) ( $out[1] ?? '' ), 'already being reinstalled' ) );

		$fn2 = new Efb_Hang_Test_Function();
		update_option( 'emsfb_addon_i18n_refetch', array( 'logic' => array( 'de_DE' ) ) );
		$refetch = $fn2->process_addon_i18n_refetch_efb();
		check( 'the translation refetch waits for the run too', 'backoff' === $refetch['skipped'] );
		break;

	case 'stale_run_blocks_only_automatic':
		set_transient( 'emsfb_addon_recovery_running', time() - 200, 600 );
		$fn = new Efb_Hang_Test_Function();
		$result = $fn->recover_missing_addons_efb( null, 'create' );
		check( 'after a run died, page views do not start the same wait again', array() === $test_requests && 'in_progress' === $result['skipped'] );
		$out = array();
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' manual_during:stale 2>&1', $out );
		check( 'the Recover button still works after a run died', 'requests=3' === ( $out[0] ?? '' ) && 'success' === ( $out[1] ?? '' ) );
		$queued = $fn->queue_addon_recovery_efb( array( 'source' => 'public_form' ) );
		check( 'a visitor can still queue the background repair', ! empty( $queued['queued'] ) );
		break;

	case ( 0 === strpos( $scenario, 'manual_during:' ) ? $scenario : '' ):
		set_transient( 'emsfb_addon_recovery_running', 'live' === substr( $scenario, 14 ) ? time() - 5 : time() - 200, 600 );
		set_transient( 'emsfb_addons_dl_backoff', 1, DAY_IN_SECONDS );
		$fn = new Efb_Hang_Test_Function();
		try {
			$fn->ajax_recover_addons_efb();
		} catch ( Efb_Test_Json_Sent $sent ) {
		}
		echo 'requests=' . count( $test_requests ), "\n";
		echo $GLOBALS['test_json']['success'] ? 'success' : (string) $GLOBALS['test_json']['data']['message'], "\n";
		exit( 0 );

	case 'background_runner_waits_for_a_live_run':
		set_transient( 'emsfb_addon_recovery_running', time() - 5, 600 );
		$fn = new Efb_Hang_Test_Function();
		$fn->run_addon_recovery_event_efb( array( 'attempt' => 1 ) );
		check( 'the runner does not download beside a live run', array() === $test_requests );
		check( 'it comes back in two minutes with the same attempt number', 1 === count( $test_cron ) && array( array( 'attempt' => 1 ) ) === $test_cron[0]['args'] && $test_cron[0]['at'] >= time() + 110 );
		check( 'it keeps the queue lock so visitors do not queue a duplicate', false !== get_transient( 'emsfb_addon_recovery_lock' ) );
		check( 'it still records that cron runs', (int) get_option( 'emsfb_cron_last_run' ) > 0 );

		$test_cron = array();
		set_transient( 'emsfb_addon_recovery_running', time() - 200, 600 );
		$fn->run_addon_recovery_event_efb( array( 'attempt' => 1 ) );
		check( 'after a run died the runner repairs as before', 3 === count( $test_requests ) && array() === $test_cron );
		break;

	case 'manual_partial_answers_success':
		$fn = new Efb_Hang_Test_Function();
		$fn->canned_download = array( 'success' => false, 'attempted' => array( 'AdnOF' ), 'installed' => array( 'AdnOF' ), 'errors' => array(), 'missing' => array( 'AdnSMF', 'AdnHSH' ), 'deferred' => array( 'AdnSMF', 'AdnHSH' ), 'renew_required' => false );
		try {
			$fn->ajax_recover_addons_efb();
		} catch ( Efb_Test_Json_Sent $sent ) {
		}
		check( 'Recover answers success so the card offers "Activate"; the reload continues', true === $test_json['success'] );
		check( 'and says it is not done yet', false === $test_json['data']['done'] );
		break;

	case 'visitor_without_cron_waits_one_short_timeout':
		$test_hosts['whitestudio.team'] = 'hang';
		$fn = new Efb_Hang_Test_Function();
		$fn->cron_disabled = true;
		$fn->addon_request_timeout_efb = 3; // Inline repairs cap it at 3-5 s.
		$started = microtime( true );
		$gate    = $fn->check_addons_for_public_request_efb( null, array( 'AdnSMF' ) );
		$elapsed = microtime( true ) - $started;
		check( 'a visitor on a site without cron waits one short timeout, not one per add-on', 1 === count( $test_requests ) && $elapsed < 4.5 );
		check( 'the form stays hidden behind the safe message', empty( $gate['success'] ) && empty( $gate['queued'] ) );
		check( 'the backoff that spares the next visitor is stored', false !== get_transient( 'emsfb_addons_dl_backoff' ) );
		$stored = get_option( 'emsfb_addon_recovery_result' );
		check( 'with no server left to ask nothing is deferred: the card gets every reason', array( 'AdnSMF', 'AdnOF', 'AdnHSH' ) === array_keys( $stored['errors'] ) && array() === $stored['deferred'] );
		break;

	case 'visitor_repair_starts_with_the_forms_addons':
		$fn = new Efb_Hang_Test_Function();
		$fn->cron_disabled = true;
		$gate = $fn->check_addons_for_public_request_efb( null, array( 'AdnHSH' ) );
		check( 'the add-on the form needs is downloaded first', 'AdnHSH' === ( $test_requests[0][1] ?? '' ) );
		check( 'the others follow in the usual order', array( 'AdnHSH', 'AdnOF', 'AdnSMF' ) === array_column( $test_requests, 1 ) );
		check( 'the visitor gets the one-time reload to the working form', ! empty( $gate['success'] ) && ! empty( $gate['recovered'] ) );
		check( 'the priority does not leak into later runs', array() === $fn->addon_recovery_priority_efb );
		break;

	default:
		echo "[FAIL] unknown scenario {$scenario}\n";
}

echo "[DONE]\n";
