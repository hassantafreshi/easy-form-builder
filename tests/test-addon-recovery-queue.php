<?php
/**
 * Background add-on recovery queue regression tests.
 *
 * Covers the guards that keep a visitor request free of network work:
 * the schedule lock, the download backoff, the failure ceiling, the retry
 * escalation, and the public health gate.
 *
 * Run: php tests/test-addon-recovery-queue.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'EMSFB_PLUGIN_DIRECTORY', dirname( __DIR__ ) . '/' );
define( 'EMSFB_PLUGIN_VERSION', '4.1.3' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

$test_options    = array();
$test_transients = array();
$test_cron       = array();

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

function esc_html__( $text, $domain = '' ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return $url; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function get_bloginfo( $what = 'name' ) { return $what === 'version' ? '7.0' : 'Test Site'; }
function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }

function current_time( $type ) { return '2026-08-05 12:00:00'; }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_]/', '', strtolower( $v ) ); }
function sanitize_text_field( $v ) { return is_string( $v ) ? trim( strip_tags( $v ) ) : ''; }
function wp_unslash( $v ) { return $v; }
function esc_url_raw( $v ) { return $v; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }

require_once dirname( __DIR__ ) . '/includes/functions.php';

class Efb_Queue_Test_Function extends efbFunction {
	public $missing          = array();
	public $download_calls   = 0;
	public $download_ok      = false;
	public $cron_disabled    = false;
	public $inline_mode_seen = false;

	/** Captured inside the download so suppression can be asserted while it matters. */
	public $suppress_during_download = null;

	public function __construct() {}

	/**
	 * Only the constant lookup is replaced. is_cron_available_efb() itself — the
	 * logic under test — runs unmodified, so a regression there fails the suite.
	 */
	protected function wp_cron_disabled_efb() {
		return (bool) $this->cron_disabled;
	}

	public function get_addon_local_health_efb( $settings = null ) {
		return array( 'missing' => $this->missing, 'present' => array() );
	}

	/**
	 * The real method caches its result in a method-level static, which lives for
	 * the whole PHP process and would leak between scenarios here. Its own
	 * behaviour is covered by test-addon-recovery-flow.php; what this file
	 * exercises is which branch check_addons_for_public_request_efb() picks.
	 */
	public function recover_missing_addons_efb( $settings = null, $source = 'automatic' ) {
		$before  = array_keys( $this->missing );
		$details = $this->download_all_addons_efb( true );
		$success = empty( $this->missing );

		return array(
			'success'         => $success,
			'needed'          => true,
			'recovered'       => $success,
			'initial_missing' => $before,
			'missing'         => array_keys( $this->missing ),
			'errors'          => isset( $details['errors'] ) ? $details['errors'] : array(),
			'renew_required'  => false,
			'source'          => $source,
		);
	}

	public function download_all_addons_efb( $return_details = false ) {
		$this->download_calls++;
		$this->suppress_during_download = $this->suppress_addon_report_efb;
		if ( $this->addon_inline_mode_efb ) {
			$this->inline_mode_seen = true;
		}
		if ( $this->download_ok ) {
			$this->missing = array();
			return $return_details ? array( 'success' => true, 'missing' => array(), 'errors' => array() ) : true;
		}
		// Mirror the real downloader's failure bookkeeping.
		update_option( 'emsfb_addons_dl_failures', (int) get_option( 'emsfb_addons_dl_failures', 0 ) + 1 );
		set_transient( 'emsfb_addons_dl_backoff', 1, DAY_IN_SECONDS );
		return $return_details ? array( 'success' => false, 'missing' => array_keys( $this->missing ), 'errors' => array() ) : false;
	}
}

$passed = 0;
$failed = 0;

function check( $label, $condition ) {
	global $passed, $failed;
	if ( $condition ) {
		$passed++;
		echo "[PASS] {$label}\n";
	} else {
		$failed++;
		echo "[FAIL] {$label}\n";
	}
}

function reset_state() {
	global $test_options, $test_transients, $test_cron;
	$test_options    = array();
	$test_transients = array();
	$test_cron       = array();
}

/* ---------------------------------------------------------------- */

reset_state();
$fn = new Efb_Queue_Test_Function();
$fn->missing = array( 'AdnSPF' => array( 'vendor/stripe/routes-efb.php' ) );

