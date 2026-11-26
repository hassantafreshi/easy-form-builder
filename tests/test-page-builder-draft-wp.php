<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * QA2-009: the Elementor widget and the WPBakery element print nothing for a draft form (the shortcode and the block
 * already did): not its wrapper and not its name ("Show Form Title" printed a draft's name to visitors). A published
 * form is printed with its title. Elementor and WPBakery are not needed: a minimal Elementor\Widget_Base stands in
 * when Elementor is not loaded.
 *
 * Needs the e2e site (EFB_E2E_SITE_DIR or EFB_WP_LOAD); [SKIP] otherwise. Run: php tests/test-page-builder-draft-wp.php
 */
$wp_load = getenv('EFB_WP_LOAD') ?: (getenv('EFB_E2E_SITE_DIR') ? rtrim(getenv('EFB_E2E_SITE_DIR'), '/') . '/wp-load.php' : '');
if ($wp_load === '' || !is_readable($wp_load)) { echo "[SKIP] WordPress bootstrap was not found (EFB_WP_LOAD / EFB_E2E_SITE_DIR)\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
require $wp_load;
global $wpdb;
$passed = 0; $failed = 0;
$t = function ($label, $ok, $info = '') use (&$passed, &$failed) {
	if ($ok) { $passed++; echo "[PASS] $label\n"; return; }
	$failed++; echo "[FAIL] $label" . ($info !== '' ? ': ' . $info : '') . "\n";
};
if (!class_exists('\\Elementor\\Widget_Base')) {
	eval('namespace Elementor; class Widget_Base { public $efb_settings = array(); public function __construct($d = array(), $a = null) {} public function get_settings_for_display($k = null) { return $this->efb_settings; } } class Controls_Manager { const SELECT = "select"; const SWITCHER = "switcher"; }');
}
$dir = dirname(__DIR__) . '/includes/page-builders/';
if (!class_exists('Emsfb_Widgets_Helper') && is_readable(dirname(__DIR__) . '/includes/class-Emsfb-widgets-helper.php')) require_once dirname(__DIR__) . '/includes/class-Emsfb-widgets-helper.php';
require_once $dir . 'elementor/class-Emsfb-elementor-widget.php';
require_once $dir . 'wpbakery/class-Emsfb-wpbakery.php';

$fid = 990095;
$name = 'QA2 draft form secret name';
$rows = array(array('type' => 'form', 'steps' => '1', 'formName' => $name, 'button_single_text' => 'Send', 'button_state' => 'single', 'show_icon' => '1'),
	array('id_' => '1', 'type' => 'step', 'dataId' => '1', 'name' => 'Step 1', 'step' => '1', 'amount' => 1, 'message' => ''),
	array('id_' => 'pbtxt01', 'dataId' => 'pbtxt01-id', 'type' => 'text', 'name' => 'Name', 'step' => '1', 'amount' => 2, 'required' => '0', 'value' => '', 'message' => '', 'classes' => ''));
$wpdb->delete($wpdb->prefix . 'emsfb_form', array('form_id' => $fid));
$wpdb->insert($wpdb->prefix . 'emsfb_form', array('form_id' => $fid, 'form_name' => $name, 'form_type' => 'form', 'form_structer' => str_replace('"', '\\"', wp_json_encode($rows)), 'form_email' => '', 'form_created_by' => 1, 'status' => 2));
wp_cache_flush();
delete_transient('efb_draft_forms');
wp_set_current_user(0);

$el = function () use ($fid) {
	$w = new \Emsfb_Elementor_Widget();
	$w->efb_settings = array('form_id' => (string) $fid, 'show_form_title' => 'yes');
	$m = new ReflectionMethod($w, 'render');
	$m->setAccessible(true);
	ob_start();
	$m->invoke($w);
	return ob_get_clean();
};
$vc = function () use ($fid) { return \Emsfb_WPBakery_Integration::get_instance()->render_shortcode(array('form_id' => (string) $fid, 'show_title' => 'yes')); };
try {
	$sc = do_shortcode('[EMS_Form_Builder id=' . $fid . ']');
	$t('the shortcode prints nothing for a draft (as before)', trim($sc) === '', substr($sc, 0, 120));
	$h = $el();
	$t('QA2-009: the Elementor widget prints nothing for a draft (no wrapper, no name)', trim($h) === '' && strpos($h, $name) === false, substr($h, 0, 160));
	$h = $vc();
	$t('QA2-009: the WPBakery element prints nothing for a draft (no wrapper, no name)', trim($h) === '' && strpos($h, $name) === false, substr($h, 0, 160));

	$wpdb->update($wpdb->prefix . 'emsfb_form', array('status' => 1), array('form_id' => $fid));
	wp_cache_flush();
	delete_transient('efb_draft_forms');
	if (class_exists('\\Emsfb\\Form_Status') && method_exists('\\Emsfb\\Form_Status', 'flush')) \Emsfb\Form_Status::flush();
	$h = $el();
	$t('a published form: the Elementor widget prints the title and the form', strpos($h, 'efb-elementor-form-title') !== false && strpos($h, 'pbtxt01') !== false, substr(strip_tags($h), 0, 160));
	$h = $vc();
	$t('a published form: the WPBakery element prints the title and the form', strpos($h, 'efb-wpbakery-form-title') !== false && strpos($h, 'pbtxt01') !== false, substr(strip_tags($h), 0, 160));
} finally {
	$wpdb->delete($wpdb->prefix . 'emsfb_form', array('form_id' => $fid));
	delete_transient('efb_draft_forms');
}
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
