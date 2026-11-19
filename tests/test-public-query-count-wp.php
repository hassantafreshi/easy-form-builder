<?php
/**
 * PERF-02: a form page made about 36 database queries (WP-CLI render of [EMS_Form_Builder id=4]); 22 of them were
 * single reads of options that are not autoloaded and mostly do not exist (emsfb_addon_<code> for each add-on code
 * get_setting_Emsfb('pub') knows, the recovery clean-up flag, the privacy and SMS options). The render now loads them
 * in one query (_Public::prime_page_options_efb(): wp_prime_option_caches(), absent ones remembered as absent).
 *
 * On the real site (under the e2e lock): a throwaway simple form is rendered through its shortcode as a visitor, the
 * queries the plugin makes are counted (a query whose call stack passes through the plugin; the e2e site's own test
 * options efb_e2e_* are left out, they do not exist on a real site):
 *  - no single read of an emsfb_addon_* option, of emsfb_prv_forms or of emsfb_sms_db;
 *  - at most 15 queries (the release budget: docs of PERF-02 in the backlog); the views counter's 3 writes included;
 *  - the add-on codes primed are the ones get_addons_list_efb() reads (class-Emsfb.php), so the two lists cannot drift.
 *
 * Run (under the e2e lock): php tests/test-public-query-count-wp.php
 */
require_once __DIR__ . '/wp-env.php';
$pass = 0; $fail = 0;
function t( $label, $ok, $detail = '' ) { global $pass, $fail; if ( $ok ) { $pass++; echo "[PASS] {$label}\n"; } else { $fail++; echo "[FAIL] {$label}" . ( '' !== $detail ? "\n       {$detail}" : '' ) . "\n"; } }

/* The lists, from the sources (no WordPress needed). */
$pub_src = file_get_contents( dirname( __DIR__ ) . '/includes/class-Emsfb-public.php' );
$core_src = file_get_contents( dirname( __DIR__ ) . '/includes/class-Emsfb.php' );
preg_match( "/const PUB_ADDON_CODES_EFB = array\(([^)]*)\)/", $pub_src, $m1 );
preg_match( '/function get_addons_list_efb\(\$settings\) \{.*?\$addon_keys = \[(.*?)\];/s', $core_src, $m2 );
preg_match_all( "/'(Adn[A-Za-z]+)'/", $m1[1] ?? '', $a );
preg_match_all( "/'(Adn[A-Za-z]+)'\s*=>/", $m2[1] ?? '', $b );
$pa = $a[1]; $pb = $b[1]; sort( $pa ); sort( $pb );
t( 'the primed add-on codes are the ones get_setting_Emsfb(\'pub\') reads (' . count( $pb ) . ')', $pa && $pa === $pb, implode( ',', array_diff( $pb, $pa ) ) . ' / ' . implode( ',', array_diff( $pa, $pb ) ) );
t( 'the shortcode primes them before it reads anything', preg_match( '/public function render_form_shortcode_efb\( \$atts = array\(\), \$content = null, \$tag = \'\' \) \{\s*self::prime_page_options_efb\(\);/', $pub_src ) === 1 );

$wp_load = efb_test_wp_load();
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
	exit( $fail ? 1 : 0 );
}
define( 'WP_USE_THEMES', false );
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require_once $wp_load;
global $wpdb;
wp_set_current_user( 0 );

$row0 = array( 'type' => 'form', 'steps' => 1, 'formName' => 'perf02 e2e', 'email' => '', 'trackingCode' => '1', 'EfbVersion' => 2, 'sendEmail' => '0', 'captcha' => '', 'stateForm' => 0, 'thank_you' => 'msg',
	'button_single_text' => 'Send', 'button_color' => 'btn-primary', 'el_text_color' => 'text-white', 'icon' => 'bi-send', 'show_icon' => '1', 'button_Next_text' => 'Next', 'button_Previous_text' => 'Previous', 'button_Next_icon' => 'bi-chevron-right', 'button_Previous_icon' => 'bi-chevron-left', 'icon_color' => 'text-white', 'el_height' => 'h-d-efb', 'label_text_color' => 'text-darkb', 'message_text_color' => 'text-muted' );
$rows = array( $row0, array( 'id_' => '1', 'type' => 'step', 'step' => 1, 'name' => 'Step 1', 'amount' => 1, 'icon' => 'bi-ui-checks-grid', 'label_text_color' => 'text-darkb', 'message' => '' ),
	array( 'id_' => 'nm', 'dataId' => 'nm-id', 'type' => 'text', 'name' => 'Name', 'step' => 1, 'amount' => 2, 'required' => '0', 'label_text_color' => 'text-labelEfb', 'el_text_color' => 'text-labelEfb', 'el_border_color' => 'border-d', 'el_height' => 'h-d-efb', 'label_position' => 'up', 'classes' => '', 'message' => '', 'size' => '100' ),
	array( 'id_' => 'em', 'dataId' => 'em-id', 'type' => 'email', 'name' => 'E-mail', 'step' => 1, 'amount' => 3, 'required' => '1', 'label_text_color' => 'text-labelEfb', 'el_text_color' => 'text-labelEfb', 'el_border_color' => 'border-d', 'el_height' => 'h-d-efb', 'label_position' => 'up', 'classes' => '', 'message' => '', 'size' => '100' ) );
$wpdb->insert( $wpdb->prefix . 'emsfb_form', array( 'form_name' => 'perf02 e2e', 'form_structer' => str_replace( '"', '\\"', wp_json_encode( $rows ) ), 'form_email' => '', 'form_type' => 'form', 'form_created_by' => '1', 'form_access_by' => '' ) );
$fid = (int) $wpdb->insert_id;
if ( ! $fid ) { echo "[SKIP] could not create the form\n"; exit( 0 ); }

/* A fresh request as far as caches go: what the previous page loads left in the object cache is gone. */
wp_cache_flush();
$log = array();
$filter = function ( $q ) use ( &$log ) {
	$efb = false;
	foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 30 ) as $f ) { if ( isset( $f['file'] ) && false !== strpos( wp_normalize_path( $f['file'] ), '/easy-form-builder/' ) && false === strpos( $f['file'], '/tests/' ) ) { $efb = true; break; } }
	if ( $efb && false === strpos( $q, 'efb_e2e_' ) ) $log[] = preg_replace( '/\s+/', ' ', substr( $q, 0, 140 ) );
	return $q;
};
add_filter( 'query', $filter );
$html = do_shortcode( '[EMS_Form_Builder id=' . $fid . ']' );
remove_filter( 'query', $filter );

t( 'the form was drawn', false !== strpos( $html, 'body_efb_' . $fid ) );
$singles = array_values( array_filter( $log, function ( $q ) { return preg_match( "/option_name = '(emsfb_addon_[A-Za-z_]+|emsfb_prv_forms|emsfb_sms_db)'/", $q ); } ) );
t( 'no single read of an add-on, privacy or SMS option (they were 20)', ! $singles, implode( ' | ', array_slice( $singles, 0, 5 ) ) );
t( 'at most 15 queries of the plugin on a simple form page (' . count( $log ) . ')', count( $log ) <= 15, implode( "\n       ", $log ) );
echo '       queries: ' . count( $log ) . "\n";

$wpdb->delete( $wpdb->prefix . 'emsfb_form', array( 'form_id' => $fid ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}emsfb_stats_ WHERE form_id = %d", $fid ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}emsfb_stts_ WHERE fid = %d", $fid ) );

echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit( $fail ? 1 : 0 );
