<?php
/**
 * Add-on recovery side effects: which forms wait for a missing add-on, when a
 * visitor's page reloads, who gets emailed, and which admin pages stay open.
 *
 * Every scenario runs in its own PHP process, because
 * recover_missing_addons_efb() caches its result for the rest of the process.
 *
 * Run: php tests/test-addon-recovery-side-effects.php
 */

$efb_scenarios = array(
	'form_requirements',
	'gate_with_cron',
	'gate_without_cron_repairs_the_needed_addon',
	'gate_without_cron_judges_by_the_form',
	'gate_without_cron_respects_backoff',
	'admin_view_sends_no_email:create',
	'admin_view_sends_no_email:panel',
	'admin_view_sends_no_email:addons',
	'admin_view_sends_no_email:manual',
	'admin_view_respects_backoff',
	'admin_view_respects_renew_backoff',
	'recover_button_ignores_backoff',
	'unattended_repair_still_reports',
	'report_throttle',
	'reload_guard',
	'wiring',
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

list( $scenario, $scenario_arg ) = array_pad( explode( ':', $argv[1], 2 ), 2, '' );

define( 'ABSPATH', __DIR__ . '/' );
define( 'EMSFB_PLUGIN_DIRECTORY', dirname( __DIR__ ) . '/' );
define( 'EMSFB_PLUGIN_VERSION', '4.2.1' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$test_options       = array();
$test_transients    = array();
$test_cron          = array();
$test_email_enabled = true;
$test_can_addons    = true;
$test_settings      = (object) array(
	'AdnHSH'         => '1',
	'AdnSMF'         => '1',
	'AdnSPF'         => '1',
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

function get_setting_Emsfb( $mode = 'decoded' ) { return $GLOBALS['test_settings']; }
function emsfb_is_email_sending_enabled_efb( $settings = null ) { return $GLOBALS['test_email_enabled']; }
function current_user_can( $capability ) { return $GLOBALS['test_can_addons']; }
function wp_create_nonce( $action = -1 ) { return 'nonce'; }
function wp_json_encode( $data, $options = 0 ) { return json_encode( $data, $options ); }
function esc_html__( $text, $domain = '' ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return $url; }
function esc_url_raw( $url ) { return $url; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function get_bloginfo( $what = 'name' ) { return 'version' === $what ? '7.0' : 'Test Site'; }
function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
function wp_kses( $text, $allowed ) { return strip_tags( (string) $text, '<' . implode( '><', array_keys( $allowed ) ) . '>' ); }
function current_time( $type ) { return '2026-09-15 12:00:00'; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return is_string( $value ) ? trim( strip_tags( $value ) ) : ''; }
function wp_unslash( $value ) { return $value; }

require_once dirname( __DIR__ ) . '/includes/functions.php';

class Efb_Side_Effects_Test extends efbFunction {
	/** Add-on key => files: what the local health check finds missing. */
	public $missing = array();

	/** Add-ons the stubbed add-on server hands over. */
	public $server_delivers = array();

	/** Add-on keys requested from the add-on server, in order. */
	public $server_requests = array();

	/** States of the report emails that would have been sent. */
	public $reports = array();

	public $cron_disabled = false;

	public function __construct() {}

	protected function wp_cron_disabled_efb() {
		return (bool) $this->cron_disabled;
	}

	public function get_addon_local_health_efb( $settings = null, $scope = 'runtime' ) {
		return array( 'missing' => $this->missing, 'checked' => array_keys( $this->missing ) );
	}

	public function is_addon_installed_locally_efb( $addon_key ) {
		return ! isset( $this->missing[ $addon_key ] );
	}

	/** $missing stands for the translation files of the admin scope too. */
	public function addon_scope_files_present_efb( $addon_key ) {
		return ! isset( $this->missing[ $addon_key ] );
	}

	public function addon_add_efb( $value ) {
		$this->server_requests[] = $value;
		if ( in_array( $value, $this->server_delivers, true ) ) {
			unset( $this->missing[ $value ] );
			return array( 'status' => true, 'message' => 'installed' );
		}
		return array( 'status' => false, 'message' => 'Cannot install add-ons of Easy Form Builder because the plugin is not able to connect to the whitestudio.team server' );
	}

	public function send_email_state_new( $to, $sub, $cont, $pro, $state, $link, $st = "null" ) {
		$this->reports[] = $state;
		return true;
	}
}

function check( $label, $condition ) {
	echo ( $condition ? '[PASS] ' : '[FAIL] ' ) . $label . "\n";
}

function reset_site() {
	global $test_options, $test_transients, $test_cron;
	$test_options    = array();
	$test_transients = array();
	$test_cron       = array();
}

$hsh   = array( 'AdnHSH' => array( 'vendor/human-shield/human-shield-efb.php' ) );
$logic = array( 'AdnSMF' => array( 'vendor/logic/logic/class-Emsfb-logic-validator.php' ) );

switch ( $scenario ) {
	case 'form_requirements':
		$fn    = new Efb_Side_Effects_Test();
		$needs = function ( $structure ) use ( $fn ) {
			return $fn->get_form_required_addons_efb( $structure );
		};
		// Some rows store the JSON with escaped quotes, newer ones store it plain.
		$escaped = function ( $json ) {
			return str_replace( '"', '\"', $json );
		};
		$contact = '[{"type":"form","steps":"1","email":"owner@example.test"},{"type":"step","id_":"1"},{"type":"email","id_":"a1"},{"type":"radio","id_":"a2"},{"type":"option","parent":"a2"}]';

		check( 'a contact form needs no add-on', array() === $needs( $contact ) );
		check( 'the same form stored with escaped quotes needs none either', array() === $needs( $escaped( $contact ) ) );
		check( 'conditional logic stored as "logic":"1" needs Conditional Logic', array( 'AdnSMF' ) === $needs( $escaped( '[{"type":"form","logic":"1"}]' ) ) );
		check( 'conditional logic rules need Conditional Logic', array( 'AdnSMF' ) === $needs( '[{"type":"form","logic":true,"logic_rules":[{"id":"r1"}]}]' ) );
		check( 'a Stripe field needs Stripe', array( 'AdnSPF' ) === $needs( '[{"type":"payment"},{"type":"stripe","id_":"s"}]' ) );
		check( 'a PayPal field needs PayPal', array( 'AdnPAP' ) === $needs( '[{"type":"payment"},{"type":"paypal","id_":"p"}]' ) );
		check( 'a ZarinPal field needs Persia Payment', array( 'AdnPPF' ) === $needs( '[{"type":"payment"},{"type":"zarinPal"}]' ) );
		check( 'a Persia Payment field needs Persia Payment', array( 'AdnPPF' ) === $needs( '[{"type":"payment"},{"type":"persiaPay"}]' ) );
		check( 'a Persian date field needs the Persian date picker', array( 'AdnPDP' ) === $needs( $escaped( '[{"type":"form"},{"type":"pdate"}]' ) ) );
		check( 'an Arabic date field needs the Arabic date picker', array( 'AdnADP' ) === $needs( '[{"type":"form"},{"type":"ardate"}]' ) );
		check( 'a state list needs the offline data add-on', array( 'AdnOF' ) === $needs( '[{"type":"form"},{"type":"statePro"}]' ) );
		check( 'a city list needs the offline data add-on', array( 'AdnOF' ) === $needs( '[{"type":"form"},{"type":"cityList"}]' ) );
		check( 'a configured autofill needs Auto-Populate', array( 'AdnATF' ) === $needs( '[{"type":"form","autofill_id":"4"}]' ) );
		check( 'an autofill API needs Auto-Populate', array( 'AdnATF' ) === $needs( '[{"type":"form","autofill_api":true,"autofill_api_id":"x"}]' ) );
		check( 'an autofill API switched off needs nothing', array() === $needs( '[{"type":"form","autofill_api":false}]' ) );
		check( 'a field type that only starts like a paid one does not count', array() === $needs( '[{"type":"form"},{"type":"stripeText"},{"type":"cityName"}]' ) );
		check( 'notification and security settings never hide a form', array() === $needs( '[{"type":"form","smsnoti":"1","telegram":"1","googleSheet":"1","sendEmail":true}]' ) );
		check(
			'a form using several add-ons lists each one once',
			array( 'AdnSMF', 'AdnSPF', 'AdnPDP', 'AdnOF' ) === $needs( '[{"type":"payment","logic_rules":[]},{"type":"stripe"},{"type":"pdate"},{"type":"country"},{"type":"cityList"}]' )
		);
		break;

	case 'gate_with_cron':
		reset_site();
		$fn          = new Efb_Side_Effects_Test();
		$fn->missing = $hsh;

		$gate = $fn->check_addons_for_public_request_efb( null, array() );
		check( 'a form that uses no add-on renders while Form Security is missing', ! empty( $gate['success'] ) );
		check( 'that form is not held back for a repair', empty( $gate['needed'] ) && empty( $gate['recovered'] ) );
		check( 'the missing add-on is still queued for a background repair', 1 === count( $GLOBALS['test_cron'] ) );
		check( 'the queued repair is recorded as a background one', 'public_background' === ( get_option( 'emsfb_addon_recovery_pending' )['source'] ?? '' ) );

		$gate = $fn->check_addons_for_public_request_efb( null, array( 'AdnSMF', 'AdnSPF' ) );
		check( 'a logic or payment form renders too while only Form Security is missing', ! empty( $gate['success'] ) );
		check( 'the background repair is not queued twice', 1 === count( $GLOBALS['test_cron'] ) );

		reset_site();
		$fn->missing = $hsh + $logic;
		$first = $fn->check_addons_for_public_request_efb( null, array( 'AdnSMF' ) );
		check( 'a logic form waits while Conditional Logic is missing', empty( $first['success'] ) );
		check( 'it reports only the add-on it needs', array( 'AdnSMF' ) === $first['missing'] );
		check( 'the first visitor queues the repair, so that one page reloads once', ! empty( $first['queued'] ) );

		$second = $fn->check_addons_for_public_request_efb( null, array( 'AdnSMF' ) );
		check( 'the next visitor finds it queued and is not reloaded', empty( $second['queued'] ) && 'already_queued' === $second['queue_reason'] );

		$plain = $fn->check_addons_for_public_request_efb( null, array() );
		check( 'a plain form on the same site keeps rendering', ! empty( $plain['success'] ) );

		$legacy = $fn->check_addons_for_public_request_efb();
		check( 'a caller that passes no requirements keeps the old all-add-ons gate', empty( $legacy['success'] ) );

		delete_transient( 'emsfb_addon_recovery_lock' );
		set_transient( 'emsfb_addons_dl_backoff', 1, DAY_IN_SECONDS );
		$after = $fn->check_addons_for_public_request_efb( null, array( 'AdnSMF' ) );
		check( 'after a failed repair visitors are not queued, so nothing reloads', empty( $after['queued'] ) && 'backoff' === $after['queue_reason'] );
		check( 'a visitor request with cron never downloads', array() === $fn->server_requests );
		break;

	case 'gate_without_cron_repairs_the_needed_addon':
		reset_site();
		$fn                  = new Efb_Side_Effects_Test();
		$fn->cron_disabled   = true;
		$fn->missing         = $logic;
		$fn->server_delivers = array( 'AdnSMF' );
		$gate = $fn->check_addons_for_public_request_efb( null, array( 'AdnSMF' ) );
		check( 'without cron the add-on the form needs is repaired inline', array( 'AdnSMF' ) === $fn->server_requests );
		check( 'the visitor gets the one-time reload transition', ! empty( $gate['success'] ) && ! empty( $gate['recovered'] ) );
		check( 'no queued-repair reload is asked for', empty( $gate['queued'] ) );
		check( 'nothing is scheduled', array() === $GLOBALS['test_cron'] );
		check( 'a repaired add-on sends no report', array() === $fn->reports );
		break;

	case 'gate_without_cron_judges_by_the_form':
		reset_site();
		$fn                  = new Efb_Side_Effects_Test();
		$fn->cron_disabled   = true;
		$fn->missing         = $hsh + $logic;
		$fn->server_delivers = array( 'AdnSMF' );
		$gate = $fn->check_addons_for_public_request_efb( null, array( 'AdnSMF' ) );
		check( 'the logic form is shown once its own add-on is back', ! empty( $gate['success'] ) && ! empty( $gate['recovered'] ) );
		check( 'even though Form Security could not be restored', in_array( 'AdnHSH', (array) $gate['missing'], true ) );

		reset_site();
		$plain                = new Efb_Side_Effects_Test();
		$plain->cron_disabled = true;
		$plain->missing       = $hsh;
		$result = $plain->check_addons_for_public_request_efb( null, array() );
		check( 'without cron a form that needs nothing renders at once', ! empty( $result['success'] ) );
		check( 'and its visitor does not wait for a download', array() === $plain->server_requests );
		break;

	case 'gate_without_cron_respects_backoff':
		reset_site();
		$fn                = new Efb_Side_Effects_Test();
		$fn->cron_disabled = true;
		$fn->missing       = $logic;
		set_transient( 'emsfb_addons_dl_backoff', 1, DAY_IN_SECONDS );
		update_option( 'emsfb_addon_recovery_result', array( 'errors' => array( 'AdnSMF' => 'Connection timed out' ) ) );
		$gate = $fn->check_addons_for_public_request_efb( null, array( 'AdnSMF' ) );
		check( 'after a failed inline repair the next visitor does not wait on the server', array() === $fn->server_requests );
		check( 'the logic form stays hidden', empty( $gate['success'] ) );
		check( 'and the page is not told to reload', empty( $gate['queued'] ) && empty( $gate['deferred'] ) );
		check( 'no report is emailed for that visitor', array() === $fn->reports );
		break;

	case 'admin_view_sends_no_email':
		reset_site();
		$fn          = new Efb_Side_Effects_Test();
		$fn->missing = $hsh;
		$result = $fn->recover_missing_addons_efb( null, $scenario_arg );
		check( "{$scenario_arg}: the repair is tried", array( 'AdnHSH' ) === $fn->server_requests );
		check( "{$scenario_arg}: the reason comes back for the screen", ! empty( $result['errors']['AdnHSH'] ) );
		check( "{$scenario_arg}: no report email is sent", array() === $fn->reports );
		check( "{$scenario_arg}: unattended runs are not left muted", false === $fn->suppress_addon_report_efb );
		check( "{$scenario_arg}: the failure starts the backoff", false !== get_transient( 'emsfb_addons_dl_backoff' ) );
		break;

	case 'admin_view_respects_backoff':
		reset_site();
		$fn          = new Efb_Side_Effects_Test();
		$fn->missing = $hsh;
		set_transient( 'emsfb_addons_dl_backoff', 1, DAY_IN_SECONDS );
		update_option( 'emsfb_addon_recovery_result', array( 'errors' => array( 'AdnHSH' => 'Connection timed out' ) ) );
		$result = $fn->recover_missing_addons_efb( null, 'panel' );
		check( 'a page view inside the backoff does not call the server', array() === $fn->server_requests );
		check( 'it still shows the last reason', 'Connection timed out' === ( $result['errors']['AdnHSH'] ?? '' ) );
		check( 'it sends no email', array() === $fn->reports );
		check( 'the add-on is still reported missing', empty( $result['success'] ) && array( 'AdnHSH' ) === $result['missing'] );
		check( 'the stored reason is kept for the next page view', 'Connection timed out' === ( get_option( 'emsfb_addon_recovery_result' )['errors']['AdnHSH'] ?? '' ) );
		break;

	case 'admin_view_respects_renew_backoff':
		reset_site();
		$fn          = new Efb_Side_Effects_Test();
		$fn->missing = $hsh;
		set_transient( 'emsfb_addons_renew_backoff', 1, DAY_IN_SECONDS );
		$result = $fn->recover_missing_addons_efb( null, 'create' );
		check( 'an expired subscription is not asked again on every page view', array() === $fn->server_requests );
		check( 'the page is told a renewal is needed', ! empty( $result['renew_required'] ) );
		break;

	case 'recover_button_ignores_backoff':
		reset_site();
		$fn                  = new Efb_Side_Effects_Test();
		$fn->missing         = $hsh;
		$fn->server_delivers = array( 'AdnHSH' );
		set_transient( 'emsfb_addons_dl_backoff', 1, DAY_IN_SECONDS );
		set_transient( 'emsfb_addons_renew_backoff', 1, DAY_IN_SECONDS );
		$result = $fn->recover_missing_addons_efb( null, 'manual' );
		check( 'Recover asks the server even inside both backoffs', array( 'AdnHSH' ) === $fn->server_requests );
		check( 'and restores the add-on', ! empty( $result['recovered'] ) );
		break;

	case 'unattended_repair_still_reports':
		reset_site();
		$fn          = new Efb_Side_Effects_Test();
		$fn->missing = $hsh;
		$fn->recover_missing_addons_efb( null, 'public_inline' );
		check( 'a failed repair nobody is watching emails the owner', array( 'addonsDlProblem' ) === $fn->reports );
		break;

	case 'report_throttle':
		reset_site();
		$fn          = new Efb_Side_Effects_Test();
		$fn->missing = $hsh;
		$fn->download_all_addons_efb( true );
		$fn->download_all_addons_efb( true );
		check( 'the same failure twice in a day sends one report', 1 === count( $fn->reports ) );

		$fn->missing += $logic;
		$fn->download_all_addons_efb( true );
		check( 'a different failure is reported', 2 === count( $fn->reports ) );

		delete_transient( 'emsfb_addons_report_sent' );
		$fn->download_all_addons_efb( true );
		check( 'the same failure is reported again once the day is over', 3 === count( $fn->reports ) );

		reset_site();
		$fn->reports = array();
		$fn->missing = $hsh;
		$fn->run_addon_recovery_event_efb( array( 'attempt' => 2 ) );
		delete_transient( 'emsfb_addons_dl_backoff' );
		$fn->run_addon_recovery_event_efb( array( 'attempt' => 2 ) );
		check( 'two retry cycles on the same day send one report', 1 === count( $fn->reports ) );

		$fn->server_delivers = array( 'AdnHSH' );
		$fn->download_all_addons_efb( true );
		check( 'a successful repair clears the throttle', false === get_transient( 'emsfb_addons_report_sent' ) );

		set_transient( 'emsfb_addons_report_sent', 'x', DAY_IN_SECONDS );
		$fn->clear_addon_recovery_state_efb();
		check( 'files coming back any other way clear the throttle too', false === get_transient( 'emsfb_addons_report_sent' ) );

		reset_site();
		$GLOBALS['test_email_enabled'] = false;
		$quiet          = new Efb_Side_Effects_Test();
		$quiet->missing = $hsh;
		$quiet->download_all_addons_efb( true );
		check( 'with email sending off no report is recorded as sent', false === get_transient( 'emsfb_addons_report_sent' ) && array() === $quiet->reports );
		break;

	case 'reload_guard':
		$fn     = new Efb_Side_Effects_Test();
		$script = $fn->addon_reload_script_efb( 25 );
		check( 'the reload still waits 25 seconds', false !== strpos( $script, '25000' ) );
		check( 'no delay means no reload script at all', '' === $fn->addon_reload_script_efb( 0 ) );

		if ( 0 !== strpos( trim( (string) shell_exec( 'node --version 2>&1' ) ), 'v' ) ) {
			echo "[SKIP] node is not available to run the reload script\n";
			break;
		}
		// Runs the shipped script as successive page loads of one browser tab.
		$harness = sys_get_temp_dir() . '/efb-reload-guard-' . getmypid() . '.js';
		file_put_contents( $harness, 'const vm = require("vm");
const script = ' . json_encode( preg_replace( '#^<script>|</script>$#', '', $script ) ) . ';
const storage = new Map();
function pageLoad(atMs, storageThrows) {
	const scheduled = [];
	const page = {
		location: { pathname: "/contact/", reload() {} },
		Date: { now: () => atMs },
		setTimeout: (fn, ms) => scheduled.push(ms),
	};
	page.window = page;
	Object.defineProperty(page, "sessionStorage", { get() {
		if (storageThrows) { throw new Error("SecurityError"); }
		return { getItem: (k) => (storage.has(k) ? storage.get(k) : null), setItem: (k, v) => storage.set(k, String(v)) };
	} });
	vm.runInNewContext(script, page);
	return scheduled.length;
}
const start = 1789470000000;
console.log(JSON.stringify([
	pageLoad(start, false),
	pageLoad(start + 25000, false),
	pageLoad(start + 26000, false),
	pageLoad(start + 6 * 60 * 1000, false),
	pageLoad(start, true),
]));
' );
		$counts = json_decode( trim( (string) shell_exec( 'node ' . escapeshellarg( $harness ) . ' 2>&1' ) ), true );
		unlink( $harness );
		check( 'the first page load schedules the reload', isset( $counts[0] ) && 1 === $counts[0] );
		check( 'the reloaded page does not schedule another one', isset( $counts[1] ) && 0 === $counts[1] );
		check( 'nor does any load within five minutes', isset( $counts[2] ) && 0 === $counts[2] );
		check( 'a load after five minutes may reload once more', isset( $counts[3] ) && 1 === $counts[3] );
		check( 'a browser that blocks sessionStorage still gets its reload', isset( $counts[4] ) && 1 === $counts[4] );
		break;

	case 'wiring':
		$public = file_get_contents( dirname( __DIR__ ) . '/includes/class-Emsfb-public.php' );
		$start  = strpos( $public, 'public function EFB_Form_Builder(' );
		$render = substr( $public, $start, strpos( $public, 'private function efb_build_inline_style_overrides(' ) - $start );
		check( 'the render calls the public gate exactly once', 1 === substr_count( $render, 'check_addons_for_public_request_efb(' ) );
		check( 'the gate runs after the form is loaded', strpos( $render, 'get_form_data_efb(' ) < strpos( $render, 'check_addons_for_public_request_efb(' ) );
		check( 'the gate is given the form\'s own add-ons', false !== strpos( $render, 'get_form_required_addons_efb( $value_form_data->form_structer )' ) );
		check( 'the visitor reload is keyed on a queued repair', false !== strpos( $render, '$retry_after = ! empty( $addon_recovery[\'queued\'] ) ? 25 : 0;' ) );
		check( 'the visitor reload is no longer keyed on deferred', false === strpos( $render, '$addon_recovery[\'deferred\'] ) ? 25' ) );

		$addon_page = file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-Emsfb-addon.php' );
		check( 'the Add-ons page never stops at the block state', false === strpos( $addon_page, "'block' === \$addon_recovery_state" ) );
		check( 'the Add-ons page shows the recovery card for block and inline', false !== strpos( $addon_page, "'none' !== \$addon_recovery_state" ) );
		foreach ( array( 'class-Emsfb-create.php', 'class-Emsfb-panel.php' ) as $admin_page ) {
			check( "{$admin_page} keeps its post-update gate", false !== strpos( file_get_contents( dirname( __DIR__ ) . '/includes/admin/' . $admin_page ), "'block' === \$addon_recovery_state" ) );
		}

		$core    = file_get_contents( dirname( __DIR__ ) . '/includes/class-Emsfb.php' );
		$upgrade = substr( $core, strpos( $core, 'private function run_upgrade_tasks_efb' ), 1500 );
		check( 'a plugin update clears the old download backoff', false !== strpos( $upgrade, "delete_transient('emsfb_addons_dl_backoff')" ) );

		$fn          = new Efb_Side_Effects_Test();
		$fn->missing = $hsh;
		check( 'the post-update card links to the Add-ons page', false !== strpos( $fn->render_addon_recovery_ui_efb( 'block' ), 'page=Emsfb_addon' ) );
		check( 'the inline banner does not repeat that link', false === strpos( $fn->render_addon_recovery_ui_efb( 'inline' ), 'page=Emsfb_addon' ) );
		$GLOBALS['test_can_addons'] = false;
		check( 'the link is hidden from users who cannot open Add-ons', false === strpos( $fn->render_addon_recovery_ui_efb( 'block' ), 'page=Emsfb_addon' ) );
		break;

	default:
		echo "[FAIL] unknown scenario {$scenario}\n";
}

echo "[DONE]\n";
