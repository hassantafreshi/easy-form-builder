<?php
/**
 * WordPress integration test for Scenario H — Addon Gating — of the E2E plan
 * (docs/conditional-logic/EFB-Conditional-Logic-E2E-TEST-FA.md §12).
 *
 * Verifies, against the REAL plugin boot path, that toggling the AdnSMF
 * add-on flips every conditional-logic gate without touching stored data:
 *
 *  AdnSMF = 0 (disabled):
 *   - server validator file is not loaded, no efb_logic_* filters registered
 *     (class-Emsfb.php ~156) → submissions take the legacy path even when the
 *     form structure contains logic_rules ("form works as a normal form").
 *   - builder asset gate is closed (class-Emsfb-create.php ~305,
 *     class-Emsfb-panel.php ~301) → conditional-logic-efb.js (admin) is not
 *     enqueued, and since the builder button is rendered BY that script the
 *     button disappears too.
 *   - public runtime gate is closed (class-Emsfb-public.php ~806) →
 *     public/assets/js/conditional-logic-efb.js is not enqueued.
 *   - saved logic_rules stay untouched in the forms table, and the save
 *     sanitizer still round-trips logic_rules (no addon gate on save).
 *
 *  AdnSMF = 1 (re-enabled):
 *   - validator loaded, filters back, submissions conditional again,
 *     all asset gates open, stored rules byte-identical.
 *
 * Each state is probed in a FRESH child PHP process (--probe mode) because
 * the load decision happens once at plugin boot.
 *
 * The test snapshots the settings row / option / transient beforehand and
 * restores them afterwards, and asserts the forms table checksum never
 * changes.
 *
 * Run: php tests/test-addon-gating-wordpress.php
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}

require_once $wp_load;

// ─────────────────────────────────────────────────────────────────────────────
// Probe mode: report the gate states of THIS freshly booted process as JSON.
// ─────────────────────────────────────────────────────────────────────────────
if ( in_array( '--probe', $argv ?? array(), true ) ) {
	$decoded = get_setting_Emsfb( 'decoded' );
	$helper  = get_efbFunction();
	$admin_addons = $helper->fun_get_addons_list_efb( $decoded );
	$pub = get_setting_Emsfb( 'pub' );

	// Real end-to-end submission behavior: a form whose structure contains an
	// active rule ("fa is x -> show_field fb") submitted with fa != x, plus a
	// row for fb as if the client force-revealed it.
	$conditional_form = array(
		array( 'logic_rules' => array( array(
			'id' => 'r1', 'enabled' => true, 'priority' => 10, 'stop_processing' => false,
			'conditions' => array( 'type' => 'group', 'operator' => 'AND', 'items' => array(
				array( 'type' => 'condition', 'source' => 'field', 'field_id' => 'fa', 'compare' => 'is', 'value' => 'x' ),
			) ),
			'actions' => array( array( 'type' => 'show_field', 'target' => 'fb' ) ),
		) ) ),
		array( 'id_' => 'fa', 'type' => 'text', 'name' => 'A' ),
		array( 'id_' => 'fb', 'type' => 'text', 'name' => 'B' ),
	);
	$rows = array(
		array( 'id_' => 'fa', 'type' => 'text', 'value' => 'not-x' ),
		array( 'id_' => 'fb', 'type' => 'text', 'value' => 'forced' ),
	);
	// Same default payload as the live submission path (class-Emsfb-public.php ~1725).
	$prepared = apply_filters(
		'efb_logic_prepare_submission',
		array( 'is_conditional' => false, 'submitted_values' => $rows, 'logic_result' => array() ),
		$conditional_form,
		$rows
	);
	$kept = array();
	foreach ( ( $prepared['submitted_values'] ?? $rows ) as $row ) {
		$kept[] = $row['id_'] ?? '';
	}

	// Save path: the REAL sanitize_logic_rules (private, functions.php ~2145)
	// must keep rules regardless of the addon state.
	$save_keeps_rules = false;
	try {
		$m = new ReflectionMethod( get_class( $helper ), 'sanitize_logic_rules' );
		$m->setAccessible( true );
		$sanitized = $m->invoke( $helper, $conditional_form[0]['logic_rules'], $conditional_form );
		$save_keeps_rules = ! empty( $sanitized )
			&& ( $sanitized[0]['actions'][0]['target'] ?? '' ) === 'fb'
			&& ( $sanitized[0]['conditions']['items'][0]['compare'] ?? '' ) === 'is';
	} catch ( \Throwable $e ) {
		$save_keeps_rules = 'reflection-error: ' . $e->getMessage();
	}

	echo wp_json_encode( array(
		'adnsmf'                    => is_object( $decoded ) && isset( $decoded->AdnSMF ) ? (int) $decoded->AdnSMF : 0,
		'validator_loaded'          => class_exists( 'Emsfb\\Emsfb_Logic_Validator' ),
		'filter_prepare'            => false !== has_filter( 'efb_logic_prepare_submission' ),
		'filter_evaluate'           => false !== has_filter( 'efb_logic_evaluate' ),
		'filter_validate'           => false !== has_filter( 'efb_logic_validate_required' ),
		'admin_builder_gate'        => isset( $admin_addons['AdnSMF'] ) && (int) $admin_addons['AdnSMF'] >= 1,
		'public_runtime_gate'       => (bool) emsfb_is_addon_active_efb( $pub[1] ?? array(), 'AdnSMF' ),
		'submission_is_conditional' => ! empty( $prepared['is_conditional'] ),
		'submission_fb_stripped'    => ! in_array( 'fb', $kept, true ),
		'save_keeps_rules'          => $save_keeps_rules,
	) ) . "\n";
	exit( 0 );
}

// ─────────────────────────────────────────────────────────────────────────────
// Runner mode: toggle AdnSMF off → probe → on → probe → restore.
// ─────────────────────────────────────────────────────────────────────────────
global $wpdb;

$pass = 0;
$fail = 0;
function check( $label, $ok ) {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "[PASS] $label\n"; }
	else { $fail++; echo "[FAIL] $label\n"; }
}

$settings_table = $wpdb->prefix . 'emsfb_setting';
$forms_table    = $wpdb->prefix . 'emsfb_form';

$setting_row = $wpdb->get_row( "SELECT id, setting FROM `$settings_table` ORDER BY id DESC LIMIT 1" );
if ( ! $setting_row || empty( $setting_row->setting ) ) {
	echo "[SKIP] No settings row found in $settings_table — configure the plugin once first.\n";
	exit( 0 );
}

// Snapshots for full restore.
$original_setting_raw = $setting_row->setting;
$original_option      = get_option( 'emsfb_settings', null );
$original_transient   = get_transient( 'emsfb_settings_transient' );

$decoded_original = json_decode( str_replace( '\\', '', $original_setting_raw ) );
if ( ! is_object( $decoded_original ) ) {
	echo "[SKIP] Settings JSON could not be decoded; aborting without changes.\n";
	exit( 0 );
}
$original_adnsmf = isset( $decoded_original->AdnSMF ) ? (int) $decoded_original->AdnSMF : 0;
echo "Current AdnSMF state: $original_adnsmf\n";

function efb_gating_write_settings( $json ) {
	global $wpdb, $settings_table, $setting_row;
	$wpdb->update( $settings_table, array( 'setting' => $json ), array( 'id' => $setting_row->id ) );
	update_option( 'emsfb_settings', $json );
	set_transient( 'emsfb_settings_transient', $json, 1800 );
	foreach ( array( 'decoded', 'pub', 'raw' ) as $mode ) {
		wp_cache_delete( 'settings:' . $mode, 'emsfb' );
	}
	get_setting_Emsfb( '_clear_cache' );
}

function efb_gating_probe() {
	$cmd = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --probe';
	$out = shell_exec( $cmd );
	$lines = array_values( array_filter( array_map( 'trim', explode( "\n", (string) $out ) ) ) );
	$json = null;
	foreach ( array_reverse( $lines ) as $line ) {
		$json = json_decode( $line, true );
		if ( is_array( $json ) ) break;
	}
	if ( ! is_array( $json ) ) {
		echo "[probe raw output]\n" . $out . "\n";
		return null;
	}
	return $json;
}

// Forms-table integrity: nothing about toggling the addon may touch form data.
$forms_checksum_before = $wpdb->get_row( "CHECKSUM TABLE `$forms_table`", ARRAY_A );
$rule_forms_before = $wpdb->get_results(
	"SELECT form_id, MD5(form_structer) AS h FROM `$forms_table` WHERE form_structer LIKE '%logic_rules%'",
	ARRAY_A
);
echo 'Forms containing logic_rules: ' . count( $rule_forms_before ) . "\n";

$restored = false;
$restore = function () use ( &$restored, $original_setting_raw, $original_option, $original_transient ) {
	if ( $restored ) return;
	$restored = true;
	efb_gating_write_settings( $original_setting_raw );
	if ( $original_option === null ) delete_option( 'emsfb_settings' );
	else update_option( 'emsfb_settings', $original_option );
	if ( $original_transient === false ) delete_transient( 'emsfb_settings_transient' );
	else set_transient( 'emsfb_settings_transient', $original_transient, 1800 );
	echo "Settings restored to original state (AdnSMF back to saved value).\n";
};
register_shutdown_function( $restore );

try {
	// ── State 1: AdnSMF disabled ─────────────────────────────────────────────
	$off = clone $decoded_original;
	$off->AdnSMF = 0;
	efb_gating_write_settings( wp_json_encode( $off, JSON_UNESCAPED_UNICODE ) );
	$p = efb_gating_probe();
	check( 'OFF: probe returned parseable JSON', is_array( $p ) );
	if ( is_array( $p ) ) {
		check( 'OFF: decoded settings report AdnSMF=0', $p['adnsmf'] === 0 );
		check( 'OFF: server validator class NOT loaded', $p['validator_loaded'] === false );
		check( 'OFF: efb_logic_prepare_submission has no callbacks', $p['filter_prepare'] === false );
		check( 'OFF: efb_logic_evaluate has no callbacks', $p['filter_evaluate'] === false );
		check( 'OFF: efb_logic_validate_required has no callbacks', $p['filter_validate'] === false );
		check( 'OFF: builder asset gate closed (no admin JS => no CL button)', $p['admin_builder_gate'] === false );
		check( 'OFF: public runtime asset gate closed', $p['public_runtime_gate'] === false );
		check( 'OFF: submission with logic_rules behaves like a NORMAL form', $p['submission_is_conditional'] === false );
		check( 'OFF: no rows stripped on the legacy path', $p['submission_fb_stripped'] === false );
		check( 'OFF: save sanitizer still round-trips logic_rules', $p['save_keeps_rules'] === true );
	}

	// ── State 2: AdnSMF re-enabled ───────────────────────────────────────────
	$on = clone $decoded_original;
	$on->AdnSMF = 1;
	efb_gating_write_settings( wp_json_encode( $on, JSON_UNESCAPED_UNICODE ) );
	$p = efb_gating_probe();
	check( 'ON: probe returned parseable JSON', is_array( $p ) );
	if ( is_array( $p ) ) {
		check( 'ON: decoded settings report AdnSMF=1', $p['adnsmf'] === 1 );
		check( 'ON: server validator class loaded', $p['validator_loaded'] === true );
		check( 'ON: efb_logic_prepare_submission registered', $p['filter_prepare'] === true );
		check( 'ON: efb_logic_evaluate registered', $p['filter_evaluate'] === true );
		check( 'ON: efb_logic_validate_required registered', $p['filter_validate'] === true );
		check( 'ON: builder asset gate open (admin JS + CL button back)', $p['admin_builder_gate'] === true );
		check( 'ON: public runtime asset gate open', $p['public_runtime_gate'] === true );
		check( 'ON: submission is conditional again', $p['submission_is_conditional'] === true );
		check( 'ON: hidden-field row stripped by server validator', $p['submission_fb_stripped'] === true );
		check( 'ON: save sanitizer round-trips logic_rules', $p['save_keeps_rules'] === true );
	}

	// ── Data integrity across the whole toggle cycle ─────────────────────────
	$forms_checksum_after = $wpdb->get_row( "CHECKSUM TABLE `$forms_table`", ARRAY_A );
	check(
		'Toggle cycle left the forms table byte-identical (rules NOT deleted)',
		isset( $forms_checksum_before['Checksum'], $forms_checksum_after['Checksum'] )
			&& $forms_checksum_before['Checksum'] === $forms_checksum_after['Checksum']
	);
	if ( ! empty( $rule_forms_before ) ) {
		$rule_forms_after = $wpdb->get_results(
			"SELECT form_id, MD5(form_structer) AS h FROM `$forms_table` WHERE form_structer LIKE '%logic_rules%'",
			ARRAY_A
		);
		$same = count( $rule_forms_before ) === count( $rule_forms_after );
		if ( $same ) {
			$before_map = array_column( $rule_forms_before, 'h', 'form_id' );
			foreach ( $rule_forms_after as $row ) {
				if ( ( $before_map[ $row['form_id'] ] ?? null ) !== $row['h'] ) { $same = false; break; }
			}
		}
		check( 'Every form containing logic_rules is unchanged after re-enable', $same );
	}
} finally {
	$restore();
}

// Confirm restore really happened.
$final_raw = $wpdb->get_var( $wpdb->prepare( "SELECT setting FROM `$settings_table` WHERE id = %d", $setting_row->id ) );
check( 'Original settings row restored exactly', $final_raw === $original_setting_raw );

echo "\n=============================\n";
echo "PASS: $pass  FAIL: $fail\n";
echo $fail === 0 ? "ALL TESTS PASSED\n" : "SOME TESTS FAILED\n";
exit( $fail === 0 ? 0 : 1 );