$r = $fn->queue_addon_recovery_efb( array( 'form_id' => 7, 'addon' => 'AdnSPF' ) );
check( 'first visitor schedules a recovery job', ! empty( $r['queued'] ) );
check( 'queueing performs no download', $fn->download_calls === 0 );
check( 'exactly one cron event scheduled', count( $GLOBALS['test_cron'] ) === 1 );
check( 'cron args carry only the attempt counter', $GLOBALS['test_cron'][0]['args'] === array( array( 'attempt' => 0 ) ) );
check( 'rich context stored in an option instead', ( get_option( 'emsfb_addon_recovery_pending' )['form_id'] ?? 0 ) === 7 );

$r2 = $fn->queue_addon_recovery_efb( array( 'form_id' => 7 ) );
check( 'concurrent visitor does not queue a duplicate', empty( $r2['queued'] ) && $r2['reason'] === 'already_queued' );
check( 'still exactly one cron event', count( $GLOBALS['test_cron'] ) === 1 );

/* ---------------------------------------------------------------- */

reset_state();
$fn = new Efb_Queue_Test_Function();
$fn->missing = array( 'AdnSPF' => array( 'x' ) );
set_transient( 'emsfb_addons_dl_backoff', 1, DAY_IN_SECONDS );
$r = $fn->queue_addon_recovery_efb();
check( 'download backoff blocks new jobs', empty( $r['queued'] ) && $r['reason'] === 'backoff' );
check( 'nothing scheduled while backing off', count( $GLOBALS['test_cron'] ) === 0 );

/* ---------------------------------------------------------------- */

reset_state();
$fn = new Efb_Queue_Test_Function();
$fn->missing = array( 'AdnSPF' => array( 'x' ) );
set_transient( 'emsfb_addons_renew_backoff', 1, DAY_IN_SECONDS );
$r = $fn->queue_addon_recovery_efb();
check( 'expired subscription blocks new jobs', empty( $r['queued'] ) && $r['reason'] === 'renew_backoff' );

/* ---------------------------------------------------------------- */

reset_state();
$fn = new Efb_Queue_Test_Function();
$fn->missing = array( 'AdnSPF' => array( 'x' ) );
update_option( 'emsfb_addons_dl_failures', 3 );
$r = $fn->queue_addon_recovery_efb();
check( 'ceiling resets once the backoff window has passed', ! empty( $r['queued'] ) );
check( 'failure counter cleared on the fresh cycle', get_option( 'emsfb_addons_dl_failures', 0 ) === false || (int) get_option( 'emsfb_addons_dl_failures', 0 ) === 0 );

/* ---------------------------------------------------------------- */

reset_state();
$fn = new Efb_Queue_Test_Function();
$fn->missing = array( 'AdnSPF' => array( 'x' ) );
$fn->run_addon_recovery_event_efb( array( 'attempt' => 0 ) );
check( 'attempt 0 downloads once', $fn->download_calls === 1 );
check( 'attempt 0 reschedules after failing', count( $GLOBALS['test_cron'] ) === 1 );
check( 'retry 1 is queued as attempt 1', $GLOBALS['test_cron'][0]['args'] === array( array( 'attempt' => 1 ) ) );
check( 'email is muted DURING a non-final attempt', $fn->suppress_during_download === true );
check( 'the mute is lifted again after the attempt', $fn->suppress_addon_report_efb === false );
check( 'the runner records a cron heartbeat', (int) get_option( 'emsfb_cron_last_run', 0 ) > 0 );

$GLOBALS['test_cron'] = array();
$fn->suppress_during_download = null;
$fn->run_addon_recovery_event_efb( array( 'attempt' => 2 ) );
check( 'final attempt does not reschedule', count( $GLOBALS['test_cron'] ) === 0 );
check( 'email is NOT muted on the final attempt', $fn->suppress_during_download === false );

/* ---------------------------------------------------------------- */

reset_state();
$fn = new Efb_Queue_Test_Function();
$fn->missing     = array( 'AdnSPF' => array( 'x' ) );
$fn->download_ok = true;
set_transient( 'emsfb_addon_recovery_lock', 1, 300 );
update_option( 'emsfb_addons_dl_failures', 2 );
$fn->run_addon_recovery_event_efb( array( 'attempt' => 0 ) );
check( 'success clears the schedule lock', get_transient( 'emsfb_addon_recovery_lock' ) === false );
check( 'success clears the failure counter', ! array_key_exists( 'emsfb_addons_dl_failures', $GLOBALS['test_options'] ) );
check( 'success clears the pending context', ! array_key_exists( 'emsfb_addon_recovery_pending', $GLOBALS['test_options'] ) );
check( 'success does not reschedule', count( $GLOBALS['test_cron'] ) === 0 );

/* ---------------------------------------------------------------- */

reset_state();
$fn = new Efb_Queue_Test_Function();
$fn->missing = array();
$fn->run_addon_recovery_event_efb( array( 'attempt' => 0 ) );
check( 'healthy files short-circuit before any download', $fn->download_calls === 0 );

