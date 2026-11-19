<?php
/**
 * PERF-01: what a page with a form loads and prints.
 *  - The response viewer (response-viewer-efb.js, 124 KB) loaded on every form page as a dependency of core-efb.js;
 *    only the confirmation code finder (and a form page opened with ?track=…) opens a response.
 *  - The form's JSON was printed twice (ajax_value and ajax_value_forms), and with several forms on a page one
 *    ajax_object_efm per form, each with every form so far.
 *  - The release budget: at most 250 KB of JavaScript (gzipped) on a simple form page.
 *
 * On the real site (under the e2e lock), in-process renders of a throwaway form's shortcode as a visitor:
 *  - a form page: no response viewer; the code finder: the response viewer before core-efb.js;
 *  - the printed footer: one `var ajax_object_efm`, ajax_value "@vf", the form's JSON once; two forms on one page:
 *    one ajax_object_efm with both forms, each JSON once;
 *  - the JavaScript the simple form page loads (jQuery included), gzipped, is under 250 KB (printed for the record).
 *
 * Run (under the e2e lock): php tests/test-public-page-weight-wp.php
 */
require_once __DIR__ . '/wp-env.php';
$wp_load = efb_test_wp_load();
if ( ! is_readable( $wp_load ) ) { echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n"; exit( 0 ); }
define( 'WP_USE_THEMES', false );
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require_once $wp_load;
global $wpdb;
wp_set_current_user( 0 );
$pass = 0; $fail = 0;
function t( $label, $ok, $detail = '' ) { global $pass, $fail; if ( $ok ) { $pass++; echo "[PASS] {$label}\n"; } else { $fail++; echo "[FAIL] {$label}" . ( '' !== $detail ? "\n       {$detail}" : '' ) . "\n"; } }

$mk = function ( $label ) use ( $wpdb ) {
	$row0 = array( 'type' => 'form', 'steps' => 1, 'formName' => $label, 'email' => '', 'trackingCode' => '1', 'EfbVersion' => 2, 'sendEmail' => '0', 'captcha' => '', 'stateForm' => 0, 'thank_you' => 'msg',
		'button_single_text' => 'Send', 'button_color' => 'btn-primary', 'el_text_color' => 'text-white', 'icon' => 'bi-send', 'show_icon' => '1', 'button_Next_text' => 'Next', 'button_Previous_text' => 'Previous', 'button_Next_icon' => 'bi-chevron-right', 'button_Previous_icon' => 'bi-chevron-left', 'icon_color' => 'text-white', 'el_height' => 'h-d-efb', 'label_text_color' => 'text-darkb', 'message_text_color' => 'text-muted' );
	$rows = array( $row0, array( 'id_' => '1', 'type' => 'step', 'step' => 1, 'name' => 'Step 1', 'amount' => 1, 'icon' => 'bi-ui-checks-grid', 'label_text_color' => 'text-darkb', 'message' => '' ),
		array( 'id_' => 'nm', 'dataId' => 'nm-id', 'type' => 'text', 'name' => $label . ' name', 'step' => 1, 'amount' => 2, 'required' => '0', 'label_text_color' => 'text-labelEfb', 'el_text_color' => 'text-labelEfb', 'el_border_color' => 'border-d', 'el_height' => 'h-d-efb', 'label_position' => 'up', 'classes' => '', 'message' => '', 'size' => '100' ),
		array( 'id_' => 'em', 'dataId' => 'em-id', 'type' => 'email', 'name' => 'E-mail', 'step' => 1, 'amount' => 3, 'required' => '1', 'label_text_color' => 'text-labelEfb', 'el_text_color' => 'text-labelEfb', 'el_border_color' => 'border-d', 'el_height' => 'h-d-efb', 'label_position' => 'up', 'classes' => '', 'message' => '', 'size' => '100' ) );
	$wpdb->insert( $wpdb->prefix . 'emsfb_form', array( 'form_name' => $label, 'form_structer' => str_replace( '"', '\\"', wp_json_encode( $rows ) ), 'form_email' => '', 'form_type' => 'form', 'form_created_by' => '1', 'form_access_by' => '' ) );
	return (int) $wpdb->insert_id;
};
$f1 = $mk( 'perf01-alpha' );
$f2 = $mk( 'perf01-beta' );

/* Render [shortcodes] as a fresh page request would, and print what wp_footer prints. */
$page = function ( $shortcodes ) {
	global $wp_scripts, $wp_styles;
	$wp_scripts = new WP_Scripts(); $wp_styles = new WP_Styles();
	wp_default_scripts( $wp_scripts ); wp_default_styles( $wp_styles );
	$ref = new ReflectionProperty( '\\Emsfb\\_Public', 'instance_efb' );
	$pub = $ref->getValue();
	$pub->value_forms = array();
	ob_start();
	$html = do_shortcode( $shortcodes );
	do_action( 'wp_enqueue_scripts' );
	wp_print_styles(); wp_print_head_scripts(); wp_print_footer_scripts();
	$printed = ob_get_clean();
	return array( $html, $printed, (array) $wp_scripts->done, $wp_scripts );
};

list( $html, $printed, $done, $scripts ) = $page( '[EMS_Form_Builder id=' . $f1 . ']' );
t( 'a form page draws the form', false !== strpos( $html, 'body_efb_' . $f1 ) );
t( 'a form page does not load the response viewer', ! in_array( 'efb-response-viewer-js', $done, true ) && false === strpos( $printed, 'response-viewer-efb' . '.js' ) && false === strpos( $printed, 'response-viewer-efb.min.js' ), implode( ',', $done ) );
t( 'core-efb.js does not depend on it', isset( $scripts->registered['Emsfb-core_js'] ) && ! in_array( 'efb-response-viewer-js', $scripts->registered['Emsfb-core_js']->deps, true ) );
t( 'one ajax_object_efm is printed', 1 === substr_count( $printed, 'var ajax_object_efm = ' ), (string) substr_count( $printed, 'var ajax_object_efm = ' ) );
t( 'ajax_value names the form in ajax_value_forms ("@vf")', false !== strpos( $printed, '"ajax_value":"@vf"' ) );
$label_count = substr_count( $printed, 'perf01-alpha name' );
t( "the form's JSON is printed once ($label_count)", 1 === $label_count );

/* The JavaScript weight of this page (the files the page loads, gzipped), jQuery included. */
$gz = 0; $raw = 0; $list = array();
foreach ( $done as $h ) {
	$o = $scripts->registered[ $h ] ?? null;
	if ( ! $o || ! is_string( $o->src ) || '' === $o->src ) continue;
	$src = strtok( $o->src, '?' );
	$file = 0 === strpos( $src, '/wp-includes/' ) || 0 === strpos( $src, '/wp-admin/' ) ? ABSPATH . ltrim( $src, '/' ) : ( 0 === strpos( $src, content_url() ) ? WP_CONTENT_DIR . substr( $src, strlen( content_url() ) ) : ( 0 === strpos( $src, site_url() ) ? ABSPATH . ltrim( substr( $src, strlen( site_url() ) ), '/' ) : '' ) );
	if ( '' === $file || ! is_readable( $file ) ) continue;
	$b = file_get_contents( $file );
	$raw += strlen( $b ); $gz += strlen( gzencode( $b, 9 ) );
	$list[] = $h . ' ' . round( strlen( gzencode( $b, 9 ) ) / 1024 ) . 'K';
}
echo '       JS on a simple form page: ' . round( $raw / 1024 ) . ' KB, ' . round( $gz / 1024 ) . ' KB gzipped: ' . implode( ', ', $list ) . "\n";
t( 'a simple form page loads at most 250 KB of JavaScript, gzipped (' . round( $gz / 1024 ) . ' KB)', $gz > 0 && $gz <= 250 * 1024 );

/* Two forms on one page. */
list( $html2, $printed2, $done2 ) = $page( '[EMS_Form_Builder id=' . $f1 . '][EMS_Form_Builder id=' . $f2 . ']' );
t( 'two forms on a page: both drawn', false !== strpos( $html2, 'body_efb_' . $f1 ) && false !== strpos( $html2, 'body_efb_' . $f2 ) );
t( 'two forms: still one ajax_object_efm', 1 === substr_count( $printed2, 'var ajax_object_efm = ' ), (string) substr_count( $printed2, 'var ajax_object_efm = ' ) );
t( "two forms: each form's JSON once", 1 === substr_count( $printed2, 'perf01-alpha name' ) && 1 === substr_count( $printed2, 'perf01-beta name' ), substr_count( $printed2, 'perf01-alpha name' ) . '/' . substr_count( $printed2, 'perf01-beta name' ) );
t( 'two forms: the one ajax_object_efm carries both', preg_match( '/var ajax_object_efm = (\{.*\});/', $printed2, $m ) && ( $o = json_decode( $m[1], true ) ) && count( $o['ajax_value_forms'] ?? array() ) === 2 );
t( 'two forms: efb_var is still printed', false !== strpos( $printed2, 'var efb_var = ' ) );

/* The confirmation code finder opens responses: the viewer is there, before core-efb.js. */
list( $html3, $printed3, $done3, $scripts3 ) = $page( '[Easy_Form_Builder_confirmation_code_finder]' );
t( 'the code finder draws its box', false !== strpos( $html3, 'trackingCodeEfb' ) );
$i_rv = array_search( 'efb-response-viewer-js', $done3, true );
$i_core = array_search( 'Emsfb-core_js', $done3, true );
t( 'the code finder loads the response viewer, before core-efb.js', false !== $i_rv && false !== $i_core && $i_rv < $i_core, implode( ',', $done3 ) );

$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}emsfb_form WHERE form_id IN (%d, %d)", $f1, $f2 ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}emsfb_stats_ WHERE form_id IN (%d, %d)", $f1, $f2 ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}emsfb_stts_ WHERE fid IN (%d, %d)", $f1, $f2 ) );

echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit( $fail ? 1 : 0 );