/* ---------------------------------------------------------------- */

reset_state();
$fn = new Efb_Queue_Test_Function();
$fn->missing = array();
$gate = $fn->check_addons_for_public_request_efb();
check( 'public gate passes when files are healthy', ! empty( $gate['success'] ) && empty( $gate['needed'] ) );
check( 'healthy public gate performs no download', $fn->download_calls === 0 );

/* --- cron availability -------------------------------------------- */

reset_state();
$fn = new Efb_Queue_Test_Function();
check( 'cron counts as available when WP-Cron is on', $fn->is_cron_available_efb() === true );

$fn->cron_disabled = true;
check( 'cron counts as unavailable with no proof it ever ran', $fn->is_cron_available_efb() === false );

update_option( 'emsfb_cron_last_run', time() - 3600 );
check( 'a recent run proves a server cron is wired up', $fn->is_cron_available_efb() === true );

update_option( 'emsfb_cron_last_run', time() - ( 3 * DAY_IN_SECONDS ) );
check( 'a stale heartbeat is not trusted', $fn->is_cron_available_efb() === false );

/* --- inline fallback when nothing will run the job ------------------ */

reset_state();
$fn = new Efb_Queue_Test_Function();
$fn->cron_disabled = true;
$fn->missing       = array( 'AdnSPF' => array( 'x' ) );
$fn->download_ok   = true;
$gate = $fn->check_addons_for_public_request_efb();
check( 'without cron the repair happens inline', $fn->download_calls === 1 );
check( 'inline repair reports success', ! empty( $gate['success'] ) );
check( 'inline repair asks the caller to reload', ! empty( $gate['recovered'] ) );
check( 'inline repair is not marked deferred', empty( $gate['deferred'] ) );
check( 'nothing was scheduled', count( $GLOBALS['test_cron'] ) === 0 );
check( 'inline mode is reset afterwards', $fn->addon_inline_mode_efb === false );
check( 'inline run used the capped single-attempt mode', $fn->inline_mode_seen === true );

/* --- the wait budget itself, not just the flag ---------------------- */

$fn = new Efb_Queue_Test_Function();
$fn->addon_inline_mode_efb = false;
$bg_en = $fn->addon_request_plan_efb( false );
$bg_fa = $fn->addon_request_plan_efb( true );
check( 'background: full 15s timeout', $bg_en['timeout'] === 15 );
check( 'background: one attempt outside Persian sites', $bg_en['attempts'] === 1 );
check( 'background: Persian sites keep the retry ladder', $bg_fa['attempts'] === 3 );

$fn->addon_inline_mode_efb = true;
$in_en = $fn->addon_request_plan_efb( false );
$in_fa = $fn->addon_request_plan_efb( true );
check( 'inline: timeout capped at 8s', $in_en['timeout'] === 8 );
check( 'inline: never more than one attempt', $in_en['attempts'] === 1 && $in_fa['attempts'] === 1 );
check( 'inline: Persian sites are capped too', $in_fa['timeout'] === 8 );
check(
	'inline worst case stays under 10s per add-on',
	( $in_fa['timeout'] * $in_fa['attempts'] ) < 10
);

reset_state();
$fn = new Efb_Queue_Test_Function();
$fn->cron_disabled = true;
$fn->missing       = array( 'AdnSPF' => array( 'x' ) );
$fn->download_ok   = false;
$gate = $fn->check_addons_for_public_request_efb();
check( 'a failed inline repair reports failure', empty( $gate['success'] ) );
check( 'a failed inline repair does not ask for a reload loop', empty( $gate['deferred'] ) );

/* --- with cron, the visitor request stays network-free -------------- */

reset_state();
$fn = new Efb_Queue_Test_Function();
$fn->missing = array( 'AdnSPF' => array( 'x' ) );
$gate = $fn->check_addons_for_public_request_efb();
check( 'with cron the public gate never downloads', $fn->download_calls === 0 );
check( 'with cron the result is marked deferred', ! empty( $gate['deferred'] ) );
check( 'with cron a job is scheduled', count( $GLOBALS['test_cron'] ) === 1 );

/* --- without cron nothing dead is scheduled ------------------------- */

reset_state();
$fn = new Efb_Queue_Test_Function();
$fn->cron_disabled = true;
$fn->missing       = array( 'AdnSPF' => array( 'x' ) );
$q = $fn->queue_addon_recovery_efb( array( 'addon' => 'AdnSPF' ) );
check( 'no cron: queueing is refused instead of scheduling a dead event', empty( $q['queued'] ) && $q['reason'] === 'cron_unavailable' );
check( 'no cron: nothing was handed to the scheduler', count( $GLOBALS['test_cron'] ) === 0 );
check( 'no cron: no lock is left behind to block a later retry', get_transient( 'emsfb_addon_recovery_lock' ) === false );
check(
	'no cron: the notice therefore carries no auto-reload (no refresh loop)',
	$fn->addon_wait_message_public_efb( empty( $q['queued'] ) ? 0 : 25 ) === $fn->addon_wait_message_public_efb( 0 )
);

/* --- reload script -------------------------------------------------- */

$fn = new Efb_Queue_Test_Function();
check( 'reload script is emitted when a retry is pending', strpos( $fn->addon_reload_script_efb( 25 ), '25000' ) !== false );
check( 'no reload script when a reload cannot help', $fn->addon_reload_script_efb( 0 ) === '' );
check( 'reload is guarded against duplicates', strpos( $fn->addon_reload_script_efb( 5 ), 'efbAddonReloadScheduled' ) !== false );
check( 'wait notice with a delay actually reloads', strpos( $fn->addon_wait_message_public_efb( 25 ), 'location.reload' ) !== false );
check( 'wait notice without a delay does not', strpos( $fn->addon_wait_message_public_efb( 0 ), 'location.reload' ) === false );

/* --- the scheduled hook must have a listener ------------------------ */
/*
 * A wp-cron.php request never runs the admin or form code, so nothing creates
 * efbFunction there. If the listener were bound in that class's constructor the
 * event would fire into the void and no add-on would ever be repaired. These
 * assertions read the plugin sources so the wiring cannot silently drift apart.
 */

$core_src = file_get_contents( dirname( __DIR__ ) . '/includes/class-Emsfb.php' );
$fn_src   = file_get_contents( dirname( __DIR__ ) . '/includes/functions.php' );

check(
	'the recovery event is bound in Emsfb, which boots on every request',
	strpos( $core_src, "add_action('emsfb_addon_recovery_event'" ) !== false
);
check(
	'Emsfb exposes the delegating handler that binding points at',
	strpos( $core_src, 'function run_addon_recovery_efb' ) !== false
);
check(
	'the lazily created class does NOT bind it a second time',
	strpos( $fn_src, 'add_action(self::ADDON_RECOVERY_EVENT_EFB' ) === false
);
check(
	'scheduler and listener agree on the hook name',
	strpos( $fn_src, "const ADDON_RECOVERY_EVENT_EFB = 'emsfb_addon_recovery_event'" ) !== false
);

/* --- the diagnostic email ------------------------------------------- */

reset_state();
$fn = new Efb_Queue_Test_Function();
update_option( 'emsfb_addon_recovery_pending', array(
	'form_id'       => 42,
	'queued_url'    => 'https://example.test/checkout',
	'cron_disabled' => true,
) );

$report = $fn->build_addon_recovery_report_efb(
	array(
		'missing' => array( 'AdnSPF' ),
		'errors'  => array( 'AdnSPF' => 'Connection timed out after 15000 ms' ),
	),
	'fallback message'
);

check( 'report names the add-on in plain language', strpos( $report, 'Stripe' ) !== false );
check( 'report includes the add-on key for support', strpos( $report, 'AdnSPF' ) !== false );
check( 'report quotes the actual server error', strpos( $report, 'Connection timed out' ) !== false );
check( 'report identifies the form involved', strpos( $report, '42' ) !== false );
check( 'report identifies the page involved', strpos( $report, '/checkout' ) !== false );
check( 'report flags a disabled WP-Cron', strpos( $report, 'DISABLE_WP_CRON' ) !== false );
check( 'report states the PHP version', strpos( $report, PHP_VERSION ) !== false );
check( 'report tells the owner what to do next', stripos( $report, 'Recover' ) !== false );

$empty_report = $fn->build_addon_recovery_report_efb( array(), 'server unreachable' );
check( 'report survives an empty details array', is_string( $empty_report ) && $empty_report !== '' );
check( 'report falls back to the last error seen', strpos( $empty_report, 'server unreachable' ) !== false );

$dirty = $fn->build_addon_recovery_report_efb(
	array( 'missing' => array( 'AdnSPF' ), 'errors' => array( 'AdnSPF' => '<script>alert(1)</script>' ) ),
	''
);
check( 'error text from the server is escaped, not injected', strpos( $dirty, '<script>alert' ) === false );

/* ---------------------------------------------------------------- */

echo "\n========================================\n";
echo "RESULTS: {$passed} passed, {$failed} failed\n";
echo "========================================\n";
exit( $failed > 0 ? 1 : 0 );
